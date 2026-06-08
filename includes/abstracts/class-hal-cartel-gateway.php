<?php
/**
 * Base class every payment gateway extends — a specialised Hal_Cartel_Extension
 * that additionally knows how to take money and report back on it.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once HAL_CARTEL_PLUGIN_DIR . 'includes/abstracts/class-hal-cartel-extension.php';

abstract class Hal_Cartel_Gateway extends Hal_Cartel_Extension {

	/**
	 * Whether this gateway can be offered for the given currency / cart context.
	 * Default accepts everything; override to restrict by currency, country, amount, etc.
	 */
	public function is_available( string $currency, array $cart_context = array() ): bool {
		$supported = $this->supported_currencies();
		return empty( $supported ) || in_array( strtoupper( $currency ), $supported, true );
	}

	/**
	 * Currency codes this gateway can charge in. Empty array = no restriction.
	 */
	public function supported_currencies(): array {
		return array();
	}

	/**
	 * Field descriptors the storefront renders at the payment step, e.g. a card-element
	 * mount point or plain instructional text. Each item: array( 'type', 'key', ... ).
	 */
	public function checkout_fields(): array {
		return array();
	}

	/**
	 * Starts the payment for an already-persisted order. MUST compute the amount from
	 * the order row itself — never from client input. Returns whatever the storefront
	 * needs to complete payment (e.g. a PaymentIntent client_secret, redirect URL, or
	 * plain instructions for an offline gateway).
	 */
	abstract public function initiate_payment( int $order_id ): array;

	/**
	 * Slug used to build this gateway's webhook route, e.g. 'stripe'. Empty string
	 * means the gateway has no webhook (e.g. Manual/Offline).
	 */
	public function webhook_id(): string {
		return '';
	}

	/**
	 * Handles an inbound webhook for this gateway. Default is a no-op 200 — gateways
	 * that accept webhooks MUST verify the request's signature and fail closed.
	 */
	public function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}
}