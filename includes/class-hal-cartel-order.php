<?php
/**
 * Order create / read helpers.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_Order {

	public static function create( array $data ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$orders_table = $wpdb->prefix . 'hal_cartel_orders';
		$items_table  = $wpdb->prefix . 'hal_cartel_order_items';

		$cart = Hal_Cartel_Cart::summary();
		if ( empty( $cart['items'] ) ) {
			return new WP_Error( 'empty_cart', __( 'Cart is empty.', 'cartel' ) );
		}

		$order_number = strtoupper( wp_generate_password( 10, false, false ) );

		$wpdb->insert( $orders_table, array(
			'order_number' => $order_number,
			'user_id'      => get_current_user_id() ?: null,
			'status'       => 'pending',
			'email'        => sanitize_email( $data['email'] ),
			'subtotal'     => $cart['subtotal'],
			'shipping'     => isset( $data['shipping_total'] ) ? (float) $data['shipping_total'] : 0,
			'tax'          => isset( $data['tax_total'] ) ? (float) $data['tax_total'] : 0,
			'total'        => $cart['subtotal'] + (float) ( $data['shipping_total'] ?? 0 ) + (float) ( $data['tax_total'] ?? 0 ),
			'currency'     => $cart['currency'],
			'billing'      => wp_json_encode( $data['billing'] ?? array() ),
			'shipping_address' => wp_json_encode( $data['shipping'] ?? array() ),
			'payment_method' => sanitize_text_field( $data['payment_method'] ?? '' ),
			'payment_ref'  => sanitize_text_field( $data['payment_ref'] ?? '' ),
			'created_at'   => $now,
			'updated_at'   => $now,
		) );
		$order_id = (int) $wpdb->insert_id;

		foreach ( $cart['items'] as $item ) {
			$wpdb->insert( $items_table, array(
				'order_id'   => $order_id,
				'product_id' => $item['product_id'],
				'name'       => $item['name'],
				'sku'        => get_post_meta( $item['product_id'], '_hal_cartel_sku', true ),
				'quantity'   => $item['quantity'],
				'price'      => $item['price'],
				'total'      => $item['total'],
			) );
			Hal_Cartel_Product::decrement_stock( $item['product_id'], $item['quantity'] );
		}

		Hal_Cartel_Cart::clear();

		do_action( 'hal_cartel_order_created', $order_id, $data );

		return array( 'order_id' => $order_id, 'order_number' => $order_number );
	}

	public static function set_status( $order_id, $status ) {
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'hal_cartel_orders',
			array( 'status' => sanitize_text_field( $status ), 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $order_id )
		);
		do_action( 'hal_cartel_order_status_changed', $order_id, $status );
	}
}
