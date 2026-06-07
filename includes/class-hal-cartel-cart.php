<?php
/**
 * Session-backed cart. Uses PHP session via WordPress transients keyed by cookie token.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_Cart {

	const COOKIE = 'hal_cartel_cart_token';
	const TTL    = DAY_IN_SECONDS * 14;

	protected static function token() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			$token = wp_generate_password( 32, false, false );
			setcookie( self::COOKIE, $token, time() + self::TTL, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			$_COOKIE[ self::COOKIE ] = $token;
			return $token;
		}
		return sanitize_text_field( $_COOKIE[ self::COOKIE ] );
	}

	public static function get() {
		$cart = get_transient( 'hal_cartel_cart_' . self::token() );
		return is_array( $cart ) ? $cart : array();
	}

	public static function save( $cart ) {
		set_transient( 'hal_cartel_cart_' . self::token(), $cart, self::TTL );
	}

	public static function add( $product_id, $qty = 1 ) {
		$product_id = (int) $product_id;
		$qty        = max( 1, (int) $qty );
		if ( get_post_type( $product_id ) !== Hal_Cartel_Product::POST_TYPE ) {
			return new WP_Error( 'invalid_product', __( 'Invalid product.', 'cartel' ) );
		}
		if ( ! Hal_Cartel_Product::in_stock( $product_id, $qty ) ) {
			return new WP_Error( 'out_of_stock', __( 'Not enough stock.', 'cartel' ) );
		}
		$cart = self::get();
		$key  = (string) $product_id;
		$cart[ $key ] = isset( $cart[ $key ] ) ? $cart[ $key ] + $qty : $qty;
		self::save( $cart );
		return self::summary();
	}

	public static function remove( $product_id ) {
		$cart = self::get();
		unset( $cart[ (string) (int) $product_id ] );
		self::save( $cart );
		return self::summary();
	}

	public static function clear() { self::save( array() ); }

	public static function summary() {
		$cart  = self::get();
		$items = array();
		$subtotal = 0.0;
		foreach ( $cart as $pid => $qty ) {
			$pid   = (int) $pid;
			$price = Hal_Cartel_Product::get_price( $pid );
			$total = $price * (int) $qty;
			$subtotal += $total;
			$items[] = array(
				'product_id' => $pid,
				'name'       => get_the_title( $pid ),
				'quantity'   => (int) $qty,
				'price'      => $price,
				'total'      => $total,
			);
		}
		return array(
			'items'    => $items,
			'subtotal' => round( $subtotal, 2 ),
			'currency' => get_option( 'hal_cartel_currency', 'USD' ),
		);
	}
}
