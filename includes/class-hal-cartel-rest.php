<?php
/**
 * REST API: cart + checkout + products.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_REST {

	const NS = 'hal-cartel/v1';

	public static function register_routes() {
		register_rest_route( self::NS, '/cart', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'get_cart' ), 'permission_callback' => '__return_true' ),
		) );
		register_rest_route( self::NS, '/cart/add', array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'add_to_cart' ),
			'permission_callback' => '__return_true',
			'args' => array(
				'product_id' => array( 'required' => true, 'type' => 'integer' ),
				'quantity'   => array( 'required' => false, 'type' => 'integer', 'default' => 1 ),
			),
		) );
		register_rest_route( self::NS, '/cart/remove', array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'remove_from_cart' ),
			'permission_callback' => '__return_true',
			'args' => array( 'product_id' => array( 'required' => true, 'type' => 'integer' ) ),
		) );
		register_rest_route( self::NS, '/checkout', array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'checkout' ),
			'permission_callback' => '__return_true',
		) );
	}

	protected static function check_nonce( $request ) {
		$nonce = $request->get_header( 'x_wp_nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'cartel' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public static function get_cart( $request ) {
		return rest_ensure_response( Hal_Cartel_Cart::summary() );
	}

	public static function add_to_cart( $request ) {
		$check = self::check_nonce( $request );
		if ( is_wp_error( $check ) ) { return $check; }
		$result = Hal_Cartel_Cart::add( $request->get_param( 'product_id' ), $request->get_param( 'quantity' ) );
		if ( is_wp_error( $result ) ) { return $result; }
		return rest_ensure_response( $result );
	}

	public static function remove_from_cart( $request ) {
		$check = self::check_nonce( $request );
		if ( is_wp_error( $check ) ) { return $check; }
		return rest_ensure_response( Hal_Cartel_Cart::remove( $request->get_param( 'product_id' ) ) );
	}

	public static function checkout( $request ) {
		$check = self::check_nonce( $request );
		if ( is_wp_error( $check ) ) { return $check; }

		$params = $request->get_json_params();
		if ( empty( $params['email'] ) || ! is_email( $params['email'] ) ) {
			return new WP_Error( 'invalid_email', __( 'Valid email is required.', 'cartel' ), array( 'status' => 400 ) );
		}

		$result = Hal_Cartel_Order::create( $params );
		if ( is_wp_error( $result ) ) { return $result; }
		return rest_ensure_response( $result );
	}
}
