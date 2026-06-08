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

		if ( '' === $mode ) {
			$mode = get_option( 'hal_cartel_default_ui_mode', 'simple' );
		}

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
		wp_localize_script( 'hal-cartel-admin', 'HalCartelUIData', array(
			'mode'                  => self::mode(),
			'copiedLabel'           => __( 'Copied', 'cartel' ),
			'removeImageLabel'      => __( 'Remove image', 'cartel' ),
			'galleryTitle'          => __( 'Select images', 'cartel' ),
			'galleryButton'         => __( 'Add to gallery', 'cartel' ),
			'attrNamePlaceholder'   => __( 'e.g. Color', 'cartel' ),
			'attrValuesPlaceholder' => __( 'Red | Blue | Green', 'cartel' ),
			'usedForVariations'     => __( 'Used for variations', 'cartel' ),
			'removeAttributeLabel'  => __( 'Remove attribute', 'cartel' ),
			'removeVariationLabel'  => __( 'Remove variation', 'cartel' ),
			'enabledLabel'          => __( 'Enabled', 'cartel' ),
			'setImageLabel'         => __( 'Set image', 'cartel' ),
			'changeImageLabel'      => __( 'Change image', 'cartel' ),
			'noAttributesLabel'     => __( '(no attributes selected)', 'cartel' ),
			'variationImageTitle'   => __( 'Select a variation image', 'cartel' ),
			'variationImageButton'  => __( 'Use image', 'cartel' ),
			'chooseFileLabel'       => __( 'Choose file', 'cartel' ),
			'changeFileLabel'       => __( 'Change file', 'cartel' ),
			'noFileSelectedLabel'   => __( 'No file selected', 'cartel' ),
			'downloadFileTitle'     => __( 'Select a downloadable file', 'cartel' ),
			'downloadFileButton'    => __( 'Use file', 'cartel' ),
			'noVariationsLabel'     => __( 'No variations yet. Mark at least one attribute "Used for variations" and click Generate above.', 'cartel' ),
			'noAttributesForGen'    => __( 'Mark at least one attribute as "Used for variations" first, then click Generate again.', 'cartel' ),
			'removeZoneLabel'       => __( 'Remove zone', 'cartel' ),
			'removeMethodLabel'     => __( 'Remove shipping method', 'cartel' ),
			'removeBracketLabel'    => __( 'Remove bracket', 'cartel' ),
			'weightBracketUpTo'     => __( 'Up to', 'cartel' ),
			'weightBracketCost'     => __( 'Cost', 'cartel' ),
		) );
		wp_enqueue_style( 'hal-cartel-admin' );
		wp_enqueue_script( 'hal-cartel-admin' );
	}
}
