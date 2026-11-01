<?php
/**
 * Per-location display settings (theme locations, the block-theme navigation and embeds).
 *
 * @package Dev3\AiMegaMenu
 */

namespace Dev3\AiMegaMenu\Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Settings stored in the `dev3mm_locations` option: location => config.
 */
class Locations {

	const OPTION = 'dev3mm_locations';

	/**
	 * Virtual location used by the Navigation-block replacement, blocks, shortcodes and builder widgets.
	 */
	const EMBED = 'dev3mm_embed';

	const BREAKPOINTS = array( 0, 600, 782, 960, 1024, 1200 );

	/**
	 * Defaults for one location.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'        => false,
			'theme'          => 'light',
			'trigger'        => 'hover',
			'hover_delay'    => 150,
			'animation'      => 'fade',
			'orientation'    => 'horizontal',
			'align'          => 'start',
			'panel_width'    => 'full',
			'sticky'         => false,
			'sticky_mobile'  => false,
			'mobile_mode'    => 'accordion',
			'offcanvas_side' => 'left',
			'breakpoint'     => 960,
			'toggle_label'   => '',
			'show_search'    => false,
			'aria_label'     => '',
		);
	}

	/**
	 * All saved configs.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * One location config (defaults merged). The embed location is always enabled.
	 *
	 * @param string $location Location.
	 * @return array
	 */
	public static function get( $location ) {
		$all    = self::all();
		$config = isset( $all[ $location ] ) && is_array( $all[ $location ] ) ? self::sanitize( $all[ $location ] ) : self::defaults();
		if ( self::EMBED === $location ) {
			$config['enabled'] = true;
		}
		return $config;
	}

	/**
	 * Whether a theme location is handled by the plugin.
	 *
	 * @param string $location Location.
	 * @return bool
	 */
	public static function enabled( $location ) {
		$all = self::all();
		return ! empty( $all[ $location ]['enabled'] );
	}

	/**
	 * Saves configs for several locations (merging with existing ones).
	 *
	 * @param array $configs location => config.
	 * @return array All configs.
	 */
	public static function save( array $configs ) {
		$all = self::all();
		foreach ( $configs as $location => $config ) {
			$location = sanitize_key( (string) $location );
			if ( '' === $location || ! is_array( $config ) ) {
				continue;
			}
			$all[ $location ] = self::sanitize( array_merge( isset( $all[ $location ] ) ? (array) $all[ $location ] : array(), $config ) );
		}
		update_option( self::OPTION, $all );
		return $all;
	}

	/**
	 * Sanitises one config.
	 *
	 * @param array $in Raw.
	 * @return array
	 */
	public static function sanitize( array $in ) {
		$out   = self::defaults();
		$enums = array(
			'trigger'        => array( 'hover', 'click' ),
			'animation'      => array( 'fade', 'slide', 'none' ),
			'orientation'    => array( 'horizontal', 'vertical' ),
			'align'          => array( 'start', 'center', 'end', 'between' ),
			'panel_width'    => array( 'full', 'container', 'auto' ),
			'mobile_mode'    => array( 'accordion', 'offcanvas' ),
			'offcanvas_side' => array( 'left', 'right' ),
		);
		foreach ( $enums as $key => $allowed ) {
			if ( isset( $in[ $key ] ) && in_array( $in[ $key ], $allowed, true ) ) {
				$out[ $key ] = $in[ $key ];
			}
		}
		foreach ( array( 'enabled', 'sticky', 'sticky_mobile', 'show_search' ) as $flag ) {
			if ( isset( $in[ $flag ] ) ) {
				$out[ $flag ] = (bool) $in[ $flag ];
			}
		}
		if ( isset( $in['theme'] ) ) {
			$theme        = sanitize_key( (string) $in['theme'] );
			$out['theme'] = '' === $theme ? 'light' : $theme;
		}
		if ( isset( $in['hover_delay'] ) ) {
			$out['hover_delay'] = min( 1000, max( 0, (int) $in['hover_delay'] ) );
		}
		if ( isset( $in['breakpoint'] ) && in_array( (int) $in['breakpoint'], self::BREAKPOINTS, true ) ) {
			$out['breakpoint'] = (int) $in['breakpoint'];
		}
		foreach ( array( 'toggle_label', 'aria_label' ) as $text ) {
			if ( isset( $in[ $text ] ) ) {
				$out[ $text ] = mb_substr( sanitize_text_field( (string) $in[ $text ] ), 0, 40 );
			}
		}
		return $out;
	}

	/**
	 * Registered theme locations plus the virtual embed location, with assignments.
	 *
	 * @return array[]
	 */
	public static function describe() {
		$assigned = get_nav_menu_locations();
		$out      = array();
		foreach ( get_registered_nav_menus() as $location => $label ) {
			$out[] = array(
				'location' => $location,
				'label'    => $label,
				'menu_id'  => isset( $assigned[ $location ] ) ? (int) $assigned[ $location ] : 0,
				'virtual'  => false,
				'config'   => self::get( $location ),
			);
		}
		$out[] = array(
			'location' => self::EMBED,
			'label'    => __( 'Blocks, shortcodes, page builders and block-theme navigation', 'dev3-ai-mega-menu' ),
			'menu_id'  => 0,
			'virtual'  => true,
			'config'   => self::get( self::EMBED ),
		);
		return $out;
	}
}
