<?php
namespace OZ_Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST — POST /wp-json/oz/v1/submit
 *
 * Pipeline:
 *   1. honeypot check (oz_website must be empty)
 *   2. time-trap check (oz_t must be ≥ 3s ago)
 *   3. Turnstile siteverify (token + action match)
 *   4. schema lookup + validate/sanitize
 *   5. store submission as oz_submission CPT
 *   6. send notification + auto-reply
 *
 * Spam paths still get stored (status=spam) so we can review false positives.
 */
class REST {

	private const NS    = 'oz/v1';
	private const ROUTE = '/submit';

	public static function register() : void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() : void {
		register_rest_route(
			self::NS,
			self::ROUTE,
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function handle( \WP_REST_Request $req ) : \WP_REST_Response {
		$params  = $req->get_params();
		$form_id = isset( $params['form_id'] ) ? sanitize_key( $params['form_id'] ) : '';

		$schema = Schema_Registry::get( $form_id );
		if ( ! $schema ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => 'Onbekend formulier.', 'reason' => 'unknown_form' ), 400 );
		}

		// 1. Honeypot — bots fill anything.
		if ( ! empty( $params['oz_website'] ) ) {
			Submission_CPT::store( $form_id, array(), 'spam', 'honeypot-tripped' );
			return self::ok_response();
		}

		// 2. Time-trap — submissions in <3s are bots.
		$started = isset( $params['oz_t'] ) ? (int) $params['oz_t'] : 0;
		if ( $started > 0 && ( time() - $started ) < 3 ) {
			Submission_CPT::store( $form_id, array(), 'spam', 'time-trap: ' . ( time() - $started ) . 's' );
			return self::ok_response();
		}

		// 3. Cloudflare Turnstile.
		$token  = isset( $params['cf-turnstile-response'] ) ? (string) $params['cf-turnstile-response'] : '';
		$verify = Turnstile::verify( $token, $form_id, self::client_ip() );
		if ( ! $verify['ok'] ) {
			Submission_CPT::store( $form_id, array(), 'spam', 'turnstile: ' . $verify['error'] );
			return new \WP_REST_Response(
				array( 'ok' => false, 'message' => 'Spam-controle mislukt. Vernieuw de pagina en probeer opnieuw.', 'reason' => 'turnstile' ),
				400
			);
		}

		// 4a. Handle any file uploads — process via wp_handle_upload and
		// inject the resulting URL into $params so the schema validator sees
		// a plain string value for the field.
		$files = $req->get_file_params();
		$upload_errors = array();
		$attachments = array();
		if ( ! empty( $files ) && is_array( $files ) ) {
			foreach ( $schema['fields'] as $fname => $fspec ) {
				if ( ( $fspec['type'] ?? '' ) !== 'file' ) {
					continue;
				}
				$is_multi = ! empty( $fspec['multiple'] );
				$entry    = $files[ $fname ] ?? null;
				if ( ! $entry ) {
					continue;
				}
				$max       = (int) ( $fspec['max_size'] ?? ( 5 * 1024 * 1024 ) );
				$max_files = (int) ( $fspec['max_files'] ?? 4 );

				// Normalize into a list of single-file arrays regardless of multi/single.
				$file_list = $is_multi ? self::flatten_multi_file( $entry ) : array( $entry );
				if ( $is_multi && count( $file_list ) > $max_files ) {
					$upload_errors[ $fname ] = sprintf( 'Maximaal %d bestanden.', $max_files );
					continue;
				}

				$urls = array();
				foreach ( $file_list as $file ) {
					if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] ) ) {
						continue;
					}
					if ( ! empty( $file['size'] ) && (int) $file['size'] > $max ) {
						$upload_errors[ $fname ] = 'Bestand is te groot.';
						continue 2;
					}
					$uploaded = self::handle_upload( $file );
					if ( isset( $uploaded['error'] ) ) {
						$upload_errors[ $fname ] = $uploaded['error'];
						continue 2;
					}
					$urls[]          = $uploaded['url'];
					$attachments[]   = $uploaded['file'];
				}

				$params[ $fname ] = $is_multi ? $urls : ( $urls[0] ?? '' );
			}
		}

		// 4b. Schema validate + sanitize.
		$result = Form::validate( $schema, $params );
		if ( ! empty( $upload_errors ) ) {
			$result['ok']     = false;
			$result['errors'] = array_merge( $result['errors'], $upload_errors );
		}
		if ( ! $result['ok'] ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'message' => 'Vul de gemarkeerde velden in.',
					'errors'  => $result['errors'],
					'reason'  => 'validation',
				),
				422
			);
		}

		// 5. Persist.
		$post_id = Submission_CPT::store( $form_id, $result['data'], 'ok' );

		/**
		 * Fires after a submission is stored successfully.
		 * Lets other plugins (oz-reviews) act on specific form_ids — e.g.
		 * create a wp_comments entry from a product-review submission.
		 *
		 * @param string $form_id
		 * @param int    $post_id     oz_submission post ID
		 * @param array  $data        Validated/sanitized fields
		 * @param array  $attachments Absolute paths of uploaded files
		 */
		do_action( 'oz_forms_submission_stored', $form_id, $post_id, $result['data'], $attachments );

		// 6. Email — failures here shouldn't fail the submission for the user.
		// Schemas can opt out by setting 'skip_email' => true (e.g. reviews, where
		// the acknowledgement flow is handled by oz-reviews instead).
		if ( empty( $schema['skip_email'] ) ) {
			try {
				Mailer::send( $schema, $result['data'], $attachments );
			} catch ( \Throwable $e ) {
				update_post_meta( $post_id, '_oz_mail_error', $e->getMessage() );
			}
		}

		$response = apply_filters(
			'oz_forms_submission_response',
			array(
				'ok'      => true,
				'message' => $schema['success_message'] ?? 'Bedankt! We nemen zo snel mogelijk contact met je op.',
			),
			$form_id,
			$result['data']
		);

		return new \WP_REST_Response( $response, 200 );
	}

	/**
	 * Flatten the awkward PHP multi-file shape:
	 *   ['name' => [a,b], 'tmp_name' => [t1,t2], ...]
	 * into an array of per-file arrays:
	 *   [ ['name'=>a,'tmp_name'=>t1,...], ['name'=>b,'tmp_name'=>t2,...] ]
	 */
	private static function flatten_multi_file( array $entry ) : array {
		$out  = array();
		$keys = array( 'name', 'type', 'tmp_name', 'error', 'size' );
		if ( ! isset( $entry['name'] ) || ! is_array( $entry['name'] ) ) {
			// Single-file shape snuck in.
			return array( $entry );
		}
		foreach ( array_keys( $entry['name'] ) as $i ) {
			$file = array();
			foreach ( $keys as $k ) {
				$file[ $k ] = $entry[ $k ][ $i ] ?? null;
			}
			$out[] = $file;
		}
		return $out;
	}

	private static function ok_response() : \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'ok'      => true,
				'message' => 'Bedankt! We nemen zo snel mogelijk contact met je op.',
			),
			200
		);
	}

	private static function client_ip() : string {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Move an uploaded file into wp-content/uploads via wp_handle_upload.
	 * Whitelists common image types only — we don't need arbitrary uploads.
	 *
	 * @param array $file A single $_FILES entry.
	 * @return array{error?: string, url?: string, file?: string}
	 */
	private static function handle_upload( array $file ) : array {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$allowed = array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'webp'     => 'image/webp',
			'heic'     => 'image/heic',
			'gif'      => 'image/gif',
		);
		$overrides = array(
			'test_form' => false,
			'mimes'     => $allowed,
		);
		$res = wp_handle_upload( $file, $overrides );
		if ( isset( $res['error'] ) ) {
			return array( 'error' => (string) $res['error'] );
		}
		return array(
			'url'  => (string) $res['url'],
			'file' => (string) $res['file'],
		);
	}
}
