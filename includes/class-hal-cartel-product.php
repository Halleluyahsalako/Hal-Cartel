<?php
/**
 * Product custom post type + meta helpers.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_Product {

	const POST_TYPE = 'hal_cartel_product';

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
	}

	public static function get_price( $product_id ) {
		$sale = (float) get_post_meta( $product_id, '_hal_cartel_sale_price', true );
		if ( $sale > 0 ) { return $sale; }
		return (float) get_post_meta( $product_id, '_hal_cartel_price', true );
	}

	public static function in_stock( $product_id, $qty = 1 ) {
		if ( ! get_post_meta( $product_id, '_hal_cartel_manage_stock', true ) ) { return true; }
		$stock = (int) get_post_meta( $product_id, '_hal_cartel_stock', true );
		return $stock >= $qty;
	}

	public static function decrement_stock( $product_id, $qty ) {
		if ( ! get_post_meta( $product_id, '_hal_cartel_manage_stock', true ) ) { return; }
		$stock = (int) get_post_meta( $product_id, '_hal_cartel_stock', true );
		update_post_meta( $product_id, '_hal_cartel_stock', max( 0, $stock - (int) $qty ) );
	}
}
