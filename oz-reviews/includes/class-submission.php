<?php
namespace OZ_Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Submission — listens to oz-forms `oz_forms_submission_stored`.
 *
 *   product-review → creates a wp_comments row (comment_type=review, WC-native).
 *   shop-review    → creates an oz_shop_review CPT post (TKT-1.2, not yet wired).
 *
 * The signed-link token validation happens in TKT-1.3; for now any submission
 * lands in moderation (comment_approved=0).
 */
class Submission {

	public static function register() : void {
		add_action( 'oz_forms_submission_stored', array( __CLASS__, 'handle' ), 10, 4 );
	}

	public static function handle( string $form_id, int $submission_post_id, array $data, array $attachments ) : void {
		if ( $form_id === 'product-review' ) {
			self::handle_product_review( $submission_post_id, $data, $attachments );
		} elseif ( $form_id === 'shop-review' ) {
			self::handle_shop_review( $submission_post_id, $data, $attachments );
		}
	}

	private static function handle_shop_review( int $submission_post_id, array $data, array $attachments ) : void {
		$title  = wp_strip_all_tags( (string) ( $data['title'] ?? '' ) );
		$body   = wp_strip_all_tags( (string) ( $data['body'] ?? '' ) );
		$author = sanitize_text_field( (string) ( $data['naam'] ?? '' ) );
		$email  = sanitize_email( (string) ( $data['email'] ?? '' ) );

		if ( $author === '' || $body === '' ) {
			update_post_meta( $submission_post_id, '_oz_review_error', 'missing_author_or_body' );
			return;
		}

		$post_id = wp_insert_post( array(
			'post_type'    => CPT::CPT,
			'post_status'  => 'pending', // moderation queue
			'post_title'   => $title !== '' ? $title : $author,
			'post_content' => $body,
		), true );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			update_post_meta( $submission_post_id, '_oz_review_error', 'wp_insert_post_failed' );
			return;
		}

		$meta = array(
			'rating'       => max( 1, min( 5, (int) ( $data['rating'] ?? 0 ) ) ),
			'source'       => 'shop_native',
			'author_name'  => $author,
			'author_email' => $email,
			'author_city'  => sanitize_text_field( (string) ( $data['stad'] ?? '' ) ),
			'publish_time' => gmdate( 'c' ),
			'project_type' => sanitize_text_field( (string) ( $data['project_type'] ?? '' ) ),
		);
		foreach ( $meta as $k => $v ) {
			if ( $v !== '' && $v !== null ) {
				update_post_meta( $post_id, '_oz_' . $k, $v );
			}
		}

		$photos = isset( $data['photos'] ) && is_array( $data['photos'] ) ? array_values( array_filter( $data['photos'] ) ) : array();
		if ( ! empty( $photos ) ) {
			update_post_meta( $post_id, '_oz_photos', $photos );
		}

		update_post_meta( $post_id, '_oz_submission_id', $submission_post_id );
		update_post_meta( $submission_post_id, '_oz_review_post_id', $post_id );
	}

	private static function handle_product_review( int $submission_post_id, array $data, array $attachments ) : void {
		$product_id = (int) ( $data['product_id'] ?? 0 );
		if ( $product_id <= 0 || get_post_type( $product_id ) !== 'product' ) {
			// No valid product — leave the oz_submission record as the paper trail
			// and flag it so admins can still see the review in the queue.
			update_post_meta( $submission_post_id, '_oz_review_error', 'invalid_product_id' );
			return;
		}

		$title = wp_strip_all_tags( (string) ( $data['title'] ?? '' ) );
		$body  = wp_strip_all_tags( (string) ( $data['body'] ?? '' ) );

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $product_id,
				'comment_author'       => sanitize_text_field( (string) ( $data['naam'] ?? '' ) ),
				'comment_author_email' => sanitize_email( (string) ( $data['email'] ?? '' ) ),
				'comment_author_IP'    => self::client_ip(),
				'comment_agent'        => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '',
				'comment_content'      => $body,
				'comment_type'         => 'review',
				'comment_approved'     => 0, // moderation queue
				'comment_parent'       => 0,
				'user_id'              => get_current_user_id(),
			)
		);

		if ( ! $comment_id ) {
			update_post_meta( $submission_post_id, '_oz_review_error', 'wp_insert_comment_failed' );
			return;
		}

		// WooCommerce-native meta — star rating and verified-buyer flag.
		update_comment_meta( $comment_id, 'rating', max( 1, min( 5, (int) ( $data['rating'] ?? 0 ) ) ) );

		// Verified-buyer flag requires BOTH (a) the order contains the product
		// for this email, AND (b) an HMAC token that only our signed email
		// links carry. Without the token, the email match alone is bypassable
		// because billing emails are guessable (info@, contact@, etc.).
		$order_id = (int) ( $data['order_id'] ?? 0 );
		$token    = (string) ( $data['token'] ?? '' );
		$email    = (string) ( $data['email'] ?? '' );
		$verified = false;
		if ( $order_id > 0 && $token !== '' && $email !== '' ) {
			if ( self::verify_review_token( $token, $product_id, $order_id, $email ) ) {
				$verified = self::order_contains_product( $order_id, $product_id, $email );
			}
		}
		update_comment_meta( $comment_id, 'verified', $verified ? 1 : 0 );
		if ( $verified ) {
			update_comment_meta( $comment_id, '_oz_verified_order', $order_id );
		}

		// Review-specific meta (oz-reviews own namespace).
		update_comment_meta( $comment_id, '_oz_title', $title );
		$photos = isset( $data['photos'] ) && is_array( $data['photos'] ) ? array_values( array_filter( $data['photos'] ) ) : array();
		if ( ! empty( $photos ) ) {
			update_comment_meta( $comment_id, '_oz_photos', $photos );
		}
		foreach ( array( 'project_type', 'color_used' ) as $k ) {
			if ( ! empty( $data[ $k ] ) ) {
				update_comment_meta( $comment_id, '_oz_' . $k, sanitize_text_field( (string) $data[ $k ] ) );
			}
		}
		if ( isset( $data['m2'] ) && $data['m2'] !== '' ) {
			update_comment_meta( $comment_id, '_oz_m2', (float) $data['m2'] );
		}
		if ( ! empty( $data['stad'] ) ) {
			update_comment_meta( $comment_id, '_oz_city', sanitize_text_field( (string) $data['stad'] ) );
		}

		// Cross-reference back to the raw submission for audit.
		update_comment_meta( $comment_id, '_oz_submission_id', $submission_post_id );
		update_post_meta( $submission_post_id, '_oz_review_comment_id', $comment_id );
	}

	private static function order_contains_product( int $order_id, int $product_id, string $email ) : bool {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return false;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}
		// Email must match the order billing email (cheap guard against random order IDs).
		if ( $email !== '' && strcasecmp( $email, (string) $order->get_billing_email() ) !== 0 ) {
			return false;
		}
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			if ( (int) $item->get_product_id() === $product_id
				|| (int) $item->get_variation_id() === $product_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Sign a review token. Used when we generate a "leave a review" email link.
	 * Ties the token to product + order + email so it can't be replayed elsewhere.
	 */
	public static function sign_review_token( int $product_id, int $order_id, string $email ) : string {
		$payload = $product_id . '|' . $order_id . '|' . strtolower( trim( $email ) );
		return hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
	}

	/**
	 * Constant-time verify of a review token.
	 */
	private static function verify_review_token( string $token, int $product_id, int $order_id, string $email ) : bool {
		$expected = self::sign_review_token( $product_id, $order_id, $email );
		return is_string( $token ) && hash_equals( $expected, $token );
	}

	private static function client_ip() : string {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
