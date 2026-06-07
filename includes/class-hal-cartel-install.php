<?php
/**
 * Activation / Deactivation: creates custom tables for orders and order items.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_Install {

	public static function activate() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$orders_table = $wpdb->prefix . 'hal_cartel_orders';
		$items_table  = $wpdb->prefix . 'hal_cartel_order_items';

		$sql1 = "CREATE TABLE {$orders_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_number VARCHAR(40) NOT NULL,
			user_id BIGINT(20) UNSIGNED NULL,
			status VARCHAR(40) NOT NULL DEFAULT 'pending',
			email VARCHAR(190) NOT NULL,
			subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
			shipping DECIMAL(12,2) NOT NULL DEFAULT 0,
			tax DECIMAL(12,2) NOT NULL DEFAULT 0,
			total DECIMAL(12,2) NOT NULL DEFAULT 0,
			currency VARCHAR(8) NOT NULL DEFAULT 'USD',
			billing LONGTEXT NULL,
			shipping_address LONGTEXT NULL,
			payment_method VARCHAR(60) NULL,
			payment_ref VARCHAR(190) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_number (order_number),
			KEY user_id (user_id),
			KEY status (status)
		) {$charset_collate};";

		$sql2 = "CREATE TABLE {$items_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT(20) UNSIGNED NOT NULL,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			variation_id BIGINT(20) UNSIGNED NULL,
			name VARCHAR(190) NOT NULL,
			sku VARCHAR(100) NULL,
			quantity INT NOT NULL DEFAULT 1,
			price DECIMAL(12,2) NOT NULL DEFAULT 0,
			total DECIMAL(12,2) NOT NULL DEFAULT 0,
			meta LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY product_id (product_id)
		) {$charset_collate};";

		dbDelta( $sql1 );
		dbDelta( $sql2 );

		add_option( 'hal_cartel_currency', 'USD' );
		add_option( 'hal_cartel_version', HAL_CARTEL_VERSION );

		// Make sure CPT is registered before flushing.
		Hal_Cartel_Product::register_post_type();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
