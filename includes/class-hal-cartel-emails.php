<?php
/**
 * Lightweight order-lifecycle emails — string-template substitution over wp_mail(),
 * no templating engine. Matches the plugin's "lightweight, no bloat" positioning.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_Emails {

	public static function init() {
		add_action( 'hal_cartel_order_created', array( __CLASS__, 'on_order_created' ), 10, 1 );
		add_action( 'hal_cartel_order_status_changed', array( __CLASS__, 'on_status_changed' ), 10, 3 );
	}

	public static function on_order_created( $order_id ) {
		if ( get_option( 'hal_cartel_email_order_received_enabled', 1 ) ) {
			self::send_order_received( $order_id );
		}
		if ( get_option( 'hal_cartel_email_new_order_alert_enabled', 1 ) ) {
			self::send_new_order_alert( $order_id );
		}
	}

	public static function on_status_changed( $order_id, $status, $previous_status = '' ) {
		// Don't email the shopper about the very first transition out of "pending payment" twice over —
		// the order-received email already told them their order arrived.
		if ( 'pending-payment' === $previous_status && in_array( $status, array( 'on-hold', 'processing' ), true ) ) {
			return;
		}
		if ( get_option( 'hal_cartel_email_status_update_enabled', 1 ) ) {
			self::send_status_update( $order_id, $status );
		}
	}

	/** Order row + line items, or null if not found — the shared data both templates render from. */
	protected static function load_order( $order_id ) {
		global $wpdb;
		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hal_cartel_orders WHERE id = %d", $order_id ) );
		if ( ! $order ) {
			return null;
		}
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hal_cartel_order_items WHERE order_id = %d", $order_id ) );
		return array( 'order' => $order, 'items' => $items );
	}

	protected static function items_list( array $items, $currency ): string {
		$lines = array();
		foreach ( $items as $item ) {
			$lines[] = sprintf( '%d × %s — %s', $item->quantity, $item->name, Hal_Cartel_Currency::format( $item->total, $currency ) );
		}
		return implode( "\n", $lines );
	}

	/** Common placeholder set every order email can use. */
	protected static function order_vars( $order, array $items ): array {
		return array(
			'site_name'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'order_number'   => $order->order_number,
			'order_status'   => self::status_label( $order->status ),
			'email'          => $order->email,
			'items'          => self::items_list( $items, $order->currency ),
			'subtotal'       => Hal_Cartel_Currency::format( $order->subtotal, $order->currency ),
			'shipping'       => Hal_Cartel_Currency::format( $order->shipping, $order->currency ),
			'tax'            => Hal_Cartel_Currency::format( $order->tax, $order->currency ),
			'total'          => Hal_Cartel_Currency::format( $order->total, $order->currency ),
			'payment_method' => $order->payment_method,
			/* Populated by Hal_Cartel_Downloads via the hal_cartel_email_download_links filter when the order contains digital goods and is complete. */
			'download_links' => apply_filters( 'hal_cartel_email_download_links', '', (int) $order->id ),
		);
	}

	protected static function status_label( $status ): string {
		$labels = Hal_Cartel_Order::statuses();
		return $labels[ $status ] ?? ucfirst( str_replace( '-', ' ', $status ) );
	}

	/**
	 * Substitutes {placeholder} tokens in $template with values from $vars.
	 * Unknown placeholders are left as-is so a merchant's typo doesn't silently vanish.
	 */
	public static function render( string $template, array $vars ): string {
		return preg_replace_callback( '/\{([a-z_]+)\}/', function ( $matches ) use ( $vars ) {
			return array_key_exists( $matches[1], $vars ) ? (string) $vars[ $matches[1] ] : $matches[0];
		}, $template );
	}

	/** Plain-text email — keeps the renderer free of HTML-escaping concerns around merchant-filtered templates and download links. */
	protected static function send( $to, string $subject, string $body ) {
		wp_mail( $to, $subject, $body );
	}

	/** Customer-facing "we received your order" email, sent right after checkout. */
	public static function send_order_received( $order_id ) {
		$data = self::load_order( $order_id );
		if ( ! $data ) { return; }
		$vars = self::order_vars( $data['order'], $data['items'] );

		$subject = apply_filters( 'hal_cartel_email_subject_order_received',
			self::render( __( '[{site_name}] We received your order #{order_number}', 'cartel' ), $vars ), $order_id );
		$body = apply_filters( 'hal_cartel_email_template_order_received',
			self::render( self::default_template_order_received(), $vars ), $order_id, $vars );

		self::send( $data['order']->email, $subject, $body );
	}

	/** Merchant-facing "you've got a new order" alert. */
	public static function send_new_order_alert( $order_id ) {
		$data = self::load_order( $order_id );
		if ( ! $data ) { return; }
		$vars = self::order_vars( $data['order'], $data['items'] );

		$to      = get_option( 'hal_cartel_email_admin_address' ) ?: get_option( 'admin_email' );
		$subject = apply_filters( 'hal_cartel_email_subject_new_order_alert',
			self::render( __( '[{site_name}] New order #{order_number} ({total})', 'cartel' ), $vars ), $order_id );
		$body = apply_filters( 'hal_cartel_email_template_new_order_alert',
			self::render( self::default_template_new_order_alert(), $vars ), $order_id, $vars );

		self::send( $to, $subject, $body );
	}

	/** Customer-facing "your order is now {status}" email — includes download links once an order completes. */
	public static function send_status_update( $order_id, $status ) {
		$data = self::load_order( $order_id );
		if ( ! $data ) { return; }
		$vars = self::order_vars( $data['order'], $data['items'] );

		$subject = apply_filters( 'hal_cartel_email_subject_status_update',
			self::render( __( '[{site_name}] Order #{order_number} is now {order_status}', 'cartel' ), $vars ), $order_id, $status );
		$body = apply_filters( 'hal_cartel_email_template_status_update',
			self::render( self::default_template_status_update(), $vars ), $order_id, $vars, $status );

		self::send( $data['order']->email, $subject, $body );
	}

	protected static function default_template_order_received(): string {
		return __(
			"Hi,\n\nThanks for your order from {site_name}!\n\nOrder #{order_number}\n{items}\n\nSubtotal: {subtotal}\nShipping: {shipping}\nTax: {tax}\nTotal: {total}\n\nPayment method: {payment_method}\n\nWe'll let you know as soon as your order ships.",
			'cartel'
		);
	}

	protected static function default_template_new_order_alert(): string {
		return __(
			"New order received on {site_name}.\n\nOrder #{order_number} — {total}\nCustomer: {email}\n\n{items}\n\nPayment method: {payment_method}",
			'cartel'
		);
	}

	protected static function default_template_status_update(): string {
		return __(
			"Hi,\n\nYour order #{order_number} from {site_name} is now: {order_status}.\n\n{items}\n\nTotal: {total}\n{download_links}",
			'cartel'
		);
	}
}