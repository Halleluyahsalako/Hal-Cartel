<?php
/**
 * Shortcodes: [hal_cartel_product id=""], [hal_cartel_buy_now id=""], [hal_cartel_checkout], [hal_cartel_my_orders].
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_Shortcodes {

	public static function register() {
		add_shortcode( 'hal_cartel_product',  array( __CLASS__, 'product' ) );
		add_shortcode( 'hal_cartel_buy_now',  array( __CLASS__, 'buy_now' ) );
		add_shortcode( 'hal_cartel_checkout', array( __CLASS__, 'checkout' ) );
		add_shortcode( 'hal_cartel_my_orders', array( __CLASS__, 'my_orders' ) );
	}

	public static function product( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts );
		$id   = (int) $atts['id'];
		if ( ! $id || get_post_type( $id ) !== Hal_Cartel_Product::POST_TYPE ) { return ''; }

		switch ( Hal_Cartel_Product::get_type( $id ) ) {
			case Hal_Cartel_Product::TYPE_VARIABLE:
				return self::render_variable_product( $id );
			case Hal_Cartel_Product::TYPE_GROUPED:
				return self::render_grouped_product( $id );
			case Hal_Cartel_Product::TYPE_EXTERNAL:
				return self::render_external_product( $id );
			default:
				return self::render_simple_product( $id );
		}
	}

	protected static function render_simple_product( $id ) {
		$price    = Hal_Cartel_Product::get_price( $id );
		$currency = Hal_Cartel_Product::get_currency( $id );
		ob_start(); ?>
		<div class="hal-cartel-product" data-product-id="<?php echo esc_attr( $id ); ?>">
			<h3 class="hal-cartel-product__title"><?php echo esc_html( get_the_title( $id ) ); ?></h3>
			<div class="hal-cartel-product__price"><?php echo esc_html( Hal_Cartel_Currency::format( $price, $currency ) ); ?></div>
			<button class="hal-cartel-add-to-cart" type="button"><?php esc_html_e( 'Add to cart', 'cartel' ); ?></button>
			<button class="hal-cartel-buy-now" type="button"><?php esc_html_e( 'Buy now', 'cartel' ); ?></button>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Variable products ship their enabled variations as a JSON blob (id/attributes/price/sku/enabled/in_stock/image)
	 * for the storefront JS to match against the shopper's attribute selections — no extra REST round trip needed.
	 */
	protected static function render_variable_product( $id ) {
		$attributes = Hal_Cartel_Product::get_variation_attributes( $id );
		$variations = array();
		foreach ( Hal_Cartel_Product::get_variation_ids( $id ) as $variation_id ) {
			if ( ! Hal_Cartel_Product_Variation::is_enabled( $variation_id ) ) { continue; }
			$variations[] = Hal_Cartel_Product_Variation::to_array( $variation_id );
		}
		$range    = Hal_Cartel_Product::get_price_range( $id );
		$currency = Hal_Cartel_Product::get_currency( $id );

		ob_start(); ?>
		<div class="hal-cartel-product hal-cartel-product--variable" data-product-id="<?php echo esc_attr( $id ); ?>" data-variations="<?php echo esc_attr( wp_json_encode( $variations ) ); ?>" data-currency-format="<?php echo esc_attr( wp_json_encode( array(
			'symbol'   => Hal_Cartel_Currency::symbol( $currency ),
			'position' => Hal_Cartel_Currency::position( $currency ),
			'decimals' => Hal_Cartel_Currency::decimals( $currency ),
		) ) ); ?>">
			<h3 class="hal-cartel-product__title"><?php echo esc_html( get_the_title( $id ) ); ?></h3>
			<div class="hal-cartel-product__price" data-hal-cartel-price>
				<?php
				if ( $range['min'] > 0 && $range['min'] !== $range['max'] ) {
					echo esc_html( Hal_Cartel_Currency::format( $range['min'], $currency ) . ' – ' . Hal_Cartel_Currency::format( $range['max'], $currency ) );
				} else {
					echo esc_html( Hal_Cartel_Currency::format( $range['min'] ?: Hal_Cartel_Product::get_price( $id ), $currency ) );
				}
				?>
			</div>

			<?php if ( $attributes ) : ?>
				<div class="hal-cartel-product__attributes">
					<?php foreach ( $attributes as $attr ) : ?>
						<label class="hal-cartel-product__attribute">
							<?php echo esc_html( $attr['name'] ); ?>
							<select data-hal-cartel-attribute="<?php echo esc_attr( $attr['name'] ); ?>">
								<option value=""><?php esc_html_e( 'Choose an option', 'cartel' ); ?></option>
								<?php foreach ( $attr['values'] as $value ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $value ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<p class="hal-cartel-product__availability" data-hal-cartel-availability hidden></p>
			<input type="hidden" data-hal-cartel-variation-id value="0" />
			<button class="hal-cartel-add-to-cart" type="button" disabled><?php esc_html_e( 'Add to cart', 'cartel' ); ?></button>
			<button class="hal-cartel-buy-now" type="button" disabled><?php esc_html_e( 'Buy now', 'cartel' ); ?></button>
		</div>
		<?php
		return ob_get_clean();
	}

	/** A grouped product is just a curated list of its member products, each with its own Add to cart. */
	protected static function render_grouped_product( $id ) {
		$linked = array();
		foreach ( Hal_Cartel_Product::get_grouped_ids( $id ) as $child_id ) {
			if ( 'publish' === get_post_status( $child_id ) ) { $linked[] = $child_id; }
		}
		if ( ! $linked ) { return ''; }

		ob_start(); ?>
		<div class="hal-cartel-product hal-cartel-product--grouped" data-product-id="<?php echo esc_attr( $id ); ?>">
			<h3 class="hal-cartel-product__title"><?php echo esc_html( get_the_title( $id ) ); ?></h3>
			<ul class="hal-cartel-product__group-list">
				<?php foreach ( $linked as $child_id ) : ?>
					<li class="hal-cartel-product__group-item" data-product-id="<?php echo esc_attr( $child_id ); ?>">
						<span class="hal-cartel-product__group-name"><?php echo esc_html( get_the_title( $child_id ) ); ?></span>
						<span class="hal-cartel-product__group-price"><?php echo esc_html( Hal_Cartel_Currency::format( Hal_Cartel_Product::get_price( $child_id ), Hal_Cartel_Product::get_currency( $child_id ) ) ); ?></span>
						<button class="hal-cartel-add-to-cart" type="button"><?php esc_html_e( 'Add to cart', 'cartel' ); ?></button>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}

	/** External/affiliate products skip Cartel's cart entirely — the button sends shoppers straight to the retailer. */
	protected static function render_external_product( $id ) {
		$url = Hal_Cartel_Product::get_external_url( $id );
		if ( ! $url ) { return ''; }
		$price    = Hal_Cartel_Product::get_price( $id );
		$currency = Hal_Cartel_Product::get_currency( $id );

		ob_start(); ?>
		<div class="hal-cartel-product hal-cartel-product--external" data-product-id="<?php echo esc_attr( $id ); ?>">
			<h3 class="hal-cartel-product__title"><?php echo esc_html( get_the_title( $id ) ); ?></h3>
			<?php if ( $price > 0 ) : ?>
				<div class="hal-cartel-product__price"><?php echo esc_html( Hal_Cartel_Currency::format( $price, $currency ) ); ?></div>
			<?php endif; ?>
			<a class="hal-cartel-buy-now hal-cartel-external-link" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer nofollow">
				<?php echo esc_html( Hal_Cartel_Product::get_external_button_text( $id ) ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function buy_now( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0, 'label' => __( 'Buy now', 'cartel' ) ), $atts );
		$id   = (int) $atts['id'];
		if ( ! $id || get_post_type( $id ) !== Hal_Cartel_Product::POST_TYPE ) { return ''; }

		// External products skip the cart entirely — send shoppers to the retailer instead.
		if ( Hal_Cartel_Product::is_external( $id ) ) {
			$url = Hal_Cartel_Product::get_external_url( $id );
			if ( ! $url ) { return ''; }
			return sprintf(
				'<a class="hal-cartel-buy-now hal-cartel-external-link" href="%s" target="_blank" rel="noopener noreferrer nofollow">%s</a>',
				esc_url( $url ),
				esc_html( $atts['label'] )
			);
		}

		// Variable products need their options picked first — show the full card instead of a bare buy button.
		if ( Hal_Cartel_Product::is_variable( $id ) ) {
			return self::render_variable_product( $id );
		}

		ob_start(); ?>
		<div class="hal-cartel-buy-now-wrap" data-product-id="<?php echo esc_attr( $id ); ?>">
			<button class="hal-cartel-buy-now-inline" type="button"><?php echo esc_html( $atts['label'] ); ?></button>
			<div class="hal-cartel-inline-checkout" hidden></div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function checkout( $atts ) {
		if ( get_option( 'hal_cartel_recaptcha_site_key' ) ) {
			wp_enqueue_script( 'hal-cartel-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true );
		}
		ob_start();
		include HAL_CARTEL_PLUGIN_DIR . 'templates/checkout.php';
		return ob_get_clean();
	}

	/**
	 * Order history. Logged-in shoppers see their own orders by `user_id`; guests
	 * (including those who never created an account, e.g. accounts-at-checkout is off)
	 * get an order-number + email lookup form — same data either way, so digital-goods
	 * re-download and order tracking work without requiring an account.
	 */
	public static function my_orders( $atts ) {
		$orders        = array();
		$looked_up     = false;
		$lookup_error  = '';

		if ( is_user_logged_in() ) {
			$orders = Hal_Cartel_Order::for_user( get_current_user_id() );
		} elseif ( ! empty( $_POST['hal_cartel_order_lookup_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hal_cartel_order_lookup_nonce'] ) ), 'hal_cartel_order_lookup' ) ) {
			$looked_up    = true;
			$order_number = sanitize_text_field( wp_unslash( $_POST['order_number'] ?? '' ) );
			$email        = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
			$order        = ( $order_number && $email ) ? Hal_Cartel_Order::find_by_number_and_email( $order_number, $email ) : null;

			if ( $order ) {
				$orders = array( $order );
			} else {
				$lookup_error = __( 'No matching order found. Please check your order number and email address.', 'cartel' );
			}
		}

		ob_start();
		include HAL_CARTEL_PLUGIN_DIR . 'templates/my-orders.php';
		return ob_get_clean();
	}
}