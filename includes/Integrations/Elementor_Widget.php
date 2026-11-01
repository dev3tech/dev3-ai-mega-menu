<?php
/**
 * Elementor widget "DEV3 AI Mega Menu". Loaded only when Elementor is active.
 *
 * @package Dev3\AiMegaMenu
 */

namespace Dev3\AiMegaMenu\Integrations;

use Dev3\AiMegaMenu\Front\Frontend;
use Dev3\AiMegaMenu\Front\Renderer;
use Dev3\AiMegaMenu\Menu\Locations;

defined( 'ABSPATH' ) || exit;

/**
 * Widget.
 */
class Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'dev3mm_mega_menu';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'DEV3 AI Mega Menu', 'dev3-ai-mega-menu' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-nav-menu';
	}

	/**
	 * Categories.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'general' );
	}

	/**
	 * Keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'menu', 'mega', 'navigation', 'dev3' );
	}

	/**
	 * Styles.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( Frontend::HANDLE );
	}

	/**
	 * Scripts.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( Frontend::HANDLE );
	}

	/**
	 * Controls.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'menu', array( 'label' => __( 'Menu', 'dev3-ai-mega-menu' ) ) );
		$menus = Builders::menu_options();
		$this->add_control(
			'menu_id',
			array(
				'label'   => __( 'Menu', 'dev3-ai-mega-menu' ),
				'type'    => 'select',
				'options' => $menus,
				'default' => $menus ? (string) array_key_first( $menus ) : '',
			)
		);
		$this->add_control(
			'theme',
			array(
				'label'   => __( 'Style', 'dev3-ai-mega-menu' ),
				'type'    => 'select',
				'options' => Builders::theme_options(),
				'default' => '',
			)
		);
		$this->add_control(
			'trigger',
			array(
				'label'   => __( 'Open submenus on', 'dev3-ai-mega-menu' ),
				'type'    => 'select',
				'options' => array(
					''      => __( 'Default', 'dev3-ai-mega-menu' ),
					'hover' => __( 'Hover', 'dev3-ai-mega-menu' ),
					'click' => __( 'Click', 'dev3-ai-mega-menu' ),
				),
				'default' => '',
			)
		);
		$this->add_control(
			'mobile_mode',
			array(
				'label'   => __( 'Mobile menu', 'dev3-ai-mega-menu' ),
				'type'    => 'select',
				'options' => array(
					''          => __( 'Default', 'dev3-ai-mega-menu' ),
					'accordion' => __( 'Accordion', 'dev3-ai-mega-menu' ),
					'offcanvas' => __( 'Off-canvas', 'dev3-ai-mega-menu' ),
				),
				'default' => '',
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Output.
	 */
	protected function render() {
		$s = $this->get_settings_for_display();
		echo Renderer::render( (int) $s['menu_id'], Locations::EMBED, Frontend::overrides( (array) $s ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the renderer.
	}
}
