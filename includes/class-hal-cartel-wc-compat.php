<?php
/**
 * WooCommerce-compatible CSV import / export (column header parity).
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_WC_Compat {

	const COLUMNS = array(
		'ID', 'Type', 'SKU', 'Name', 'Published', 'Visibility in catalogue',
		'Short description', 'Description', 'In stock?', 'Stock',
		'Regular price', 'Sale price', 'Categories', 'Tags', 'Images', 'Weight (kg)',
	);

	public static function export_csv() {
		$query = new WP_Query( array( 'post_type' => Hal_Cartel_Product::POST_TYPE, 'posts_per_page' => -1, 'post_status' => 'any' ) );
		$fh = fopen( 'php://output', 'w' );
		fputcsv( $fh, self::COLUMNS );
		foreach ( $query->posts as $p ) {
			$row = array(
				$p->ID, 'simple',
				get_post_meta( $p->ID, '_hal_cartel_sku', true ),
				$p->post_title,
				$p->post_status === 'publish' ? 1 : 0,
				'visible',
				$p->post_excerpt,
				$p->post_content,
				Hal_Cartel_Product::in_stock( $p->ID, 1 ) ? 1 : 0,
				get_post_meta( $p->ID, '_hal_cartel_stock', true ),
				get_post_meta( $p->ID, '_hal_cartel_price', true ),
				get_post_meta( $p->ID, '_hal_cartel_sale_price', true ),
				'', '', get_the_post_thumbnail_url( $p->ID, 'full' ),
				get_post_meta( $p->ID, '_hal_cartel_weight', true ),
			);
			fputcsv( $fh, $row );
		}
		fclose( $fh );
	}

	public static function import_csv( $path ) {
		if ( ! file_exists( $path ) ) { return new WP_Error( 'no_file', 'File missing' ); }
		$fh = fopen( $path, 'r' );
		$headers = fgetcsv( $fh );
		$map = array_flip( $headers );
		$count = 0;
		while ( ( $row = fgetcsv( $fh ) ) !== false ) {
			$post_id = wp_insert_post( array(
				'post_type'    => Hal_Cartel_Product::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $row[ $map['Name'] ] ?? 'Untitled',
				'post_content' => $row[ $map['Description'] ] ?? '',
				'post_excerpt' => $row[ $map['Short description'] ] ?? '',
			) );
			if ( ! $post_id || is_wp_error( $post_id ) ) { continue; }
			update_post_meta( $post_id, '_hal_cartel_sku',   $row[ $map['SKU'] ] ?? '' );
			update_post_meta( $post_id, '_hal_cartel_price', (float) ( $row[ $map['Regular price'] ] ?? 0 ) );
			update_post_meta( $post_id, '_hal_cartel_sale_price', (float) ( $row[ $map['Sale price'] ] ?? 0 ) );
			update_post_meta( $post_id, '_hal_cartel_stock', (int) ( $row[ $map['Stock'] ] ?? 0 ) );
			update_post_meta( $post_id, '_hal_cartel_manage_stock', isset( $row[ $map['Stock'] ] ) && $row[ $map['Stock'] ] !== '' );
			$count++;
		}
		fclose( $fh );
		return $count;
	}
}
