<?php
/**
 * Main Cartel bootstrap class.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Hal_Cartel {
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Priority 0: activate() registers post types and flushes rewrite rules, both of which need
		// $wp_rewrite — unavailable this early at plugins_loaded, so defer to init (before the priority-10 registrations below).
		add_action( 'init', array( $this, 'maybe_upgrade' ), 0 );

		add_action( 'init', array( 'Hal_Cartel_Product', 'register_post_type' ) );
		add_action( 'init', array( 'Hal_Cartel_Product_Variation', 'register_post_type' ) );
		add_action( 'init', array( 'Hal_Cartel_Shortcodes', 'register' ) );
		add_action( 'rest_api_init', array( 'Hal_Cartel_REST', 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'hal_cartel_cleanup_abandoned_orders', array( 'Hal_Cartel_Order', 'cleanup_abandoned' ) );

		$this->register_gateways();

		Hal_Cartel_Emails::init();
		Hal_Cartel_Downloads::init();
		Hal_Cartel_UI::init();
		Hal_Cartel_Gateways::init();

		if ( is_admin() ) {
			Hal_Cartel_Admin::init();
			Hal_Cartel_Extensions::init();
		}
	}

	/** Registers the payment gateways that ship with Cartel core. Third-party gateways register the same way via Hal_Cartel_Gateways::register(). */
	private function register_gateways() {
		Hal_Cartel_Gateways::register( new Hal_Cartel_Gateway_Manual() );
		Hal_Cartel_Gateways::register( new Hal_Cartel_Gateway_Stripe() );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'cartel', false, dirname( plugin_basename( HAL_CARTEL_PLUGIN_FILE ) ) . '/languages' );
	}

	/** Re-runs the dbDelta install routine when the stored version lags the plugin file — keeps existing sites' schema current after an update, without requiring deactivate/reactivate. */
	public function maybe_upgrade() {
		if ( get_option( 'hal_cartel_version' ) !== HAL_CARTEL_VERSION ) {
			Hal_Cartel_Install::activate();
		}
	}

	public function enqueue_public_assets() {
		wp_register_style( 'hal-cartel-public', HAL_CARTEL_PLUGIN_URL . 'public/assets/hal-cartel.css', array(), HAL_CARTEL_VERSION );
		wp_register_script( 'hal-cartel-public', HAL_CARTEL_PLUGIN_URL . 'public/assets/hal-cartel.js', array(), HAL_CARTEL_VERSION, true );
		$currency_code = Hal_Cartel_Currency::default_code();
		wp_localize_script( 'hal-cartel-public', 'HalCartelData', array(
			'restUrl'        => esc_url_raw( rest_url( 'hal-cartel/v1/' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'currency'       => $currency_code,
			'currencyFormat' => array(
				'symbol'   => Hal_Cartel_Currency::symbol( $currency_code ),
				'position' => Hal_Cartel_Currency::position( $currency_code ),
				'decimals' => Hal_Cartel_Currency::decimals( $currency_code ),
			),
			'inStockLabel'   => __( 'In stock', 'cartel' ),
			'outOfStockLabel' => __( 'Out of stock', 'cartel' ),
			'shippingHintLabel'    => __( 'Enter your destination country to see available shipping methods.', 'cartel' ),
			'shippingNoneLabel'    => __( 'No shipping methods are available for this destination.', 'cartel' ),
			'shippingLoadingLabel' => __( 'Loading shipping methods…', 'cartel' ),
			'paymentNoneLabel'     => __( 'No payment methods are available. Please contact the store.', 'cartel' ),
			'paymentLoadingLabel'  => __( 'Loading payment methods…', 'cartel' ),
		) );
		wp_enqueue_style( 'hal-cartel-public' );
		wp_enqueue_script( 'hal-cartel-public' );
	}
}
