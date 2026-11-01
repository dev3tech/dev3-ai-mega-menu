<?php
/**
 * Page-builder adapters: an Elementor widget and a WPBakery element.
 *
 * @package Dev3\AiMegaMenu
 */

namespace Dev3\AiMegaMenu\Integrations;

use Dev3\AiMegaMenu\Menu\Themes;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the adapters only when the builder is active.
 */
class Builders {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'elementor/widgets/register', array( $this, 'elementor' ) );
		add_action( 'vc_before_init', array( $this, 'wpbakery' ) );
	}

	/**
	 * Menus as id => name.
	 *
	 * @return array
	 */
	public static function menu_options() {
		$out = array();
		foreach ( wp_get_nav_menus() as $menu ) {
			$out[ (int) $menu->term_id ] = $menu->name;
		}
		return $out;
	}

	/**
	 * Themes as id => name ('' = location default).
	 *
	 * @return array
	 */
	public static function theme_options() {
		$out = array( '' => __( 'Default (from Locations)', 'dev3-ai-mega-menu' ) );
		foreach ( Themes::all() as $id => $theme ) {
			$out[ $id ] = $theme['name'];
		}
		return $out;
	}

	/**
	 * Elementor widget.
	 *
	 * @param object $manager \Elementor\Widgets_Manager.
	 */
	public function elementor( $manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) || ! is_object( $manager ) || ! method_exists( $manager, 'register' ) ) {
			return;
		}
		require_once __DIR__ . '/Elementor_Widget.php';
		$manager->register( new Elementor_Widget() );
	}

	/**
	 * WPBakery element mapped to the [dev3mm_menu] shortcode.
	 */
	public function wpbakery() {
		if ( ! function_exists( 'vc_map' ) ) {
			return;
		}
		vc_map( self::wpbakery_map() );
	}

	/**
	 * The vc_map() definition (public for tests).
	 *
	 * @return array
	 */
	public static function wpbakery_map() {
		return array(
			'name'        => __( 'DEV3 AI Mega Menu', 'dev3-ai-mega-menu' ),
			'base'        => 'dev3mm_menu',
			'category'    => __( 'DEV3 AI', 'dev3-ai-mega-menu' ),
			'description' => __( 'Accessible mega menu', 'dev3-ai-mega-menu' ),
			'icon'        => DEV3MM_URL . 'assets/img/icon.svg',
			'params'      => array(
				array(
					'type'        => 'dropdown',
					'heading'     => __( 'Menu', 'dev3-ai-mega-menu' ),
					'param_name'  => 'menu',
					'value'       => array_flip( self::menu_options() ),
					'admin_label' => true,
				),
				array(
					'type'       => 'dropdown',
					'heading'    => __( 'Style', 'dev3-ai-mega-menu' ),
					'param_name' => 'theme',
					'value'      => array_flip( self::theme_options() ),
				),
				array(
					'type'       => 'dropdown',
					'heading'    => __( 'Open submenus on', 'dev3-ai-mega-menu' ),
					'param_name' => 'trigger',
					'value'      => array(
						__( 'Default', 'dev3-ai-mega-menu' ) => '',
						__( 'Hover', 'dev3-ai-mega-menu' ) => 'hover',
						__( 'Click', 'dev3-ai-mega-menu' ) => 'click',
					),
				),
				array(
					'type'       => 'dropdown',
					'heading'    => __( 'Mobile menu', 'dev3-ai-mega-menu' ),
					'param_name' => 'mobile_mode',
					'value'      => array(
						__( 'Default', 'dev3-ai-mega-menu' )    => '',
						__( 'Accordion', 'dev3-ai-mega-menu' )  => 'accordion',
						__( 'Off-canvas', 'dev3-ai-mega-menu' ) => 'offcanvas',
					),
				),
			),
		);
	}
}
