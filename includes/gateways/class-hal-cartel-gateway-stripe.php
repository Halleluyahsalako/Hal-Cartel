<?php
/**
 * Stripe — card payments via PaymentIntents. No Stripe SDK/Composer dependency
 * (consistent with the plugin's zero-bloat, no-vendor-dir posture): talks to
 * Stripe's REST API directly with wp_remote_post(), and verifies webhooks by
 * hand-rolling the same HMAC-SHA256 construction Stripe's own libraries use.
 *
 * PCI scope: card data never touches this server. The client confirms the
 * PaymentIntent with Stripe.js/Elements; we only ever see IDs and tokens.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once HAL_CARTEL_PLUGIN_DIR . 'includes/abstracts/class-hal-cartel-gateway.php';

class Hal_Cartel_Gateway_Stripe extends Hal_Cartel_Gateway {

	const API_BASE = 'https://api.stripe.com/v1';

	public function id(): string {
		return 'stripe';
	}

	public function title(): string {
		return __( 'Stripe', 'cartel' );
	}

	public function description(): string {
		return __( 'Accept card payments worldwide via Stripe. Card details are entered into Stripe\'s own secure form and never touch your server.', 'cartel' );
	}

	public function settings_schema(): array {
		return array(
			array(
				'key'         => 'test_mode',
				'label'       => __( 'Test mode', 'cartel' ),
				'type'        => 'checkbox',
				'default'     => 1,
				'description' => __( 'Use your Stripe test-mode keys while you set things up. Switch off to take real payments.', 'cartel' ),
			),
			array(
				'key'         => 'publishable_key',
				'label'       => __( 'Publishable key', 'cartel' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'From your Stripe dashboard → Developers → API keys.', 'cartel' ),
			),
			array(
				'key'         => 'secret_key',
				'label'       => __( 'Secret key', 'cartel' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'Kept server-side only — never sent to the browser.', 'cartel' ),
				'advanced'    => true,
			),
			array(
				'key'         => 'webhook_secret',
				'label'       => __( 'Webhook signing secret', 'cartel' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'From the webhook endpoint you create in Stripe pointing at the URL shown below. Required — without it, incoming payment confirmations are rejected.', 'cartel' ),
				'advanced'    => true,
			),
		);
	}

	/**
	 * Currencies Stripe can charge in (its "three-letter ISO currency code" list,
	 * trimmed to the codes Hal_Cartel_Currency already knows how to format/display).
	 */
	public function supported_currencies(): array {
		return array(
			'USD', 'EUR', 'GBP', 'JPY', 'CNY', 'AUD', 'CAD', 'CHF', 'HKD', 'NZD',
			'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON', 'TRY', 'INR', 'IDR',
			'MYR', 'SGD', 'THB', 'PHP', 'KRW', 'AED', 'SAR', 'ILS', 'ZAR', 'NGN',
			'EGP', 'KES', 'GHS', 'BRL', 'MXN', 'ARS', 'CLP', 'COP', 'PKR', 'BDT',
		);
	}

	/** Webhook URL the merchant pastes into their Stripe dashboard. */
	public function webhook_url(): string {
		return rest_url( 'hal-cartel/v1/payment/webhook/' . $this->webhook_id() );
	}

	public function checkout_fields(): array {
		$settings = Hal_Cartel_Gateways::settings( $this->id() );
		return array(
			array(
				'type'            => 'card_element',
				'key'             => 'stripe_card',
				'publishable_key' => $settings['publishable_key'] ?? '',
			),
		);
	}

	public function on_activate(): void {
		// Nudge the merchant to finish setup — keys are required before this gateway can actually charge anyone.
	}

	private function secret_key(): string {
		$settings = Hal_Cartel_Gateways::settings( $this->id() );
		return trim( (string) ( $settings['secret_key'] ?? '' ) );
	}

	/**
	 * Creates a PaymentIntent for an already-persisted order. The amount is computed
	 * from the order row itself (never client input) and converted to Stripe's minor
	 * unit using the currency's known decimal precision.
	 */
	public function initiate_payment( int $order_id ): array {
		$secret_key = $this->secret_key();
		if ( ! $secret_key ) {
			return array( 'error' => __( 'Stripe is not fully configured yet. Please choose another payment method.', 'cartel' ) );
		}

		global $wpdb;
		$order = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, total, currency FROM {$wpdb->prefix}hal_cartel_orders WHERE id = %d",
			$order_id
		) );
		if ( ! $order ) {
			return array( 'error' => __( 'Order not found.', 'cartel' ) );
		}

		$currency = strtolower( $order->currency );
		$decimals = Hal_Cartel_Currency::decimals( $order->currency );
		$amount   = (int) round( (float) $order->total * ( 10 ** $decimals ) );

		$response = wp_remote_post( self::API_BASE . '/payment_intents', array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $secret_key,
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body' => array(
				'amount'                 => $amount,
				'currency'               => $currency,
				'metadata[hal_cartel_order_id]' => (string) $order_id,
				'automatic_payment_methods[enabled]' => 'true',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['client_secret'] ) ) {
			$message = $body['error']['message'] ?? __( 'Stripe could not start this payment. Please try again.', 'cartel' );
			return array( 'error' => $message );
		}

		$wpdb->update( $wpdb->prefix . 'hal_cartel_orders',
			array( 'payment_ref' => sanitize_text_field( $body['id'] ?? '' ), 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $order_id )
		);

		return array(
			'status'        => 'requires_confirmation',
			'client_secret' => $body['client_secret'],
			'publishable_key' => Hal_Cartel_Gateways::settings( $this->id() )['publishable_key'] ?? '',
		);
	}

	public function webhook_id(): string {
		return 'stripe';
	}

	/**
	 * Verifies the Stripe-Signature header against the RAW request body using the
	 * configured webhook secret, then dispatches recognised event types. Fails
	 * closed: any verification problem returns 400 and changes nothing.
	 */
	public function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
		$settings       = Hal_Cartel_Gateways::settings( $this->id() );
		$webhook_secret = trim( (string) ( $settings['webhook_secret'] ?? '' ) );
		if ( ! $webhook_secret ) {
			return new WP_REST_Response( array( 'error' => 'webhook_not_configured' ), 400 );
		}

		$payload   = $request->get_body();
		$sig_header = $request->get_header( 'stripe_signature' );
		if ( ! $payload || ! $sig_header || ! $this->verify_signature( $payload, $sig_header, $webhook_secret ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_signature' ), 400 );
		}

		$event = json_decode( $payload, true );
		if ( empty( $event['id'] ) || empty( $event['type'] ) ) {
			return new WP_REST_Response( array( 'error' => 'malformed_event' ), 400 );
		}

		// Idempotency: Stripe retries webhook delivery — insert-or-noop on the unique event id before acting.
		if ( ! Hal_Cartel_Payment_Events::record( 'stripe', $event['id'], $event['type'], $this->order_id_from_event( $event ) ) ) {
			return new WP_REST_Response( array( 'received' => true, 'duplicate' => true ), 200 );
		}

		switch ( $event['type'] ) {
			case 'payment_intent.succeeded':
				$order_id = $this->order_id_from_event( $event );
				if ( $order_id ) {
					Hal_Cartel_Order::set_status( $order_id, 'processing' );
				}
				break;

			case 'payment_intent.payment_failed':
				$order_id = $this->order_id_from_event( $event );
				if ( $order_id ) {
					Hal_Cartel_Order::restock( $order_id );
					Hal_Cartel_Order::set_status( $order_id, 'failed' );
				}
				break;
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	private function order_id_from_event( array $event ): int {
		$metadata = $event['data']['object']['metadata'] ?? array();
		return isset( $metadata['hal_cartel_order_id'] ) ? (int) $metadata['hal_cartel_order_id'] : 0;
	}

	/**
	 * Hand-rolled equivalent of Stripe\Webhook::constructEvent's signature check:
	 * the header is "t=<timestamp>,v1=<hex hmac>[,v1=<hex hmac>...]"; the signed
	 * payload is "<timestamp>.<raw body>", HMAC-SHA256'd with the webhook secret.
	 */
	private function verify_signature( string $payload, string $sig_header, string $secret ): bool {
		$parts = array();
		foreach ( explode( ',', $sig_header ) as $pair ) {
			$pair = explode( '=', trim( $pair ), 2 );
			if ( 2 === count( $pair ) ) {
				$parts[ $pair[0] ][] = $pair[1];
			}
		}

		if ( empty( $parts['t'][0] ) || empty( $parts['v1'] ) ) {
			return false;
		}

		$timestamp = $parts['t'][0];
		$expected  = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );

		foreach ( $parts['v1'] as $candidate ) {
			if ( hash_equals( $expected, $candidate ) ) {
				return true;
			}
		}
		return false;
	}
}