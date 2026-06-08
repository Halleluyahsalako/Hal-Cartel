<?php
/**
 * Shipping zones, methods and rate calculation.
 *
 * A zone groups destination countries; each zone holds an ordered list of methods
 * (flat rate / free shipping / local pickup / weight-based). At checkout the
 * shopper's destination resolves to the first matching zone (or a catch-all zone
 * with no locations), and that zone's enabled methods become the selectable rates.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_Shipping {

	const TYPE_FLAT_RATE     = 'flat_rate';
	const TYPE_FREE_SHIPPING = 'free_shipping';
	const TYPE_LOCAL_PICKUP  = 'local_pickup';
	const TYPE_WEIGHT_BASED  = 'weight_based';

	protected static function zones_table() {
		global $wpdb;
		return $wpdb->prefix . 'hal_cartel_shipping_zones';
	}

	protected static function methods_table() {
		global $wpdb;
		return $wpdb->prefix . 'hal_cartel_shipping_methods';
	}

	/** ISO 3166-1 alpha-2 => name. Common destinations — not the full ISO list. */
	public static function countries(): array {
		return array(
			'US' => __( 'United States', 'cartel' ),
			'CA' => __( 'Canada', 'cartel' ),
			'MX' => __( 'Mexico', 'cartel' ),
			'GB' => __( 'United Kingdom', 'cartel' ),
			'IE' => __( 'Ireland', 'cartel' ),
			'FR' => __( 'France', 'cartel' ),
			'DE' => __( 'Germany', 'cartel' ),
			'ES' => __( 'Spain', 'cartel' ),
			'PT' => __( 'Portugal', 'cartel' ),
			'IT' => __( 'Italy', 'cartel' ),
			'NL' => __( 'Netherlands', 'cartel' ),
			'BE' => __( 'Belgium', 'cartel' ),
			'LU' => __( 'Luxembourg', 'cartel' ),
			'CH' => __( 'Switzerland', 'cartel' ),
			'AT' => __( 'Austria', 'cartel' ),
			'SE' => __( 'Sweden', 'cartel' ),
			'NO' => __( 'Norway', 'cartel' ),
			'DK' => __( 'Denmark', 'cartel' ),
			'FI' => __( 'Finland', 'cartel' ),
			'IS' => __( 'Iceland', 'cartel' ),
			'PL' => __( 'Poland', 'cartel' ),
			'CZ' => __( 'Czechia', 'cartel' ),
			'SK' => __( 'Slovakia', 'cartel' ),
			'HU' => __( 'Hungary', 'cartel' ),
			'RO' => __( 'Romania', 'cartel' ),
			'BG' => __( 'Bulgaria', 'cartel' ),
			'GR' => __( 'Greece', 'cartel' ),
			'TR' => __( 'Turkey', 'cartel' ),
			'RU' => __( 'Russia', 'cartel' ),
			'UA' => __( 'Ukraine', 'cartel' ),
			'EE' => __( 'Estonia', 'cartel' ),
			'LV' => __( 'Latvia', 'cartel' ),
			'LT' => __( 'Lithuania', 'cartel' ),
			'HR' => __( 'Croatia', 'cartel' ),
			'AU' => __( 'Australia', 'cartel' ),
			'NZ' => __( 'New Zealand', 'cartel' ),
			'JP' => __( 'Japan', 'cartel' ),
			'CN' => __( 'China', 'cartel' ),
			'HK' => __( 'Hong Kong', 'cartel' ),
			'TW' => __( 'Taiwan', 'cartel' ),
			'KR' => __( 'South Korea', 'cartel' ),
			'SG' => __( 'Singapore', 'cartel' ),
			'MY' => __( 'Malaysia', 'cartel' ),
			'TH' => __( 'Thailand', 'cartel' ),
			'VN' => __( 'Vietnam', 'cartel' ),
			'PH' => __( 'Philippines', 'cartel' ),
			'ID' => __( 'Indonesia', 'cartel' ),
			'IN' => __( 'India', 'cartel' ),
			'PK' => __( 'Pakistan', 'cartel' ),
			'BD' => __( 'Bangladesh', 'cartel' ),
			'AE' => __( 'United Arab Emirates', 'cartel' ),
			'SA' => __( 'Saudi Arabia', 'cartel' ),
			'IL' => __( 'Israel', 'cartel' ),
			'EG' => __( 'Egypt', 'cartel' ),
			'ZA' => __( 'South Africa', 'cartel' ),
			'NG' => __( 'Nigeria', 'cartel' ),
			'KE' => __( 'Kenya', 'cartel' ),
			'GH' => __( 'Ghana', 'cartel' ),
			'BR' => __( 'Brazil', 'cartel' ),
			'AR' => __( 'Argentina', 'cartel' ),
			'CL' => __( 'Chile', 'cartel' ),
			'CO' => __( 'Colombia', 'cartel' ),
			'PE' => __( 'Peru', 'cartel' ),
		);
	}

	/** All known method types and their customer-facing labels — drives the admin type selector. */
	public static function method_types(): array {
		return array(
			self::TYPE_FLAT_RATE     => __( 'Flat rate', 'cartel' ),
			self::TYPE_FREE_SHIPPING => __( 'Free shipping', 'cartel' ),
			self::TYPE_LOCAL_PICKUP  => __( 'Local pickup', 'cartel' ),
			self::TYPE_WEIGHT_BASED  => __( 'Weight-based', 'cartel' ),
		);
	}

	/** Every zone with its methods attached, ordered for matching/display. */
	public static function get_zones(): array {
		global $wpdb;
		$zones = $wpdb->get_results( "SELECT * FROM " . self::zones_table() . " ORDER BY zone_order ASC, id ASC" );
		$out   = array();
		foreach ( $zones as $zone ) {
			$out[] = self::shape_zone( $zone );
		}
		return $out;
	}

	public static function get_zone( $zone_id ) {
		global $wpdb;
		$zone = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::zones_table() . " WHERE id = %d", (int) $zone_id ) );
		return $zone ? self::shape_zone( $zone ) : null;
	}

	protected static function shape_zone( $row ): array {
		global $wpdb;
		$locations = json_decode( (string) $row->locations, true );
		$methods   = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . self::methods_table() . " WHERE zone_id = %d ORDER BY method_order ASC, id ASC",
			(int) $row->id
		) );

		$out_methods = array();
		foreach ( $methods as $m ) {
			$out_methods[] = array(
				'id'       => (int) $m->id,
				'type'     => (string) $m->type,
				'title'    => (string) $m->title,
				'enabled'  => (bool) $m->enabled,
				'settings' => json_decode( (string) $m->settings, true ) ?: array(),
			);
		}

		return array(
			'id'        => (int) $row->id,
			'name'      => (string) $row->name,
			'order'     => (int) $row->zone_order,
			'locations' => is_array( $locations ) ? array_values( array_filter( array_map( 'strtoupper', $locations ) ) ) : array(),
			'methods'   => $out_methods,
		);
	}

	/**
	 * Whole-form replace: deletes zones/methods that are no longer present, upserts the rest.
	 * Mirrors Hal_Cartel_Admin::sync_variations()'s create/update/delete-leftovers flow.
	 */
	public static function save_zones( array $zones ) {
		global $wpdb;
		$zones_table   = self::zones_table();
		$methods_table = self::methods_table();

		$kept_zone_ids = array();

		foreach ( $zones as $index => $zone ) {
			$name      = sanitize_text_field( $zone['name'] ?? '' );
			if ( '' === $name ) { continue; }

			$locations = array();
			if ( ! empty( $zone['locations'] ) && is_array( $zone['locations'] ) ) {
				$known     = array_keys( self::countries() );
				$locations = array_values( array_intersect( array_map( 'strtoupper', array_map( 'sanitize_text_field', $zone['locations'] ) ), $known ) );
			}

			$row = array(
				'name'       => $name,
				'zone_order' => (int) $index,
				'locations'  => wp_json_encode( $locations ),
			);

			$zone_id = isset( $zone['id'] ) ? (int) $zone['id'] : 0;
			if ( $zone_id && self::get_zone( $zone_id ) ) {
				$wpdb->update( $zones_table, $row, array( 'id' => $zone_id ) );
			} else {
				$wpdb->insert( $zones_table, $row );
				$zone_id = (int) $wpdb->insert_id;
			}
			$kept_zone_ids[] = $zone_id;

			self::save_methods( $zone_id, is_array( $zone['methods'] ?? null ) ? $zone['methods'] : array() );
		}

		// Remove zones (and their methods) that were dropped from the form.
		$existing_ids = array_map( 'intval', $wpdb->get_col( "SELECT id FROM {$zones_table}" ) );
		foreach ( array_diff( $existing_ids, $kept_zone_ids ) as $stale_id ) {
			$wpdb->delete( $methods_table, array( 'zone_id' => $stale_id ) );
			$wpdb->delete( $zones_table, array( 'id' => $stale_id ) );
		}

		return self::get_zones();
	}

	protected static function save_methods( $zone_id, array $methods ) {
		global $wpdb;
		$methods_table = self::methods_table();
		$known_types   = array_keys( self::method_types() );
		$kept_ids      = array();

		foreach ( $methods as $index => $method ) {
			$type = sanitize_text_field( $method['type'] ?? '' );
			if ( ! in_array( $type, $known_types, true ) ) { continue; }

			$row = array(
				'zone_id'      => (int) $zone_id,
				'type'         => $type,
				'title'        => sanitize_text_field( $method['title'] ?? self::method_types()[ $type ] ),
				'enabled'      => empty( $method['enabled'] ) ? 0 : 1,
				'method_order' => (int) $index,
				'settings'     => wp_json_encode( self::sanitize_method_settings( $type, is_array( $method['settings'] ?? null ) ? $method['settings'] : array() ) ),
			);

			$method_id = isset( $method['id'] ) ? (int) $method['id'] : 0;
			if ( $method_id && (int) $wpdb->get_var( $wpdb->prepare( "SELECT zone_id FROM {$methods_table} WHERE id = %d", $method_id ) ) === (int) $zone_id ) {
				$wpdb->update( $methods_table, $row, array( 'id' => $method_id ) );
			} else {
				$wpdb->insert( $methods_table, $row );
				$method_id = (int) $wpdb->insert_id;
			}
			$kept_ids[] = $method_id;
		}

		$existing_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$methods_table} WHERE zone_id = %d", (int) $zone_id ) ) );
		foreach ( array_diff( $existing_ids, $kept_ids ) as $stale_id ) {
			$wpdb->delete( $methods_table, array( 'id' => $stale_id ) );
		}
	}

	protected static function sanitize_method_settings( $type, array $settings ): array {
		switch ( $type ) {
			case self::TYPE_FLAT_RATE:
				$class_costs = array();
				if ( ! empty( $settings['class_costs'] ) && is_array( $settings['class_costs'] ) ) {
					foreach ( $settings['class_costs'] as $term_id => $cost ) {
						$class_costs[ (int) $term_id ] = (float) $cost;
					}
				}
				return array(
					'base_cost'   => (float) ( $settings['base_cost'] ?? 0 ),
					'class_costs' => $class_costs,
				);

			case self::TYPE_FREE_SHIPPING:
				return array( 'min_order_amount' => (float) ( $settings['min_order_amount'] ?? 0 ) );

			case self::TYPE_LOCAL_PICKUP:
				return array( 'cost' => (float) ( $settings['cost'] ?? 0 ) );

			case self::TYPE_WEIGHT_BASED:
				$brackets = array();
				if ( ! empty( $settings['brackets'] ) && is_array( $settings['brackets'] ) ) {
					foreach ( $settings['brackets'] as $bracket ) {
						if ( ! is_array( $bracket ) ) { continue; }
						$brackets[] = array(
							'up_to' => isset( $bracket['up_to'] ) && '' !== $bracket['up_to'] ? (float) $bracket['up_to'] : null,
							'cost'  => (float) ( $bracket['cost'] ?? 0 ),
						);
					}
				}
				return array( 'brackets' => $brackets );
		}
		return array();
	}

	public static function delete_zone( $zone_id ) {
		global $wpdb;
		$zone_id = (int) $zone_id;
		$wpdb->delete( self::methods_table(), array( 'zone_id' => $zone_id ) );
		$wpdb->delete( self::zones_table(), array( 'id' => $zone_id ) );
	}

	/**
	 * First zone (in order) whose locations include the destination country, or the
	 * first catch-all zone (empty locations = "Rest of the world"). Null if nothing matches.
	 */
	public static function match_zone( $country_code, $state = '' ) {
		$country_code = strtoupper( sanitize_text_field( (string) $country_code ) );
		$catch_all    = null;

		foreach ( self::get_zones() as $zone ) {
			if ( empty( $zone['locations'] ) ) {
				if ( null === $catch_all ) { $catch_all = $zone; }
				continue;
			}
			if ( in_array( $country_code, $zone['locations'], true ) ) {
				return $zone;
			}
		}
		return $catch_all;
	}

	/** Total cart weight = Σ (product/variation weight × line quantity). */
	protected static function cart_weight( array $cart_summary ): float {
		$total = 0.0;
		foreach ( $cart_summary['items'] ?? array() as $item ) {
			$weight_id = ! empty( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'];
			$weight    = (float) get_post_meta( $weight_id, '_hal_cartel_weight', true );
			$total    += $weight * (int) $item['quantity'];
		}
		return $total;
	}

	/** Quantities of cart items that carry a given shipping-class term, keyed by term_id. */
	protected static function class_quantities( array $cart_summary ): array {
		$out = array();
		foreach ( $cart_summary['items'] ?? array() as $item ) {
			$term = Hal_Cartel_Product::get_shipping_class( $item['product_id'] );
			if ( ! $term ) { continue; }
			$out[ $term->term_id ] = ( $out[ $term->term_id ] ?? 0 ) + (int) $item['quantity'];
		}
		return $out;
	}

	/**
	 * Enabled methods of $zone_id as selectable rates: [ { id, type, title, cost } ].
	 * Methods that aren't applicable to this cart (e.g. free shipping below the threshold) are omitted.
	 */
	public static function get_rates( $zone_id, array $cart_summary ): array {
		$zone = self::get_zone( $zone_id );
		if ( ! $zone ) { return array(); }

		$subtotal = (float) ( $cart_summary['subtotal'] ?? 0 );
		$rates    = array();

		foreach ( $zone['methods'] as $method ) {
			if ( empty( $method['enabled'] ) ) { continue; }
			$settings = $method['settings'];
			$cost     = null;

			switch ( $method['type'] ) {
				case self::TYPE_FLAT_RATE:
					$cost = (float) ( $settings['base_cost'] ?? 0 );
					$class_costs = $settings['class_costs'] ?? array();
					if ( $class_costs ) {
						foreach ( self::class_quantities( $cart_summary ) as $term_id => $qty ) {
							if ( isset( $class_costs[ $term_id ] ) ) {
								$cost += (float) $class_costs[ $term_id ] * $qty;
							}
						}
					}
					break;

				case self::TYPE_FREE_SHIPPING:
					$minimum = (float) ( $settings['min_order_amount'] ?? 0 );
					if ( $minimum > 0 && $subtotal < $minimum ) { continue 2; }
					$cost = 0.0;
					break;

				case self::TYPE_LOCAL_PICKUP:
					$cost = (float) ( $settings['cost'] ?? 0 );
					break;

				case self::TYPE_WEIGHT_BASED:
					$brackets = $settings['brackets'] ?? array();
					if ( ! $brackets ) { continue 2; }
					$weight = self::cart_weight( $cart_summary );
					foreach ( $brackets as $bracket ) {
						if ( null === $bracket['up_to'] || $weight <= (float) $bracket['up_to'] ) {
							$cost = (float) $bracket['cost'];
							break;
						}
					}
					if ( null === $cost ) { continue 2; } // weight exceeded every bracket — no applicable rate
					break;
			}

			if ( null === $cost ) { continue; }
			$rates[] = array(
				'id'    => $method['id'],
				'type'  => $method['type'],
				'title' => $method['title'],
				'cost'  => round( $cost, 2 ),
			);
		}

		return $rates;
	}
}