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
		add_action( 'init', array( 'Hal_Cartel_Product', 'register_post_type' ) );
		add_action( 'init', array( 'Hal_Cartel_Shortcodes', 'register' ) );
		add_action( 'rest_api_init', array( 'Hal_Cartel_REST', 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		Hal_Cartel_UI::init();

		if ( is_admin() ) {
			Hal_Cartel_Admin::init();
			Hal_Cartel_Extensions::init();
		}
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'cartel', false, dirname( plugin_basename( HAL_CARTEL_PLUGIN_FILE ) ) . '/languages' );
	}

	public function enqueue_public_assets() {
		wp_register_style( 'hal-cartel-public', HAL_CARTEL_PLUGIN_URL . 'public/assets/hal-cartel.css', array(), HAL_CARTEL_VERSION );
		wp_register_script( 'hal-cartel-public', HAL_CARTEL_PLUGIN_URL . 'public/assets/hal-cartel.js', array(), HAL_CARTEL_VERSION, true );
		wp_localize_script( 'hal-cartel-public', 'HalCartelData', array(
			'restUrl'  => esc_url_raw( rest_url( 'hal-cartel/v1/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'currency' => get_option( 'hal_cartel_currency', 'USD' ),
		) );
		wp_enqueue_style( 'hal-cartel-public' );
		wp_enqueue_script( 'hal-cartel-public' );
	}
}
