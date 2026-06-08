<?php
/**
 * One-page checkout template — rendered by [hal_cartel_checkout].
 *
 * @package Cartel
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<?php $cart_needs_shipping = Hal_Cartel_Cart::needs_shipping(); ?>
<form class="hal-cartel-checkout" id="hal-cartel-checkout-form" novalidate
	data-hal-cartel-needs-shipping="<?php echo $cart_needs_shipping ? '1' : '0'; ?>">
	<div class="hal-cartel-checkout__grid">
		<section class="hal-cartel-checkout__contact">
			<h2><?php esc_html_e( 'Contact', 'cartel' ); ?></h2>
			<label><?php esc_html_e( 'Email', 'cartel' ); ?>
				<input type="email" name="email" required />
			</label>
		</section>
		<section class="hal-cartel-checkout__shipping"<?php echo $cart_needs_shipping ? '' : ' hidden'; ?>>
			<h2><?php esc_html_e( 'Shipping', 'cartel' ); ?></h2>
			<input name="shipping[name]"    placeholder="<?php esc_attr_e( 'Full name', 'cartel' ); ?>" />
			<input name="shipping[address]" placeholder="<?php esc_attr_e( 'Address', 'cartel' ); ?>" />
			<input name="shipping[city]"    placeholder="<?php esc_attr_e( 'City', 'cartel' ); ?>" />
			<input name="shipping[postcode]" placeholder="<?php esc_attr_e( 'Postcode', 'cartel' ); ?>" />
			<select name="shipping[country]" data-hal-cartel-shipping-country>
				<option value=""><?php esc_html_e( 'Select a country…', 'cartel' ); ?></option>
				<?php foreach ( Hal_Cartel_Shipping::countries() as $code => $name ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?></option>
				<?php endforeach; ?>
			</select>

			<h3><?php esc_html_e( 'Shipping method', 'cartel' ); ?></h3>
			<div class="hal-cartel-checkout__shipping-rates" data-hal-cartel-shipping-rates>
				<p class="hal-cartel-checkout__shipping-hint"><?php esc_html_e( 'Enter your destination country to see available shipping methods.', 'cartel' ); ?></p>
			</div>
		</section>
		<section class="hal-cartel-checkout__payment">
			<h2><?php esc_html_e( 'Payment', 'cartel' ); ?></h2>
			<div class="hal-cartel-checkout__payment-methods" data-hal-cartel-payment-methods>
				<p class="hal-cartel-checkout__shipping-hint"><?php esc_html_e( 'Loading payment methods…', 'cartel' ); ?></p>
			</div>
		</section>
		<aside class="hal-cartel-checkout__summary">
			<h2><?php esc_html_e( 'Your order', 'cartel' ); ?></h2>
			<div data-hal-cartel-cart-summary></div>

			<?php if ( get_option( 'hal_cartel_mailchimp_api_key' ) && get_option( 'hal_cartel_mailchimp_list_id' ) ) : ?>
				<label class="hal-cartel-checkout__opt-in">
					<input type="checkbox" name="mailchimp_opt_in" value="1" />
					<?php esc_html_e( 'Subscribe me to the newsletter', 'cartel' ); ?>
				</label>
			<?php endif; ?>

			<?php if ( get_option( 'hal_cartel_recaptcha_site_key' ) ) : ?>
				<div class="g-recaptcha" data-sitekey="<?php echo esc_attr( get_option( 'hal_cartel_recaptcha_site_key' ) ); ?>" data-hal-cartel-recaptcha></div>
			<?php endif; ?>

			<button type="submit" class="hal-cartel-place-order" data-hal-cartel-place-order disabled><?php esc_html_e( 'Place order', 'cartel' ); ?></button>
		</aside>
	</div>
</form>
