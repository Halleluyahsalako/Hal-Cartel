<?php
/**
 * Plugin Name:       Cartel
 * Plugin URI:        https://cartel.example.com
 * Description:       Lightweight, security-conscious eCommerce for WordPress. A free alternative to WooCommerce + paid extensions. One-page checkout, instant Buy Now, stock & order management, shipping/mailing/captcha integrations, WooCommerce-compatible import/export.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Halleluyah
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cartel
 * Domain Path:       /languages
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'HAL_CARTEL_VERSION', '0.1.0' );
define( 'HAL_CARTEL_PLUGIN_FILE', __FILE__ );
define( 'HAL_CARTEL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HAL_CARTEL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once HAL_CARTEL_PLUGIN_DIR . 'includes/abstracts/class-hal-cartel-extension.php';
require_once HAL_CARTEL_PLUGIN_DIR . 'includes/class-hal-cartel.php';
require_once HAL_CARTEL_PLUGIN_DIR . 'includes/class-hal-cartel-install.php';
require_once HAL_CARTEL_PLUGIN_DIR . 'includes/class-hal-cartel-product.php';
require_once HAL_CARTEL_PLUGIN_DIR . 'includes/class-hal-cartel-cart.php';
require_once HAL_CARTEL_PLUGIN_DIR . 'includes/class-hal-cartel-order.php';
require_once HAL_CARTEL_PLUGIN_DIR . 'includes/class-hal-cartel-rest.php';
require_once HAL_CARTEL_PLUGIN_DIR . 'includes/class-hal-cartel-shortcodes.php';
require_once HAL_CARTEL_PLUGIN_DIR . 'includes/class-hal-cartel-wc-compat.php';
require_once HAL_CARTEL_PLUGIN_DIR . 'includes/class-hal-cartel-ui.php';
require_once HAL_CARTEL_PLUGIN_DIR . 'includes/class-hal-cartel-extensions.php';
require_once HAL_CARTEL_PLUGIN_DIR . 'admin/class-hal-cartel-admin.php';

register_activation_hook( __FILE__, array( 'Hal_Cartel_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Hal_Cartel_Install', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Hal_Cartel', 'instance' ) );