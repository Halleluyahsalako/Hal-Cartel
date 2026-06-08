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
			shipping_method VARCHAR(190) NULL,
			tax DECIMAL(12,2) NOT NULL DEFAULT 0,
			total DECIMAL(12,2) NOT NULL DEFAULT 0,
			currency VARCHAR(8) NOT NULL DEFAULT 'USD',
			billing LONGTEXT NULL,
			shipping_address LONGTEXT NULL,
			gateway_id VARCHAR(40) NULL,
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

		$zones_table   = $wpdb->prefix . 'hal_cartel_shipping_zones';
		$methods_table = $wpdb->prefix . 'hal_cartel_shipping_methods';

		$sql3 = "CREATE TABLE {$zones_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			zone_order INT NOT NULL DEFAULT 0,
			locations LONGTEXT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		$sql4 = "CREATE TABLE {$methods_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			zone_id BIGINT(20) UNSIGNED NOT NULL,
			type VARCHAR(40) NOT NULL,
			title VARCHAR(190) NOT NULL,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			method_order INT NOT NULL DEFAULT 0,
			settings LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY zone_id (zone_id)
		) {$charset_collate};";

		$payment_events_table = $wpdb->prefix . 'hal_cartel_payment_events';
		$sql5 = "CREATE TABLE {$payment_events_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			gateway VARCHAR(40) NOT NULL,
			event_id VARCHAR(190) NOT NULL,
			order_id BIGINT(20) UNSIGNED NULL,
			type VARCHAR(60) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_id (event_id),
			KEY order_id (order_id)
		) {$charset_collate};";

		$downloads_table = $wpdb->prefix . 'hal_cartel_downloads';
		$sql6 = "CREATE TABLE {$downloads_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT(20) UNSIGNED NOT NULL,
			order_item_id BIGINT(20) UNSIGNED NOT NULL,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			variation_id BIGINT(20) UNSIGNED NULL,
			token VARCHAR(64) NOT NULL,
			download_count INT NOT NULL DEFAULT 0,
			max_downloads INT NULL,
			expires_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY order_id (order_id)
		) {$charset_collate};";

		dbDelta( $sql1 );
		dbDelta( $sql2 );
		dbDelta( $sql3 );
		dbDelta( $sql4 );
		dbDelta( $sql5 );
		dbDelta( $sql6 );

		add_option( 'hal_cartel_currency', 'USD' );
		add_option( 'hal_cartel_weight_unit', 'kg' );
		// Manual/Offline is active out of the box — every fresh install has a working checkout immediately.
		add_option( 'hal_cartel_active_gateways', array( 'manual' ) );
		add_option( 'hal_cartel_abandoned_order_timeout', 60 );
		update_option( 'hal_cartel_version', HAL_CARTEL_VERSION );

		if ( ! wp_next_scheduled( 'hal_cartel_cleanup_abandoned_orders' ) ) {
			wp_schedule_event( time(), 'hourly', 'hal_cartel_cleanup_abandoned_orders' );
		}

		// Make sure CPT is registered before flushing.
		Hal_Cartel_Product::register_post_type();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'hal_cartel_cleanup_abandoned_orders' );
		flush_rewrite_rules();
	}
}
