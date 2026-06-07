<?php
/**
 * Shortcodes: [hal_cartel_product id=""], [hal_cartel_buy_now id=""], [hal_cartel_checkout].
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_Shortcodes {

	public static function register() {
		add_shortcode( 'hal_cartel_product',  array( __CLASS__, 'product' ) );
		add_shortcode( 'hal_cartel_buy_now',  array( __CLASS__, 'buy_now' ) );
		add_shortcode( 'hal_cartel_checkout', array( __CLASS__, 'checkout' ) );
	}

	public static function product( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts );
		$id   = (int) $atts['id'];
		if ( ! $id || get_post_type( $id ) !== Hal_Cartel_Product::POST_TYPE ) { return ''; }
		$price = Hal_Cartel_Product::get_price( $id );
		ob_start(); ?>
		<div class="hal-cartel-product" data-product-id="<?php echo esc_attr( $id ); ?>">
			<h3 class="hal-cartel-product__title"><?php echo esc_html( get_the_title( $id ) ); ?></h3>
			<div class="hal-cartel-product__price"><?php echo esc_html( number_format( $price, 2 ) ); ?></div>
			<button class="hal-cartel-add-to-cart" type="button"><?php esc_html_e( 'Add to cart', 'cartel' ); ?></button>
			<button class="hal-cartel-buy-now" type="button"><?php esc_html_e( 'Buy now', 'cartel' ); ?></button>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function buy_now( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0, 'label' => __( 'Buy now', 'cartel' ) ), $atts );
		$id   = (int) $atts['id'];
		if ( ! $id ) { return ''; }
		ob_start(); ?>
		<div class="hal-cartel-buy-now-wrap" data-product-id="<?php echo esc_attr( $id ); ?>">
			<button class="hal-cartel-buy-now-inline" type="button"><?php echo esc_html( $atts['label'] ); ?></button>
			<div class="hal-cartel-inline-checkout" hidden></div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function checkout( $atts ) {
		ob_start();
		include HAL_CARTEL_PLUGIN_DIR . 'templates/checkout.php';
		return ob_get_clean();
	}
}
