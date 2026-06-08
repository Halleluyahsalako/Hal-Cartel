<?php
/**
 * Product custom post type + meta helpers.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_Product {

	const POST_TYPE = 'hal_cartel_product';
	const CAT_TAXONOMY = 'hal_cartel_product_cat';
	const TAG_TAXONOMY = 'hal_cartel_product_tag';
	const SHIPPING_CLASS_TAXONOMY = 'hal_cartel_shipping_class';

	/** Product types — mirrors the WooCommerce vocabulary so imports/exports stay compatible. */
	const TYPE_SIMPLE   = 'simple';
	const TYPE_VARIABLE = 'variable';
	const TYPE_GROUPED  = 'grouped';
	const TYPE_EXTERNAL = 'external';

	public static function register_post_type() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name'          => __( 'Products', 'cartel' ),
				'singular_name' => __( 'Product', 'cartel' ),
				'add_new_item'  => __( 'Add New Product', 'cartel' ),
				'edit_item'     => __( 'Edit Product', 'cartel' ),
			),
			'public'       => true,
			'show_in_rest' => true,
			'has_archive'  => true,
			'menu_icon'    => 'dashicons-cart',
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'rewrite'      => array( 'slug' => 'product' ),
		) );

		register_taxonomy( self::CAT_TAXONOMY, self::POST_TYPE, array(
			'labels' => array(
				'name'          => __( 'Product categories', 'cartel' ),
				'singular_name' => __( 'Product category', 'cartel' ),
				'add_new_item'  => __( 'Add New Category', 'cartel' ),
				'edit_item'     => __( 'Edit Category', 'cartel' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'product-category' ),
		) );

		register_taxonomy( self::TAG_TAXONOMY, self::POST_TYPE, array(
			'labels' => array(
				'name'          => __( 'Product tags', 'cartel' ),
				'singular_name' => __( 'Product tag', 'cartel' ),
				'add_new_item'  => __( 'Add New Tag', 'cartel' ),
				'edit_item'     => __( 'Edit Tag', 'cartel' ),
			),
			'hierarchical'      => false,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'product-tag' ),
		) );

		register_taxonomy( self::SHIPPING_CLASS_TAXONOMY, self::POST_TYPE, array(
			'labels' => array(
				'name'          => __( 'Shipping classes', 'cartel' ),
				'singular_name' => __( 'Shipping class', 'cartel' ),
				'add_new_item'  => __( 'Add New Shipping Class', 'cartel' ),
				'edit_item'     => __( 'Edit Shipping Class', 'cartel' ),
			),
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => false,
			'rewrite'           => false,
		) );

		register_post_meta( self::POST_TYPE, '_hal_cartel_gallery', array(
			'type'          => 'array',
			'single'        => true,
			'show_in_rest'  => array(
				'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );

		register_post_meta( self::POST_TYPE, '_hal_cartel_product_type', array(
			'type' => 'string', 'single' => true, 'show_in_rest' => true, 'default' => self::TYPE_SIMPLE,
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );
		register_post_meta( self::POST_TYPE, '_hal_cartel_virtual', array(
			'type' => 'boolean', 'single' => true, 'show_in_rest' => true,
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );
		register_post_meta( self::POST_TYPE, '_hal_cartel_downloadable', array(
			'type' => 'boolean', 'single' => true, 'show_in_rest' => true,
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );
		register_post_meta( self::POST_TYPE, '_hal_cartel_download_limit', array(
			'type' => 'integer', 'single' => true, 'show_in_rest' => true,
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );
		register_post_meta( self::POST_TYPE, '_hal_cartel_download_expiry', array(
			'type' => 'integer', 'single' => true, 'show_in_rest' => true,
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );
		register_post_meta( self::POST_TYPE, '_hal_cartel_download_file', array(
			'type' => 'integer', 'single' => true, 'show_in_rest' => true,
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );
		register_post_meta( self::POST_TYPE, '_hal_cartel_external_url', array(
			'type' => 'string', 'single' => true, 'show_in_rest' => true,
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );
		register_post_meta( self::POST_TYPE, '_hal_cartel_external_button_text', array(
			'type' => 'string', 'single' => true, 'show_in_rest' => true,
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );
		register_post_meta( self::POST_TYPE, '_hal_cartel_grouped_products', array(
			'type'          => 'array',
			'single'        => true,
			'show_in_rest'  => array(
				'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			),
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );
		register_post_meta( self::POST_TYPE, '_hal_cartel_attributes', array(
			'type'          => 'array',
			'single'        => true,
			'show_in_rest'  => array(
				'schema' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'name'                => array( 'type' => 'string' ),
							'values'              => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
							'used_for_variations' => array( 'type' => 'boolean' ),
						),
					),
				),
			),
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );

		register_post_meta( self::POST_TYPE, '_hal_cartel_price', array(
			'type' => 'number', 'single' => true, 'show_in_rest' => true,
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );
		register_post_meta( self::POST_TYPE, '_hal_cartel_sale_price', array(
			'type' => 'number', 'single' => true, 'show_in_rest' => true,
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );
		register_post_meta( self::POST_TYPE, '_hal_cartel_sku', array(
			'type' => 'string', 'single' => true, 'show_in_rest' => true,
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );
		register_post_meta( self::POST_TYPE, '_hal_cartel_stock', array(
			'type' => 'integer', 'single' => true, 'show_in_rest' => true,
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );
		register_post_meta( self::POST_TYPE, '_hal_cartel_manage_stock', array(
			'type' => 'boolean', 'single' => true, 'show_in_rest' => true,
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );
		register_post_meta( self::POST_TYPE, '_hal_cartel_weight', array(
			'type' => 'number', 'single' => true, 'show_in_rest' => true,
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );

		// Empty string = inherit the store-wide default (Hal_Cartel_Currency::default_code()).
		register_post_meta( self::POST_TYPE, '_hal_cartel_currency', array(
			'type' => 'string', 'single' => true, 'show_in_rest' => true,
			'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
		) );
	}

	public static function get_gallery_ids( $product_id ): array {
		$ids = get_post_meta( $product_id, '_hal_cartel_gallery', true );
		return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
	}

	public static function get_type( $product_id ): string {
		$type = get_post_meta( $product_id, '_hal_cartel_product_type', true );
		$valid = array( self::TYPE_SIMPLE, self::TYPE_VARIABLE, self::TYPE_GROUPED, self::TYPE_EXTERNAL );
		return in_array( $type, $valid, true ) ? $type : self::TYPE_SIMPLE;
	}

	public static function is_variable( $product_id ): bool {
		return self::TYPE_VARIABLE === self::get_type( $product_id );
	}

	public static function is_grouped( $product_id ): bool {
		return self::TYPE_GROUPED === self::get_type( $product_id );
	}

	public static function is_external( $product_id ): bool {
		return self::TYPE_EXTERNAL === self::get_type( $product_id );
	}

	public static function is_virtual( $product_id ): bool {
		return (bool) get_post_meta( $product_id, '_hal_cartel_virtual', true );
	}

	public static function is_downloadable( $product_id ): bool {
		return (bool) get_post_meta( $product_id, '_hal_cartel_downloadable', true );
	}

	public static function get_download_file_id( $product_id ): int {
		return (int) get_post_meta( $product_id, '_hal_cartel_download_file', true );
	}

	public static function get_external_url( $product_id ): string {
		return (string) get_post_meta( $product_id, '_hal_cartel_external_url', true );
	}

	public static function get_external_button_text( $product_id ): string {
		$text = get_post_meta( $product_id, '_hal_cartel_external_button_text', true );
		return $text ? (string) $text : __( 'Buy product', 'cartel' );
	}

	public static function get_grouped_ids( $product_id ): array {
		$ids = get_post_meta( $product_id, '_hal_cartel_grouped_products', true );
		return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
	}

	/**
	 * Attribute definitions live on the parent product: [ [ name, values[], used_for_variations ], ... ].
	 * Variations then reference these by name + a single chosen value (see Hal_Cartel_Product_Variation).
	 */
	public static function get_attributes( $product_id ): array {
		$attributes = get_post_meta( $product_id, '_hal_cartel_attributes', true );
		if ( ! is_array( $attributes ) ) { return array(); }

		$clean = array();
		foreach ( $attributes as $attr ) {
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

	public static function get_variation_attributes( $product_id ): array {
		return array_values( array_filter( self::get_attributes( $product_id ), function( $attr ) {
			return ! empty( $attr['used_for_variations'] );
		} ) );
	}

	/**
	 * Variations are child posts (post_parent = $product_id) of type
	 * Hal_Cartel_Product_Variation::POST_TYPE — see that class for per-variation helpers.
	 */
	public static function get_variation_ids( $product_id ): array {
		if ( ! self::is_variable( $product_id ) ) { return array(); }

		return get_posts( array(
			'post_type'      => Hal_Cartel_Product_Variation::POST_TYPE,
			'post_parent'    => $product_id,
			'post_status'    => array( 'publish', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'fields'         => 'ids',
		) );
	}

	/** The product's own currency override, or the store-wide default when it has none set. */
	public static function get_currency( $product_id ): string {
		$override = (string) get_post_meta( $product_id, '_hal_cartel_currency', true );
		return $override ? strtoupper( $override ) : Hal_Cartel_Currency::default_code();
	}

	/** First shipping class assigned to the product, or null if it has none. */
	public static function get_shipping_class( $product_id ): ?WP_Term {
		$terms = get_the_terms( $product_id, self::SHIPPING_CLASS_TAXONOMY );
		return ( is_array( $terms ) && $terms ) ? $terms[0] : null;
	}

	/**
	 * Pass $variation_id for a variable product to get that variation's price
	 * (it overrides the parent's). Simple/grouped/external products ignore it.
	 */
	public static function get_price( $product_id, $variation_id = 0 ) {
		if ( $variation_id ) {
			return Hal_Cartel_Product_Variation::get_price( $variation_id );
		}
		$sale = (float) get_post_meta( $product_id, '_hal_cartel_sale_price', true );
		if ( $sale > 0 ) { return $sale; }
		return (float) get_post_meta( $product_id, '_hal_cartel_price', true );
	}

	/**
	 * The lowest and highest variation price — used to show "From $X" on
	 * variable product cards before the shopper has picked any options.
	 */
	public static function get_price_range( $product_id ): array {
		$prices = array();
		foreach ( self::get_variation_ids( $product_id ) as $variation_id ) {
			$prices[] = Hal_Cartel_Product_Variation::get_price( $variation_id );
		}
		$prices = array_filter( $prices, function( $p ) { return $p > 0; } );
		if ( ! $prices ) { return array( 'min' => 0.0, 'max' => 0.0 ); }
		return array( 'min' => min( $prices ), 'max' => max( $prices ) );
	}

	public static function in_stock( $product_id, $qty = 1, $variation_id = 0 ) {
		if ( $variation_id ) {
			return Hal_Cartel_Product_Variation::in_stock( $variation_id, $qty );
		}
		if ( self::is_virtual( $product_id ) || ! get_post_meta( $product_id, '_hal_cartel_manage_stock', true ) ) { return true; }
		$stock = (int) get_post_meta( $product_id, '_hal_cartel_stock', true );
		return $stock >= $qty;
	}

	public static function decrement_stock( $product_id, $qty, $variation_id = 0 ) {
		if ( $variation_id ) {
			Hal_Cartel_Product_Variation::decrement_stock( $variation_id, $qty );
			return;
		}
		if ( ! get_post_meta( $product_id, '_hal_cartel_manage_stock', true ) ) { return; }
		$stock = (int) get_post_meta( $product_id, '_hal_cartel_stock', true );
		update_post_meta( $product_id, '_hal_cartel_stock', max( 0, $stock - (int) $qty ) );
	}

	/** Restocks a product/variation — the inverse of decrement_stock(), used when an order is restocked (cancelled, abandoned, refunded). */
	public static function increment_stock( $product_id, $qty, $variation_id = 0 ) {
		if ( $variation_id ) {
			Hal_Cartel_Product_Variation::increment_stock( $variation_id, $qty );
			return;
		}
		if ( ! get_post_meta( $product_id, '_hal_cartel_manage_stock', true ) ) { return; }
		$stock = (int) get_post_meta( $product_id, '_hal_cartel_stock', true );
		update_post_meta( $product_id, '_hal_cartel_stock', $stock + (int) $qty );
	}
}
