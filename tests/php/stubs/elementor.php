<?php
/**
 * Minimal Elementor stubs for testing the widget adapter without Elementor installed.
 *
 * @package Dev3\AiMegaMenu
 */

namespace Elementor {
	if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
		/**
		 * Stub.
		 */
		abstract class Widget_Base {
			public $controls = array();
			public $settings = array();
			public function start_controls_section( $id, $args ) {}
			public function end_controls_section() {}
			public function add_control( $id, $args ) {
				$this->controls[ $id ] = $args;
			}
			public function get_settings_for_display() {
				return $this->settings;
			}
		}
	}
}

namespace {
	/**
	 * Stub widgets manager.
	 */
	class Dev3mm_Fake_Widgets_Manager {
		public $widgets = array();
		public function register( $widget ) {
			$this->widgets[] = $widget;
		}
	}
}
