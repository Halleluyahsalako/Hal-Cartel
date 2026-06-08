<?php
/**
 * Order history — rendered by [hal_cartel_my_orders].
 * Expects $orders (array of order rows), $looked_up (bool), $lookup_error (string)
 * from Hal_Cartel_Shortcodes::my_orders().
 *
 * @package Cartel
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="hal-cartel-my-orders">

	<?php if ( ! is_user_logged_in() ) : ?>
		<form class="hal-cartel-order-lookup" method="post">
			<h2><?php esc_html_e( 'Look up your order', 'cartel' ); ?></h2>
			<p><?php esc_html_e( "Enter your order number and the email address you used at checkout.", 'cartel' ); ?></p>
			<?php wp_nonce_field( 'hal_cartel_order_lookup', 'hal_cartel_order_lookup_nonce' ); ?>
			<label><?php esc_html_e( 'Order number', 'cartel' ); ?>
				<input type="text" name="order_number" value="<?php echo esc_attr( wp_unslash( $_POST['order_number'] ?? '' ) ); ?>" required />
			</label>
			<label><?php esc_html_e( 'Email', 'cartel' ); ?>
				<input type="email" name="email" value="<?php echo esc_attr( wp_unslash( $_POST['email'] ?? '' ) ); ?>" required />
			</label>
			<button type="submit" class="hal-cartel-place-order"><?php esc_html_e( 'Find my order', 'cartel' ); ?></button>
			<?php if ( $looked_up && $lookup_error ) : ?>
				<p class="hal-cartel-my-orders__error"><?php echo esc_html( $lookup_error ); ?></p>
			<?php endif; ?>
		</form>
	<?php endif; ?>

	<?php if ( $orders ) : ?>
		<h2><?php esc_html_e( 'Your orders', 'cartel' ); ?></h2>
		<ul class="hal-cartel-my-orders__list">
			<?php foreach ( $orders as $order ) : ?>
				<li class="hal-cartel-my-orders__order">
					<div class="hal-cartel-my-orders__order-head">
						<strong>#<?php echo esc_html( $order->order_number ); ?></strong>
						<span class="hal-cartel-my-orders__date"><?php echo esc_html( mysql2date( get_option( 'date_format' ), $order->created_at ) ); ?></span>
						<span class="hal-cartel-my-orders__status"><?php echo esc_html( Hal_Cartel_Order::statuses()[ $order->status ] ?? $order->status ); ?></span>
						<span class="hal-cartel-my-orders__total"><?php echo esc_html( Hal_Cartel_Currency::format( $order->total, $order->currency ) ); ?></span>
					</div>
					<?php if ( 'completed' === $order->status ) :
						$downloads = Hal_Cartel_Downloads::for_order( $order->id );
						if ( $downloads ) : ?>
							<ul class="hal-cartel-my-orders__downloads">
								<?php foreach ( $downloads as $download ) : ?>
									<li><a href="<?php echo esc_url( $download['url'] ); ?>"><?php echo esc_html( $download['name'] ); ?></a></li>
								<?php endforeach; ?>
							</ul>
						<?php endif;
					endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php elseif ( $looked_up && ! $lookup_error ) : ?>
		<p><?php esc_html_e( 'No orders found.', 'cartel' ); ?></p>
	<?php elseif ( is_user_logged_in() ) : ?>
		<p><?php esc_html_e( "You haven't placed any orders yet.", 'cartel' ); ?></p>
	<?php endif; ?>

</div>