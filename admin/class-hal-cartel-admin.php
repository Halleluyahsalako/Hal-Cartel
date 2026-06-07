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
	}

	public static function menu() {
		add_menu_page( 'Cartel', 'Cartel', 'manage_options', 'hal-cartel', array( __CLASS__, 'dashboard_page' ), 'dashicons-cart', 56 );
		add_submenu_page( 'hal-cartel', 'Orders', 'Orders', 'manage_options', 'hal-cartel-orders', array( __CLASS__, 'orders_page' ) );
		add_submenu_page( 'hal-cartel', 'Import / Export', 'Import / Export', 'manage_options', 'hal-cartel-io', array( __CLASS__, 'io_page' ) );
		add_submenu_page( 'hal-cartel', 'Settings', 'Settings', 'manage_options', 'hal-cartel-settings', array( __CLASS__, 'settings_page' ) );
	}

	public static function register_settings() {
		register_setting( 'hal_cartel', 'hal_cartel_currency' );
		register_setting( 'hal_cartel', 'hal_cartel_recaptcha_site_key' );
		register_setting( 'hal_cartel', 'hal_cartel_recaptcha_secret_key' );
		register_setting( 'hal_cartel', 'hal_cartel_mailchimp_api_key' );
		register_setting( 'hal_cartel', 'hal_cartel_mailchimp_list_id' );
		register_setting( 'hal_cartel', 'hal_cartel_shipping_provider' ); // e.g. easypost, shippo
		register_setting( 'hal_cartel', 'hal_cartel_shipping_api_key' );
	}

	public static function dashboard_page() {
		echo '<div class="wrap"><h1>Cartel</h1><p>Lightweight commerce for WordPress.</p></div>';
	}

	public static function orders_page() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}hal_cartel_orders ORDER BY id DESC LIMIT 100" );
		echo '<div class="wrap"><h1>Orders</h1><table class="widefat striped"><thead><tr><th>#</th><th>Status</th><th>Email</th><th>Total</th><th>Date</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			printf( '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s %s</td><td>%s</td></tr>',
				esc_html( $r->order_number ), esc_html( $r->status ), esc_html( $r->email ),
				esc_html( $r->currency ), esc_html( $r->total ), esc_html( $r->created_at ) );
		}
		echo '</tbody></table></div>';
	}

	public static function io_page() {
		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=hal_cartel_export_csv' ), 'hal_cartel_export' );
		?>
		<div class="wrap">
			<h1>Import / Export</h1>
			<h2>Export (WooCommerce-compatible)</h2>
			<a class="button button-primary" href="<?php echo esc_url( $export_url ); ?>">Download CSV</a>
			<h2 style="margin-top:2em;">Import</h2>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'hal_cartel_import' ); ?>
				<input type="hidden" name="action" value="hal_cartel_import_csv" />
				<input type="file" name="csv" accept=".csv" required />
				<button type="submit" class="button button-primary">Upload</button>
			</form>
		</div>
		<?php
	}

	public static function settings_page() {
		?>
		<div class="wrap"><h1>Cartel Settings</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'hal_cartel' ); ?>
			<table class="form-table">
				<tr><th>Currency</th><td><input name="hal_cartel_currency" value="<?php echo esc_attr( get_option( 'hal_cartel_currency', 'USD' ) ); ?>" /></td></tr>
				<tr><th>reCAPTCHA Site Key</th><td><input name="hal_cartel_recaptcha_site_key" value="<?php echo esc_attr( get_option( 'hal_cartel_recaptcha_site_key' ) ); ?>" /></td></tr>
				<tr><th>reCAPTCHA Secret</th><td><input name="hal_cartel_recaptcha_secret_key" value="<?php echo esc_attr( get_option( 'hal_cartel_recaptcha_secret_key' ) ); ?>" /></td></tr>
				<tr><th>Mailchimp API Key</th><td><input name="hal_cartel_mailchimp_api_key" value="<?php echo esc_attr( get_option( 'hal_cartel_mailchimp_api_key' ) ); ?>" /></td></tr>
				<tr><th>Mailchimp List ID</th><td><input name="hal_cartel_mailchimp_list_id" value="<?php echo esc_attr( get_option( 'hal_cartel_mailchimp_list_id' ) ); ?>" /></td></tr>
				<tr><th>Shipping Provider</th><td><input name="hal_cartel_shipping_provider" value="<?php echo esc_attr( get_option( 'hal_cartel_shipping_provider' ) ); ?>" placeholder="easypost | shippo" /></td></tr>
				<tr><th>Shipping API Key</th><td><input name="hal_cartel_shipping_api_key" value="<?php echo esc_attr( get_option( 'hal_cartel_shipping_api_key' ) ); ?>" /></td></tr>
			</table>
			<?php submit_button(); ?>
		</form></div>
		<?php
	}

	public static function product_meta_box() {
		add_meta_box( 'hal_cartel_product_data', 'Product data', array( __CLASS__, 'render_product_meta' ), Hal_Cartel_Product::POST_TYPE );
	}

	public static function render_product_meta( $post ) {
		wp_nonce_field( 'hal_cartel_save_product', 'hal_cartel_product_nonce' );
		$f = function( $k ) use ( $post ) { return esc_attr( get_post_meta( $post->ID, $k, true ) ); };
		?>
		<p><label>SKU<br><input name="_hal_cartel_sku" value="<?php echo $f('_hal_cartel_sku'); ?>"></label></p>
		<p><label>Regular price<br><input name="_hal_cartel_price" type="number" step="0.01" value="<?php echo $f('_hal_cartel_price'); ?>"></label></p>
		<p><label>Sale price<br><input name="_hal_cartel_sale_price" type="number" step="0.01" value="<?php echo $f('_hal_cartel_sale_price'); ?>"></label></p>
		<p><label><input type="checkbox" name="_hal_cartel_manage_stock" value="1" <?php checked( get_post_meta( $post->ID, '_hal_cartel_manage_stock', true ), 1 ); ?>> Manage stock</label></p>
		<p><label>Stock<br><input name="_hal_cartel_stock" type="number" value="<?php echo $f('_hal_cartel_stock'); ?>"></label></p>
		<p><label>Weight<br><input name="_hal_cartel_weight" type="number" step="0.01" value="<?php echo $f('_hal_cartel_weight'); ?>"></label></p>
		<?php
	}

	public static function save_product_meta( $post_id, $post ) {
		if ( ! isset( $_POST['hal_cartel_product_nonce'] ) || ! wp_verify_nonce( $_POST['hal_cartel_product_nonce'], 'hal_cartel_save_product' ) ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
		foreach ( array( '_hal_cartel_sku', '_hal_cartel_price', '_hal_cartel_sale_price', '_hal_cartel_stock', '_hal_cartel_weight' ) as $k ) {
			if ( isset( $_POST[ $k ] ) ) { update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) ); }
		}
		update_post_meta( $post_id, '_hal_cartel_manage_stock', ! empty( $_POST['_hal_cartel_manage_stock'] ) ? 1 : 0 );
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
