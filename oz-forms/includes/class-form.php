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
		// Conservative: enqueue when the current main post contains an oz/form block.
		if ( self::$rendered ) {
			return true;
		}
		global $post;
		if ( $post instanceof \WP_Post && has_block( 'oz/form', $post ) ) {
			return true;
		}
		return false;
	}

	public static function render( string $form_id ) : string {
		$schema = Schema_Registry::get( $form_id );
		if ( ! $schema ) {
			return '';
		}

		self::$rendered = true;

		ob_start();
		$action = $schema['id']; // used for Turnstile data-action match
		?>
		<form
			class="oz-form"
			data-form-id="<?php echo esc_attr( $schema['id'] ); ?>"
			data-action="<?php echo esc_attr( $action ); ?>"
			novalidate
		>
			<?php foreach ( $schema['fields'] as $name => $spec ) : ?>
				<?php echo self::render_field( $name, $spec ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php endforeach; ?>

			<?php /* Honeypot — hidden text input. Real users leave it blank. */ ?>
			<div class="oz-form__hp" aria-hidden="true">
				<label>Website <input type="text" name="oz_website" tabindex="-1" autocomplete="off"></label>
			</div>

			<?php /* Time-trap — submit time stamp. <3 seconds = bot. */ ?>
			<input type="hidden" name="oz_t" value="<?php echo esc_attr( (string) time() ); ?>">

			<?php /* Turnstile widget mount point. JS calls turnstile.render() here. */ ?>
			<div class="oz-form__turnstile" data-action="<?php echo esc_attr( $action ); ?>"></div>

			<button type="submit" class="oz-form__submit oz-hp-btn oz-hp-btn--teal">
				<?php echo esc_html( $schema['submit_label'] ?? 'Verstuur' ); ?>
			</button>

			<div class="oz-form__status" role="status" aria-live="polite"></div>
		</form>
		<?php
		return (string) ob_get_clean();
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
