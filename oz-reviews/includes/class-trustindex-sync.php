<?php
namespace OZ_Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trustindex_Sync — daily cron that snapshots Trustindex's cached Google
 * reviews into our oz_shop_review CPT.
 *
 * Why: Trustindex's `wp-reviews-plugin-for-google` maintains a rolling cache
 * of ~10 recent Google reviews in `{prefix}_trustindex_google_reviews`. When
 * new reviews arrive, older ones are evicted. By snapshotting on a schedule
 * we build up a persistent archive (the CPT grows; Trustindex stays capped).
 *
 * Runtime rendering split:
 *   /reviews/ hub     → reads the accumulated CPT archive (this sync's output)
 *   everywhere else   → reads the live Trustindex table directly (Shortcode::render_live)
 *
 * Dedup: `reviewId` column is Google's stable review id → stored as
 * `_oz_external_id`. Same row on a future sync = update-in-place.
 */
class Trustindex_Sync {

	private const CRON_HOOK       = 'oz_reviews_trustindex_sync';
	private const STATUS_OPT      = 'oz_reviews_last_trustindex_sync';
	private const HASH_OPT        = 'oz_reviews_trustindex_hash';
	private const ROWS_TRANSIENT  = 'oz_reviews_trustindex_rows';
	private const ROWS_TTL        = 15 * MINUTE_IN_SECONDS;

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
	 * Fetch all non-hidden reviews from the Trustindex table, newest first.
	 *
	 * Hot path: called per-render on homepage/PDP/ruimte pages. Per-request
	 * static memo + short-TTL transient keep this at ~one query per 15 min
	 * rather than one per visitor. Trustindex_Sync::run() invalidates the
	 * transient after a daily snapshot.
	 *
	 * Returns [] if the Trustindex plugin is not active or the table is empty.
	 */
	public static function fetch_rows() : array {
		static $memo = null;
		if ( $memo !== null ) {
			return $memo;
		}

		$cached = get_transient( self::ROWS_TRANSIENT );
		if ( is_array( $cached ) ) {
			$memo = $cached;
			return $memo;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'trustindex_google_reviews';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			$memo = array();
			set_transient( self::ROWS_TRANSIENT, $memo, self::ROWS_TTL );
			return $memo;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE hidden = 0 ORDER BY date DESC, id DESC', $table ) );
		$memo = is_array( $rows ) ? $rows : array();
		set_transient( self::ROWS_TRANSIENT, $memo, self::ROWS_TTL );
		return $memo;
	}

	/**
	 * Read Trustindex's page-details option for site-wide aggregate.
	 * Returns array{ rating: float, rating_count: int } with safe defaults.
	 */
	public static function get_aggregate() : array {
		$pd = get_option( 'trustindex-google-page-details' );
		if ( ! is_array( $pd ) ) {
			return array( 'rating' => 0.0, 'rating_count' => 0 );
		}
		return array(
			'rating'       => isset( $pd['rating_score'] ) ? (float) $pd['rating_score'] : 0.0,
			'rating_count' => isset( $pd['rating_number'] ) ? (int) $pd['rating_number'] : 0,
		);
	}

	/**
	 * Single entry point for templates/shortcodes needing the aggregate with
	 * fallback defaults when Trustindex has no data yet.
	 */
	public static function get_resolved_aggregate( float $default_rating = 4.8, int $default_count = 0 ) : array {
		$agg = self::get_aggregate();
		if ( $agg['rating_count'] > 0 ) {
			return $agg;
		}
		return array( 'rating' => $default_rating, 'rating_count' => $default_count );
	}

	/**
	 * Daily sync: snapshot whatever Trustindex currently holds into our CPT.
	 *
	 * @return array{ok: bool, inserted: int, updated: int, skipped: int, total: int, error?: string}
	 */
	public static function run() : array {
		delete_transient( self::ROWS_TRANSIENT );
		$rows = self::fetch_rows();

		if ( empty( $rows ) ) {
			$status = array( 'ok' => true, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'total' => 0, 'ran_at' => time() );
			update_option( self::STATUS_OPT, $status, false );
			return $status;
		}

		// Short-circuit when Trustindex hasn't added/changed anything since
		// the last sync — the upsert loop would run 10 get_posts + wp_update_post
		// calls for no net change.
		$hash = self::rows_hash( $rows );
		$prev = (string) get_option( self::HASH_OPT, '' );
		if ( $hash === $prev ) {
			$status = array(
				'ok'       => true,
				'inserted' => 0,
				'updated'  => 0,
				'skipped'  => count( $rows ),
				'total'    => count( $rows ),
				'ran_at'   => time(),
				'noop'     => true,
			);
			update_option( self::STATUS_OPT, $status, false );
			return $status;
		}

		$inserted = 0;
		$updated  = 0;
		$skipped  = 0;

		foreach ( $rows as $row ) {
			$dto = Review_DTO::from_trustindex_row( $row );
			if ( empty( $dto['external_id'] ) ) {
				$skipped++;
				continue;
			}
			$existing = CPT::find_by_external_id( $dto['external_id'] );
			$id       = CPT::upsert_external( $dto, 'publish' );
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

		update_option( self::HASH_OPT, $hash, false );

		$status = array(
			'ok'       => true,
			'inserted' => $inserted,
			'updated'  => $updated,
			'skipped'  => $skipped,
			'total'    => count( $rows ),
			'ran_at'   => time(),
		);
		update_option( self::STATUS_OPT, $status, false );

		return $status;
	}

	/** Deterministic hash of the Trustindex row set — detects "nothing changed". */
	private static function rows_hash( array $rows ) : string {
		$parts = array();
		foreach ( $rows as $row ) {
			$row = (array) $row;
			$parts[] = implode( '|', array(
				(string) ( $row['reviewId'] ?? '' ),
				(string) ( $row['date'] ?? '' ),
				(string) ( $row['rating'] ?? '' ),
				(string) ( $row['text'] ?? '' ),
				(string) ( $row['reply'] ?? '' ),
			) );
		}
		return sha1( implode( "\n", $parts ) );
	}
}
