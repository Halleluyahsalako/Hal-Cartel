<?php
/**
 * Admin menu + settings + product meta box + CSV import/export pages.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'product_meta_box' ) );
		add_action( 'save_post_' . Hal_Cartel_Product::POST_TYPE, array( __CLASS__, 'save_product_meta' ), 10, 2 );
		add_action( 'admin_post_hal_cartel_export_csv', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_hal_cartel_import_csv', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_hal_cartel_update_order_status', array( __CLASS__, 'handle_update_order_status' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_product_editor_assets' ) );
	}

	public static function menu() {
		add_menu_page( 'Cartel', 'Cartel', 'manage_options', 'hal-cartel', array( __CLASS__, 'dashboard_page' ), 'dashicons-cart', 56 );
		add_submenu_page( 'hal-cartel', 'Orders', 'Orders', 'manage_options', 'hal-cartel-orders', array( __CLASS__, 'orders_page' ) );
		add_submenu_page( 'hal-cartel', 'Shipping', 'Shipping', 'manage_options', 'hal-cartel-shipping', array( __CLASS__, 'shipping_page' ) );
		add_submenu_page( 'hal-cartel', 'Import / Export', 'Import / Export', 'manage_options', 'hal-cartel-io', array( __CLASS__, 'io_page' ) );
		add_submenu_page( 'hal-cartel', 'Shortcodes', 'Shortcodes', 'edit_posts', 'hal-cartel-shortcodes', array( __CLASS__, 'shortcodes_page' ) );
		add_submenu_page( 'hal-cartel', 'Settings', 'Settings', 'manage_options', 'hal-cartel-settings', array( __CLASS__, 'settings_page' ) );
	}

	public static function enqueue_product_editor_assets( $hook ) {
		$screen = get_current_screen();
		$on_product = $screen && Hal_Cartel_Product::POST_TYPE === $screen->post_type;
		$on_settings = $screen && 'cartel_page_hal-cartel-settings' === $screen->id;
		if ( $on_product || $on_settings ) {
			wp_enqueue_media();
		}
		if ( $on_settings ) {
			wp_add_inline_script( 'jquery-core', self::logo_picker_js() );
		}
	}

	private static function logo_picker_js(): string {
		return <<<'JS'
jQuery(function($){
  var frame;
  $('#hal_cartel_pick_email_logo').on('click', function(e){
    e.preventDefault();
    if (frame) { frame.open(); return; }
    frame = wp.media({ title: 'Choose email logo', button: { text: 'Use this image' }, multiple: false });
    frame.on('select', function(){
      var att = frame.state().get('selection').first().toJSON();
      $('#hal_cartel_email_logo_url').val(att.url);
    });
    frame.open();
  });
});
JS;
	}

	public static function register_settings() {
		register_setting( 'hal_cartel', 'hal_cartel_currency' );
		register_setting( 'hal_cartel', 'hal_cartel_weight_unit' );
		register_setting( 'hal_cartel', 'hal_cartel_default_ui_mode' );
		register_setting( 'hal_cartel', 'hal_cartel_recaptcha_site_key' );
		register_setting( 'hal_cartel', 'hal_cartel_recaptcha_secret_key' );
		register_setting( 'hal_cartel', 'hal_cartel_mailchimp_api_key' );
		register_setting( 'hal_cartel', 'hal_cartel_mailchimp_list_id' );
		register_setting( 'hal_cartel', 'hal_cartel_shipping_provider' ); // e.g. easypost, shippo
		register_setting( 'hal_cartel', 'hal_cartel_shipping_api_key' );
		register_setting( 'hal_cartel', 'hal_cartel_email_admin_address', array( 'sanitize_callback' => 'sanitize_email' ) );
		register_setting( 'hal_cartel', 'hal_cartel_email_from_name', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'hal_cartel', 'hal_cartel_email_from_email', array( 'sanitize_callback' => 'sanitize_email' ) );
		register_setting( 'hal_cartel', 'hal_cartel_email_logo_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'hal_cartel', 'hal_cartel_email_brand_color', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
		register_setting( 'hal_cartel', 'hal_cartel_email_footer_text', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		register_setting( 'hal_cartel', 'hal_cartel_email_order_received_enabled' );
		register_setting( 'hal_cartel', 'hal_cartel_email_new_order_alert_enabled' );
		register_setting( 'hal_cartel', 'hal_cartel_email_status_update_enabled' );
		register_setting( 'hal_cartel', 'hal_cartel_create_accounts_at_checkout' );
	}

	public static function dashboard_page() {
		?>
		<div class="wrap hal-cartel-admin-wrap">
			<h1 class="hal-cartel-admin-title"><span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'Cartel', 'cartel' ); ?></h1>
			<p class="hal-cartel-lede"><?php esc_html_e( 'Lightweight commerce for WordPress — products, cart, checkout and orders, without the bloat.', 'cartel' ); ?></p>
			<div class="hal-cartel-extensions-grid">
				<div class="hal-cartel-card">
					<h2><?php esc_html_e( 'Products', 'cartel' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Add products, set prices, manage stock, and group variations.', 'cartel' ); ?></p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Hal_Cartel_Product::POST_TYPE ) ); ?>"><?php esc_html_e( 'View products', 'cartel' ); ?></a>
				</div>
				<div class="hal-cartel-card">
					<h2><?php esc_html_e( 'Orders', 'cartel' ); ?></h2>
					<p class="description"><?php esc_html_e( 'See what has been purchased and track order status.', 'cartel' ); ?></p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=hal-cartel-orders' ) ); ?>"><?php esc_html_e( 'View orders', 'cartel' ); ?></a>
				</div>
				<div class="hal-cartel-card">
					<h2><?php esc_html_e( 'Shortcodes', 'cartel' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Copy ready-made shortcodes for any page, post or page builder.', 'cartel' ); ?></p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=hal-cartel-shortcodes' ) ); ?>"><?php esc_html_e( 'Browse shortcodes', 'cartel' ); ?></a>
				</div>
			</div>
		</div>
		<?php
	}

	/** Maps an order status to the badge variant used to colour it consistently across the admin. */
	protected static function order_status_variant( $status ): string {
		return Hal_Cartel_Order::status_variant( $status );
	}

	protected static function order_status_label( $status ): string {
		$labels = Hal_Cartel_Order::statuses();
		return $labels[ $status ] ?? ucfirst( str_replace( '-', ' ', $status ) );
	}

	public static function orders_page() {
		if ( isset( $_GET['action'], $_GET['order'] ) && 'view' === $_GET['action'] ) {
			self::order_view_page( (int) $_GET['order'] );
			return;
		}

		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}hal_cartel_orders ORDER BY id DESC LIMIT 100" );
		?>
		<div class="wrap hal-cartel-admin-wrap">
			<h1 class="hal-cartel-admin-title"><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Orders', 'cartel' ); ?></h1>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Order', 'cartel' ); ?></th>
					<th><?php esc_html_e( 'Status', 'cartel' ); ?></th>
					<th><?php esc_html_e( 'Email', 'cartel' ); ?></th>
					<th><?php esc_html_e( 'Total', 'cartel' ); ?></th>
					<th><?php esc_html_e( 'Date', 'cartel' ); ?></th>
				</tr></thead>
				<tbody>
					<?php if ( ! $rows ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No orders yet.', 'cartel' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $rows as $r ) :
						$view_url = admin_url( 'admin.php?page=hal-cartel-orders&action=view&order=' . $r->id );
						?>
						<tr>
							<td><a href="<?php echo esc_url( $view_url ); ?>"><strong><?php echo esc_html( $r->order_number ); ?></strong></a></td>
							<td><span class="hal-cartel-badge hal-cartel-badge--<?php echo esc_attr( self::order_status_variant( $r->status ) ); ?>"><?php echo esc_html( self::order_status_label( $r->status ) ); ?></span></td>
							<td><?php echo esc_html( $r->email ); ?></td>
							<td><?php echo esc_html( Hal_Cartel_Currency::format( $r->total, $r->currency ) ); ?></td>
							<td><?php echo esc_html( $r->created_at ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/** Single-order detail view: line items, addresses, totals, payment info, and a status-change control. */
	public static function order_view_page( $order_id ) {
		global $wpdb;
		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hal_cartel_orders WHERE id = %d", $order_id ) );

		if ( ! $order ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Order not found', 'cartel' ) . '</h1></div>';
			return;
		}

		$items    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hal_cartel_order_items WHERE order_id = %d", $order_id ) );
		$billing  = json_decode( (string) $order->billing, true ) ?: array();
		$shipping = json_decode( (string) $order->shipping_address, true ) ?: array();
		$back_url = admin_url( 'admin.php?page=hal-cartel-orders' );
		?>
		<div class="wrap hal-cartel-admin-wrap">
			<h1 class="hal-cartel-admin-title">
				<span class="dashicons dashicons-list-view"></span>
				<?php
				/* translators: %s: order number */
				echo esc_html( sprintf( __( 'Order %s', 'cartel' ), $order->order_number ) );
				?>
				<span class="hal-cartel-badge hal-cartel-badge--<?php echo esc_attr( self::order_status_variant( $order->status ) ); ?>"><?php echo esc_html( self::order_status_label( $order->status ) ); ?></span>
			</h1>
			<p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Back to orders', 'cartel' ); ?></a></p>

			<div class="hal-cartel-extensions-grid">
				<div class="hal-cartel-card">
					<h2><?php esc_html_e( 'Items', 'cartel' ); ?></h2>
					<table class="widefat striped">
						<thead><tr>
							<th><?php esc_html_e( 'Item', 'cartel' ); ?></th>
							<th><?php esc_html_e( 'SKU', 'cartel' ); ?></th>
							<th><?php esc_html_e( 'Qty', 'cartel' ); ?></th>
							<th><?php esc_html_e( 'Price', 'cartel' ); ?></th>
							<th><?php esc_html_e( 'Total', 'cartel' ); ?></th>
						</tr></thead>
						<tbody>
							<?php foreach ( $items as $item ) : ?>
								<tr>
									<td><?php echo esc_html( $item->name ); ?></td>
									<td><?php echo esc_html( $item->sku ); ?></td>
									<td><?php echo esc_html( $item->quantity ); ?></td>
									<td><?php echo esc_html( Hal_Cartel_Currency::format( $item->price, $order->currency ) ); ?></td>
									<td><?php echo esc_html( Hal_Cartel_Currency::format( $item->total, $order->currency ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr><th colspan="4" style="text-align:right"><?php esc_html_e( 'Subtotal', 'cartel' ); ?></th><td><?php echo esc_html( Hal_Cartel_Currency::format( $order->subtotal, $order->currency ) ); ?></td></tr>
							<tr><th colspan="4" style="text-align:right"><?php echo esc_html( $order->shipping_method ?: __( 'Shipping', 'cartel' ) ); ?></th><td><?php echo esc_html( Hal_Cartel_Currency::format( $order->shipping, $order->currency ) ); ?></td></tr>
							<tr><th colspan="4" style="text-align:right"><?php esc_html_e( 'Tax', 'cartel' ); ?></th><td><?php echo esc_html( Hal_Cartel_Currency::format( $order->tax, $order->currency ) ); ?></td></tr>
							<tr><th colspan="4" style="text-align:right"><?php esc_html_e( 'Total', 'cartel' ); ?></th><td><strong><?php echo esc_html( Hal_Cartel_Currency::format( $order->total, $order->currency ) ); ?></strong></td></tr>
						</tfoot>
					</table>
				</div>

				<div class="hal-cartel-card">
					<h2><?php esc_html_e( 'Update status', 'cartel' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'hal_cartel_update_order_status_' . $order->id ); ?>
						<input type="hidden" name="action" value="hal_cartel_update_order_status" />
						<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->id ); ?>" />
						<select name="status">
							<?php foreach ( Hal_Cartel_Order::statuses() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $order->status, $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php submit_button( __( 'Update', 'cartel' ), 'primary', 'submit', false ); ?>
					</form>

					<h2><?php esc_html_e( 'Payment', 'cartel' ); ?></h2>
					<p>
						<strong><?php esc_html_e( 'Method:', 'cartel' ); ?></strong> <?php echo esc_html( $order->payment_method ?: '—' ); ?><br>
						<strong><?php esc_html_e( 'Reference:', 'cartel' ); ?></strong> <?php echo esc_html( $order->payment_ref ?: '—' ); ?><br>
						<strong><?php esc_html_e( 'Currency:', 'cartel' ); ?></strong> <?php echo esc_html( $order->currency ); ?>
					</p>

					<h2><?php esc_html_e( 'Customer', 'cartel' ); ?></h2>
					<p><strong><?php esc_html_e( 'Email:', 'cartel' ); ?></strong> <?php echo esc_html( $order->email ); ?></p>

					<h3><?php esc_html_e( 'Billing', 'cartel' ); ?></h3>
					<p><?php echo wp_kses_post( self::format_address( $billing ) ); ?></p>

					<h3><?php esc_html_e( 'Shipping', 'cartel' ); ?></h3>
					<p><?php echo wp_kses_post( self::format_address( $shipping ) ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/** Renders an order's stored address JSON as a simple multi-line block. */
	protected static function format_address( array $address ): string {
		$lines = array_filter( array(
			$address['name'] ?? '',
			$address['address'] ?? '',
			trim( ( $address['city'] ?? '' ) . ' ' . ( $address['postcode'] ?? '' ) ),
			$address['country'] ?? '',
		) );
		return $lines ? implode( '<br>', array_map( 'esc_html', $lines ) ) : esc_html__( '—', 'cartel' );
	}

	/** Handles the order-detail "Update status" form post. */
	public static function handle_update_order_status() {
		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;

		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'hal_cartel_update_order_status_' . $order_id ) ) {
			wp_die( esc_html__( 'Forbidden', 'cartel' ) );
		}

		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( $order_id && array_key_exists( $status, Hal_Cartel_Order::statuses() ) ) {
			Hal_Cartel_Order::set_status( $order_id, $status );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=hal-cartel-orders&action=view&order=' . $order_id ) );
		exit;
	}

	public static function io_page() {
		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=hal_cartel_export_csv' ), 'hal_cartel_export' );
		?>
		<div class="wrap hal-cartel-admin-wrap">
			<h1 class="hal-cartel-admin-title"><span class="dashicons dashicons-database-import"></span> <?php esc_html_e( 'Import / Export', 'cartel' ); ?></h1>
			<div class="hal-cartel-extensions-grid">
				<div class="hal-cartel-card">
					<h2><?php esc_html_e( 'Export', 'cartel' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Download your catalogue as a WooCommerce-compatible CSV.', 'cartel' ); ?></p>
					<a class="button button-primary" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Download CSV', 'cartel' ); ?></a>
				</div>
				<div class="hal-cartel-card">
					<h2><?php esc_html_e( 'Import', 'cartel' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Upload a CSV to bulk-create or update products.', 'cartel' ); ?></p>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'hal_cartel_import' ); ?>
						<input type="hidden" name="action" value="hal_cartel_import_csv" />
						<p><input type="file" name="csv" accept=".csv" required /></p>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Upload', 'cartel' ); ?></button>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	public static function settings_page() {
		?>
		<div class="wrap hal-cartel-admin-wrap">
			<h1 class="hal-cartel-admin-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Cartel Settings', 'cartel' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'hal_cartel' ); ?>
				<h2><?php esc_html_e( 'General', 'cartel' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Currency', 'cartel' ); ?></th>
						<td>
							<select name="hal_cartel_currency">
								<?php foreach ( Hal_Cartel_Currency::all() as $code => $row ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( get_option( 'hal_cartel_currency', 'USD' ), $code ); ?>><?php echo esc_html( $code . ' — ' . $row[1] . ' (' . $row[0] . ')' ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'The store-wide default. Individual products can override this from their "Currency" field in Advanced mode.', 'cartel' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Weight unit', 'cartel' ); ?></th>
						<td>
							<select name="hal_cartel_weight_unit">
								<option value="kg" <?php selected( get_option( 'hal_cartel_weight_unit', 'kg' ), 'kg' ); ?>><?php esc_html_e( 'Kilograms (kg)', 'cartel' ); ?></option>
								<option value="lb" <?php selected( get_option( 'hal_cartel_weight_unit', 'kg' ), 'lb' ); ?>><?php esc_html_e( 'Pounds (lb)', 'cartel' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Used for product weights and weight-based shipping rates.', 'cartel' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Default editor mode', 'cartel' ); ?></th>
						<td>
							<fieldset>
								<label class="hal-cartel-flag-label">
									<input type="radio" name="hal_cartel_default_ui_mode" value="simple" <?php checked( get_option( 'hal_cartel_default_ui_mode', 'simple' ), 'simple' ); ?> />
									<?php esc_html_e( 'Simple — show only the essentials (price, stock, images)', 'cartel' ); ?>
								</label><br>
								<label class="hal-cartel-flag-label">
									<input type="radio" name="hal_cartel_default_ui_mode" value="advanced" <?php checked( get_option( 'hal_cartel_default_ui_mode', 'simple' ), 'advanced' ); ?> />
									<?php esc_html_e( 'Advanced — show everything (attributes, variations, downloads, shipping…)', 'cartel' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'New users start in this mode. Anyone can switch their own view any time from the "Cartel" item in the admin toolbar.', 'cartel' ); ?></p>
							</fieldset>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Marketing & growth', 'cartel' ); ?></h2>
				<table class="form-table">
					<tr><th>reCAPTCHA Site Key</th><td><input name="hal_cartel_recaptcha_site_key" value="<?php echo esc_attr( get_option( 'hal_cartel_recaptcha_site_key' ) ); ?>" /></td></tr>
					<tr><th>reCAPTCHA Secret</th><td><input name="hal_cartel_recaptcha_secret_key" value="<?php echo esc_attr( get_option( 'hal_cartel_recaptcha_secret_key' ) ); ?>" /></td></tr>
					<tr><th>Mailchimp API Key</th><td><input name="hal_cartel_mailchimp_api_key" value="<?php echo esc_attr( get_option( 'hal_cartel_mailchimp_api_key' ) ); ?>" /></td></tr>
					<tr><th>Mailchimp List ID</th><td><input name="hal_cartel_mailchimp_list_id" value="<?php echo esc_attr( get_option( 'hal_cartel_mailchimp_list_id' ) ); ?>" /></td></tr>
				</table>

				<h2><?php esc_html_e( 'Shipping', 'cartel' ); ?></h2>
				<table class="form-table">
					<tr><th>Shipping Provider</th><td><input name="hal_cartel_shipping_provider" value="<?php echo esc_attr( get_option( 'hal_cartel_shipping_provider' ) ); ?>" placeholder="easypost | shippo" /></td></tr>
					<tr><th>Shipping API Key</th><td><input name="hal_cartel_shipping_api_key" value="<?php echo esc_attr( get_option( 'hal_cartel_shipping_api_key' ) ); ?>" /></td></tr>
				</table>

				<h2><?php esc_html_e( 'Emails', 'cartel' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Sender name', 'cartel' ); ?></th>
						<td>
							<input type="text" name="hal_cartel_email_from_name" value="<?php echo esc_attr( get_option( 'hal_cartel_email_from_name' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'The name your customers see in their inbox. Leave blank to use the site name.', 'cartel' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Sender email', 'cartel' ); ?></th>
						<td>
							<input type="email" name="hal_cartel_email_from_email" value="<?php echo esc_attr( get_option( 'hal_cartel_email_from_email' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'The "From" address on all outgoing emails. Leave blank to use the site admin email.', 'cartel' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Logo', 'cartel' ); ?></th>
						<td>
							<div style="display:flex;align-items:center;gap:10px;">
								<input type="url" name="hal_cartel_email_logo_url" id="hal_cartel_email_logo_url" value="<?php echo esc_attr( get_option( 'hal_cartel_email_logo_url' ) ); ?>" class="regular-text" placeholder="https://…" />
								<button type="button" class="button" id="hal_cartel_pick_email_logo"><?php esc_html_e( 'Choose image', 'cartel' ); ?></button>
							</div>
							<?php if ( get_option( 'hal_cartel_email_logo_url' ) ) : ?>
								<p><img src="<?php echo esc_url( get_option( 'hal_cartel_email_logo_url' ) ); ?>" style="max-height:60px;margin-top:8px;" /></p>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Appears at the top of every email. Recommended width: 200–300px.', 'cartel' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Brand color', 'cartel' ); ?></th>
						<td>
							<input type="color" name="hal_cartel_email_brand_color" value="<?php echo esc_attr( get_option( 'hal_cartel_email_brand_color', '#1a1a2e' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Used as the email header background and button color.', 'cartel' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Footer text', 'cartel' ); ?></th>
						<td>
							<textarea name="hal_cartel_email_footer_text" class="large-text" rows="3"><?php echo esc_textarea( get_option( 'hal_cartel_email_footer_text' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Appears at the bottom of every email. E.g. your address, unsubscribe note, or copyright line.', 'cartel' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'New-order alerts go to', 'cartel' ); ?></th>
						<td>
							<input type="email" name="hal_cartel_email_admin_address" value="<?php echo esc_attr( get_option( 'hal_cartel_email_admin_address' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave blank to use the site admin email.', 'cartel' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Send to the customer', 'cartel' ); ?></th>
						<td>
							<label class="hal-cartel-flag-label">
								<input type="checkbox" name="hal_cartel_email_order_received_enabled" value="1" <?php checked( get_option( 'hal_cartel_email_order_received_enabled', 1 ), 1 ); ?> />
								<?php esc_html_e( '"We received your order" confirmation', 'cartel' ); ?>
							</label><br>
							<label class="hal-cartel-flag-label">
								<input type="checkbox" name="hal_cartel_email_status_update_enabled" value="1" <?php checked( get_option( 'hal_cartel_email_status_update_enabled', 1 ), 1 ); ?> />
								<?php esc_html_e( 'Order status updates (on hold, processing, completed, …)', 'cartel' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Send to the merchant', 'cartel' ); ?></th>
						<td>
							<label class="hal-cartel-flag-label">
								<input type="checkbox" name="hal_cartel_email_new_order_alert_enabled" value="1" <?php checked( get_option( 'hal_cartel_email_new_order_alert_enabled', 1 ), 1 ); ?> />
								<?php esc_html_e( '"New order received" alert', 'cartel' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Turn this off if you already track new orders with your own tooling.', 'cartel' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Customer accounts', 'cartel' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Account creation', 'cartel' ); ?></th>
						<td>
							<label class="hal-cartel-flag-label">
								<input type="checkbox" name="hal_cartel_create_accounts_at_checkout" value="1" <?php checked( get_option( 'hal_cartel_create_accounts_at_checkout' ), 1 ); ?> />
								<?php esc_html_e( 'Automatically create a customer account at checkout', 'cartel' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Off by default — guest checkout always works regardless of this setting; shoppers can look up orders by order number + email.', 'cartel' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public static function product_meta_box() {
		add_meta_box( 'hal_cartel_product_data', 'Product data', array( __CLASS__, 'render_product_meta' ), Hal_Cartel_Product::POST_TYPE );
	}

	/** "Color: Red, Size: M" — used as the heading badge for a variation row. */
	protected static function format_variation_label( array $attributes ): string {
		$parts = array();
		foreach ( $attributes as $name => $value ) {
			$parts[] = trim( $name ) . ': ' . trim( $value );
		}
		return $parts ? implode( ', ', $parts ) : __( '(no attributes selected)', 'cartel' );
	}

	/** Raw, editable per-variation data for the Variations tab (unlike ::to_array(), no parent fallback — the form must show what is actually stored). */
	protected static function get_editable_variations( $product_id ): array {
		$rows = array();
		foreach ( Hal_Cartel_Product::get_variation_ids( $product_id ) as $variation_id ) {
			$rows[] = array(
				'id'           => $variation_id,
				'attributes'   => Hal_Cartel_Product_Variation::get_attributes( $variation_id ),
				'enabled'      => Hal_Cartel_Product_Variation::is_enabled( $variation_id ),
				'sku'          => get_post_meta( $variation_id, '_hal_cartel_sku', true ),
				'price'        => get_post_meta( $variation_id, '_hal_cartel_price', true ),
				'sale_price'   => get_post_meta( $variation_id, '_hal_cartel_sale_price', true ),
				'manage_stock' => (bool) get_post_meta( $variation_id, '_hal_cartel_manage_stock', true ),
				'stock'        => get_post_meta( $variation_id, '_hal_cartel_stock', true ),
				'weight'       => get_post_meta( $variation_id, '_hal_cartel_weight', true ),
				'image'        => Hal_Cartel_Product_Variation::get_image_id( $variation_id ),
			);
		}
		return $rows;
	}

	public static function render_product_meta( $post ) {
		wp_nonce_field( 'hal_cartel_save_product', 'hal_cartel_product_nonce' );
		$f = function( $k ) use ( $post ) { return esc_attr( get_post_meta( $post->ID, $k, true ) ); };
		$gallery_ids  = Hal_Cartel_Product::get_gallery_ids( $post->ID );
		$has_id       = $post->ID && 'auto-draft' !== $post->post_status;

		$type            = Hal_Cartel_Product::get_type( $post->ID );
		$virtual         = Hal_Cartel_Product::is_virtual( $post->ID );
		$downloadable    = Hal_Cartel_Product::is_downloadable( $post->ID );
		$attributes      = Hal_Cartel_Product::get_attributes( $post->ID );
		$variations      = self::get_editable_variations( $post->ID );
		$grouped_ids     = Hal_Cartel_Product::get_grouped_ids( $post->ID );
		$external_url    = Hal_Cartel_Product::get_external_url( $post->ID );
		$external_button = Hal_Cartel_Product::get_external_button_text( $post->ID );
		$download_file   = Hal_Cartel_Product::get_download_file_id( $post->ID );
		$currency        = (string) get_post_meta( $post->ID, '_hal_cartel_currency', true );
		$shipping_class  = Hal_Cartel_Product::get_shipping_class( $post->ID );
		?>
		<div class="hal-cartel-product-data" data-hal-cartel-tabs>
			<div class="hal-cartel-product-data__type-bar">
				<label>
					<?php esc_html_e( 'Product type', 'cartel' ); ?><br>
					<select name="_hal_cartel_product_type" data-hal-cartel-type-select>
						<option value="<?php echo esc_attr( Hal_Cartel_Product::TYPE_SIMPLE ); ?>" <?php selected( $type, Hal_Cartel_Product::TYPE_SIMPLE ); ?>><?php esc_html_e( 'Simple product', 'cartel' ); ?></option>
						<option value="<?php echo esc_attr( Hal_Cartel_Product::TYPE_VARIABLE ); ?>" <?php selected( $type, Hal_Cartel_Product::TYPE_VARIABLE ); ?>><?php esc_html_e( 'Variable product', 'cartel' ); ?></option>
						<option value="<?php echo esc_attr( Hal_Cartel_Product::TYPE_GROUPED ); ?>" <?php selected( $type, Hal_Cartel_Product::TYPE_GROUPED ); ?>><?php esc_html_e( 'Grouped product', 'cartel' ); ?></option>
						<option value="<?php echo esc_attr( Hal_Cartel_Product::TYPE_EXTERNAL ); ?>" <?php selected( $type, Hal_Cartel_Product::TYPE_EXTERNAL ); ?>><?php esc_html_e( 'External / Affiliate product', 'cartel' ); ?></option>
					</select>
				</label>
				<label class="hal-cartel-flag-label">
					<input type="checkbox" name="_hal_cartel_virtual" value="1" data-hal-cartel-virtual <?php checked( $virtual ); ?> />
					<?php esc_html_e( 'Virtual', 'cartel' ); ?>
					<span class="description">— <?php esc_html_e( 'no shipping required', 'cartel' ); ?></span>
				</label>
				<label class="hal-cartel-flag-label">
					<input type="checkbox" name="_hal_cartel_downloadable" value="1" data-hal-cartel-downloadable <?php checked( $downloadable ); ?> />
					<?php esc_html_e( 'Downloadable', 'cartel' ); ?>
					<span class="description">— <?php esc_html_e( 'gives buyers a file to download', 'cartel' ); ?></span>
				</label>
			</div>

			<ul class="hal-cartel-product-data__tabs">
				<li class="hal-cartel-product-data__tab--active"><a href="#" data-tab="general"><?php esc_html_e( 'General', 'cartel' ); ?></a></li>
				<li data-tab-for-types="simple variable grouped"><a href="#" data-tab="inventory"><?php esc_html_e( 'Inventory', 'cartel' ); ?></a></li>
				<li data-tab-for-types="simple variable grouped" data-tab-hide-if-virtual="1"><a href="#" data-tab="shipping"><?php esc_html_e( 'Shipping', 'cartel' ); ?></a></li>
				<li><a href="#" data-tab="attributes"><?php esc_html_e( 'Attributes', 'cartel' ); ?></a></li>
				<li data-tab-for-types="variable"><a href="#" data-tab="variations"><?php esc_html_e( 'Variations', 'cartel' ); ?></a></li>
				<li data-tab-for-types="grouped"><a href="#" data-tab="linked"><?php esc_html_e( 'Linked products', 'cartel' ); ?></a></li>
				<li data-tab-for-types="external"><a href="#" data-tab="external"><?php esc_html_e( 'External product', 'cartel' ); ?></a></li>
				<li data-tab-show-if-downloadable="1"><a href="#" data-tab="downloads"><?php esc_html_e( 'Downloads', 'cartel' ); ?></a></li>
				<li><a href="#" data-tab="images"><?php esc_html_e( 'Images', 'cartel' ); ?></a></li>
				<li><a href="#" data-tab="shortcode"><?php esc_html_e( 'Shortcode', 'cartel' ); ?></a></li>
			</ul>

			<div class="hal-cartel-product-data__panel" data-panel="general">
				<p>
					<label><?php esc_html_e( 'Regular price', 'cartel' ); ?><br>
						<input name="_hal_cartel_price" type="number" step="0.01" value="<?php echo $f( '_hal_cartel_price' ); ?>" />
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'Sale price', 'cartel' ); ?><br>
						<input name="_hal_cartel_sale_price" type="number" step="0.01" value="<?php echo $f( '_hal_cartel_sale_price' ); ?>" />
					</label>
				</p>
				<?php Hal_Cartel_UI::advanced( function() use ( $currency ) { ?>
					<p>
						<label><?php esc_html_e( 'Currency', 'cartel' ); ?><br>
							<select name="_hal_cartel_currency">
								<option value=""><?php
									/* translators: %s: store-wide default currency code, e.g. USD */
									printf( esc_html__( 'Use store default (%s)', 'cartel' ), esc_html( Hal_Cartel_Currency::default_code() ) );
								?></option>
								<?php foreach ( Hal_Cartel_Currency::all() as $code => $row ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $currency, $code ); ?>><?php echo esc_html( $code . ' — ' . $row[1] ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<p class="description"><?php esc_html_e( 'Sell this product in a different currency than the rest of your store — e.g. an imported item priced by its supplier in EUR. Shoppers cannot mix items priced in different currencies in one order.', 'cartel' ); ?></p>
					</p>
				<?php } ); ?>
				<p class="description" data-tab-for-types="variable">
					<?php esc_html_e( 'For variable products these act only as a fallback shown before a shopper picks options — set the real prices per variation on the Variations tab.', 'cartel' ); ?>
				</p>
				<p class="description" data-tab-for-types="external">
					<?php esc_html_e( 'Show shoppers what the item costs at the external retailer — Cartel will not process payment for it directly.', 'cartel' ); ?>
				</p>
			</div>

			<div class="hal-cartel-product-data__panel" data-panel="inventory" hidden>
				<p>
					<label><?php esc_html_e( 'SKU', 'cartel' ); ?><br>
						<input name="_hal_cartel_sku" value="<?php echo $f( '_hal_cartel_sku' ); ?>" />
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" name="_hal_cartel_manage_stock" value="1" <?php checked( get_post_meta( $post->ID, '_hal_cartel_manage_stock', true ), 1 ); ?> />
						<?php esc_html_e( 'Track stock quantity for this product', 'cartel' ); ?>
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'Stock quantity', 'cartel' ); ?><br>
						<input name="_hal_cartel_stock" type="number" value="<?php echo $f( '_hal_cartel_stock' ); ?>" />
					</label>
				</p>
			</div>

			<div class="hal-cartel-product-data__panel" data-panel="shipping" hidden>
				<p>
					<label>
						<?php
						printf(
							/* translators: %s: the store's configured weight unit, e.g. kg */
							esc_html__( 'Weight (%s)', 'cartel' ),
							esc_html( get_option( 'hal_cartel_weight_unit', 'kg' ) )
						);
						?><br>
						<input name="_hal_cartel_weight" type="number" step="0.01" value="<?php echo $f( '_hal_cartel_weight' ); ?>" />
					</label>
					<span class="description">— <?php esc_html_e( 'used by weight-based shipping rates', 'cartel' ); ?></span>
				</p>
				<?php Hal_Cartel_UI::advanced( function() use ( $shipping_class ) {
					$terms = get_terms( array( 'taxonomy' => Hal_Cartel_Product::SHIPPING_CLASS_TAXONOMY, 'hide_empty' => false ) );
					$terms = is_array( $terms ) ? $terms : array();
					?>
					<p>
						<label><?php esc_html_e( 'Shipping class', 'cartel' ); ?><br>
							<select name="_hal_cartel_shipping_class">
								<option value=""><?php esc_html_e( 'No shipping class', 'cartel' ); ?></option>
								<?php foreach ( $terms as $term ) : ?>
									<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $shipping_class ? $shipping_class->term_id : 0, $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<p class="description">
							<?php
							printf(
								/* translators: %s: link to the shipping classes admin screen */
								wp_kses( __( 'Group products that should ship at a different rate (e.g. "Heavy", "Fragile") — manage classes %s.', 'cartel' ), array( 'a' => array( 'href' => array() ) ) ),
								'<a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=' . Hal_Cartel_Product::SHIPPING_CLASS_TAXONOMY . '&post_type=' . Hal_Cartel_Product::POST_TYPE ) ) . '" target="_blank">' . esc_html__( 'here', 'cartel' ) . '</a>'
							);
							?>
						</p>
					</p>
				<?php } ); ?>
			</div>

			<div class="hal-cartel-product-data__panel" data-panel="attributes" hidden>
				<p class="description"><?php esc_html_e( 'Describe this product — e.g. Color or Size. Tick "Used for variations" on any attribute whose values should generate purchasable variations on the Variations tab.', 'cartel' ); ?></p>
				<div class="hal-cartel-attributes" data-hal-cartel-attributes>
					<?php foreach ( $attributes as $attr ) : ?>
						<div class="hal-cartel-attribute-row" data-hal-cartel-attribute-row>
							<input type="text" placeholder="<?php esc_attr_e( 'e.g. Color', 'cartel' ); ?>" data-attr-name value="<?php echo esc_attr( $attr['name'] ); ?>" />
							<input type="text" placeholder="<?php esc_attr_e( 'Red | Blue | Green', 'cartel' ); ?>" data-attr-values value="<?php echo esc_attr( implode( ' | ', $attr['values'] ) ); ?>" />
							<label class="hal-cartel-flag-label"><input type="checkbox" data-attr-used-for-variations <?php checked( ! empty( $attr['used_for_variations'] ) ); ?> /> <?php esc_html_e( 'Used for variations', 'cartel' ); ?></label>
							<button type="button" class="hal-cartel-attribute-remove" data-hal-cartel-attribute-remove aria-label="<?php esc_attr_e( 'Remove attribute', 'cartel' ); ?>">&times;</button>
						</div>
					<?php endforeach; ?>
				</div>
				<p><button type="button" class="button" data-hal-cartel-attribute-add><?php esc_html_e( 'Add attribute', 'cartel' ); ?></button></p>
				<input type="hidden" name="_hal_cartel_attributes_json" value="<?php echo esc_attr( wp_json_encode( $attributes ) ); ?>" data-hal-cartel-attributes-input />
			</div>

			<div class="hal-cartel-product-data__panel" data-panel="variations" hidden>
				<p class="description"><?php esc_html_e( 'Click "Generate" to create one row per combination of the attributes marked "Used for variations", then set a price (and optionally stock, SKU, weight and an image) for each.', 'cartel' ); ?></p>
				<p><button type="button" class="button button-primary" data-hal-cartel-generate-variations><?php esc_html_e( 'Generate variations from attributes', 'cartel' ); ?></button></p>
				<div class="hal-cartel-variations" data-hal-cartel-variations>
					<?php if ( empty( $variations ) ) : ?>
						<p class="hal-cartel-variations-empty" data-hal-cartel-variations-empty><?php esc_html_e( 'No variations yet. Mark at least one attribute "Used for variations" and click Generate above.', 'cartel' ); ?></p>
					<?php else : ?>
						<?php foreach ( $variations as $v ) : ?>
							<?php self::render_variation_row( $v ); ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				<input type="hidden" name="_hal_cartel_variations_json" value="<?php echo esc_attr( wp_json_encode( $variations ) ); ?>" data-hal-cartel-variations-input />
				<template data-hal-cartel-variation-template>
					<?php
					self::render_variation_row( array(
						'id'           => 0,
						'attributes'   => array(),
						'enabled'      => true,
						'sku'          => '',
						'price'        => '',
						'sale_price'   => '',
						'manage_stock' => false,
						'stock'        => '',
						'weight'       => '',
						'image'        => 0,
					) );
					?>
				</template>
			</div>

			<div class="hal-cartel-product-data__panel" data-panel="linked" hidden>
				<p class="description"><?php esc_html_e( 'Pick the products that should be offered together as part of this group.', 'cartel' ); ?></p>
				<select name="_hal_cartel_grouped_products[]" multiple size="8" class="widefat">
					<?php
					$candidates = get_posts( array(
						'post_type'      => Hal_Cartel_Product::POST_TYPE,
						'posts_per_page' => -1,
						'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
						'exclude'        => array( $post->ID ),
						'orderby'        => 'title',
						'order'          => 'ASC',
					) );
					foreach ( $candidates as $candidate ) :
						?>
						<option value="<?php echo esc_attr( $candidate->ID ); ?>" <?php selected( in_array( $candidate->ID, $grouped_ids, true ) ); ?>><?php echo esc_html( $candidate->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="hal-cartel-product-data__panel" data-panel="external" hidden>
				<p>
					<label><?php esc_html_e( 'Product URL', 'cartel' ); ?><br>
						<input type="url" class="widefat" name="_hal_cartel_external_url" placeholder="https://example.com/product" value="<?php echo esc_attr( $external_url ); ?>" />
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'Button text', 'cartel' ); ?><br>
						<input type="text" name="_hal_cartel_external_button_text" placeholder="<?php esc_attr_e( 'Buy on Amazon', 'cartel' ); ?>" value="<?php echo esc_attr( $external_button ); ?>" />
					</label>
				</p>
				<p class="description"><?php esc_html_e( 'Shoppers are sent straight to this URL — Cartel\'s cart and checkout are skipped for external products.', 'cartel' ); ?></p>
			</div>

			<div class="hal-cartel-product-data__panel" data-panel="downloads" hidden>
				<p class="description"><?php esc_html_e( 'Buyers get a link to this file once their order is marked complete.', 'cartel' ); ?></p>
				<p>
					<button type="button" class="button" data-hal-cartel-download-choose>
						<?php echo $download_file ? esc_html__( 'Change file', 'cartel' ) : esc_html__( 'Choose file', 'cartel' ); ?>
					</button>
					<span class="description" data-hal-cartel-download-filename>
						<?php echo $download_file ? esc_html( wp_basename( get_attached_file( $download_file ) ) ) : esc_html__( 'No file selected', 'cartel' ); ?>
					</span>
				</p>
				<input type="hidden" name="_hal_cartel_download_file" value="<?php echo esc_attr( $download_file ); ?>" data-hal-cartel-download-file-input />
				<p>
					<label><?php esc_html_e( 'Download limit', 'cartel' ); ?><br>
						<input type="number" name="_hal_cartel_download_limit" value="<?php echo $f( '_hal_cartel_download_limit' ); ?>" placeholder="<?php esc_attr_e( '-1 = unlimited', 'cartel' ); ?>" />
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'Download expiry (days)', 'cartel' ); ?><br>
						<input type="number" name="_hal_cartel_download_expiry" value="<?php echo $f( '_hal_cartel_download_expiry' ); ?>" placeholder="<?php esc_attr_e( '-1 = never', 'cartel' ); ?>" />
					</label>
				</p>
			</div>

			<div class="hal-cartel-product-data__panel" data-panel="images" hidden>
				<p class="description"><?php esc_html_e( 'Add extra images to show alongside the featured image — e.g. in themes or shortcodes that support product galleries.', 'cartel' ); ?></p>
				<ul class="hal-cartel-gallery" data-hal-cartel-gallery>
					<?php foreach ( $gallery_ids as $attachment_id ) : ?>
						<li class="hal-cartel-gallery__item" data-id="<?php echo esc_attr( $attachment_id ); ?>">
							<?php echo wp_get_attachment_image( $attachment_id, 'thumbnail' ); ?>
							<button type="button" class="hal-cartel-gallery__remove" aria-label="<?php esc_attr_e( 'Remove image', 'cartel' ); ?>">&times;</button>
						</li>
					<?php endforeach; ?>
				</ul>
				<input type="hidden" name="_hal_cartel_gallery" value="<?php echo esc_attr( implode( ',', $gallery_ids ) ); ?>" data-hal-cartel-gallery-input />
				<p><button type="button" class="button" data-hal-cartel-gallery-add><?php esc_html_e( 'Add images', 'cartel' ); ?></button></p>
			</div>

			<div class="hal-cartel-product-data__panel" data-panel="shortcode" hidden>
				<?php if ( $has_id ) : ?>
					<p><?php esc_html_e( 'Paste this anywhere — a page, post, widget, or Elementor text element — to display this product:', 'cartel' ); ?></p>
					<p class="hal-cartel-copy-row">
						<input type="text" readonly value="<?php echo esc_attr( sprintf( '[hal_cartel_product id="%d"]', $post->ID ) ); ?>" class="widefat code hal-cartel-copy-source" onclick="this.select();" />
						<button type="button" class="button hal-cartel-copy-btn" data-hal-cartel-copy><?php esc_html_e( 'Copy', 'cartel' ); ?></button>
					</p>
					<p class="description">
						<?php esc_html_e( 'Want a standalone "Buy now" button instead?', 'cartel' ); ?>
						<code><?php echo esc_html( sprintf( '[hal_cartel_buy_now id="%d"]', $post->ID ) ); ?></code>
					</p>
					<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=hal-cartel-shortcodes' ) ); ?>"><?php esc_html_e( 'See all available shortcodes →', 'cartel' ); ?></a></p>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'Save or publish this product to get its shortcode.', 'cartel' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/** Renders one editable row in the Variations grid — also the template the JS clones for newly generated combinations. */
	protected static function render_variation_row( array $v ) {
		?>
		<div class="hal-cartel-variation" data-hal-cartel-variation data-attributes="<?php echo esc_attr( wp_json_encode( $v['attributes'] ) ); ?>">
			<div class="hal-cartel-variation__head">
				<span class="hal-cartel-badge hal-cartel-badge--neutral"><?php echo esc_html( self::format_variation_label( $v['attributes'] ) ); ?></span>
				<label class="hal-cartel-flag-label"><input type="checkbox" data-var-enabled <?php checked( ! empty( $v['enabled'] ) ); ?> /> <?php esc_html_e( 'Enabled', 'cartel' ); ?></label>
				<button type="button" class="hal-cartel-variation__remove" data-hal-cartel-variation-remove aria-label="<?php esc_attr_e( 'Remove variation', 'cartel' ); ?>">&times;</button>
			</div>
			<div class="hal-cartel-variation__body">
				<div class="hal-cartel-variation__image" data-hal-cartel-variation-image data-image-id="<?php echo esc_attr( $v['image'] ); ?>">
					<?php if ( ! empty( $v['image'] ) ) : ?>
						<?php echo wp_get_attachment_image( $v['image'], 'thumbnail' ); ?>
					<?php else : ?>
						<span><?php esc_html_e( 'Set image', 'cartel' ); ?></span>
					<?php endif; ?>
				</div>
				<div>
					<label><?php esc_html_e( 'SKU', 'cartel' ); ?></label>
					<input type="text" data-var-sku value="<?php echo esc_attr( $v['sku'] ); ?>" />
				</div>
				<div>
					<label><?php esc_html_e( 'Regular price', 'cartel' ); ?></label>
					<input type="number" step="0.01" data-var-price value="<?php echo esc_attr( $v['price'] ); ?>" />
				</div>
				<div>
					<label><?php esc_html_e( 'Sale price', 'cartel' ); ?></label>
					<input type="number" step="0.01" data-var-sale-price value="<?php echo esc_attr( $v['sale_price'] ); ?>" />
				</div>
				<div>
					<label><input type="checkbox" data-var-manage-stock <?php checked( ! empty( $v['manage_stock'] ) ); ?> /> <?php esc_html_e( 'Manage stock', 'cartel' ); ?></label>
					<input type="number" data-var-stock value="<?php echo esc_attr( $v['stock'] ); ?>" placeholder="<?php esc_attr_e( 'Qty', 'cartel' ); ?>" />
				</div>
				<div>
					<label><?php esc_html_e( 'Weight', 'cartel' ); ?></label>
					<input type="number" step="0.01" data-var-weight value="<?php echo esc_attr( $v['weight'] ); ?>" />
				</div>
			</div>
			<input type="hidden" data-var-id value="<?php echo esc_attr( $v['id'] ); ?>" />
		</div>
		<?php
	}

	/** Direct child-post query, bypassing Hal_Cartel_Product::get_variation_ids()'s is_variable() guard — needed when cleaning up after a type change away from "variable". */
	protected static function get_variation_ids_for_cleanup( $product_id ): array {
		return get_posts( array(
			'post_type'      => Hal_Cartel_Product_Variation::POST_TYPE,
			'post_parent'    => $product_id,
			'post_status'    => array( 'publish', 'private', 'draft' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );
	}

	/** Decodes a JSON hidden-input field into an array, or [] if missing/invalid. */
	protected static function decode_json_field( $key ): array {
		if ( empty( $_POST[ $key ] ) ) { return array(); }
		$decoded = json_decode( wp_unslash( $_POST[ $key ] ), true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/** Sanitizes the raw attributes array posted from the Attributes tab into the canonical [name, values[], used_for_variations] shape. */
	protected static function sanitize_attributes_input( array $raw ): array {
		$clean = array();
		foreach ( $raw as $attr ) {
			if ( empty( $attr['name'] ) ) { continue; }
			$values = isset( $attr['values'] ) && is_array( $attr['values'] )
				? array_values( array_filter( array_map( 'sanitize_text_field', $attr['values'] ) ) )
				: array();
			if ( ! $values ) { continue; }
			$clean[] = array(
				'name'                => sanitize_text_field( $attr['name'] ),
				'values'              => $values,
				'used_for_variations' => ! empty( $attr['used_for_variations'] ),
			);
		}
		return $clean;
	}

	/**
	 * Creates, updates, and deletes Hal_Cartel_Product_Variation child posts so they
	 * match what was submitted from the Variations tab (one JSON row per variation).
	 */
	protected static function sync_variations( $product_id, array $rows ) {
		$kept_ids = array();

		foreach ( $rows as $row ) {
			$attributes = array();
			if ( isset( $row['attributes'] ) && is_array( $row['attributes'] ) ) {
				foreach ( $row['attributes'] as $name => $value ) {
					$attributes[ sanitize_text_field( (string) $name ) ] = sanitize_text_field( (string) $value );
				}
			}
			if ( ! $attributes ) { continue; }

			$variation_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$title        = sprintf( '%s — %s', get_the_title( $product_id ), self::format_variation_label( $attributes ) );

			if ( $variation_id && get_post( $variation_id ) && (int) wp_get_post_parent_id( $variation_id ) === (int) $product_id ) {
				wp_update_post( array( 'ID' => $variation_id, 'post_title' => $title ) );
			} else {
				$variation_id = wp_insert_post( array(
					'post_type'   => Hal_Cartel_Product_Variation::POST_TYPE,
					'post_parent' => $product_id,
					'post_title'  => $title,
					'post_status' => 'publish',
				), true );
				if ( is_wp_error( $variation_id ) ) { continue; }
			}

			$kept_ids[] = $variation_id;

			update_post_meta( $variation_id, '_hal_cartel_variation_attributes', $attributes );
			update_post_meta( $variation_id, '_hal_cartel_enabled', empty( $row['enabled'] ) ? 0 : 1 );
			update_post_meta( $variation_id, '_hal_cartel_sku', isset( $row['sku'] ) ? sanitize_text_field( $row['sku'] ) : '' );
			update_post_meta( $variation_id, '_hal_cartel_price', isset( $row['price'] ) ? sanitize_text_field( $row['price'] ) : '' );
			update_post_meta( $variation_id, '_hal_cartel_sale_price', isset( $row['sale_price'] ) ? sanitize_text_field( $row['sale_price'] ) : '' );
			update_post_meta( $variation_id, '_hal_cartel_manage_stock', empty( $row['manage_stock'] ) ? 0 : 1 );
			update_post_meta( $variation_id, '_hal_cartel_stock', isset( $row['stock'] ) ? sanitize_text_field( $row['stock'] ) : '' );
			update_post_meta( $variation_id, '_hal_cartel_weight', isset( $row['weight'] ) ? sanitize_text_field( $row['weight'] ) : '' );
			update_post_meta( $variation_id, '_hal_cartel_image', isset( $row['image'] ) ? absint( $row['image'] ) : 0 );
		}

		// Remove variations that are no longer present in the submitted set.
		foreach ( self::get_variation_ids_for_cleanup( $product_id ) as $existing_id ) {
			if ( ! in_array( $existing_id, $kept_ids, true ) ) {
				wp_delete_post( $existing_id, true );
			}
		}
	}

	public static function save_product_meta( $post_id, $post ) {
		if ( ! isset( $_POST['hal_cartel_product_nonce'] ) || ! wp_verify_nonce( $_POST['hal_cartel_product_nonce'], 'hal_cartel_save_product' ) ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

		foreach ( array( '_hal_cartel_sku', '_hal_cartel_price', '_hal_cartel_sale_price', '_hal_cartel_stock', '_hal_cartel_weight' ) as $k ) {
			if ( isset( $_POST[ $k ] ) ) { update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) ); }
		}
		update_post_meta( $post_id, '_hal_cartel_manage_stock', ! empty( $_POST['_hal_cartel_manage_stock'] ) ? 1 : 0 );

		// --- Currency override: empty string = inherit the store-wide default ---
		$currency = isset( $_POST['_hal_cartel_currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['_hal_cartel_currency'] ) ) ) : '';
		update_post_meta( $post_id, '_hal_cartel_currency', isset( Hal_Cartel_Currency::all()[ $currency ] ) ? $currency : '' );

		// --- Shipping class: single-select, replace rather than append ---
		$shipping_class_id = isset( $_POST['_hal_cartel_shipping_class'] ) ? absint( $_POST['_hal_cartel_shipping_class'] ) : 0;
		wp_set_object_terms( $post_id, $shipping_class_id ? array( $shipping_class_id ) : array(), Hal_Cartel_Product::SHIPPING_CLASS_TAXONOMY, false );

		if ( isset( $_POST['_hal_cartel_gallery'] ) ) {
			$ids = array_filter( array_map( 'absint', explode( ',', wp_unslash( $_POST['_hal_cartel_gallery'] ) ) ) );
			update_post_meta( $post_id, '_hal_cartel_gallery', array_values( $ids ) );
		}

		// --- Product type + Virtual / Downloadable flags ---
		$type  = isset( $_POST['_hal_cartel_product_type'] ) ? sanitize_text_field( wp_unslash( $_POST['_hal_cartel_product_type'] ) ) : Hal_Cartel_Product::TYPE_SIMPLE;
		$types = array( Hal_Cartel_Product::TYPE_SIMPLE, Hal_Cartel_Product::TYPE_VARIABLE, Hal_Cartel_Product::TYPE_GROUPED, Hal_Cartel_Product::TYPE_EXTERNAL );
		if ( ! in_array( $type, $types, true ) ) { $type = Hal_Cartel_Product::TYPE_SIMPLE; }
		update_post_meta( $post_id, '_hal_cartel_product_type', $type );
		update_post_meta( $post_id, '_hal_cartel_virtual', ! empty( $_POST['_hal_cartel_virtual'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_hal_cartel_downloadable', ! empty( $_POST['_hal_cartel_downloadable'] ) ? 1 : 0 );

		// --- External / Affiliate ---
		update_post_meta( $post_id, '_hal_cartel_external_url', isset( $_POST['_hal_cartel_external_url'] ) ? esc_url_raw( wp_unslash( $_POST['_hal_cartel_external_url'] ) ) : '' );
		update_post_meta( $post_id, '_hal_cartel_external_button_text', isset( $_POST['_hal_cartel_external_button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['_hal_cartel_external_button_text'] ) ) : '' );

		// --- Grouped ---
		$grouped_ids = isset( $_POST['_hal_cartel_grouped_products'] ) && is_array( $_POST['_hal_cartel_grouped_products'] )
			? array_values( array_filter( array_map( 'absint', wp_unslash( $_POST['_hal_cartel_grouped_products'] ) ) ) )
			: array();
		update_post_meta( $post_id, '_hal_cartel_grouped_products', $grouped_ids );

		// --- Downloads ---
		update_post_meta( $post_id, '_hal_cartel_download_file', isset( $_POST['_hal_cartel_download_file'] ) ? absint( $_POST['_hal_cartel_download_file'] ) : 0 );
		foreach ( array( '_hal_cartel_download_limit', '_hal_cartel_download_expiry' ) as $k ) {
			update_post_meta( $post_id, $k, isset( $_POST[ $k ] ) && '' !== $_POST[ $k ] ? intval( $_POST[ $k ] ) : '' );
		}

		// --- Attributes (serialized as JSON by the JS on submit) ---
		$attributes = self::sanitize_attributes_input( self::decode_json_field( '_hal_cartel_attributes_json' ) );
		update_post_meta( $post_id, '_hal_cartel_attributes', $attributes );

		// --- Variations: only meaningful for variable products, otherwise drop any leftovers ---
		if ( Hal_Cartel_Product::TYPE_VARIABLE === $type ) {
			self::sync_variations( $post_id, self::decode_json_field( '_hal_cartel_variations_json' ) );
		} else {
			foreach ( self::get_variation_ids_for_cleanup( $post_id ) as $existing_id ) {
				wp_delete_post( $existing_id, true );
			}
		}
	}

	public static function shipping_page() {
		if ( isset( $_POST['hal_cartel_shipping_nonce'] ) && wp_verify_nonce( $_POST['hal_cartel_shipping_nonce'], 'hal_cartel_save_shipping' ) && current_user_can( 'manage_options' ) ) {
			Hal_Cartel_Shipping::save_zones( self::decode_json_field( '_hal_cartel_shipping_zones_json' ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Shipping zones saved.', 'cartel' ) . '</p></div>';
		}

		$zones            = Hal_Cartel_Shipping::get_zones();
		$shipping_classes = get_terms( array( 'taxonomy' => Hal_Cartel_Product::SHIPPING_CLASS_TAXONOMY, 'hide_empty' => false ) );
		$shipping_classes = is_array( $shipping_classes ) ? $shipping_classes : array();
		?>
		<div class="wrap hal-cartel-admin-wrap">
			<h1 class="hal-cartel-admin-title"><span class="dashicons dashicons-airplane"></span> <?php esc_html_e( 'Shipping zones', 'cartel' ); ?></h1>
			<p class="hal-cartel-lede"><?php esc_html_e( 'Define where you ship and what it costs. Zones are matched top to bottom — the first zone whose countries include the destination wins; a zone with no countries selected catches everything else ("Rest of the world").', 'cartel' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'hal_cartel_save_shipping', 'hal_cartel_shipping_nonce' ); ?>

				<div class="hal-cartel-shipping-zones" data-hal-cartel-shipping-zones>
					<?php foreach ( $zones as $zone ) : self::render_shipping_zone( $zone, $shipping_classes ); endforeach; ?>
				</div>
				<p><button type="button" class="button button-secondary" data-hal-cartel-shipping-zone-add><?php esc_html_e( 'Add zone', 'cartel' ); ?></button></p>

				<input type="hidden" name="_hal_cartel_shipping_zones_json" value="<?php echo esc_attr( wp_json_encode( $zones ) ); ?>" data-hal-cartel-shipping-zones-input />

				<template data-hal-cartel-shipping-zone-template>
					<?php self::render_shipping_zone( array( 'id' => 0, 'name' => '', 'order' => 0, 'locations' => array(), 'methods' => array() ), $shipping_classes ); ?>
				</template>
				<template data-hal-cartel-shipping-method-template>
					<?php
					self::render_shipping_method( array(
						'id'       => 0,
						'type'     => Hal_Cartel_Shipping::TYPE_FLAT_RATE,
						'title'    => __( 'Standard shipping', 'cartel' ),
						'enabled'  => true,
						'settings' => array(),
					), $shipping_classes );
					?>
				</template>

				<?php submit_button( __( 'Save shipping zones', 'cartel' ) ); ?>
			</form>
		</div>
		<?php
	}

	/** A destination zone — name, matched countries, and an ordered list of rate methods. Mirrors render_variation_row()'s clone-and-serialize convention, one level deeper. */
	protected static function render_shipping_zone( array $zone, array $shipping_classes ) {
		$countries = Hal_Cartel_Shipping::countries();
		?>
		<div class="hal-cartel-shipping-zone" data-hal-cartel-shipping-zone>
			<div class="hal-cartel-shipping-zone__head">
				<input type="text" class="regular-text" data-zone-name placeholder="<?php esc_attr_e( 'e.g. Domestic, Europe, Rest of the world', 'cartel' ); ?>" value="<?php echo esc_attr( $zone['name'] ); ?>" />
				<button type="button" class="hal-cartel-shipping-zone__remove" data-hal-cartel-shipping-zone-remove aria-label="<?php esc_attr_e( 'Remove zone', 'cartel' ); ?>">&times;</button>
			</div>
			<p>
				<label><?php esc_html_e( 'Countries', 'cartel' ); ?><br>
					<select multiple size="6" data-zone-locations>
						<?php foreach ( $countries as $code => $name ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( in_array( $code, $zone['locations'], true ) ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<span class="description"><?php esc_html_e( 'Leave empty to match any destination not covered by another zone ("Rest of the world"). Hold Ctrl/Cmd to select more than one.', 'cartel' ); ?></span>
			</p>
			<div class="hal-cartel-shipping-methods" data-hal-cartel-shipping-methods>
				<?php foreach ( $zone['methods'] as $method ) : self::render_shipping_method( $method, $shipping_classes ); endforeach; ?>
			</div>
			<p><button type="button" class="button" data-hal-cartel-shipping-method-add><?php esc_html_e( 'Add shipping method', 'cartel' ); ?></button></p>
			<input type="hidden" data-zone-id value="<?php echo esc_attr( $zone['id'] ); ?>" />
		</div>
		<?php
	}

	/** A single rate method within a zone — type-specific settings panels are shown/hidden client-side as the merchant switches the type. */
	protected static function render_shipping_method( array $method, array $shipping_classes ) {
		$settings = $method['settings'];
		?>
		<div class="hal-cartel-shipping-method" data-hal-cartel-shipping-method>
			<div class="hal-cartel-shipping-method__head">
				<select data-method-type-select>
					<?php foreach ( Hal_Cartel_Shipping::method_types() as $type => $type_label ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $method['type'], $type ); ?>><?php echo esc_html( $type_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" data-method-title placeholder="<?php esc_attr_e( 'Customer-facing label, e.g. Standard shipping', 'cartel' ); ?>" value="<?php echo esc_attr( $method['title'] ); ?>" />
				<label class="hal-cartel-flag-label"><input type="checkbox" data-method-enabled <?php checked( ! empty( $method['enabled'] ) ); ?> /> <?php esc_html_e( 'Enabled', 'cartel' ); ?></label>
				<button type="button" class="hal-cartel-shipping-method__remove" data-hal-cartel-shipping-method-remove aria-label="<?php esc_attr_e( 'Remove shipping method', 'cartel' ); ?>">&times;</button>
			</div>

			<div class="hal-cartel-shipping-method__settings" data-method-settings-for="<?php echo esc_attr( Hal_Cartel_Shipping::TYPE_FLAT_RATE ); ?>">
				<label><?php esc_html_e( 'Base cost', 'cartel' ); ?>
					<input type="number" step="0.01" data-setting-base-cost value="<?php echo esc_attr( $settings['base_cost'] ?? '' ); ?>" />
				</label>
				<?php if ( $shipping_classes ) : ?>
					<p class="description"><?php esc_html_e( 'Extra cost added per item carrying each shipping class:', 'cartel' ); ?></p>
					<?php foreach ( $shipping_classes as $term ) : $cost = $settings['class_costs'][ $term->term_id ] ?? ''; ?>
						<label><?php echo esc_html( $term->name ); ?>
							<input type="number" step="0.01" data-setting-class-cost data-term-id="<?php echo esc_attr( $term->term_id ); ?>" value="<?php echo esc_attr( $cost ); ?>" />
						</label>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<div class="hal-cartel-shipping-method__settings" data-method-settings-for="<?php echo esc_attr( Hal_Cartel_Shipping::TYPE_FREE_SHIPPING ); ?>">
				<label><?php esc_html_e( 'Minimum order amount (0 = always free)', 'cartel' ); ?>
					<input type="number" step="0.01" data-setting-min-order value="<?php echo esc_attr( $settings['min_order_amount'] ?? '' ); ?>" />
				</label>
			</div>

			<div class="hal-cartel-shipping-method__settings" data-method-settings-for="<?php echo esc_attr( Hal_Cartel_Shipping::TYPE_LOCAL_PICKUP ); ?>">
				<label><?php esc_html_e( 'Pickup cost (commonly 0)', 'cartel' ); ?>
					<input type="number" step="0.01" data-setting-cost value="<?php echo esc_attr( $settings['cost'] ?? '' ); ?>" />
				</label>
			</div>

			<div class="hal-cartel-shipping-method__settings" data-method-settings-for="<?php echo esc_attr( Hal_Cartel_Shipping::TYPE_WEIGHT_BASED ); ?>">
				<p class="description"><?php esc_html_e( 'Cost bands by total cart weight. Leave "Up to" blank on a row to mean "and above" — that row then catches anything heavier than the others.', 'cartel' ); ?></p>
				<div data-hal-cartel-weight-brackets>
					<?php foreach ( ( $settings['brackets'] ?? array() ) as $bracket ) : ?>
						<div class="hal-cartel-weight-bracket" data-hal-cartel-weight-bracket>
							<input type="number" step="0.01" data-bracket-up-to placeholder="<?php esc_attr_e( 'Up to', 'cartel' ); ?>" value="<?php echo esc_attr( $bracket['up_to'] ?? '' ); ?>" />
							<input type="number" step="0.01" data-bracket-cost placeholder="<?php esc_attr_e( 'Cost', 'cartel' ); ?>" value="<?php echo esc_attr( $bracket['cost'] ?? '' ); ?>" />
							<button type="button" data-hal-cartel-weight-bracket-remove aria-label="<?php esc_attr_e( 'Remove bracket', 'cartel' ); ?>">&times;</button>
						</div>
					<?php endforeach; ?>
				</div>
				<p><button type="button" class="button" data-hal-cartel-weight-bracket-add><?php esc_html_e( 'Add weight bracket', 'cartel' ); ?></button></p>
			</div>

			<input type="hidden" data-method-id value="<?php echo esc_attr( $method['id'] ); ?>" />
		</div>
		<?php
	}

	public static function shortcodes_page() {
		$shortcodes = array(
			array(
				'tag'         => 'hal_cartel_product',
				'example'     => '[hal_cartel_product id="123"]',
				'description' => __( 'Displays a single product card — title, price, "Add to cart" and "Buy now" buttons. Find the ready-made version of this for any product on its "Shortcode" tab in the product editor.', 'cartel' ),
			),
			array(
				'tag'         => 'hal_cartel_buy_now',
				'example'     => '[hal_cartel_buy_now id="123" label="Buy this now"]',
				'description' => __( 'A standalone "Buy now" button that adds the product to the cart and opens an inline checkout. The "label" attribute is optional.', 'cartel' ),
			),
			array(
				'tag'         => 'hal_cartel_checkout',
				'example'     => '[hal_cartel_checkout]',
				'description' => __( 'Renders the full one-page checkout form. Place it on a dedicated "Checkout" page.', 'cartel' ),
			),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Cartel Shortcodes', 'cartel' ); ?></h1>
			<p><?php esc_html_e( 'Copy any of these into a page, post, widget, or page-builder text element (Elementor, etc.).', 'cartel' ); ?></p>
			<div class="hal-cartel-shortcode-ref">
				<?php foreach ( $shortcodes as $sc ) : ?>
					<div class="hal-cartel-shortcode-ref__item">
						<h2><code>[<?php echo esc_html( $sc['tag'] ); ?>]</code></h2>
						<p class="hal-cartel-copy-row">
							<input type="text" readonly value="<?php echo esc_attr( $sc['example'] ); ?>" class="widefat code hal-cartel-copy-source" onclick="this.select();" />
							<button type="button" class="button hal-cartel-copy-btn" data-hal-cartel-copy><?php esc_html_e( 'Copy', 'cartel' ); ?></button>
						</p>
						<p class="description"><?php echo esc_html( $sc['description'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'hal_cartel_export' ) ) { wp_die( 'Forbidden' ); }
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="cartel-products.csv"' );
		Hal_Cartel_WC_Compat::export_csv();
		exit;
	}

	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'hal_cartel_import' ) ) { wp_die( 'Forbidden' ); }
		if ( empty( $_FILES['csv']['tmp_name'] ) ) { wp_die( 'No file' ); }
		$count = Hal_Cartel_WC_Compat::import_csv( $_FILES['csv']['tmp_name'] );
		wp_safe_redirect( add_query_arg( 'imported', is_wp_error( $count ) ? 0 : $count, admin_url( 'admin.php?page=hal-cartel-io' ) ) );
		exit;
	}
}
