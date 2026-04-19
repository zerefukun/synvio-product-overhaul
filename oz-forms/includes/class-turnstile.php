<?php
namespace OZ_Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cloudflare Turnstile server-side verifier.
 * Docs: https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
 */
class Turnstile {

	private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	/** Hostnames we accept tokens from. Anything else is treated as a replay/forgery. */
	private const ALLOWED_HOSTS = array(
		'beton-cire-webshop.nl',
		'staging.beton-cire-webshop.nl',
	);

	/**
	 * @param string $token         The cf-turnstile-response token from the client.
	 * @param string $expected_action The data-action set on the widget; must match.
	 * @param string $remote_ip     Visitor IP.
	 * @return array{ok: bool, error: ?string, raw: array}
	 */
	public static function verify( string $token, string $expected_action, string $remote_ip ) : array {
		if ( ! defined( 'OZ_TURNSTILE_SECRET_KEY' ) || ! OZ_TURNSTILE_SECRET_KEY ) {
			return array( 'ok' => false, 'error' => 'turnstile-not-configured', 'raw' => array() );
		}
		if ( $token === '' ) {
			return array( 'ok' => false, 'error' => 'missing-token', 'raw' => array() );
		}

		$body = array(
			'secret'          => OZ_TURNSTILE_SECRET_KEY,
			'response'        => $token,
			'remoteip'        => $remote_ip,
			'idempotency_key' => wp_generate_uuid4(),
		);

		$resp = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 5,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'error' => 'transport-error: ' . $resp->get_error_message(), 'raw' => array() );
		}

		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $data ) ) {
			return array( 'ok' => false, 'error' => 'bad-siteverify-response', 'raw' => array() );
		}

		if ( empty( $data['success'] ) ) {
			$codes = isset( $data['error-codes'] ) ? implode( ',', (array) $data['error-codes'] ) : 'unknown';
			return array( 'ok' => false, 'error' => 'cf-rejected: ' . $codes, 'raw' => $data );
		}

		$host = $data['hostname'] ?? '';
		if ( ! in_array( $host, self::ALLOWED_HOSTS, true ) ) {
			return array( 'ok' => false, 'error' => 'host-mismatch: ' . $host, 'raw' => $data );
		}

		$action = $data['action'] ?? '';
		if ( $action !== $expected_action ) {
			return array( 'ok' => false, 'error' => 'action-mismatch: ' . $action . ' vs ' . $expected_action, 'raw' => $data );
		}

		return array( 'ok' => true, 'error' => null, 'raw' => $data );
	}
}
