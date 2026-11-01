<?php
/**
 * Plugin-specific settings (shared AI settings live in Core3\Core\Settings_Store).
 *
 * @package Dev3\AiMegaMenu
 */

namespace Dev3\AiMegaMenu;

defined( 'ABSPATH' ) || exit;

/**
 * Hooked into dev3mm_settings_defaults / dev3mm_settings_sanitize.
 */
class Settings {

	/**
	 * Adds plugin defaults.
	 *
	 * @param array $defaults Shared defaults.
	 * @return array
	 */
	public static function defaults( $defaults ) {
		return array_merge(
			$defaults,
			array(
				'load_assets'          => 'auto',
				'block_nav_replace'    => 'off',
				'block_nav_menu'       => 0,
				'ai_language'          => '',
				'ai_max_pages'         => 400,
				'ai_include_products'  => true,
				'delete_on_uninstall'  => false,
				'last_audit'           => array(),
				'onboarding_dismissed' => false,
			)
		);
	}

	/**
	 * Sanitizes plugin keys.
	 *
	 * @param array $out     Sanitized so far.
	 * @param array $input   Raw input.
	 * @param array $current Current.
	 * @return array
	 */
	public static function sanitize( $out, $input, $current ) {
		unset( $current );
		if ( isset( $input['load_assets'] ) && in_array( $input['load_assets'], array( 'auto', 'always' ), true ) ) {
			$out['load_assets'] = $input['load_assets'];
		}
		if ( isset( $input['block_nav_replace'] ) && in_array( $input['block_nav_replace'], array( 'off', 'first', 'all' ), true ) ) {
			$out['block_nav_replace'] = $input['block_nav_replace'];
		}
		if ( isset( $input['block_nav_menu'] ) ) {
			$out['block_nav_menu'] = max( 0, (int) $input['block_nav_menu'] );
		}
		if ( isset( $input['ai_language'] ) ) {
			$out['ai_language'] = mb_substr( sanitize_text_field( wp_unslash( (string) $input['ai_language'] ) ), 0, 40 );
		}
		if ( isset( $input['ai_max_pages'] ) ) {
			$out['ai_max_pages'] = min( 1000, max( 20, (int) $input['ai_max_pages'] ) );
		}
		foreach ( array( 'ai_include_products', 'delete_on_uninstall', 'onboarding_dismissed' ) as $flag ) {
			if ( isset( $input[ $flag ] ) ) {
				$out[ $flag ] = (bool) $input[ $flag ];
			}
		}
		if ( isset( $input['last_audit'] ) && is_array( $input['last_audit'] ) ) {
			$audit             = $input['last_audit'];
			$out['last_audit'] = array(
				'menu_id' => isset( $audit['menu_id'] ) ? max( 0, (int) $audit['menu_id'] ) : 0,
				'score'   => isset( $audit['score'] ) ? min( 100, max( 0, (int) $audit['score'] ) ) : 0,
				'issues'  => isset( $audit['issues'] ) ? max( 0, (int) $audit['issues'] ) : 0,
				'at'      => isset( $audit['at'] ) ? sanitize_text_field( (string) $audit['at'] ) : '',
			);
		}
		return $out;
	}
}
