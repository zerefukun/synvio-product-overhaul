<?php
namespace OZ_Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mailer — sends two emails per submission:
 *   1. Notification to the site owner (schema's notify_to).
 *   2. Auto-reply to the submitter (if their email is in the data).
 *
 * Emails ride on whatever wp_mail() is configured to use (wp-mail-smtp on prod).
 */
class Mailer {

	public static function send( array $schema, array $data, array $attachments = array() ) : void {
		add_filter( 'wp_mail_content_type', array( __CLASS__, 'html_content_type' ) );

		try {
			self::send_notification( $schema, $data, $attachments );
			self::send_autoreply( $schema, $data );
		} finally {
			remove_filter( 'wp_mail_content_type', array( __CLASS__, 'html_content_type' ) );
		}
	}

	public static function html_content_type() : string {
		return 'text/html';
	}

	private static function send_notification( array $schema, array $data, array $attachments = array() ) : void {
		$to = $schema['notify_to'] ?? get_option( 'admin_email' );
		$subject = self::resolve( $schema['subject'] ?? ( $schema['title'] ?? 'New submission' ), $data );
		$body = self::wrap_html(
			'<h2 style="margin:0 0 16px;font-family:Georgia,serif;color:#135350;">' . esc_html( $schema['title'] ?? 'Form submission' ) . '</h2>'
			. self::data_table( $schema, $data )
		);

		$headers = array();
		if ( ! empty( $data['email'] ) && is_email( $data['email'] ) ) {
			$name = $data['naam'] ?? ( $data['name'] ?? '' );
			$headers[] = 'Reply-To: ' . self::format_address( $data['email'], $name );
		}

		wp_mail( $to, $subject, $body, $headers, $attachments );
	}

	private static function send_autoreply( array $schema, array $data ) : void {
		if ( empty( $schema['reply_subject'] ) || empty( $schema['reply_body'] ) ) {
			return;
		}
		if ( empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
			return;
		}

		$subject = self::resolve( $schema['reply_subject'], $data );
		$body    = self::wrap_html( self::resolve( $schema['reply_body'], $data ) );

		wp_mail( $data['email'], $subject, $body );
	}

	/**
	 * Resolve a string-or-callable into a string.
	 *
	 * @param string|callable $what
	 */
	private static function resolve( $what, array $data ) : string {
		if ( is_callable( $what ) ) {
			return (string) $what( $data );
		}
		return (string) $what;
	}

	private static function format_address( string $email, string $name = '' ) : string {
		// SECURITY: strip all CR/LF/TAB/NUL before interpolating into a mail
		// header, otherwise a submitter can inject extra headers (Bcc, Subject)
		// by embedding \r\n in the name field. wp_strip_all_tags leaves those
		// control bytes in place, so we must handle them explicitly.
		$name = (string) wp_strip_all_tags( $name );
		$name = preg_replace( '/[\r\n\t\0\x0B]+/', ' ', $name );
		$name = trim( mb_substr( (string) $name, 0, 100 ) );
		return $name !== '' ? sprintf( '%s <%s>', $name, $email ) : $email;
	}

	private static function data_table( array $schema, array $data ) : string {
		$rows = '';
		$fields = $schema['fields'] ?? array();
		foreach ( $data as $key => $value ) {
			$label = $fields[ $key ]['label'] ?? $key;
			$rendered = is_array( $value ) ? esc_html( implode( ', ', array_map( 'strval', $value ) ) ) : nl2br( esc_html( (string) $value ) );
			$rows .= '<tr><td style="padding:8px 12px;border-bottom:1px solid #eee;font-weight:600;color:#135350;vertical-align:top;">'
				. esc_html( $label )
				. '</td><td style="padding:8px 12px;border-bottom:1px solid #eee;">' . $rendered . '</td></tr>';
		}
		return '<table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;color:#333;">' . $rows . '</table>';
	}

	private static function wrap_html( string $inner ) : string {
		return '<!doctype html><html><body style="margin:0;padding:24px;background:#f7f5f2;font-family:Arial,sans-serif;color:#333;">'
			. '<div style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #e5e5e3;border-radius:6px;padding:32px;">'
			. $inner
			. '<hr style="border:none;border-top:1px solid #eee;margin:32px 0 16px;">'
			. '<p style="font-size:12px;color:#999;margin:0;">Beton Ciré Webshop &mdash; <a href="https://beton-cire-webshop.nl" style="color:#135350;">beton-cire-webshop.nl</a></p>'
			. '</div></body></html>';
	}
}
