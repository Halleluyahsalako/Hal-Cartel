<?php
/**
 * Digital-goods fulfillment: signed, expiring download tokens granted on order
 * completion and redeemed through a public, account-free streaming endpoint.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_Downloads {

	const QUERY_VAR = 'hal_cartel_download';

	public static function init() {
		add_action( 'hal_cartel_order_status_changed', array( __CLASS__, 'on_status_changed' ), 5, 2 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ) );
		add_filter( 'hal_cartel_email_download_links', array( __CLASS__, 'render_email_links' ), 10, 2 );
	}

	public static function register_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function on_status_changed( $order_id, $status ) {
		if ( 'completed' === $status ) {
			self::grant_for_order( $order_id );
		}
	}

	/** Generates one token per downloadable line item on an order — a no-op for items that already have one (replay-safe, e.g. status bounces back and forth). */
	public static function grant_for_order( $order_id ) {
		global $wpdb;
		$items_table     = $wpdb->prefix . 'hal_cartel_order_items';
		$downloads_table = $wpdb->prefix . 'hal_cartel_downloads';
		$now             = current_time( 'mysql' );

		$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$items_table} WHERE order_id = %d", $order_id ) );

		foreach ( $items as $item ) {
			if ( ! Hal_Cartel_Product::is_downloadable( $item->product_id ) ) {
				continue;
			}

			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$downloads_table} WHERE order_item_id = %d", $item->id
			) );
			if ( $existing ) {
				continue;
			}

			$limit  = (int) get_post_meta( $item->product_id, '_hal_cartel_download_limit', true );
			$expiry = (int) get_post_meta( $item->product_id, '_hal_cartel_download_expiry', true );

			$wpdb->insert( $downloads_table, array(
				'order_id'      => $order_id,
				'order_item_id' => $item->id,
				'product_id'    => $item->product_id,
				'variation_id'  => $item->variation_id ?: null,
				'token'         => wp_generate_password( 32, false ),
				'download_count' => 0,
				'max_downloads' => $limit > 0 ? $limit : null,
				'expires_at'    => $expiry > 0 ? gmdate( 'Y-m-d H:i:s', time() + $expiry * DAY_IN_SECONDS ) : null,
				'created_at'    => $now,
			) );
		}
	}

	/** This order's granted downloads as `{name, url}` pairs — shared by the email-links filter and [hal_cartel_my_orders]. */
	public static function for_order( $order_id ): array {
		global $wpdb;
		$downloads_table = $wpdb->prefix . 'hal_cartel_downloads';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT d.token, i.name FROM {$downloads_table} d INNER JOIN {$wpdb->prefix}hal_cartel_order_items i ON i.id = d.order_item_id WHERE d.order_id = %d",
			$order_id
		) );
		return array_map( function ( $row ) {
			return array( 'name' => $row->name, 'url' => self::download_url( $row->token ) );
		}, $rows );
	}

	/** Plain-text URLs — one per line, matching the plain-text email convention (no HTML anchors to escape). */
	public static function render_email_links( $links, $order_id ): string {
		$rows = self::for_order( $order_id );
		if ( ! $rows ) {
			return $links;
		}

		$lines = array();
		foreach ( $rows as $row ) {
			$lines[] = sprintf( '%s: %s', $row['name'], $row['url'] );
		}
		return implode( "\n", $lines );
	}

	public static function download_url( string $token ): string {
		return add_query_arg( self::QUERY_VAR, $token, home_url( '/' ) );
	}

	/** Validates the token from the request URL and streams the file, or shows a friendly error — runs on every front-end request, so bail immediately when the query var is absent. */
	public static function maybe_serve() {
		$token = get_query_var( self::QUERY_VAR );
		if ( ! $token ) {
			return;
		}
		self::serve( sanitize_text_field( $token ) );
		exit;
	}

	public static function serve( string $token ) {
		global $wpdb;
		$downloads_table = $wpdb->prefix . 'hal_cartel_downloads';

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$downloads_table} WHERE token = %s", $token ) );
		if ( ! $row ) {
			wp_die( esc_html__( 'This download link is invalid.', 'cartel' ), '', array( 'response' => 404 ) );
		}
		if ( $row->expires_at && strtotime( $row->expires_at ) < time() ) {
			wp_die( esc_html__( 'This download link has expired.', 'cartel' ), '', array( 'response' => 410 ) );
		}
		if ( $row->max_downloads && (int) $row->download_count >= (int) $row->max_downloads ) {
			wp_die( esc_html__( 'This download link has reached its download limit.', 'cartel' ), '', array( 'response' => 410 ) );
		}

		$file_id   = Hal_Cartel_Product::get_download_file_id( $row->product_id );
		$file_path = $file_id ? get_attached_file( $file_id ) : false;
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'The file for this download is no longer available. Please contact the store.', 'cartel' ), '', array( 'response' => 404 ) );
		}

		$wpdb->update( $downloads_table,
			array( 'download_count' => (int) $row->download_count + 1 ),
			array( 'id' => (int) $row->id )
		);

		nocache_headers();
		header( 'Content-Type: ' . ( wp_check_filetype( $file_path )['type'] ?: 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . basename( $file_path ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		readfile( $file_path );
	}
}