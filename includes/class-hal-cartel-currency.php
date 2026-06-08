<?php
/**
 * Currency lookup + formatting — store-wide default with optional per-product overrides.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_Currency {

	/**
	 * code => [ symbol, name, decimals, position ('before'|'after') ].
	 * Covers the currencies merchants most commonly sell in — not an exhaustive ISO 4217 list.
	 */
	protected static function table(): array {
		return array(
			'USD' => array( '$',   'US Dollar',           2, 'before' ),
			'EUR' => array( '€',   'Euro',                2, 'before' ),
			'GBP' => array( '£',   'British Pound',       2, 'before' ),
			'JPY' => array( '¥',   'Japanese Yen',        0, 'before' ),
			'CNY' => array( '¥',   'Chinese Yuan',        2, 'before' ),
			'AUD' => array( '$',   'Australian Dollar',   2, 'before' ),
			'CAD' => array( '$',   'Canadian Dollar',     2, 'before' ),
			'CHF' => array( 'CHF', 'Swiss Franc',         2, 'before' ),
			'HKD' => array( '$',   'Hong Kong Dollar',    2, 'before' ),
			'NZD' => array( '$',   'New Zealand Dollar',  2, 'before' ),
			'SEK' => array( 'kr',  'Swedish Krona',       2, 'after'  ),
			'NOK' => array( 'kr',  'Norwegian Krone',     2, 'after'  ),
			'DKK' => array( 'kr',  'Danish Krone',        2, 'after'  ),
			'PLN' => array( 'zł',  'Polish Złoty',        2, 'after'  ),
			'CZK' => array( 'Kč',  'Czech Koruna',        2, 'after'  ),
			'HUF' => array( 'Ft',  'Hungarian Forint',    0, 'after'  ),
			'RON' => array( 'lei', 'Romanian Leu',        2, 'after'  ),
			'TRY' => array( '₺',   'Turkish Lira',        2, 'before' ),
			'RUB' => array( '₽',   'Russian Ruble',       2, 'after'  ),
			'INR' => array( '₹',   'Indian Rupee',        2, 'before' ),
			'IDR' => array( 'Rp',  'Indonesian Rupiah',   0, 'before' ),
			'MYR' => array( 'RM',  'Malaysian Ringgit',   2, 'before' ),
			'SGD' => array( '$',   'Singapore Dollar',    2, 'before' ),
			'THB' => array( '฿',   'Thai Baht',           2, 'before' ),
			'PHP' => array( '₱',   'Philippine Peso',     2, 'before' ),
			'VND' => array( '₫',   'Vietnamese Đồng',     0, 'after'  ),
			'KRW' => array( '₩',   'South Korean Won',    0, 'before' ),
			'AED' => array( 'د.إ', 'UAE Dirham',          2, 'after'  ),
			'SAR' => array( '﷼',   'Saudi Riyal',         2, 'after'  ),
			'ILS' => array( '₪',   'Israeli New Shekel',  2, 'before' ),
			'ZAR' => array( 'R',   'South African Rand',  2, 'before' ),
			'NGN' => array( '₦',   'Nigerian Naira',      2, 'before' ),
			'EGP' => array( 'E£',  'Egyptian Pound',      2, 'before' ),
			'KES' => array( 'KSh', 'Kenyan Shilling',     2, 'before' ),
			'GHS' => array( '₵',   'Ghanaian Cedi',       2, 'before' ),
			'BRL' => array( 'R$',  'Brazilian Real',      2, 'before' ),
			'MXN' => array( '$',   'Mexican Peso',        2, 'before' ),
			'ARS' => array( '$',   'Argentine Peso',      2, 'before' ),
			'CLP' => array( '$',   'Chilean Peso',        0, 'before' ),
			'COP' => array( '$',   'Colombian Peso',      2, 'before' ),
			'PKR' => array( '₨',   'Pakistani Rupee',     2, 'before' ),
			'BDT' => array( '৳',   'Bangladeshi Taka',    2, 'before' ),
		);
	}

	/** Full lookup table — used to populate currency `<select>` dropdowns. */
	public static function all(): array {
		return self::table();
	}

	/** Resolves a currency code to its row, falling back to USD for anything unknown. */
	protected static function row( $code ): array {
		$table = self::table();
		$code  = strtoupper( (string) $code );
		return $table[ $code ] ?? $table['USD'];
	}

	public static function symbol( $code ): string {
		return self::row( $code )[0];
	}

	public static function decimals( $code ): int {
		return self::row( $code )[2];
	}

	public static function position( $code ): string {
		return self::row( $code )[3];
	}

	/** The store's default currency code, from Settings. */
	public static function default_code(): string {
		return strtoupper( (string) get_option( 'hal_cartel_currency', 'USD' ) );
	}

	/**
	 * Formats an amount with the right symbol, position and decimal precision.
	 * Pass $code to format in a specific currency (e.g. a product's override) — omit it to use the store default.
	 */
	public static function format( $amount, $code = null ): string {
		$code     = $code ?: self::default_code();
		list( $symbol, , $decimals, $position ) = self::row( $code );
		$number = number_format( (float) $amount, $decimals );
		return 'before' === $position ? ( $symbol . $number ) : ( $number . ' ' . $symbol );
	}
}