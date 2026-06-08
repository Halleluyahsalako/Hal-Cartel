<?php
/**
 * Webhook idempotency ledger — payment providers retry delivery, so every
 * webhook handler must insert-or-noop on the provider's unique event id
 * before acting. A second delivery of the same event becomes a silent no-op.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_Payment_Events {

	/**
	 * Records a webhook event if it hasn't been seen before.
	 * @return bool true if this is the first time we've recorded this event id (caller should act on it),
	 *              false if it's a replay (caller should no-op).
	 */
	public static function record( string $gateway, string $event_id, string $type, int $order_id = 0 ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'hal_cartel_payment_events';

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE event_id = %s", $event_id ) );
		if ( $existing ) {
			return false;
		}

		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$table} (gateway, event_id, order_id, type, created_at) VALUES (%s, %s, %d, %s, %s)",
			$gateway,
			$event_id,
			$order_id,
			$type,
			current_time( 'mysql' )
		) );

		// INSERT IGNORE silently no-ops on a unique-key collision from a concurrent retry — treat that as "already seen".
		return (bool) $wpdb->rows_affected;
	}
}