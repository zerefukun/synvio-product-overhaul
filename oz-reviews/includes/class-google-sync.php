<?php
namespace OZ_Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google_Sync — daily cron that polls Places API (New) and upserts reviews.
 *
 * Dedup: external_id = sha1(author + publish_time_minute). If a scraped
 * historical review has the same key, we skip (the scrape is canonical).
 *
 * Failures are logged to the option 'oz_reviews_last_sync' so the admin
 * settings page can surface status without emailing the user.
 */
class Google_Sync {

	private const CRON_HOOK    = 'oz_reviews_google_sync';
	private const STATUS_OPT   = 'oz_reviews_last_sync';

	public static function register() : void {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'ensure_scheduled' ) );
	}

	public static function ensure_scheduled() : void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'daily', self::CRON_HOOK );
		}
	}

	public static function unschedule() : void {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
	}

	/**
	 * Manual + cron entry point.
	 *
	 * @return array{ok: bool, inserted: int, updated: int, skipped: int, error?: string}
	 */
	public static function run() : array {
		$place_id = (string) Settings::get( 'google_place_id' );
		$api_key  = (string) Settings::get( 'google_places_api_key' );

		if ( $place_id === '' || $api_key === '' ) {
			$status = array( 'ok' => false, 'error' => 'Place ID or API key missing', 'ran_at' => time() );
			update_option( self::STATUS_OPT, $status, false );
			return array( 'ok' => false, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'error' => $status['error'] );
		}

		$res = Places_Client::get_place( $place_id, $api_key );
		if ( ! $res['ok'] ) {
			$status = array( 'ok' => false, 'error' => $res['error'] ?? 'Unknown error', 'ran_at' => time() );
			update_option( self::STATUS_OPT, $status, false );
			return array( 'ok' => false, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'error' => $status['error'] );
		}

		$reviews = (array) ( $res['data']['reviews'] ?? array() );
		$inserted = 0;
		$updated  = 0;
		$skipped  = 0;

		foreach ( $reviews as $r ) {
			$dto = Places_Client::review_to_dto( $r );
			if ( empty( $dto['external_id'] ) ) {
				$skipped++;
				continue;
			}
			$existing = CPT::find_by_external_id( $dto['external_id'] );
			$id = CPT::upsert_external( $dto, 'publish' );
			if ( ! $id ) {
				$skipped++;
				continue;
			}
			if ( $existing ) {
				$updated++;
			} else {
				$inserted++;
			}
		}

		// Also store place-level aggregate (rating + userRatingCount) for use on /reviews/ hub.
		update_option(
			'oz_reviews_google_aggregate',
			array(
				'rating'       => (float) ( $res['data']['rating'] ?? 0 ),
				'rating_count' => (int) ( $res['data']['userRatingCount'] ?? 0 ),
				'updated_at'   => time(),
			),
			false
		);

		$status = array(
			'ok'       => true,
			'inserted' => $inserted,
			'updated'  => $updated,
			'skipped'  => $skipped,
			'total'    => count( $reviews ),
			'ran_at'   => time(),
		);
		update_option( self::STATUS_OPT, $status, false );

		return array( 'ok' => true, 'inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped );
	}
}
