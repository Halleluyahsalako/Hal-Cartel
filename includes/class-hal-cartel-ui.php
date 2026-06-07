<?php
/**
 * Progressive disclosure — "Simple" vs "Advanced" mode (see spec §4).
 *
 * One global per-user toggle that every admin screen reads, instead of
 * maintaining two separate UIs. Wrap any field/section that should stay
 * out of a newcomer's way with Hal_Cartel_UI::advanced( $callback ).
 *
 * @package Cartel
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hal_Cartel_UI {

	const META_KEY = 'hal_cartel_ui_mode';

	public static function init() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'toolbar_toggle' ), 100 );
		add_action( 'admin_post_hal_cartel_set_ui_mode', array( __CLASS__, 'handle_set_mode' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * 'simple' (default) or 'advanced' for the given user (current user if omitted).
	 */
	public static function mode( $user_id = 0 ): string {
		$user_id = $user_id ? $user_id : get_current_user_id();
		$mode    = get_user_meta( $user_id, self::META_KEY, true );
		return 'advanced' === $mode ? 'advanced' : 'simple';
	}

	public static function is_advanced( $user_id = 0 ): bool {
		return 'advanced' === self::mode( $user_id );
	}

	/**
	 * Render $callback's output wrapped in a container that the client-side
	 * toggle shows/hides based on the user's stored mode. Markup is always
	 * present (single source of truth, no duplicated templates); only
	 * visibility changes — see admin/assets/hal-cartel-admin.js.
	 */
	public static function advanced( callable $callback ) {
		$hidden = ! self::is_advanced();
		printf(
			'<div class="hal-cartel-advanced-field%s" data-hal-cartel-advanced="1">',
			$hidden ? ' hal-cartel-advanced-field--hidden' : ''
		);
		$callback();
		echo '</div>';
	}

	public static function toolbar_toggle( $wp_admin_bar ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$mode = self::mode();
		$next = 'advanced' === $mode ? 'simple' : 'advanced';
		$url  = wp_nonce_url(
			admin_url( 'admin-post.php?action=hal_cartel_set_ui_mode&mode=' . $next ),
			'hal_cartel_set_ui_mode'
		);

		$wp_admin_bar->add_node( array(
			'id'    => 'hal-cartel-ui-mode',
			'title' => 'advanced' === $mode
				? esc_html__( 'Cartel: Advanced mode', 'cartel' )
				: esc_html__( 'Cartel: Simple mode', 'cartel' ),
			'href'  => esc_url( $url ),
			'meta'  => array(
				'title' => 'advanced' === $mode
					? esc_attr__( 'Switch to Simple mode', 'cartel' )
					: esc_attr__( 'Switch to Advanced mode', 'cartel' ),
			),
		) );
	}

	public static function handle_set_mode() {
		if ( ! is_user_logged_in() || ! check_admin_referer( 'hal_cartel_set_ui_mode' ) ) {
			wp_die( esc_html__( 'Forbidden', 'cartel' ) );
		}

		$mode = ( isset( $_GET['mode'] ) && 'advanced' === $_GET['mode'] ) ? 'advanced' : 'simple';
		update_user_meta( get_current_user_id(), self::META_KEY, $mode );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	public static function enqueue() {
		wp_register_style( 'hal-cartel-admin', HAL_CARTEL_PLUGIN_URL . 'admin/assets/hal-cartel-admin.css', array(), HAL_CARTEL_VERSION );
		wp_register_script( 'hal-cartel-admin', HAL_CARTEL_PLUGIN_URL . 'admin/assets/hal-cartel-admin.js', array(), HAL_CARTEL_VERSION, true );
		wp_localize_script( 'hal-cartel-admin', 'HalCartelUIData', array( 'mode' => self::mode() ) );
		wp_enqueue_style( 'hal-cartel-admin' );
		wp_enqueue_script( 'hal-cartel-admin' );
	}
}
