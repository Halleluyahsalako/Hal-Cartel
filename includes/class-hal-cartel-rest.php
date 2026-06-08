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
				'product_id'   => array( 'required' => true, 'type' => 'integer' ),
				'variation_id' => array( 'required' => false, 'type' => 'integer', 'default' => 0 ),
				'quantity'     => array( 'required' => false, 'type' => 'integer', 'default' => 1 ),
			),
		) );
		register_rest_route( self::NS, '/cart/remove', array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'remove_from_cart' ),
			'permission_callback' => '__return_true',
			'args' => array(
				'product_id'   => array( 'required' => true, 'type' => 'integer' ),
				'variation_id' => array( 'required' => false, 'type' => 'integer', 'default' => 0 ),
			),
		) );
		register_rest_route( self::NS, '/checkout', array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'checkout' ),
			'permission_callback' => '__return_true',
		) );
		// Public on purpose — these are external POSTs from payment providers, so they can't carry a WP nonce/cookie.
		// Each gateway's own signature check is the real authentication, and it must fail closed.
		register_rest_route( self::NS, '/payment/webhook/(?P<gateway>[\w-]+)', array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'payment_webhook' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/payment/methods', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'payment_methods' ), 'permission_callback' => '__return_true' ),
		) );
		register_rest_route( self::NS, '/shipping/rates', array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'shipping_rates' ),
			'permission_callback' => '__return_true',
			'args' => array(
				'country' => array( 'required' => true, 'type' => 'string' ),
				'state'   => array( 'required' => false, 'type' => 'string', 'default' => '' ),
			),
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
		$result = Hal_Cartel_Cart::add(
			$request->get_param( 'product_id' ),
			$request->get_param( 'quantity' ),
			$request->get_param( 'variation_id' )
		);
		if ( is_wp_error( $result ) ) { return $result; }
		return rest_ensure_response( $result );
	}

	public static function remove_from_cart( $request ) {
		$check = self::check_nonce( $request );
		if ( is_wp_error( $check ) ) { return $check; }
		return rest_ensure_response( Hal_Cartel_Cart::remove( $request->get_param( 'product_id' ), $request->get_param( 'variation_id' ) ) );
	}

	/** Selectable shipping rates for the shopper's destination, given the current cart. */
	public static function shipping_rates( $request ) {
		$zone = Hal_Cartel_Shipping::match_zone( $request->get_param( 'country' ), $request->get_param( 'state' ) );
		if ( ! $zone ) { return rest_ensure_response( array() ); }

		$summary  = Hal_Cartel_Cart::summary();
		$currency = $summary['currency'] ?? Hal_Cartel_Currency::default_code();
		$rates    = Hal_Cartel_Shipping::get_rates( $zone['id'], $summary );

		return rest_ensure_response( array_map( function ( $rate ) use ( $currency ) {
			$rate['formatted_cost'] = Hal_Cartel_Currency::format( $rate['cost'], $currency );
			return $rate;
		}, $rates ) );
	}

	/** Payment methods the current cart's currency is eligible for, with each gateway's storefront field descriptors. */
	public static function payment_methods( $request ) {
		$cart     = Hal_Cartel_Cart::summary();
		$currency = $cart['currency'] ?? Hal_Cartel_Currency::default_code();

		return rest_ensure_response( array_values( array_map( function ( $gateway ) {
			return array(
				'id'     => $gateway->id(),
				'title'  => $gateway->title(),
				'fields' => $gateway->checkout_fields(),
			);
		}, Hal_Cartel_Gateways::available_for( $currency, $cart ) ) ) );
	}

	public static function checkout( $request ) {
		$check = self::check_nonce( $request );
		if ( is_wp_error( $check ) ) { return $check; }

		$params = $request->get_json_params();
		if ( empty( $params['email'] ) || ! is_email( $params['email'] ) ) {
			return new WP_Error( 'invalid_email', __( 'Valid email is required.', 'cartel' ), array( 'status' => 400 ) );
		}

		// Fail closed only when a site key is configured — sites that haven't set up reCAPTCHA keep working unchanged.
		if ( get_option( 'hal_cartel_recaptcha_site_key' ) ) {
			$captcha = self::verify_recaptcha( $params['recaptcha_token'] ?? '' );
			if ( is_wp_error( $captcha ) ) { return $captcha; }
		}

		// Never trust the client's gateway choice blindly — resolve it against the active,
		// currency-eligible set server-side (same "never trust, revalidate" precedent as shipping).
		$cart        = Hal_Cartel_Cart::summary();
		$currency    = $cart['currency'] ?? Hal_Cartel_Currency::default_code();
		$gateway_id  = sanitize_key( $params['gateway_id'] ?? '' );
		$available   = Hal_Cartel_Gateways::available_for( $currency, $cart );
		if ( ! $gateway_id || ! isset( $available[ $gateway_id ] ) ) {
			return new WP_Error( 'invalid_gateway', __( 'Please choose a payment method.', 'cartel' ), array( 'status' => 400 ) );
		}
		$gateway = $available[ $gateway_id ];

		$result = Hal_Cartel_Order::create( $params, $gateway );
		if ( is_wp_error( $result ) ) { return $result; }

		// Fire-and-forget — never blocks or fails checkout on a marketing-list hiccup.
		if ( ! empty( $params['mailchimp_opt_in'] ) ) {
			Hal_Cartel_Mailchimp::subscribe( $params['email'] );
		}

		$payment = $gateway->initiate_payment( $result['order_id'] );

		return rest_ensure_response( array_merge( $result, array(
			'payment'        => $payment,
			'email'          => sanitize_email( $params['email'] ?? '' ),
			'payment_method' => $gateway->title(),
		) ) );
	}

	/** Verifies a checkout's reCAPTCHA v2 response against Google's siteverify endpoint — fails closed (rejects) on any network or verification problem. */
	protected static function verify_recaptcha( $token ) {
		if ( empty( $token ) ) {
			return new WP_Error( 'captcha_failed', __( 'Please complete the CAPTCHA.', 'cartel' ), array( 'status' => 400 ) );
		}

		$response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
			'timeout' => 10,
			'body'    => array(
				'secret'   => get_option( 'hal_cartel_recaptcha_secret_key' ),
				'response' => $token,
				'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
			),
		) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'captcha_failed', __( 'CAPTCHA verification could not be completed. Please try again.', 'cartel' ), array( 'status' => 400 ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['success'] ) ) {
			return new WP_Error( 'captcha_failed', __( 'CAPTCHA verification failed. Please try again.', 'cartel' ), array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Dispatches an inbound payment-provider webhook to the matching gateway.
	 * The route is public; the gateway's own signature verification is the real
	 * authentication and must fail closed (see Hal_Cartel_Gateway::handle_webhook()).
	 */
	public static function payment_webhook( $request ) {
		$id      = sanitize_key( $request->get_param( 'gateway' ) );
		$gateway = Hal_Cartel_Gateways::get( $id );
		if ( ! $gateway || ! Hal_Cartel_Gateways::is_active( $id ) || ! $gateway->webhook_id() ) {
			return new WP_REST_Response( array( 'error' => 'unknown_gateway' ), 404 );
		}
		return $gateway->handle_webhook( $request );
	}
}
