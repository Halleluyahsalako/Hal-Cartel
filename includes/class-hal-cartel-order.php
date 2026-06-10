<?php
/**
 * Order create / read helpers.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_Order {

	/**
	 * Statuses Cartel recognises, in the WooCommerce-subset vocabulary (keeps CSV
	 * import/export compatible with the wider ecosystem and gives every status a label).
	 */
	public static function statuses(): array {
		return array(
			'pending-payment' => __( 'Pending payment', 'cartel' ),
			'on-hold'         => __( 'On hold', 'cartel' ),
			'processing'      => __( 'Processing', 'cartel' ),
			'completed'       => __( 'Completed', 'cartel' ),
			'cancelled'       => __( 'Cancelled', 'cartel' ),
			'refunded'        => __( 'Refunded', 'cartel' ),
			'failed'          => __( 'Failed', 'cartel' ),
		);
	}

	/** Maps a status to the badge variant used to colour it consistently in admin order views and [hal_cartel_my_orders]. */
	public static function status_variant( $status ): string {
		$variants = array(
			'pending-payment' => 'warning',
			'on-hold'         => 'warning',
			'processing'      => 'neutral',
			'completed'       => 'success',
			'cancelled'       => 'danger',
			'refunded'        => 'danger',
			'failed'          => 'danger',
		);
		return $variants[ $status ] ?? 'neutral';
	}

	/**
	 * Creates the order row in `pending-payment`, storing the chosen gateway's id —
	 * never raw client-sent payment strings (mirrors the "never trust the client"
	 * server-side recompute already used for shipping costs). $gateway must already
	 * be resolved/validated by the caller (Hal_Cartel_Gateways::available_for()).
	 * The REST layer calls $gateway->initiate_payment() once this returns.
	 */
	public static function create( array $data, Hal_Cartel_Gateway $gateway ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$orders_table = $wpdb->prefix . 'hal_cartel_orders';
		$items_table  = $wpdb->prefix . 'hal_cartel_order_items';

		$cart = Hal_Cartel_Cart::summary();
		if ( empty( $cart['items'] ) ) {
			return new WP_Error( 'empty_cart', __( 'Cart is empty.', 'cartel' ) );
		}

		$needs_shipping = Hal_Cartel_Cart::needs_shipping();

		if ( $needs_shipping ) {
			// Never trust a client-sent shipping cost — recompute server-side from the destination + configured zones/methods.
			$country = sanitize_text_field( $data['shipping']['country'] ?? '' );
			$state   = sanitize_text_field( $data['shipping']['state'] ?? '' );
			$zone    = $country ? Hal_Cartel_Shipping::match_zone( $country, $state ) : null;
			$rates   = $zone ? Hal_Cartel_Shipping::get_rates( $zone['id'], $cart ) : array();

			$method_id = (int) ( $data['shipping_method_id'] ?? 0 );
			$rate      = null;
			foreach ( $rates as $candidate ) {
				if ( $candidate['id'] === $method_id ) { $rate = $candidate; break; }
			}
			if ( ! $rate ) {
				return new WP_Error( 'invalid_shipping_method', __( 'Please choose a shipping method for your destination.', 'cartel' ) );
			}
			$shipping_cost   = (float) $rate['cost'];
			$shipping_method = sanitize_text_field( $rate['title'] );
		} else {
			$shipping_cost   = 0.0;
			$shipping_method = '';
		}

		$tax_total     = (float) ( $data['tax_total'] ?? 0 );
		$order_number  = strtoupper( wp_generate_password( 10, false, false ) );
		$user_id       = get_current_user_id() ?: self::maybe_create_account( sanitize_email( $data['email'] ) );

		$wpdb->insert( $orders_table, array(
			'order_number' => $order_number,
			'user_id'      => $user_id ?: null,
			'status'       => 'pending-payment',
			'email'        => sanitize_email( $data['email'] ),
			'subtotal'     => $cart['subtotal'],
			'shipping'     => $shipping_cost,
			'shipping_method' => $shipping_method,
			'tax'          => $tax_total,
			'total'        => $cart['subtotal'] + $shipping_cost + $tax_total,
			'currency'     => $cart['currency'],
			// Checkout collects a single address; treat it as billing too unless a distinct billing address was supplied.
			'billing'      => wp_json_encode( $data['billing'] ?? $data['shipping'] ?? array() ),
			'shipping_address' => wp_json_encode( $data['shipping'] ?? array() ),
			'gateway_id'   => $gateway->id(),
			'payment_method' => $gateway->title(),
			'payment_ref'  => null,
			'created_at'   => $now,
			'updated_at'   => $now,
		) );
		$order_id = (int) $wpdb->insert_id;

		foreach ( $cart['items'] as $item ) {
			$variation_id = $item['variation_id'] ?? 0;
			$sku          = $variation_id
				? Hal_Cartel_Product_Variation::get_sku( $variation_id )
				: get_post_meta( $item['product_id'], '_hal_cartel_sku', true );

			$wpdb->insert( $items_table, array(
				'order_id'     => $order_id,
				'product_id'   => $item['product_id'],
				'variation_id' => $variation_id ?: null,
				'name'         => $item['name'],
				'sku'          => $sku,
				'quantity'     => $item['quantity'],
				'price'        => $item['price'],
				'total'        => $item['total'],
				'meta'         => ! empty( $item['attributes'] ) ? wp_json_encode( array( 'attributes' => $item['attributes'] ) ) : null,
			) );
			Hal_Cartel_Product::decrement_stock( $item['product_id'], $item['quantity'], $variation_id );
		}

		Hal_Cartel_Cart::clear();

		do_action( 'hal_cartel_order_created', $order_id, $data );

		return array(
			'order_id'     => $order_id,
			'order_number' => $order_number,
			'items'        => $cart['items'],
			'subtotal'     => $cart['subtotal'],
			'shipping'     => $shipping_cost,
			'tax'          => $tax_total,
			'total'        => round( $cart['subtotal'] + $shipping_cost + $tax_total, 2 ),
			'currency'     => $cart['currency'],
		);
	}

	/**
	 * Auto-creates a customer account for a guest checkout email — opt-in via
	 * `hal_cartel_create_accounts_at_checkout` (off by default; many small merchants
	 * prefer pure guest checkout). Returns the linked user id, or 0 when account
	 * creation is disabled, the email already has an account, or creation fails.
	 */
	protected static function maybe_create_account( $email ) {
		if ( ! get_option( 'hal_cartel_create_accounts_at_checkout' ) ) {
			return 0;
		}
		$existing = get_user_by( 'email', $email );
		if ( $existing ) {
			return (int) $existing->ID;
		}

		$username = sanitize_user( current( explode( '@', $email ) ), true );
		if ( username_exists( $username ) || empty( $username ) ) {
			$username = sanitize_user( $email, true );
		}

		$user_id = wp_create_user( $username, wp_generate_password( 20 ), $email );
		if ( is_wp_error( $user_id ) ) {
			return 0;
		}

		wp_new_user_notification( $user_id, null, 'user' );

		return (int) $user_id;
	}

	/** A logged-in shopper's order history, most recent first — feeds [hal_cartel_my_orders]. */
	public static function for_user( $user_id ): array {
		global $wpdb;
		$orders_table = $wpdb->prefix . 'hal_cartel_orders';
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$orders_table} WHERE user_id = %d ORDER BY created_at DESC", $user_id
		) );
	}

	/** Guest order lookup by order number + email — the account-free path through [hal_cartel_my_orders]. Both must match so a guessed order number alone can't expose another shopper's order. */
	public static function find_by_number_and_email( $order_number, $email ) {
		global $wpdb;
		$orders_table = $wpdb->prefix . 'hal_cartel_orders';
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$orders_table} WHERE order_number = %s AND email = %s", $order_number, $email
		) );
	}

	/** Transitions an order's status — a guarded no-op if it's already there (webhook retries replay safely). */
	public static function set_status( $order_id, $status ) {
		global $wpdb;
		$status       = sanitize_text_field( $status );
		$orders_table = $wpdb->prefix . 'hal_cartel_orders';

		$current = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders_table} WHERE id = %d", $order_id ) );
		if ( $current === $status ) {
			return;
		}

		$wpdb->update( $orders_table,
			array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $order_id )
		);
		do_action( 'hal_cartel_order_status_changed', $order_id, $status, $current );
	}

	/** Restocks an order's line items — the inverse of Hal_Cartel_Product::decrement_stock(), used when an order fails/cancels after stock was already reserved. */
	public static function restock( $order_id ) {
		global $wpdb;
		$items_table = $wpdb->prefix . 'hal_cartel_order_items';
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT product_id, variation_id, quantity FROM {$items_table} WHERE order_id = %d", $order_id ) );
		foreach ( $items as $item ) {
			Hal_Cartel_Product::increment_stock( $item->product_id, $item->quantity, (int) $item->variation_id );
		}
	}

	/**
	 * Restocks and fails `pending-payment` orders that have sat untouched past the
	 * configurable timeout — otherwise abandoned card forms silently leak stock forever.
	 * Hooked to an hourly cron registered in Hal_Cartel_Install::activate().
	 */
	public static function cleanup_abandoned() {
		global $wpdb;
		$orders_table = $wpdb->prefix . 'hal_cartel_orders';
		$timeout_minutes = (int) get_option( 'hal_cartel_abandoned_order_timeout', 60 );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $timeout_minutes * MINUTE_IN_SECONDS );

		$stale_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$orders_table} WHERE status = 'pending-payment' AND created_at < %s",
			$cutoff
		) );

		foreach ( $stale_ids as $order_id ) {
			self::restock( $order_id );
			self::set_status( $order_id, 'failed' );
		}
	}
}
