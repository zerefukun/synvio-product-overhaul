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

		// 4. Schema validate + sanitize.
		$result = Form::validate( $schema, $params );
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

		// 6. Email — failures here shouldn't fail the submission for the user.
		try {
			Mailer::send( $schema, $result['data'] );
		} catch ( \Throwable $e ) {
			update_post_meta( $post_id, '_oz_mail_error', $e->getMessage() );
		}

		return self::ok_response();
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
}
