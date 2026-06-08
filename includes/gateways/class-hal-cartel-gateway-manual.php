<?php
/**
 * Manual / Offline payment — cash on delivery, bank transfer, etc. Zero external
 * dependency, so every merchant has a working checkout the moment Cartel activates.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once HAL_CARTEL_PLUGIN_DIR . 'includes/abstracts/class-hal-cartel-gateway.php';

class Hal_Cartel_Gateway_Manual extends Hal_Cartel_Gateway {

	public function id(): string {
		return 'manual';
	}

	public function title(): string {
		return __( 'Manual / Offline payment', 'cartel' );
	}

	public function description(): string {
		return __( 'Cash on delivery, bank transfer, or any payment you collect yourself. The order is placed on hold for you to confirm once payment arrives.', 'cartel' );
	}

	public function settings_schema(): array {
		return array(
			array(
				'key'         => 'instructions',
				'label'       => __( 'Customer instructions', 'cartel' ),
				'type'        => 'textarea',
				'default'     => __( "Thank you for your order! Please transfer the total amount to:\n\nAccount name: \nAccount number: \nBank: \n\nUse your order number as the payment reference.", 'cartel' ),
				'description' => __( 'Shown to the shopper after they place an order, and included in their order email.', 'cartel' ),
			),
			array(
				'key'         => 'auto_confirm_days',
				'label'       => __( 'Auto-confirm after (days)', 'cartel' ),
				'type'        => 'number',
				'default'     => 0,
				'description' => __( 'Optional: automatically move orders from "On hold" to "Processing" after this many days. Leave at 0 to always confirm manually.', 'cartel' ),
				'advanced'    => true,
			),
		);
	}

	public function checkout_fields(): array {
		$settings = Hal_Cartel_Gateways::settings( $this->id() );
		return array(
			array(
				'type'  => 'instructions',
				'key'   => 'instructions',
				'value' => $settings['instructions'] ?? '',
			),
		);
	}

	/**
	 * Nothing to charge — the order simply waits for the merchant to confirm payment
	 * was received by hand. Returns the instructions so the storefront can display them.
	 */
	public function initiate_payment( int $order_id ): array {
		$settings = Hal_Cartel_Gateways::settings( $this->id() );

		Hal_Cartel_Order::set_status( $order_id, 'on-hold' );

		return array(
			'status'       => 'awaiting_confirmation',
			'instructions' => $settings['instructions'] ?? '',
		);
	}
}