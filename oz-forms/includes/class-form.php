<?php
namespace OZ_Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form — schema-driven renderer + server-side validator.
 *
 * Field spec:
 *   array(
 *     'label'       => 'Naam',
 *     'type'        => 'text|email|tel|number|textarea|select|checkbox|radio',
 *     'required'    => true,
 *     'placeholder' => 'optional',
 *     'options'     => array('value' => 'label')   // for select/radio/checkbox-group
 *     'maxlength'   => 200,
 *     'pattern'     => '^[0-9]+$',
 *     'help'        => 'small helper text',
 *   )
 */
class Form {

	/** Set whenever a form is rendered on the current request. Used to gate enqueues. */
	private static $rendered = false;

	public static function page_has_form() : bool {
		// Conservative: enqueue when the current main post contains an oz/form block
		// or the [oz_form] shortcode.
		if ( self::$rendered ) {
			return true;
		}
		global $post;
		if ( $post instanceof \WP_Post ) {
			if ( has_block( 'oz/form', $post ) ) {
				return true;
			}
			if ( has_shortcode( $post->post_content, 'oz_form' ) ) {
				return true;
			}
		}
		return false;
	}

	public static function render( string $form_id ) : string {
		$schema = Schema_Registry::get( $form_id );
		if ( ! $schema ) {
			return '';
		}

		self::$rendered = true;

		$action     = $schema['id']; // Turnstile data-action match
		$has_steps  = ! empty( $schema['steps'] ) && is_array( $schema['steps'] );
		$submit_lbl = esc_html( $schema['submit_label'] ?? 'Verstuur' );

		ob_start();
		?>
		<form
			class="oz-form<?php echo $has_steps ? ' oz-form--steps' : ''; ?>"
			data-form-id="<?php echo esc_attr( $schema['id'] ); ?>"
			data-action="<?php echo esc_attr( $action ); ?>"
			<?php echo $has_steps ? ' data-steps="' . count( $schema['steps'] ) . '"' : ''; ?>
			novalidate
		>
			<?php if ( $has_steps ) : ?>
				<?php echo self::render_steps_progress( $schema['steps'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php foreach ( $schema['steps'] as $i => $step ) : ?>
					<fieldset class="oz-form__step" data-step="<?php echo (int) $i; ?>"<?php echo $i === 0 ? '' : ' hidden'; ?>>
						<?php if ( ! empty( $step['title'] ) ) : ?>
							<legend class="oz-form__step-title"><?php echo esc_html( $step['title'] ); ?></legend>
						<?php endif; ?>
						<?php if ( ! empty( $step['intro'] ) ) : ?>
							<p class="oz-form__step-intro"><?php echo esc_html( $step['intro'] ); ?></p>
						<?php endif; ?>
						<?php foreach ( $step['fields'] as $name ) : ?>
							<?php
							$spec = $schema['fields'][ $name ] ?? null;
							if ( $spec ) {
								echo self::render_field( $name, $spec ); // phpcs:ignore WordPress.Security.EscapeOutput
							}
							?>
						<?php endforeach; ?>
					</fieldset>
				<?php endforeach; ?>
			<?php else : ?>
				<?php foreach ( $schema['fields'] as $name => $spec ) : ?>
					<?php echo self::render_field( $name, $spec ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php /* Honeypot — hidden text input. Real users leave it blank. */ ?>
			<div class="oz-form__hp" aria-hidden="true">
				<label>Website <input type="text" name="oz_website" tabindex="-1" autocomplete="off"></label>
			</div>

			<?php /* Time-trap — submit time stamp. <3 seconds = bot. */ ?>
			<input type="hidden" name="oz_t" value="<?php echo esc_attr( (string) time() ); ?>">

			<?php /* Turnstile mount — only on the final step. JS will (re-)render when shown. */ ?>
			<div class="oz-form__turnstile" data-action="<?php echo esc_attr( $action ); ?>"<?php echo $has_steps ? ' hidden' : ''; ?>></div>

			<div class="oz-form__nav">
				<?php if ( $has_steps ) : ?>
					<button type="button" class="oz-form__prev oz-hp-btn oz-hp-btn--ghost" hidden>Vorige</button>
					<button type="button" class="oz-form__next oz-hp-btn oz-hp-btn--teal">Volgende</button>
				<?php endif; ?>
				<button type="submit" class="oz-form__submit oz-hp-btn oz-hp-btn--teal"<?php echo $has_steps ? ' hidden' : ''; ?>>
					<?php echo $submit_lbl; ?>
				</button>
			</div>

			<div class="oz-form__status" role="status" aria-live="polite"></div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	private static function render_steps_progress( array $steps ) : string {
		$out = '<ol class="oz-form__progress" aria-label="Voortgang">';
		foreach ( $steps as $i => $step ) {
			$is_first = $i === 0 ? ' is-active' : '';
			$out .= sprintf(
				'<li class="oz-form__progress-step%s" data-step="%d"><span class="oz-form__progress-num">%d</span><span class="oz-form__progress-label">%s</span></li>',
				$is_first,
				$i,
				$i + 1,
				esc_html( $step['title'] ?? ( 'Stap ' . ( $i + 1 ) ) )
			);
		}
		$out .= '</ol>';
		return $out;
	}

	private static function render_field( string $name, array $spec ) : string {
		$id       = 'oz-' . sanitize_html_class( $name );
		$type     = $spec['type'] ?? 'text';
		$label    = $spec['label'] ?? $name;
		$required = ! empty( $spec['required'] );
		$req_attr = $required ? ' required' : '';
		$req_mark = $required ? ' <span class="oz-form__req" aria-hidden="true">*</span>' : '';
		$help     = ! empty( $spec['help'] ) ? '<small class="oz-form__help">' . esc_html( $spec['help'] ) . '</small>' : '';

		$common = sprintf(
			' id="%s" name="%s"%s%s%s%s',
			esc_attr( $id ),
			esc_attr( $name ),
			$req_attr,
			! empty( $spec['placeholder'] ) ? ' placeholder="' . esc_attr( $spec['placeholder'] ) . '"' : '',
			! empty( $spec['maxlength'] ) ? ' maxlength="' . (int) $spec['maxlength'] . '"' : '',
			! empty( $spec['pattern'] ) ? ' pattern="' . esc_attr( $spec['pattern'] ) . '"' : ''
		);

		$control = '';
		switch ( $type ) {
			case 'textarea':
				$rows = (int) ( $spec['rows'] ?? 5 );
				$control = '<textarea' . $common . ' rows="' . $rows . '"></textarea>';
				break;
			case 'select':
				$options = $spec['options'] ?? array();
				$opts_html = '<option value="">' . esc_html( $spec['placeholder'] ?? 'Maak een keuze' ) . '</option>';
				foreach ( $options as $val => $opt_label ) {
					$opts_html .= '<option value="' . esc_attr( $val ) . '">' . esc_html( $opt_label ) . '</option>';
				}
				$control = '<select' . $common . '>' . $opts_html . '</select>';
				break;
			case 'checkbox':
				// Single boolean checkbox.
				$control = '<label class="oz-form__check"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . $req_attr . '> ' . esc_html( $label ) . '</label>';
				$label   = ''; // skip the outer label rendering
				break;
			case 'radio':
				$options = $spec['options'] ?? array();
				$radios  = '';
				foreach ( $options as $val => $opt_label ) {
					$rid    = $id . '-' . sanitize_html_class( (string) $val );
					$radios .= '<label class="oz-form__radio"><input type="radio" id="' . esc_attr( $rid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '"' . $req_attr . '> <span>' . esc_html( $opt_label ) . '</span></label>';
				}
				$control = '<div class="oz-form__radios" role="radiogroup" aria-labelledby="' . esc_attr( $id ) . '-label">' . $radios . '</div>';
				break;
			default:
				$input_type = in_array( $type, array( 'text', 'email', 'tel', 'number', 'url', 'date' ), true ) ? $type : 'text';
				$control    = '<input type="' . esc_attr( $input_type ) . '"' . $common . '>';
		}

		$label_html = $label !== '' ? '<label for="' . esc_attr( $id ) . '" class="oz-form__label">' . esc_html( $label ) . $req_mark . '</label>' : '';

		return '<div class="oz-form__field oz-form__field--' . esc_attr( $type ) . '">' . $label_html . $control . $help . '<span class="oz-form__error" aria-live="polite"></span></div>';
	}

	/**
	 * Sanitize + validate raw payload against schema.
	 *
	 * @return array{ok: bool, data: array, errors: array<string,string>}
	 */
	public static function validate( array $schema, array $raw ) : array {
		$data   = array();
		$errors = array();

		foreach ( $schema['fields'] as $name => $spec ) {
			$type     = $spec['type'] ?? 'text';
			$required = ! empty( $spec['required'] );
			$value    = $raw[ $name ] ?? '';

			if ( is_string( $value ) ) {
				$value = trim( $value );
			}

			// Required check.
			if ( $required && ( $value === '' || $value === null ) ) {
				$errors[ $name ] = 'Dit veld is verplicht.';
				continue;
			}
			if ( $value === '' || $value === null ) {
				$data[ $name ] = '';
				continue;
			}

			// Type-specific sanitize + validate.
			switch ( $type ) {
				case 'email':
					$value = sanitize_email( $value );
					if ( ! is_email( $value ) ) {
						$errors[ $name ] = 'Vul een geldig e-mailadres in.';
					}
					break;
				case 'tel':
					$value = preg_replace( '/[^0-9+()\-\s]/', '', (string) $value );
					break;
				case 'number':
					if ( ! is_numeric( $value ) ) {
						$errors[ $name ] = 'Vul een getal in.';
					}
					break;
				case 'textarea':
					$value = sanitize_textarea_field( $value );
					break;
				case 'select':
					$allowed = array_keys( $spec['options'] ?? array() );
					if ( ! in_array( $value, $allowed, true ) ) {
						$errors[ $name ] = 'Maak een geldige keuze.';
					}
					break;
				case 'checkbox':
					$value = $value ? '1' : '';
					break;
				case 'radio':
					$allowed = array_keys( $spec['options'] ?? array() );
					if ( ! in_array( $value, $allowed, true ) ) {
						$errors[ $name ] = 'Maak een geldige keuze.';
					}
					break;
				default:
					$value = sanitize_text_field( $value );
			}

			if ( ! empty( $spec['maxlength'] ) && is_string( $value ) && mb_strlen( $value ) > (int) $spec['maxlength'] ) {
				$errors[ $name ] = sprintf( 'Maximaal %d tekens.', (int) $spec['maxlength'] );
			}

			$data[ $name ] = $value;
		}

		return array(
			'ok'     => empty( $errors ),
			'data'   => $data,
			'errors' => $errors,
		);
	}
}
