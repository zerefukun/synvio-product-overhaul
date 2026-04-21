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
 *     'type'        => 'text|email|tel|number|textarea|select|autocomplete|checkbox|radio|multiselect|file|rating|hidden',
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
					<button type="button" class="oz-form__btn oz-form__prev" hidden>
						<span aria-hidden="true">&larr;</span> Vorige
					</button>
					<button type="button" class="oz-form__btn oz-form__btn--primary oz-form__next">
						Volgende <span aria-hidden="true">&rarr;</span>
					</button>
				<?php endif; ?>
				<button type="submit" class="oz-form__btn oz-form__btn--primary oz-form__submit"<?php echo $has_steps ? ' hidden' : ''; ?>>
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

		$autocomplete = self::resolve_autocomplete( $name, $type, $spec );

		$common = sprintf(
			' id="%s" name="%s"%s%s%s%s%s',
			esc_attr( $id ),
			esc_attr( $name ),
			$req_attr,
			! empty( $spec['placeholder'] ) ? ' placeholder="' . esc_attr( $spec['placeholder'] ) . '"' : '',
			! empty( $spec['maxlength'] ) ? ' maxlength="' . (int) $spec['maxlength'] . '"' : '',
			! empty( $spec['pattern'] ) ? ' pattern="' . esc_attr( $spec['pattern'] ) . '"' : '',
			$autocomplete !== '' ? ' autocomplete="' . esc_attr( $autocomplete ) . '"' : ''
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
			case 'autocomplete':
				// Single-select combobox with typeahead filtering. JS upgrades
				// the markup into a searchable dropdown; the non-JS fallback
				// is a plain native <select> so the form still submits.
				$options     = $spec['options'] ?? array();
				$placeholder = $spec['placeholder'] ?? 'Typ om te zoeken…';
				$opts_json   = wp_json_encode( $options );
				$opts_html   = '<option value="">' . esc_html( $placeholder ) . '</option>';
				foreach ( $options as $val => $opt_label ) {
					$opts_html .= '<option value="' . esc_attr( $val ) . '">' . esc_html( $opt_label ) . '</option>';
				}
				$control  = '<div class="oz-form__autocomplete" data-name="' . esc_attr( $name ) . '" data-options="' . esc_attr( $opts_json ) . '" data-placeholder="' . esc_attr( $placeholder ) . '">';
				$control .= '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '"' . $req_attr . ' class="oz-form__autocomplete-native">' . $opts_html . '</select>';
				$control .= '</div>';
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
			case 'multiselect':
				// Autocomplete multi-select. JS upgrades the markup into a chip picker;
				// non-JS fallback is a native multi-select so the form still works.
				$options    = $spec['options'] ?? array();
				$placeholder = esc_attr( $spec['placeholder'] ?? 'Typ om te zoeken…' );
				$opts_json  = wp_json_encode( $options );
				$opts_html  = '';
				foreach ( $options as $val => $opt_label ) {
					$opts_html .= '<option value="' . esc_attr( $val ) . '">' . esc_html( $opt_label ) . '</option>';
				}
				$control  = '<div class="oz-form__multiselect" data-name="' . esc_attr( $name ) . '" data-options="' . esc_attr( $opts_json ) . '" data-placeholder="' . $placeholder . '">';
				$control .= '<select multiple name="' . esc_attr( $name ) . '[]" id="' . esc_attr( $id ) . '"' . $req_attr . ' class="oz-form__multiselect-native">' . $opts_html . '</select>';
				$control .= '</div>';
				break;
			case 'file':
				$accept   = ! empty( $spec['accept'] ) ? ' accept="' . esc_attr( $spec['accept'] ) . '"' : '';
				$multiple = ! empty( $spec['multiple'] ) ? ' multiple' : '';
				$input_name = ! empty( $spec['multiple'] ) ? $name . '[]' : $name;
				$control  = '<input type="file" id="' . esc_attr( $id ) . '" name="' . esc_attr( $input_name ) . '"' . $accept . $multiple . $req_attr . ' class="oz-form__file-input">';
				break;
			case 'rating':
				// 5 radios in reverse so :checked ~ label can fill stars leftward with pure CSS.
				$radios = '';
				for ( $n = 5; $n >= 1; $n-- ) {
					$rid     = $id . '-' . $n;
					$radios .= '<input type="radio" id="' . esc_attr( $rid ) . '" name="' . esc_attr( $name ) . '" value="' . $n . '"' . $req_attr . '>';
					$radios .= '<label for="' . esc_attr( $rid ) . '" aria-label="' . $n . ' sterren"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg></label>';
				}
				$control = '<div class="oz-form__rating" role="radiogroup" aria-label="' . esc_attr( $label ) . '">' . $radios . '</div>';
				break;
			case 'hidden':
				$val = isset( $spec['value'] ) ? (string) $spec['value'] : '';
				// Optional: populate from ?query_param= so signed email links (TKT-1.3)
				// and manual test URLs can pre-fill without extra wiring.
				if ( $val === '' && ! empty( $spec['from_query'] ) && isset( $_GET[ $name ] ) ) {
					$val = sanitize_text_field( wp_unslash( (string) $_GET[ $name ] ) );
				}
				$control = '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '">';
				return $control; // no label/wrapper
			default:
				$input_type = in_array( $type, array( 'text', 'email', 'tel', 'number', 'url', 'date' ), true ) ? $type : 'text';
				$control    = '<input type="' . esc_attr( $input_type ) . '"' . $common . '>';
		}

		$label_html = $label !== '' ? '<label for="' . esc_attr( $id ) . '" class="oz-form__label">' . esc_html( $label ) . $req_mark . '</label>' : '';

		return '<div class="oz-form__field oz-form__field--' . esc_attr( $type ) . ' oz-form__field--' . esc_attr( sanitize_html_class( $name ) ) . '">' . $label_html . $control . $help . '<span class="oz-form__error" aria-live="polite"></span></div>';
	}

	/**
	 * Map a field to a WHATWG autocomplete token so browsers can autofill.
	 * Explicit 'autocomplete' in the spec always wins (including 'off').
	 */
	private static function resolve_autocomplete( string $name, string $type, array $spec ) : string {
		if ( array_key_exists( 'autocomplete', $spec ) ) {
			return (string) $spec['autocomplete'];
		}

		$autofillable = array( 'text', 'email', 'tel', 'number', 'url', 'date' );
		if ( ! in_array( $type, $autofillable, true ) ) {
			return '';
		}

		if ( $type === 'email' ) {
			return 'email';
		}
		if ( $type === 'tel' ) {
			return 'tel';
		}
		if ( $type === 'url' ) {
			return 'url';
		}

		$map = array(
			'name'          => 'name',
			'naam'          => 'name',
			'volledige_naam'=> 'name',
			'fullname'      => 'name',
			'full_name'     => 'name',
			'voornaam'      => 'given-name',
			'given_name'    => 'given-name',
			'first_name'    => 'given-name',
			'achternaam'    => 'family-name',
			'family_name'   => 'family-name',
			'last_name'     => 'family-name',
			'mail'          => 'email',
			'e_mail'        => 'email',
			'emailadres'    => 'email',
			'telefoon'      => 'tel',
			'phone'         => 'tel',
			'mobiel'        => 'tel',
			'bedrijf'       => 'organization',
			'company'       => 'organization',
			'organization'  => 'organization',
			'adres'         => 'street-address',
			'address'       => 'street-address',
			'straat'        => 'street-address',
			'street'        => 'street-address',
			'postcode'      => 'postal-code',
			'postal_code'   => 'postal-code',
			'zip'           => 'postal-code',
			'stad'          => 'address-level2',
			'plaats'        => 'address-level2',
			'woonplaats'    => 'address-level2',
			'city'          => 'address-level2',
			'provincie'     => 'address-level1',
			'region'        => 'address-level1',
			'land'          => 'country-name',
			'country'       => 'country-name',
			'website'       => 'url',
		);

		return $map[ strtolower( $name ) ] ?? '';
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

			// File type is handled separately by REST layer ($_FILES).
			// The REST handler injects the uploaded URL(s) into $raw[$name] before validation.
			// Multi-file fields receive arrays; single-file fields receive strings.
			if ( $type === 'file' ) {
				$is_multi = ! empty( $spec['multiple'] );
				if ( $is_multi ) {
					$arr = is_array( $value ) ? array_filter( array_map( 'esc_url_raw', $value ) ) : array();
					if ( $required && empty( $arr ) ) {
						$errors[ $name ] = 'Kies minstens één bestand.';
						continue;
					}
					$data[ $name ] = array_values( $arr );
				} else {
					if ( $required && empty( $value ) ) {
						$errors[ $name ] = 'Kies een bestand.';
						continue;
					}
					$data[ $name ] = is_string( $value ) ? esc_url_raw( $value ) : '';
				}
				continue;
			}

			// Rating: 1..5 integer.
			if ( $type === 'rating' ) {
				$n = (int) $value;
				if ( $required && $n < 1 ) {
					$errors[ $name ] = 'Geef een beoordeling.';
					continue;
				}
				if ( $n < 0 || $n > 5 ) {
					$errors[ $name ] = 'Ongeldige beoordeling.';
					continue;
				}
				$data[ $name ] = $n;
				continue;
			}

			// Hidden: just sanitize and carry through. Never required-enforced (UI can't surface).
			if ( $type === 'hidden' ) {
				$data[ $name ] = sanitize_text_field( (string) $value );
				continue;
			}

			// Multiselect: value is an array of option keys.
			if ( $type === 'multiselect' ) {
				$arr = is_array( $value ) ? $value : array();
				$allowed = array_keys( $spec['options'] ?? array() );
				$arr = array_values( array_filter( array_map( 'sanitize_text_field', $arr ), function ( $v ) use ( $allowed ) {
					return in_array( $v, $allowed, true );
				} ) );
				if ( $required && empty( $arr ) ) {
					$errors[ $name ] = 'Maak minstens één keuze.';
					continue;
				}
				$data[ $name ] = $arr;
				continue;
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
				case 'autocomplete':
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
