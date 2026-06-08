<?php
/**
 * Thin Mailchimp Lists API wrapper — fire-and-forget newsletter opt-in.
 * Never blocks or fails checkout: failures are logged via a filterable action only.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_Mailchimp {

	public static function is_configured(): bool {
		return (bool) ( get_option( 'hal_cartel_mailchimp_api_key' ) && get_option( 'hal_cartel_mailchimp_list_id' ) );
	}

	/** Subscribes an email to the configured list. Fire-and-forget: errors are reported via the `hal_cartel_mailchimp_error` action, never thrown. */
	public static function subscribe( string $email ) {
		if ( ! self::is_configured() ) {
			return;
		}

		$api_key = get_option( 'hal_cartel_mailchimp_api_key' );
		$list_id = get_option( 'hal_cartel_mailchimp_list_id' );

		// The API key encodes its data center as a "-usX" suffix — Mailchimp's standard convention.
		$parts = explode( '-', $api_key );
		$dc    = end( $parts );
		if ( ! $dc || $dc === $api_key ) {
			do_action( 'hal_cartel_mailchimp_error', 'invalid_api_key', $email );
			return;
		}

		$response = wp_remote_post( "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/members", array(
			'timeout' => 10,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'anystring:' . $api_key ),
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'email_address' => $email,
				'status'        => 'subscribed',
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			do_action( 'hal_cartel_mailchimp_error', $response->get_error_message(), $email );
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		// "Member Exists" is an expected outcome for repeat subscribers, not an error worth reporting.
		if ( $code >= 400 && ( $body['title'] ?? '' ) !== 'Member Exists' ) {
			do_action( 'hal_cartel_mailchimp_error', wp_remote_retrieve_body( $response ), $email );
		}
	}
}