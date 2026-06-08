<?php
/**
 * Payment-gateway registry + shared admin shell ("Payment methods").
 *
 * Mirrors Hal_Cartel_Extensions: gateways register here and get a card on the
 * "Payment methods" screen, an auto-generated settings panel (from
 * settings_schema(), respecting progressive disclosure), and activation lifecycle.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_Gateways {

	const ACTIVE_OPTION = 'hal_cartel_active_gateways';

	/** @var Hal_Cartel_Gateway[] */
	private static $gateways = array();

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 31 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_hal_cartel_toggle_gateway', array( __CLASS__, 'handle_toggle' ) );
	}

	public static function register( Hal_Cartel_Gateway $gateway ): Hal_Cartel_Gateway {
		self::$gateways[ $gateway->id() ] = $gateway;
		return $gateway;
	}

	/** @return Hal_Cartel_Gateway[] */
	public static function all(): array {
		return self::$gateways;
	}

	public static function get( string $id ) {
		return isset( self::$gateways[ $id ] ) ? self::$gateways[ $id ] : null;
	}

	public static function is_active( string $id ): bool {
		return in_array( $id, get_option( self::ACTIVE_OPTION, array() ), true );
	}

	/**
	 * Active gateways willing to take payment in the given currency — the set the
	 * storefront (and the server-side checkout validation) is allowed to offer/accept.
	 * @return Hal_Cartel_Gateway[]
	 */
	public static function available_for( string $currency, array $cart_context = array() ): array {
		$available = array();
		foreach ( self::$gateways as $id => $gateway ) {
			if ( self::is_active( $id ) && $gateway->is_available( $currency, $cart_context ) ) {
				$available[ $id ] = $gateway;
			}
		}
		return $available;
	}

	public static function settings_option_key( string $id ): string {
		return 'hal_cartel_gw_' . $id;
	}

	public static function settings( string $id ): array {
		$gateway = self::get( $id );
		if ( ! $gateway ) {
			return array();
		}

		$defaults = array();
		foreach ( $gateway->settings_schema() as $field ) {
			$defaults[ $field['key'] ] = isset( $field['default'] ) ? $field['default'] : '';
		}

		return wp_parse_args( get_option( self::settings_option_key( $id ), array() ), $defaults );
	}

	public static function register_settings() {
		foreach ( self::$gateways as $gateway ) {
			if ( ! $gateway->settings_schema() ) {
				continue;
			}
			register_setting(
				'hal_cartel_gateways',
				self::settings_option_key( $gateway->id() ),
				array(
					'type'              => 'array',
					'sanitize_callback' => function ( $value ) use ( $gateway ) {
						return self::sanitize_settings( $gateway, (array) $value );
					},
				)
			);
		}
	}

	private static function sanitize_settings( Hal_Cartel_Gateway $gateway, array $input ): array {
		$clean = array();
		foreach ( $gateway->settings_schema() as $field ) {
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
			__( 'Payment methods', 'cartel' ),
			__( 'Payment methods', 'cartel' ),
			'manage_options',
			'hal-cartel-gateways',
			array( __CLASS__, 'render_screen' )
		);
	}

	public static function handle_toggle() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'hal_cartel_toggle_gateway' ) ) {
			wp_die( esc_html__( 'Forbidden', 'cartel' ) );
		}

		$id      = isset( $_GET['gateway'] ) ? sanitize_key( wp_unslash( $_GET['gateway'] ) ) : '';
		$gateway = self::get( $id );
		if ( ! $gateway ) {
			wp_die( esc_html__( 'Unknown payment method.', 'cartel' ) );
		}

		$active = get_option( self::ACTIVE_OPTION, array() );
		if ( in_array( $id, $active, true ) ) {
			$active = array_values( array_diff( $active, array( $id ) ) );
			$gateway->on_deactivate();
		} else {
			$active[] = $id;
			$gateway->on_activate();
		}

		update_option( self::ACTIVE_OPTION, $active );
		wp_safe_redirect( admin_url( 'admin.php?page=hal-cartel-gateways' ) );
		exit;
	}

	public static function render_screen() {
		echo '<div class="wrap"><h1>' . esc_html__( 'Payment methods', 'cartel' ) . '</h1>';
		echo '<p>' . esc_html__( 'Activate the payment methods you want to accept. Each shopper sees only the methods that support their cart\'s currency.', 'cartel' ) . '</p>';

		if ( empty( self::$gateways ) ) {
			echo '<p>' . esc_html__( 'No payment methods registered yet.', 'cartel' ) . '</p></div>';
			return;
		}

		echo '<div class="hal-cartel-extensions-grid">';
		foreach ( self::$gateways as $gateway ) {
			self::render_card( $gateway );
		}
		echo '</div></div>';
	}

	private static function render_card( Hal_Cartel_Gateway $gateway ) {
		$id         = $gateway->id();
		$active     = self::is_active( $id );
		$toggle_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=hal_cartel_toggle_gateway&gateway=' . $id ),
			'hal_cartel_toggle_gateway'
		);

		echo '<div class="hal-cartel-extension-card">';
		printf( '<h2>%s</h2>', esc_html( $gateway->title() ) );
		printf( '<p>%s</p>', esc_html( $gateway->description() ) );
		printf(
			'<p><a class="button %1$s" href="%2$s">%3$s</a></p>',
			$active ? 'button-secondary' : 'button-primary',
			esc_url( $toggle_url ),
			$active ? esc_html__( 'Deactivate', 'cartel' ) : esc_html__( 'Activate', 'cartel' )
		);

		if ( $active && $gateway->settings_schema() ) {
			self::render_settings_form( $gateway );
		}
		echo '</div>';
	}

	private static function render_settings_form( Hal_Cartel_Gateway $gateway ) {
		$option = self::settings_option_key( $gateway->id() );
		$values = self::settings( $gateway->id() );
		?>
		<form method="post" action="options.php" class="hal-cartel-extension-settings">
			<?php settings_fields( 'hal_cartel_gateways' ); ?>
			<table class="form-table">
				<?php foreach ( $gateway->settings_schema() as $field ) : ?>
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
			case 'textarea':
				printf( '<textarea name="%1$s" rows="4" class="large-text">%2$s</textarea>', esc_attr( $name ), esc_textarea( $val ) );
				break;
			default:
				printf( '<input type="text" name="%1$s" value="%2$s" class="regular-text" />', esc_attr( $name ), esc_attr( $val ) );
		}
	}
}