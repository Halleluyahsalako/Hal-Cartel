<?php
/**
 * Extension registry + shared admin shell — the "godmode ecosystem" seam (spec §11).
 *
 * Official and third-party Hal_Cartel_Extension subclasses register here and get,
 * for free: a card on the "Extensions" screen, an auto-generated settings
 * panel (from settings_schema(), respecting §4 progressive disclosure),
 * and activation/deactivation lifecycle hooks.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_Extensions {

	const ACTIVE_OPTION = 'hal_cartel_active_extensions';

	/** @var Hal_Cartel_Extension[] */
	private static $extensions = array();

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_hal_cartel_toggle_extension', array( __CLASS__, 'handle_toggle' ) );
	}

	public static function register( Hal_Cartel_Extension $extension ): Hal_Cartel_Extension {
		self::$extensions[ $extension->id() ] = $extension;
		return $extension;
	}

	/** @return Hal_Cartel_Extension[] */
	public static function all(): array {
		return self::$extensions;
	}

	public static function get( string $id ) {
		return isset( self::$extensions[ $id ] ) ? self::$extensions[ $id ] : null;
	}

	public static function is_active( string $id ): bool {
		return in_array( $id, get_option( self::ACTIVE_OPTION, array() ), true );
	}

	public static function settings_option_key( string $id ): string {
		return 'hal_cartel_ext_' . $id;
	}

	public static function settings( string $id ): array {
		$extension = self::get( $id );
		if ( ! $extension ) {
			return array();
		}

		$defaults = array();
		foreach ( $extension->settings_schema() as $field ) {
			$defaults[ $field['key'] ] = isset( $field['default'] ) ? $field['default'] : '';
		}

		return wp_parse_args( get_option( self::settings_option_key( $id ), array() ), $defaults );
	}

	public static function register_settings() {
		foreach ( self::$extensions as $extension ) {
			if ( ! $extension->settings_schema() ) {
				continue;
			}
			register_setting(
				'hal_cartel_extensions',
				self::settings_option_key( $extension->id() ),
				array(
					'type'              => 'array',
					'sanitize_callback' => function ( $value ) use ( $extension ) {
						return self::sanitize_settings( $extension, (array) $value );
					},
				)
			);
		}
	}

	private static function sanitize_settings( Hal_Cartel_Extension $extension, array $input ): array {
		$clean = array();
		foreach ( $extension->settings_schema() as $field ) {
			$key = $field['key'];
			$raw = isset( $input[ $key ] ) ? $input[ $key ] : '';

			switch ( isset( $field['type'] ) ? $field['type'] : 'text' ) {
				case 'checkbox':
					$clean[ $key ] = empty( $raw ) ? 0 : 1;
					break;
				case 'number':
					$clean[ $key ] = is_numeric( $raw ) ? $raw + 0 : 0;
					break;
				default:
					$clean[ $key ] = sanitize_text_field( wp_unslash( $raw ) );
			}
		}
		return $clean;
	}

	public static function menu() {
		add_submenu_page(
			'hal-cartel',
			__( 'Extensions', 'cartel' ),
			__( 'Extensions', 'cartel' ),
			'manage_options',
			'hal-cartel-extensions',
			array( __CLASS__, 'render_screen' )
		);
	}

	public static function handle_toggle() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'hal_cartel_toggle_extension' ) ) {
			wp_die( esc_html__( 'Forbidden', 'cartel' ) );
		}

		$id        = isset( $_GET['extension'] ) ? sanitize_key( wp_unslash( $_GET['extension'] ) ) : '';
		$extension = self::get( $id );
		if ( ! $extension ) {
			wp_die( esc_html__( 'Unknown extension.', 'cartel' ) );
		}

		$active = get_option( self::ACTIVE_OPTION, array() );
		if ( in_array( $id, $active, true ) ) {
			$active = array_values( array_diff( $active, array( $id ) ) );
			$extension->on_deactivate();
			do_action( 'hal_cartel_extension_deactivated', $id );
		} else {
			$active[] = $id;
			$extension->on_activate();
			do_action( 'hal_cartel_extension_activated', $id );
		}

		update_option( self::ACTIVE_OPTION, $active );
		wp_safe_redirect( admin_url( 'admin.php?page=hal-cartel-extensions' ) );
		exit;
	}

	public static function render_screen() {
		echo '<div class="wrap"><h1>' . esc_html__( 'Extensions', 'cartel' ) . '</h1>';
		echo '<p>' . esc_html__( 'Official and third-party Cartel extensions register here and share this admin shell — one cohesive product, not a pile of unrelated plugins.', 'cartel' ) . '</p>';

		if ( empty( self::$extensions ) ) {
			echo '<p>' . esc_html__( 'No extensions registered yet.', 'cartel' ) . '</p></div>';
			return;
		}

		echo '<div class="hal-cartel-extensions-grid">';
		foreach ( self::$extensions as $extension ) {
			self::render_card( $extension );
		}
		echo '</div></div>';
	}

	private static function render_card( Hal_Cartel_Extension $extension ) {
		$id         = $extension->id();
		$active     = self::is_active( $id );
		$toggle_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=hal_cartel_toggle_extension&extension=' . $id ),
			'hal_cartel_toggle_extension'
		);

		echo '<div class="hal-cartel-extension-card">';
		printf( '<h2>%s</h2>', esc_html( $extension->title() ) );
		printf( '<p>%s</p>', esc_html( $extension->description() ) );
		printf(
			'<p><a class="button %1$s" href="%2$s">%3$s</a></p>',
			$active ? 'button-secondary' : 'button-primary',
			esc_url( $toggle_url ),
			$active ? esc_html__( 'Deactivate', 'cartel' ) : esc_html__( 'Activate', 'cartel' )
		);

		if ( $active && $extension->settings_schema() ) {
			self::render_settings_form( $extension );
		}
		echo '</div>';
	}

	private static function render_settings_form( Hal_Cartel_Extension $extension ) {
		$option = self::settings_option_key( $extension->id() );
		$values = self::settings( $extension->id() );
		?>
		<form method="post" action="options.php" class="hal-cartel-extension-settings">
			<?php settings_fields( 'hal_cartel_extensions' ); ?>
			<table class="form-table">
				<?php foreach ( $extension->settings_schema() as $field ) : ?>
					<tr>
						<th><?php echo esc_html( $field['label'] ); ?></th>
						<td>
							<?php
							$render_field = function () use ( $field, $option, $values ) {
								self::render_field( $field, $option, $values );
							};
							if ( ! empty( $field['advanced'] ) ) {
								Hal_Cartel_UI::advanced( $render_field );
							} else {
								$render_field();
							}
							if ( ! empty( $field['description'] ) ) {
								echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button( __( 'Save settings', 'cartel' ) ); ?>
		</form>
		<?php
	}

	private static function render_field( array $field, string $option, array $values ) {
		$key  = $field['key'];
		$name = $option . '[' . $key . ']';
		$val  = isset( $values[ $key ] ) ? $values[ $key ] : '';

		switch ( isset( $field['type'] ) ? $field['type'] : 'text' ) {
			case 'checkbox':
				printf(
					'<input type="checkbox" name="%1$s" value="1" %2$s />',
					esc_attr( $name ),
					checked( $val, 1, false )
				);
				break;
			case 'number':
				printf( '<input type="number" name="%1$s" value="%2$s" />', esc_attr( $name ), esc_attr( $val ) );
				break;
			case 'select':
				printf( '<select name="%1$s">', esc_attr( $name ) );
				foreach ( (array) ( isset( $field['options'] ) ? $field['options'] : array() ) as $opt_value => $opt_label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $opt_value ),
						selected( $val, $opt_value, false ),
						esc_html( $opt_label )
					);
				}
				echo '</select>';
				break;
			default:
				printf( '<input type="text" name="%1$s" value="%2$s" class="regular-text" />', esc_attr( $name ), esc_attr( $val ) );
		}
	}
}
