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

		// 0. Per-IP rate limit — 10 submissions per hour per IP across all forms.
		// Rejected requests are NOT persisted, so botnets can't bloat wp_posts.
		$rate_err = self::rate_limit_check( $form_id, $params );
		if ( $rate_err !== null ) {
			return new \WP_REST_Response(
				array( 'ok' => false, 'message' => 'Te veel verzoeken. Probeer het later opnieuw.', 'reason' => $rate_err ),
				429
			);
		}

		// 1. Honeypot — bots fill anything. Don't persist — a spam flood would
		// bloat wp_posts otherwise (every bot POST = one row).
		if ( ! empty( $params['oz_website'] ) ) {
			return self::ok_response();
		}

		// 2. Time-trap — submissions in <3s are bots. Same no-persist rule.
		$started = isset( $params['oz_t'] ) ? (int) $params['oz_t'] : 0;
		if ( $started > 0 && ( time() - $started ) < 3 ) {
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
	 * Token-bucket-ish rate limit using transients.
	 *
	 * - Per IP: 10 req/hour (any form)
	 * - For product-review: extra 3/day per email, 1/day per (IP,product_id)
	 *
	 * Returns null when request is allowed, otherwise a reason string.
	 */
	private static function rate_limit_check( string $form_id, array $params ) : ?string {
		$ip = self::client_ip();
		if ( $ip !== '' ) {
			$key = 'oz_sub_ip_' . md5( $ip );
			$hits = (int) get_transient( $key );
			if ( $hits >= 10 ) {
				return 'rate_ip_hour';
			}
			set_transient( $key, $hits + 1, HOUR_IN_SECONDS );
		}

		if ( $form_id === 'product-review' ) {
			$email = isset( $params['email'] ) ? strtolower( trim( (string) $params['email'] ) ) : '';
			if ( $email !== '' ) {
				$ekey = 'oz_rev_em_' . md5( $email );
				$ehits = (int) get_transient( $ekey );
				if ( $ehits >= 3 ) {
					return 'rate_email_day';
				}
				set_transient( $ekey, $ehits + 1, DAY_IN_SECONDS );
			}
			$product_id = isset( $params['product_id'] ) ? (int) $params['product_id'] : 0;
			if ( $ip !== '' && $product_id > 0 ) {
				$pkey = 'oz_rev_ip_pid_' . md5( $ip . '|' . $product_id );
				if ( get_transient( $pkey ) ) {
					return 'rate_ip_product_day';
				}
				set_transient( $pkey, 1, DAY_IN_SECONDS );
			}
		}

		return null;
	}

	/**
	 * Move an uploaded file into wp-content/uploads/oz-forms/YYYY/MM/ via
	 * wp_handle_upload. Kept separate from the normal media library so form
	 * attachments don't clutter wp-content/uploads/YYYY/MM alongside product
	 * images, and admins can prune form-media independently.
	 *
	 * Security layers applied (belt-and-braces):
	 *   1. Hard MIME whitelist — only image formats we actually need.
	 *   2. Extension whitelist via wp_handle_upload's mimes override.
	 *   3. finfo_file() — reads the file's magic bytes server-side; fails if
	 *      the real content doesn't match the claimed MIME (blocks spoofed
	 *      polyglot/phar/php files dressed as images).
	 *   4. Filename sanitation — reject anything containing a dangerous
	 *      double-extension (.php, .phtml, .phar, .pl, .py, .sh, .cgi, etc.).
	 *   5. Hardened target dir — .htaccess + index.html dropped on first
	 *      upload to block PHP execution and directory listing.
	 *
	 * @param array $file A single $_FILES entry.
	 * @return array{error?: string, url?: string, file?: string}
	 */
	private static function handle_upload( array $file ) : array {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Defensive: reject filenames with a script extension anywhere in the name.
		// wp_unique_filename already prevents collisions, but double-extensions like
		// "evil.php.jpg" should never even be attempted.
		$name = isset( $file['name'] ) ? (string) $file['name'] : '';
		if ( preg_match( '/\.(php\d?|phtml|phar|pl|py|sh|cgi|asp|aspx|jsp|htaccess|htpasswd)(\.|$)/i', $name ) ) {
			return array( 'error' => 'Bestandsnaam bevat een niet-toegestane extensie.' );
		}

		// Verify the actual file content (magic bytes) matches an allowed image type.
		// Trusting $file['type'] (client-supplied Content-Type) is not enough.
		$allowed_mimes = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic' );
		$real_mime = self::detect_mime( $file['tmp_name'] ?? '' );
		if ( $real_mime === '' || ! in_array( $real_mime, $allowed_mimes, true ) ) {
			return array( 'error' => 'Ongeldig of niet-toegestaan bestandstype.' );
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

		add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir_to_oz_forms' ) );
		$res = wp_handle_upload( $file, $overrides );
		remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir_to_oz_forms' ) );

		if ( isset( $res['error'] ) ) {
			return array( 'error' => (string) $res['error'] );
		}

		// Ensure the upload dir has .htaccess + index.html guards.
		self::harden_upload_dir( dirname( (string) $res['file'] ) );

		return array(
			'url'  => (string) $res['url'],
			'file' => (string) $res['file'],
		);
	}

	/**
	 * Detect the MIME type of a file from its magic bytes. Returns '' on failure.
	 */
	private static function detect_mime( string $path ) : string {
		if ( $path === '' || ! is_readable( $path ) ) {
			return '';
		}
		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			if ( $finfo ) {
				$mime = (string) finfo_file( $finfo, $path );
				finfo_close( $finfo );
				return $mime;
			}
		}
		// Fallback — getimagesize reads magic bytes too and covers the image formats we allow.
		$info = @getimagesize( $path );
		if ( is_array( $info ) && ! empty( $info['mime'] ) ) {
			return (string) $info['mime'];
		}
		return '';
	}

	/**
	 * Drop .htaccess and index.html into the target directory (idempotent).
	 * .htaccess disables PHP execution and script handling; index.html blocks
	 * directory listing on servers that otherwise allow it.
	 *
	 * Walks up one level to harden /uploads/oz-forms root as well.
	 */
	private static function harden_upload_dir( string $dir ) : void {
		$base = trailingslashit( wp_upload_dir()['basedir'] ?? '' ) . 'oz-forms';
		$dirs = array( $dir, $base );
		foreach ( array_unique( $dirs ) as $d ) {
			if ( ! is_dir( $d ) ) {
				continue;
			}
			$htaccess = $d . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				$rules = "# oz-forms upload dir — no script execution allowed.\n"
					. "<FilesMatch \"\\.(php\\d?|phtml|phar|pl|py|sh|cgi|asp|aspx|jsp)$\">\n"
					. "    Require all denied\n"
					. "</FilesMatch>\n"
					. "<IfModule mod_php7.c>\n    php_flag engine off\n</IfModule>\n"
					. "<IfModule mod_php8.c>\n    php_flag engine off\n</IfModule>\n"
					. "Options -Indexes -ExecCGI\n"
					. "RemoveHandler .php .phtml .phar .pl .py .sh .cgi\n"
					. "AddType text/plain .php .phtml .phar .pl .py .sh .cgi\n";
				@file_put_contents( $htaccess, $rules );
			}
			$index = $d . '/index.html';
			if ( ! file_exists( $index ) ) {
				@file_put_contents( $index, '' );
			}
		}
	}

	/**
	 * upload_dir filter — route form uploads into /uploads/oz-forms/YYYY/MM.
	 * Prepends /oz-forms to whatever subdir WP would otherwise use.
	 *
	 * @param array $dirs
	 */
	public static function filter_upload_dir_to_oz_forms( array $dirs ) : array {
		$subdir = '/oz-forms' . ( $dirs['subdir'] ?? '' );
		$dirs['path']   = $dirs['basedir'] . $subdir;
		$dirs['url']    = $dirs['baseurl'] . $subdir;
		$dirs['subdir'] = $subdir;
		return $dirs;
	}
}
