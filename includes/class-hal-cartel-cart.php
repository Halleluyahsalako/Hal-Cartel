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

	/** A cart can hold the same product more than once if shoppers pick different variations — this is the per-line identity. */
	protected static function line_key( $product_id, $variation_id ) {
		return ( (int) $product_id ) . ':' . ( (int) $variation_id );
	}

	public static function get() {
		$cart = get_transient( 'hal_cartel_cart_' . self::token() );
		if ( ! is_array( $cart ) ) { return array(); }

		// Normalize legacy [ product_id => quantity ] entries from before variations existed.
		$normalized = array();
		foreach ( $cart as $key => $value ) {
			if ( is_array( $value ) && isset( $value['product_id'], $value['quantity'] ) ) {
				$product_id   = (int) $value['product_id'];
				$variation_id = isset( $value['variation_id'] ) ? (int) $value['variation_id'] : 0;
				$normalized[ self::line_key( $product_id, $variation_id ) ] = array(
					'product_id'   => $product_id,
					'variation_id' => $variation_id,
					'quantity'     => max( 1, (int) $value['quantity'] ),
				);
			} elseif ( is_numeric( $value ) ) {
				$product_id = (int) $key;
				$normalized[ self::line_key( $product_id, 0 ) ] = array(
					'product_id'   => $product_id,
					'variation_id' => 0,
					'quantity'     => max( 1, (int) $value ),
				);
			}
		}
		return $normalized;
	}

	public static function save( $cart ) {
		set_transient( 'hal_cartel_cart_' . self::token(), $cart, self::TTL );
	}

	/**
	 * Adds a product (optionally a specific variation) to the cart.
	 * Variable products require a valid, enabled $variation_id belonging to $product_id.
	 * External products cannot be added — shoppers are sent to the external URL instead.
	 */
	public static function add( $product_id, $qty = 1, $variation_id = 0 ) {
		$product_id   = (int) $product_id;
		$variation_id = (int) $variation_id;
		$qty          = max( 1, (int) $qty );

		if ( get_post_type( $product_id ) !== Hal_Cartel_Product::POST_TYPE ) {
			return new WP_Error( 'invalid_product', __( 'Invalid product.', 'cartel' ) );
		}
		if ( Hal_Cartel_Product::is_external( $product_id ) ) {
			return new WP_Error( 'external_product', __( 'This product is sold on another site — use its "Buy" link to purchase it there.', 'cartel' ) );
		}

		if ( Hal_Cartel_Product::is_variable( $product_id ) ) {
			$valid_variation = $variation_id
				&& (int) wp_get_post_parent_id( $variation_id ) === $product_id
				&& Hal_Cartel_Product_Variation::is_enabled( $variation_id );
			if ( ! $valid_variation ) {
				return new WP_Error( 'variation_required', __( 'Please choose product options before adding this to your cart.', 'cartel' ) );
			}
		} else {
			$variation_id = 0;
		}

		if ( ! Hal_Cartel_Product::in_stock( $product_id, $qty, $variation_id ) ) {
			return new WP_Error( 'out_of_stock', __( 'Not enough stock.', 'cartel' ) );
		}

		$cart = self::get();

		// Items priced in different currencies can't share one order total — see Hal_Cartel_Currency.
		$existing_currency = self::cart_currency( $cart );
		if ( $existing_currency ) {
			$incoming_currency = Hal_Cartel_Product::get_currency( $product_id );
			if ( $incoming_currency !== $existing_currency ) {
				return new WP_Error(
					'currency_mismatch',
					__( "Items priced in different currencies can't be combined in one order — please complete or empty your current cart first.", 'cartel' )
				);
			}
		}

		$key          = self::line_key( $product_id, $variation_id );
		$existing_qty = isset( $cart[ $key ] ) ? (int) $cart[ $key ]['quantity'] : 0;
		$cart[ $key ] = array(
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'quantity'     => $existing_qty + $qty,
		);
		self::save( $cart );
		return self::summary();
	}

	public static function remove( $product_id, $variation_id = 0 ) {
		$cart = self::get();
		unset( $cart[ self::line_key( $product_id, $variation_id ) ] );
		self::save( $cart );
		return self::summary();
	}

	public static function clear() { self::save( array() ); }

	/** The currency the cart is currently locked into — the first line's product currency, or '' when empty. */
	protected static function cart_currency( array $cart ): string {
		$first = reset( $cart );
		return $first ? Hal_Cartel_Product::get_currency( $first['product_id'] ) : '';
	}

	/** "Color: Red, Size: M" — appended to a cart line's display name so shoppers see exactly what they chose. */
	protected static function format_attributes( array $attributes ): string {
		$parts = array();
		foreach ( $attributes as $name => $value ) {
			$parts[] = $name . ': ' . $value;
		}
		return implode( ', ', $parts );
	}

	/** True if ANY item in the cart is a physical product (not virtual, downloadable, or digital). */
	public static function needs_shipping(): bool {
		foreach ( self::get() as $line ) {
			$pid = $line['product_id'];
			if ( ! Hal_Cartel_Product::is_downloadable( $pid ) && ! Hal_Cartel_Product::is_virtual( $pid ) ) {
				return true;
			}
		}
		return false;
	}

	public static function summary() {
		$cart     = self::get();
		$items    = array();
		$subtotal = 0.0;

		foreach ( $cart as $line ) {
			$product_id   = $line['product_id'];
			$variation_id = $line['variation_id'];
			$qty          = $line['quantity'];

			$price = Hal_Cartel_Product::get_price( $product_id, $variation_id );
			$total = $price * $qty;
			$subtotal += $total;

			$name       = get_the_title( $product_id );
			$attributes = array();
			if ( $variation_id ) {
				$attributes = Hal_Cartel_Product_Variation::get_attributes( $variation_id );
				$label      = self::format_attributes( $attributes );
				if ( $label ) { $name .= " \u{2014} " . $label; }
			}

			$items[] = array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'name'         => $name,
				'attributes'   => $attributes,
				'quantity'     => $qty,
				'price'        => $price,
				'total'        => $total,
			);
		}

		return array(
			'items'    => $items,
			'subtotal' => round( $subtotal, 2 ),
			'currency' => self::cart_currency( $cart ) ?: Hal_Cartel_Currency::default_code(),
		);
	}
}