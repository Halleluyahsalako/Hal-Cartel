<?php
/**
 * One-page checkout template — rendered by [hal_cartel_checkout].
 *
 * @package Cartel
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<form class="hal-cartel-checkout" id="hal-cartel-checkout-form" novalidate>
	<div class="hal-cartel-checkout__grid">
		<section class="hal-cartel-checkout__contact">
			<h2><?php esc_html_e( 'Contact', 'cartel' ); ?></h2>
			<label><?php esc_html_e( 'Email', 'cartel' ); ?>
				<input type="email" name="email" required />
			</label>
		</section>
		<section class="hal-cartel-checkout__shipping">
			<h2><?php esc_html_e( 'Shipping', 'cartel' ); ?></h2>
			<input name="shipping[name]"    placeholder="<?php esc_attr_e( 'Full name', 'cartel' ); ?>" required />
			<input name="shipping[address]" placeholder="<?php esc_attr_e( 'Address', 'cartel' ); ?>" required />
			<input name="shipping[city]"    placeholder="<?php esc_attr_e( 'City', 'cartel' ); ?>" required />
			<input name="shipping[postcode]" placeholder="<?php esc_attr_e( 'Postcode', 'cartel' ); ?>" required />
			<input name="shipping[country]" placeholder="<?php esc_attr_e( 'Country', 'cartel' ); ?>" required />
		</section>
		<aside class="hal-cartel-checkout__summary">
			<h2><?php esc_html_e( 'Your order', 'cartel' ); ?></h2>
			<div data-hal-cartel-cart-summary></div>
			<button type="submit" class="hal-cartel-place-order"><?php esc_html_e( 'Place order', 'cartel' ); ?></button>
		</aside>
	</div>
</form>
