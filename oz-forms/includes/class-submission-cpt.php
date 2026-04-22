<?php
namespace OZ_Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * oz_submission CPT — every form submission becomes one post.
 * Submission payload is stored in post meta (_oz_data, _oz_form, _oz_status, _oz_ip).
 * Admin list adds a Form-Type filter and a CSV export per type.
 */
class Submission_CPT {

	public const CPT = 'oz_submission';

	public static function register() : void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'form_type_filter' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_form_type_filter' ) );
		add_action( 'admin_post_oz_forms_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_notices', array( __CLASS__, 'export_button' ) );
		add_action( 'add_meta_boxes_' . self::CPT, array( __CLASS__, 'register_meta_boxes' ) );
	}

	public static function register_post_type() : void {
		register_post_type(
			self::CPT,
			array(
				'label'             => 'Form submissions',
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => true,
				'menu_icon'         => 'dashicons-email-alt',
				'menu_position'     => 56,
				'capability_type'   => 'post',
				'capabilities'      => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'      => true,
				'supports'          => array( 'title' ),
				'show_in_rest'      => false,
			)
		);
	}

	/* ────────────────────────── Detail view ────────────────────────── */

	public static function register_meta_boxes() : void {
		add_meta_box(
			'oz_submission_data',
			'Inzending',
			array( __CLASS__, 'render_data_meta_box' ),
			self::CPT,
			'normal',
			'high'
		);
		add_meta_box(
			'oz_submission_meta',
			'Details',
			array( __CLASS__, 'render_meta_meta_box' ),
			self::CPT,
			'side',
			'default'
		);
	}

	public static function render_data_meta_box( \WP_Post $post ) : void {
		$form_id = (string) get_post_meta( $post->ID, '_oz_form', true );
		$data    = get_post_meta( $post->ID, '_oz_data', true );
		$schema  = Schema_Registry::get( $form_id );
		$fields  = is_array( $schema ) && isset( $schema['fields'] ) ? $schema['fields'] : array();

		if ( ! is_array( $data ) || empty( $data ) ) {
			echo '<p><em>Geen velden opgeslagen voor deze inzending.</em></p>';
			return;
		}

		echo '<table class="widefat striped" style="margin:0;">';
		echo '<tbody>';
		foreach ( $data as $key => $value ) {
			$label = isset( $fields[ $key ]['label'] ) ? (string) $fields[ $key ]['label'] : (string) $key;
			$rendered = self::render_value( $value );
			echo '<tr>';
			echo '<th style="width:30%;text-align:left;padding:10px 12px;vertical-align:top;">' . esc_html( $label ) . '</th>';
			echo '<td style="padding:10px 12px;">' . $rendered . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Render a single field value for the admin detail view.
	 * Handles arrays, URLs (clickable link), and image URLs (thumbnail + link).
	 *
	 * @param mixed $value
	 */
	private static function render_value( $value ) : string {
		if ( is_array( $value ) ) {
			$parts = array();
			foreach ( $value as $v ) {
				$parts[] = self::render_value( $v );
			}
			return implode( '<br>', $parts );
		}

		$str = (string) $value;
		$trimmed = trim( $str );

		// URL? Render as link (with thumbnail only for images we host ourselves).
		if ( $trimmed !== '' && preg_match( '#^https?://#i', $trimmed ) && filter_var( $trimmed, FILTER_VALIDATE_URL ) ) {
			// Deliberately excludes SVG — SVGs can carry inline <script> that
			// would execute in the admin origin when the img is loaded via
			// some browser contexts, or when an admin opens it in a new tab.
			$is_image_ext = (bool) preg_match( '/\.(jpe?g|png|gif|webp|avif)(\?|$)/i', $trimmed );
			// Only inline-preview images hosted on our own uploads dir. Any
			// external URL could be a tracking pixel aimed at the admin's IP
			// or a huge image that hangs the admin view.
			$uploads    = wp_get_upload_dir();
			$our_base   = isset( $uploads['baseurl'] ) ? (string) $uploads['baseurl'] : '';
			$is_own_url = ( $our_base !== '' && stripos( $trimmed, $our_base ) === 0 );
			if ( $is_image_ext && $is_own_url ) {
				return '<a href="' . esc_url( $trimmed ) . '" target="_blank" rel="noopener">'
					. '<img src="' . esc_url( $trimmed ) . '" alt="" style="max-width:280px;max-height:280px;display:block;margin:4px 0 8px;border:1px solid #ddd;border-radius:4px;">'
					. '</a>'
					. '<small><a href="' . esc_url( $trimmed ) . '" target="_blank" rel="noopener">Open in nieuw tabblad</a></small>';
			}
			// External URL or non-image: render as plain escaped text, NOT as
			// a clickable link — clicking an attacker-submitted URL from the
			// admin leaks the admin's IP + referrer.
			if ( ! $is_own_url ) {
				return esc_html( $trimmed );
			}
			return '<a href="' . esc_url( $trimmed ) . '" target="_blank" rel="noopener">' . esc_html( $trimmed ) . '</a>';
		}

		return nl2br( esc_html( $str ) );
	}

	public static function render_meta_meta_box( \WP_Post $post ) : void {
		$form   = (string) get_post_meta( $post->ID, '_oz_form', true );
		$status = (string) get_post_meta( $post->ID, '_oz_status', true );
		$note   = (string) get_post_meta( $post->ID, '_oz_note', true );
		$ip     = (string) get_post_meta( $post->ID, '_oz_ip', true );
		$ua     = (string) get_post_meta( $post->ID, '_oz_ua', true );

		echo '<p><strong>Formulier:</strong><br>' . esc_html( $form ) . '</p>';
		echo '<p><strong>Status:</strong><br>' . ( $status === 'spam' ? '<span style="color:#b32d2e;">spam</span>' : esc_html( $status ) ) . '</p>';
		if ( $note !== '' ) {
			echo '<p><strong>Notitie:</strong><br>' . esc_html( $note ) . '</p>';
		}
		if ( $ip !== '' ) {
			echo '<p><strong>IP:</strong><br><code>' . esc_html( $ip ) . '</code></p>';
		}
		if ( $ua !== '' ) {
			echo '<p><strong>User-agent:</strong><br><small style="word-break:break-all;">' . esc_html( $ua ) . '</small></p>';
		}
	}

	/**
	 * Persist a submission. Returns the post ID.
	 *
	 * @param string $form_id  Schema id (e.g. "contact").
	 * @param array  $data     Sanitized field data.
	 * @param string $status   "ok" | "spam" | "error".
	 * @param string $note     Optional note (e.g. spam reason).
	 */
	public static function store( string $form_id, array $data, string $status, string $note = '' ) : int {
		$schema = Schema_Registry::get( $form_id );
		$title  = sprintf( '[%s] %s', $form_id, self::summary_title( $data ) );

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		update_post_meta( $post_id, '_oz_form', $form_id );
		update_post_meta( $post_id, '_oz_data', $data );
		update_post_meta( $post_id, '_oz_status', $status );
		update_post_meta( $post_id, '_oz_note', $note );
		update_post_meta( $post_id, '_oz_ip', self::client_ip() );
		update_post_meta( $post_id, '_oz_ua', isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' );

		return $post_id;
	}

	private static function summary_title( array $data ) : string {
		$candidates = array( 'naam', 'name', 'email', 'voornaam' );
		foreach ( $candidates as $k ) {
			if ( ! empty( $data[ $k ] ) ) {
				return wp_strip_all_tags( (string) $data[ $k ] );
			}
		}
		return current_time( 'mysql' );
	}

	private static function client_ip() : string {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/* ────────────────────────── Admin UI ────────────────────────── */

	public static function columns( array $cols ) : array {
		$new = array(
			'cb'       => $cols['cb'] ?? '',
			'title'    => 'Submission',
			'oz_form'  => 'Form',
			'oz_email' => 'Email',
			'oz_status'=> 'Status',
			'date'     => $cols['date'] ?? 'Date',
		);
		return $new;
	}

	public static function column_value( string $col, int $post_id ) : void {
		switch ( $col ) {
			case 'oz_form':
				echo esc_html( get_post_meta( $post_id, '_oz_form', true ) );
				break;
			case 'oz_email':
				$data = get_post_meta( $post_id, '_oz_data', true );
				if ( is_array( $data ) && ! empty( $data['email'] ) ) {
					echo esc_html( $data['email'] );
				}
				break;
			case 'oz_status':
				$status = get_post_meta( $post_id, '_oz_status', true );
				$label  = $status === 'spam' ? '<span style="color:#b32d2e;">spam</span>' : esc_html( $status );
				echo wp_kses_post( $label );
				break;
		}
	}

	public static function form_type_filter() : void {
		global $typenow;
		if ( $typenow !== self::CPT ) {
			return;
		}
		$current = isset( $_GET['oz_form'] ) ? sanitize_key( wp_unslash( $_GET['oz_form'] ) ) : '';
		echo '<select name="oz_form"><option value="">All forms</option>';
		foreach ( Schema_Registry::ids() as $id ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $id ),
				selected( $current, $id, false ),
				esc_html( $id )
			);
		}
		echo '</select>';
	}

	public static function apply_form_type_filter( \WP_Query $q ) : void {
		if ( ! is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( ( $q->get( 'post_type' ) ?? '' ) !== self::CPT ) {
			return;
		}
		$form = isset( $_GET['oz_form'] ) ? sanitize_key( wp_unslash( $_GET['oz_form'] ) ) : '';
		if ( $form !== '' ) {
			$meta = (array) $q->get( 'meta_query' );
			$meta[] = array( 'key' => '_oz_form', 'value' => $form );
			$q->set( 'meta_query', $meta );
		}
	}

	public static function export_button() : void {
		global $typenow;
		if ( $typenow !== self::CPT ) {
			return;
		}
		$form = isset( $_GET['oz_form'] ) ? sanitize_key( wp_unslash( $_GET['oz_form'] ) ) : '';
		$url  = wp_nonce_url(
			admin_url( 'admin-post.php?action=oz_forms_export&oz_form=' . rawurlencode( $form ) ),
			'oz_forms_export'
		);
		echo '<div class="notice notice-info"><p>';
		echo '<a class="button" href="' . esc_url( $url ) . '">Export current view to CSV</a>';
		echo '</p></div>';
	}

	public static function export_csv() : void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( 'oz_forms_export' );

		$form = isset( $_GET['oz_form'] ) ? sanitize_key( wp_unslash( $_GET['oz_form'] ) ) : '';

		$args = array(
			'post_type'      => self::CPT,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);
		if ( $form !== '' ) {
			$args['meta_query'] = array( array( 'key' => '_oz_form', 'value' => $form ) );
		}

		$posts = get_posts( $args );

		// Build column union
		$columns = array( 'date', 'form', 'status' );
		foreach ( $posts as $p ) {
			$d = get_post_meta( $p->ID, '_oz_data', true );
			if ( is_array( $d ) ) {
				foreach ( array_keys( $d ) as $k ) {
					if ( ! in_array( $k, $columns, true ) ) {
						$columns[] = $k;
					}
				}
			}
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="oz-submissions-' . ( $form ?: 'all' ) . '-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array_map( array( __CLASS__, 'csv_escape' ), $columns ) );
		foreach ( $posts as $p ) {
			$d = get_post_meta( $p->ID, '_oz_data', true );
			$row = array(
				$p->post_date,
				get_post_meta( $p->ID, '_oz_form', true ),
				get_post_meta( $p->ID, '_oz_status', true ),
			);
			foreach ( array_slice( $columns, 3 ) as $k ) {
				$row[] = is_array( $d[ $k ] ?? null ) ? wp_json_encode( $d[ $k ] ) : (string) ( $d[ $k ] ?? '' );
			}
			fputcsv( $out, array_map( array( __CLASS__, 'csv_escape' ), $row ) );
		}
		fclose( $out );
		exit;
	}

	/**
	 * Escape a CSV cell to neutralize spreadsheet formula injection.
	 * If a value starts with =, +, -, @, tab, or CR it will be interpreted
	 * as a formula by Excel/Numbers/Sheets — prepend a single quote so the
	 * cell is treated as plain text.
	 *
	 * @param mixed $value
	 */
	private static function csv_escape( $value ) : string {
		$str = (string) $value;
		if ( $str !== '' && preg_match( "/^[=+\-@\t\r]/", $str ) ) {
			return "'" . $str;
		}
		return $str;
	}
}
