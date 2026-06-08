<?php
/**
 * Product variations — child posts of a Variable product, one per attribute
 * combination (e.g. Color: Red + Size: M), each with its own price/stock/image.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_Product_Variation {

	const POST_TYPE = 'hal_cartel_variation';

	public static function register_post_type() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name'          => __( 'Product variations', 'cartel' ),
				'singular_name' => __( 'Product variation', 'cartel' ),
			),
			'public'              => false,
			'show_ui'             => false,
			'show_in_rest'        => true,
			'hierarchical'        => false,
			'supports'            => array( 'title' ),
			'capability_type'     => 'product',
			'map_meta_cap'        => false,
			'capabilities'        => array(
				'edit_post'   => 'edit_posts',
				'edit_posts'  => 'edit_posts',
				'read_post'   => 'read',
				'delete_post' => 'delete_posts',
			),
		) );

		foreach ( array(
			'_hal_cartel_price'         => 'number',
			'_hal_cartel_sale_price'    => 'number',
			'_hal_cartel_sku'           => 'string',
			'_hal_cartel_stock'         => 'integer',
			'_hal_cartel_manage_stock'  => 'boolean',
			'_hal_cartel_weight'        => 'number',
			'_hal_cartel_image'         => 'integer',
			'_hal_cartel_enabled'       => 'boolean',
		) as $key => $type ) {
			register_post_meta( self::POST_TYPE, $key, array(
				'type' => $type, 'single' => true, 'show_in_rest' => true,
				'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
			) );
		}

		register_post_meta( self::POST_TYPE, '_hal_cartel_variation_attributes', array(
			'type'          => 'object',
			'single'        => true,
			'show_in_rest'  => array(
				'schema' => array( 'type' => 'object', 'additionalProperties' => array( 'type' => 'string' ) ),
			),
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );
	}

	/** Attribute name => chosen value, e.g. [ 'Color' => 'Red', 'Size' => 'M' ]. */
	public static function get_attributes( $variation_id ): array {
		$attrs = get_post_meta( $variation_id, '_hal_cartel_variation_attributes', true );
		if ( ! is_array( $attrs ) ) { return array(); }

		$clean = array();
		foreach ( $attrs as $name => $value ) {
			$clean[ sanitize_text_field( (string) $name ) ] = sanitize_text_field( (string) $value );
		}
		return $clean;
	}

	public static function is_enabled( $variation_id ): bool {
		return (bool) get_post_meta( $variation_id, '_hal_cartel_enabled', true );
	}

	public static function get_price( $variation_id ) {
		$sale = (float) get_post_meta( $variation_id, '_hal_cartel_sale_price', true );
		if ( $sale > 0 ) { return $sale; }

		$price = get_post_meta( $variation_id, '_hal_cartel_price', true );
		if ( '' !== $price ) { return (float) $price; }

		// No price set on this variation — fall back to the parent product's price.
		$parent_id = wp_get_post_parent_id( $variation_id );
		return $parent_id ? Hal_Cartel_Product::get_price( $parent_id ) : 0.0;
	}

	public static function get_sku( $variation_id ): string {
		$sku = get_post_meta( $variation_id, '_hal_cartel_sku', true );
		if ( $sku ) { return (string) $sku; }

		$parent_id = wp_get_post_parent_id( $variation_id );
		return $parent_id ? (string) get_post_meta( $parent_id, '_hal_cartel_sku', true ) : '';
	}

	public static function get_image_id( $variation_id ): int {
		return (int) get_post_meta( $variation_id, '_hal_cartel_image', true );
	}

	public static function in_stock( $variation_id, $qty = 1 ): bool {
		if ( ! self::is_enabled( $variation_id ) ) { return false; }

		$parent_id = wp_get_post_parent_id( $variation_id );
		if ( $parent_id && Hal_Cartel_Product::is_virtual( $parent_id ) ) { return true; }

		if ( ! get_post_meta( $variation_id, '_hal_cartel_manage_stock', true ) ) { return true; }
		$stock = (int) get_post_meta( $variation_id, '_hal_cartel_stock', true );
		return $stock >= $qty;
	}

	public static function decrement_stock( $variation_id, $qty ) {
		if ( ! get_post_meta( $variation_id, '_hal_cartel_manage_stock', true ) ) { return; }
		$stock = (int) get_post_meta( $variation_id, '_hal_cartel_stock', true );
		update_post_meta( $variation_id, '_hal_cartel_stock', max( 0, $stock - (int) $qty ) );
	}

	/** Restocks a variation — the inverse of decrement_stock(). */
	public static function increment_stock( $variation_id, $qty ) {
		if ( ! get_post_meta( $variation_id, '_hal_cartel_manage_stock', true ) ) { return; }
		$stock = (int) get_post_meta( $variation_id, '_hal_cartel_stock', true );
		update_post_meta( $variation_id, '_hal_cartel_stock', $stock + (int) $qty );
	}

	/**
	 * Find the variation whose attribute selections exactly match $selection,
	 * e.g. [ 'Color' => 'Red', 'Size' => 'M' ] — used when a shopper picks
	 * options on a variable product and we need its price/stock/SKU.
	 */
	public static function find_matching( $product_id, array $selection ): int {
		$selection = array_map( 'sanitize_text_field', array_map( 'strval', $selection ) );

		foreach ( Hal_Cartel_Product::get_variation_ids( $product_id ) as $variation_id ) {
			if ( self::get_attributes( $variation_id ) === $selection ) {
				return $variation_id;
			}
		}
		return 0;
	}

	/** Lightweight array representation for REST/JS consumption. */
	public static function to_array( $variation_id ): array {
		$image_id = self::get_image_id( $variation_id );
		return array(
			'id'           => $variation_id,
			'attributes'   => self::get_attributes( $variation_id ),
			'price'        => self::get_price( $variation_id ),
			'sku'          => self::get_sku( $variation_id ),
			'enabled'      => self::is_enabled( $variation_id ),
			'in_stock'     => self::in_stock( $variation_id ),
			'image'        => $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '',
		);
	}
}