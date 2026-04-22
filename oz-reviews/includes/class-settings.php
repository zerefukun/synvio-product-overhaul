<?php
namespace OZ_Reviews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page under Shop Reviews menu.
 * Stores options in wp_options under key 'oz_reviews_settings'.
 *
 * Option shape:
 *   google_place_id    string  (e.g. ChIJj-6B3Xe3xUcRmjg1lTEhkIM)
 *   request_delay_days int     (days after order-completed to send first email)
 *   reminder_delay_days int    (days after first email if no review received)
 *   slack_webhook_url  string  (low-rating alerts)
 *   slack_min_rating   int     (1..5, alert when review <= this rating)
 */
class Settings {

	public const OPTION = 'oz_reviews_settings';

	public static function defaults() : array {
		return array(
			'google_place_id'       => 'ChIJj-6B3Xe3xUcRmjg1lTEhkIM',
			'google_places_api_key' => '',
			'request_delay_days'    => 3,
			'reminder_delay_days'   => 10,
			'slack_webhook_url'     => '',
			'slack_min_rating'      => 3,
		);
	}

	public static function get( string $key ) {
		$opts = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
		return $opts[ $key ] ?? null;
	}

	public static function register() : void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_fields' ) );
	}

	public static function menu() : void {
		add_submenu_page(
			'edit.php?post_type=' . CPT::CPT,
			'Review settings',
			'Settings',
			'manage_options',
			'oz-reviews-settings',
			array( __CLASS__, 'render' )
		);
	}

	public static function register_fields() : void {
		register_setting(
			'oz_reviews',
			self::OPTION,
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public static function sanitize( $input ) : array {
		$out = self::defaults();
		$out['google_place_id']       = sanitize_text_field( $input['google_place_id'] ?? '' );
		$out['google_places_api_key'] = sanitize_text_field( $input['google_places_api_key'] ?? '' );
		$out['request_delay_days']    = max( 0, min( 60, (int) ( $input['request_delay_days'] ?? 3 ) ) );
		$out['reminder_delay_days']   = max( 0, min( 90, (int) ( $input['reminder_delay_days'] ?? 10 ) ) );
		$out['slack_webhook_url']     = esc_url_raw( $input['slack_webhook_url'] ?? '' );
		$out['slack_min_rating']      = max( 1, min( 5, (int) ( $input['slack_min_rating'] ?? 3 ) ) );
		return $out;
	}

	public static function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$opts = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
		?>
		<div class="wrap">
			<h1>Review settings</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'oz_reviews' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="oz-reviews-place-id">Google Place ID</label></th>
						<td>
							<input type="text" id="oz-reviews-place-id"
								name="<?php echo esc_attr( self::OPTION ); ?>[google_place_id]"
								value="<?php echo esc_attr( $opts['google_place_id'] ); ?>"
								class="regular-text">
							<p class="description">Used for the direct-to-Google review CTA and for the daily Places API sync.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="oz-reviews-places-key">Places API (New) key</label></th>
						<td>
							<input type="password" id="oz-reviews-places-key"
								name="<?php echo esc_attr( self::OPTION ); ?>[google_places_api_key]"
								value="<?php echo esc_attr( $opts['google_places_api_key'] ); ?>"
								class="regular-text" autocomplete="off">
							<p class="description">Google Cloud API key with Places API (New) enabled. Used once daily to sync the 5 most-recent reviews. Historical reviews come from one-time scrape import.</p>
							<?php
							$last = get_option( 'oz_reviews_last_sync' );
							if ( is_array( $last ) && ! empty( $last['ran_at'] ) ) {
								$when = human_time_diff( (int) $last['ran_at'], time() );
								if ( ! empty( $last['ok'] ) ) {
									printf(
										'<p class="description"><strong>Laatste sync:</strong> %s geleden — ingevoegd: %d, bijgewerkt: %d, overgeslagen: %d</p>',
										esc_html( $when ),
										(int) ( $last['inserted'] ?? 0 ),
										(int) ( $last['updated'] ?? 0 ),
										(int) ( $last['skipped'] ?? 0 )
									);
								} else {
									printf(
										'<p class="description" style="color:#b32d2e;"><strong>Laatste sync mislukte:</strong> %s (%s geleden)</p>',
										esc_html( (string) ( $last['error'] ?? 'onbekend' ) ),
										esc_html( $when )
									);
								}
							}
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="oz-reviews-request-delay">Request email delay (days)</label></th>
						<td>
							<input type="number" min="0" max="60" id="oz-reviews-request-delay"
								name="<?php echo esc_attr( self::OPTION ); ?>[request_delay_days]"
								value="<?php echo esc_attr( $opts['request_delay_days'] ); ?>"
								class="small-text">
							<p class="description">Days after order-completed to send first review request.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="oz-reviews-reminder-delay">Reminder delay (days)</label></th>
						<td>
							<input type="number" min="0" max="90" id="oz-reviews-reminder-delay"
								name="<?php echo esc_attr( self::OPTION ); ?>[reminder_delay_days]"
								value="<?php echo esc_attr( $opts['reminder_delay_days'] ); ?>"
								class="small-text">
							<p class="description">Days after first email to send one reminder (if no review yet).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="oz-reviews-slack-url">Slack webhook URL</label></th>
						<td>
							<input type="url" id="oz-reviews-slack-url"
								name="<?php echo esc_attr( self::OPTION ); ?>[slack_webhook_url]"
								value="<?php echo esc_attr( $opts['slack_webhook_url'] ); ?>"
								class="regular-text">
							<p class="description">Incoming webhook for low-rating alerts.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="oz-reviews-slack-rating">Slack alert threshold</label></th>
						<td>
							<input type="number" min="1" max="5" id="oz-reviews-slack-rating"
								name="<?php echo esc_attr( self::OPTION ); ?>[slack_min_rating]"
								value="<?php echo esc_attr( $opts['slack_min_rating'] ); ?>"
								class="small-text">
							<p class="description">Alert when a new review is at or below this rating.</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
