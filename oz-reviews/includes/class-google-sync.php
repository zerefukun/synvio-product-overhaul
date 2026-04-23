<?php
namespace OZ_Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google_Sync — retired.
 *
 * Historical role: daily poll of Places API (New), direct ingestion into the
 * CPT. Replaced by Trustindex_Sync — Trustindex's `wp-reviews-plugin-for-google`
 * owns the collection pipeline and caches Google reviews into its own table,
 * which we snapshot into the CPT for the /reviews/ hub.
 *
 * We keep this class as a no-op shim so:
 *  - existing settings/CLI that still reference it don't fatal
 *  - any leftover cron event ('oz_reviews_google_sync') unschedules itself
 *  - manual run() calls redirect to the new pipeline
 */
class Google_Sync {

	private const CRON_HOOK  = 'oz_reviews_google_sync';
	private const STATUS_OPT = 'oz_reviews_last_sync';

	public static function register() : void {
		// Unschedule any legacy daily event — Trustindex_Sync owns the cron now.
		add_action( 'init', array( __CLASS__, 'unschedule' ) );
	}

	public static function unschedule() : void {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
	}

	/**
	 * Delegated entry point — routes any remaining callers to Trustindex_Sync.
	 *
	 * @return array{ok: bool, inserted: int, updated: int, skipped: int, error?: string}
	 */
	public static function run() : array {
		$res = Trustindex_Sync::run();
		$status = array(
			'ok'       => (bool) ( $res['ok'] ?? false ),
			'inserted' => (int) ( $res['inserted'] ?? 0 ),
			'updated'  => (int) ( $res['updated'] ?? 0 ),
			'skipped'  => (int) ( $res['skipped'] ?? 0 ),
			'ran_at'   => time(),
			'via'      => 'trustindex',
		);
		if ( ! empty( $res['error'] ) ) {
			$status['error'] = (string) $res['error'];
		}
		update_option( self::STATUS_OPT, $status, false );
		return $status;
	}
}
