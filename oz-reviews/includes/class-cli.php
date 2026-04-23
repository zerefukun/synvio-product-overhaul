<?php
namespace OZ_Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * WP-CLI commands for oz-reviews.
 *
 *   wp oz-reviews trustindex-sync
 *       → Snapshot Trustindex's cached Google reviews into the CPT archive.
 *         Same job the daily cron runs — use this to backfill or verify.
 *
 *   wp oz-reviews sync-google
 *       → Legacy alias. Now delegates to trustindex-sync (Places API sync retired).
 *
 *   wp oz-reviews import-google <file>
 *       → Bulk-import a JSON dump of historically scraped Google reviews.
 *         Stored with source='import' so they're distinguishable from live
 *         Trustindex snapshots. File format:
 *         [
 *           {
 *             "author_name": "Jan Jansen",
 *             "author_photo": "https://...",    // optional
 *             "rating": 5,
 *             "publish_time": "2025-03-11T10:22:00Z",
 *             "text": "...review body...",
 *             "photos": ["https://...", ...]    // optional
 *           },
 *           ...
 *         ]
 *
 *   wp oz-reviews list [--source=trustindex|import|shop_native]
 *       → List review posts for sanity checks.
 */
class CLI {

	public static function register() : void {
		\WP_CLI::add_command( 'oz-reviews trustindex-sync', array( __CLASS__, 'trustindex_sync' ) );
		\WP_CLI::add_command( 'oz-reviews sync-google', array( __CLASS__, 'sync_google' ) );
		\WP_CLI::add_command( 'oz-reviews import-google', array( __CLASS__, 'import_google' ) );
		\WP_CLI::add_command( 'oz-reviews list', array( __CLASS__, 'list_reviews' ) );
	}

	public static function trustindex_sync() : void {
		$res = Trustindex_Sync::run();
		if ( empty( $res['ok'] ) ) {
			\WP_CLI::error( 'Sync failed: ' . ( $res['error'] ?? 'unknown' ) );
		}
		\WP_CLI::success( sprintf(
			'Trustindex snapshot: %d total, %d inserted, %d updated, %d skipped.',
			(int) $res['total'],
			(int) $res['inserted'],
			(int) $res['updated'],
			(int) $res['skipped']
		) );
	}

	public static function sync_google() : void {
		\WP_CLI::log( 'Direct Places API sync is retired. Delegating to Trustindex snapshot.' );
		self::trustindex_sync();
	}

	public static function import_google( array $args ) : void {
		$path = $args[0] ?? '';
		if ( $path === '' || ! is_readable( $path ) ) {
			\WP_CLI::error( 'File not found or not readable: ' . $path );
		}

		$json = file_get_contents( $path );
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			\WP_CLI::error( 'Not valid JSON array.' );
		}

		$inserted = 0;
		$updated  = 0;
		$skipped  = 0;

		foreach ( $data as $i => $r ) {
			$author  = (string) ( $r['author_name'] ?? '' );
			$publish = (string) ( $r['publish_time'] ?? '' );
			if ( $author === '' || $publish === '' ) {
				$skipped++;
				continue;
			}

			$dto = Review_DTO::from_array( array(
				'source'       => 'import',
				'external_id'  => Review_DTO::external_id( $author, $publish ),
				'rating'       => (int) ( $r['rating'] ?? 0 ),
				'author_name'  => $author,
				'author_photo' => (string) ( $r['author_photo'] ?? '' ),
				'author_city'  => (string) ( $r['author_city'] ?? '' ),
				'date_iso'     => $publish,
				'body'         => (string) ( $r['text'] ?? $r['body'] ?? '' ),
				'photos'       => (array) ( $r['photos'] ?? array() ),
			) );

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

		\WP_CLI::success( sprintf( '%d reviews — inserted %d, updated %d, skipped %d.', count( $data ), $inserted, $updated, $skipped ) );
	}

	public static function list_reviews( array $args, array $assoc ) : void {
		$source = isset( $assoc['source'] ) ? sanitize_key( $assoc['source'] ) : '';
		$meta_query = array();
		if ( $source !== '' ) {
			$meta_query[] = array( 'key' => '_oz_source', 'value' => $source );
		}
		$posts = get_posts( array(
			'post_type'      => CPT::CPT,
			'posts_per_page' => 50,
			'post_status'    => array( 'publish', 'pending' ),
			'meta_query'     => $meta_query,
		) );
		$rows = array();
		foreach ( $posts as $p ) {
			$rows[] = array(
				'id'     => $p->ID,
				'source' => get_post_meta( $p->ID, '_oz_source', true ),
				'rating' => get_post_meta( $p->ID, '_oz_rating', true ),
				'author' => get_post_meta( $p->ID, '_oz_author_name', true ),
				'date'   => get_post_meta( $p->ID, '_oz_publish_time', true ),
			);
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'source', 'rating', 'author', 'date' ) );
	}
}
