<?php
/**
 * Base class every official/third-party Cartel extension extends.
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

abstract class Hal_Cartel_Extension {

	/**
	 * Unique slug, e.g. 'abandoned-cart-recovery'.
	 */
	abstract public function id(): string;

	abstract public function title(): string;

	abstract public function description(): string;

	/**
	 * Settings fields rendered by Hal_Cartel_Extensions on the shared shell.
	 * Each item: array( 'key', 'label', 'type' => text|checkbox|number|select,
	 *   'default', 'description', 'options' (for select), 'advanced' => bool ).
	 * Fields marked 'advanced' are wrapped in Hal_Cartel_UI::advanced() (see §4).
	 */
	public function settings_schema(): array {
		return array();
	}

	public function on_activate(): void {}

	public function on_deactivate(): void {}
}
