<?php
/**
 * Style presets and custom themes (design tokens → CSS custom properties).
 *
 * @package Dev3\AiMegaMenu
 */

namespace Dev3\AiMegaMenu\Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Built-in presets plus custom themes saved in the `dev3mm_themes` option.
 */
class Themes {

	const OPTION = 'dev3mm_themes';

	const COLOR_TOKENS = array( 'bar_bg', 'bar_text', 'bar_hover_bg', 'bar_hover_text', 'panel_bg', 'panel_text', 'panel_muted', 'panel_heading', 'panel_hover', 'accent', 'border', 'mobile_bg', 'mobile_text', 'badge_new', 'badge_sale', 'badge_hot', 'badge_info' );

	/**
	 * Token defaults (the "Light" preset).
	 *
	 * @return array
	 */
	public static function base_tokens() {
		return array(
			'bar_bg'         => '#ffffff',
			'bar_text'       => '#1f2937',
			'bar_hover_bg'   => '#f3f4f6',
			'bar_hover_text' => '#111827',
			'panel_bg'       => '#ffffff',
			'panel_text'     => '#374151',
			'panel_muted'    => '#6b7280',
			'panel_heading'  => '#111827',
			'panel_hover'    => '#4f46e5',
			'accent'         => '#4f46e5',
			'border'         => '#e5e7eb',
			'mobile_bg'      => '#ffffff',
			'mobile_text'    => '#1f2937',
			'badge_new'      => '#047857',
			'badge_sale'     => '#b91c1c',
			'badge_hot'      => '#c2410c',
			'badge_info'     => '#1d4ed8',
			'radius'         => 10,
			'bar_height'     => 60,
			'font_size'      => 15,
			'font_weight'    => 500,
			'item_padding'   => 16,
			'panel_padding'  => 28,
			'shadow'         => 'soft',
			'uppercase'      => false,
			'font_family'    => 'inherit',
		);
	}

	/**
	 * Built-in presets (read-only; duplicate one to customise).
	 *
	 * @return array id => theme
	 */
	public static function presets() {
		$base    = self::base_tokens();
		$presets = array(
			'light'   => array(
				'name'   => __( 'Light', 'dev3-ai-mega-menu' ),
				'tokens' => $base,
			),
			'dark'    => array(
				'name'   => __( 'Midnight', 'dev3-ai-mega-menu' ),
				'tokens' => array_merge(
					$base,
					array(
						'bar_bg'         => '#111827',
						'bar_text'       => '#e5e7eb',
						'bar_hover_bg'   => '#1f2937',
						'bar_hover_text' => '#ffffff',
						'panel_bg'       => '#1f2937',
						'panel_text'     => '#d1d5db',
						'panel_muted'    => '#9ca3af',
						'panel_heading'  => '#ffffff',
						'panel_hover'    => '#a5b4fc',
						'accent'         => '#818cf8',
						'border'         => '#374151',
						'mobile_bg'      => '#111827',
						'mobile_text'    => '#e5e7eb',
						'badge_new'      => '#059669',
						'badge_sale'     => '#dc2626',
						'badge_hot'      => '#ea580c',
						'badge_info'     => '#2563eb',
						'shadow'         => 'strong',
					)
				),
			),
			'brand'   => array(
				'name'   => __( 'Indigo', 'dev3-ai-mega-menu' ),
				'tokens' => array_merge(
					$base,
					array(
						'bar_bg'         => '#4338ca',
						'bar_text'       => '#ffffff',
						'bar_hover_bg'   => '#3730a3',
						'bar_hover_text' => '#ffffff',
						'accent'         => '#4338ca',
						'panel_hover'    => '#4338ca',
					)
				),
			),
			'minimal' => array(
				'name'   => __( 'Minimal (inherit theme)', 'dev3-ai-mega-menu' ),
				'tokens' => array_merge(
					$base,
					array(
						'bar_bg'         => 'transparent',
						'bar_text'       => 'inherit',
						'bar_hover_bg'   => 'transparent',
						'bar_hover_text' => 'inherit',
						'radius'         => 4,
						'shadow'         => 'none',
						'font_weight'    => 400,
						'item_padding'   => 12,
					)
				),
			),
			'warm'    => array(
				'name'   => __( 'Boutique', 'dev3-ai-mega-menu' ),
				'tokens' => array_merge(
					$base,
					array(
						'bar_bg'         => '#fdf8f3',
						'bar_text'       => '#3f2d20',
						'bar_hover_bg'   => '#f5e9dc',
						'bar_hover_text' => '#2a1d13',
						'panel_bg'       => '#fffdfa',
						'panel_text'     => '#4b3a2c',
						'panel_muted'    => '#7c6a5b',
						'panel_heading'  => '#2a1d13',
						'panel_hover'    => '#9a3412',
						'accent'         => '#9a3412',
						'border'         => '#eadfd3',
						'radius'         => 2,
						'uppercase'      => true,
						'font_size'      => 13,
						'font_weight'    => 600,
					)
				),
			),
		);
		foreach ( $presets as $id => $preset ) {
			$presets[ $id ]['id']     = $id;
			$presets[ $id ]['preset'] = true;
		}
		/**
		 * Filters the built-in style presets.
		 *
		 * @param array $presets id => [ name, tokens ].
		 */
		return (array) apply_filters( 'dev3mm_presets', $presets );
	}

	/**
	 * Custom themes.
	 *
	 * @return array id => theme
	 */
	public static function custom() {
		$saved = get_option( self::OPTION, array() );
		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Presets + custom.
	 *
	 * @return array id => theme
	 */
	public static function all() {
		return array_merge( self::presets(), self::custom() );
	}

	/**
	 * One theme (falls back to Light).
	 *
	 * @param string $id Id.
	 * @return array
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : $all['light'];
	}

	/**
	 * Saves the full list of custom themes.
	 *
	 * @param array $themes Raw themes (list or map).
	 * @return array Saved custom themes.
	 */
	public static function save_custom( array $themes ) {
		$out     = array();
		$presets = self::presets();
		foreach ( array_slice( array_values( $themes ), 0, 30 ) as $theme ) {
			if ( ! is_array( $theme ) ) {
				continue;
			}
			$id = isset( $theme['id'] ) ? sanitize_key( (string) $theme['id'] ) : '';
			if ( '' === $id || isset( $presets[ $id ] ) ) {
				$id = 'custom-' . substr( md5( wp_json_encode( $theme ) . count( $out ) ), 0, 8 );
			}
			$out[ $id ] = array(
				'id'     => $id,
				'name'   => isset( $theme['name'] ) && '' !== trim( (string) $theme['name'] ) ? mb_substr( sanitize_text_field( (string) $theme['name'] ), 0, 40 ) : __( 'Custom style', 'dev3-ai-mega-menu' ),
				'preset' => false,
				'tokens' => self::sanitize_tokens( isset( $theme['tokens'] ) && is_array( $theme['tokens'] ) ? $theme['tokens'] : array() ),
			);
		}
		update_option( self::OPTION, $out, false );
		return $out;
	}

	/**
	 * Sanitises tokens against the base set.
	 *
	 * @param array $in Raw tokens.
	 * @return array
	 */
	public static function sanitize_tokens( array $in ) {
		$out = self::base_tokens();
		foreach ( self::COLOR_TOKENS as $key ) {
			if ( isset( $in[ $key ] ) ) {
				$out[ $key ] = self::sanitize_color( (string) $in[ $key ], $out[ $key ] );
			}
		}
		$ranges = array(
			'radius'        => array( 0, 24 ),
			'bar_height'    => array( 40, 96 ),
			'font_size'     => array( 12, 20 ),
			'font_weight'   => array( 300, 800 ),
			'item_padding'  => array( 6, 32 ),
			'panel_padding' => array( 12, 48 ),
		);
		foreach ( $ranges as $key => $range ) {
			if ( isset( $in[ $key ] ) && is_numeric( $in[ $key ] ) ) {
				$out[ $key ] = min( $range[1], max( $range[0], (int) $in[ $key ] ) );
			}
		}
		if ( isset( $in['shadow'] ) && in_array( $in['shadow'], array( 'none', 'soft', 'strong' ), true ) ) {
			$out['shadow'] = $in['shadow'];
		}
		if ( isset( $in['uppercase'] ) ) {
			$out['uppercase'] = (bool) $in['uppercase'];
		}
		if ( isset( $in['font_family'] ) ) {
			$family             = preg_replace( '/[^a-zA-Z0-9 ,\'"-]/', '', (string) $in['font_family'] );
			$out['font_family'] = '' === trim( $family ) ? 'inherit' : mb_substr( $family, 0, 80 );
		}
		return $out;
	}

	/**
	 * Hex colour, `transparent` or `inherit`.
	 *
	 * @param string $value    Value.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	public static function sanitize_color( $value, $fallback ) {
		$value = strtolower( trim( $value ) );
		if ( in_array( $value, array( 'transparent', 'inherit' ), true ) ) {
			return $value;
		}
		$hex = sanitize_hex_color( $value );
		return $hex ? $hex : $fallback;
	}

	/**
	 * CSS custom properties for a theme, as an inline style string.
	 *
	 * @param array $tokens Tokens.
	 * @return string
	 */
	public static function css_vars( array $tokens ) {
		$t       = self::sanitize_tokens( $tokens );
		$shadows = array(
			'none'   => 'none',
			'soft'   => '0 12px 32px -8px rgba(15,23,42,.18), 0 2px 6px rgba(15,23,42,.06)',
			'strong' => '0 24px 48px -12px rgba(0,0,0,.45), 0 4px 12px rgba(0,0,0,.2)',
		);
		$vars    = array();
		foreach ( self::COLOR_TOKENS as $key ) {
			$vars[ '--dev3mm-' . str_replace( '_', '-', $key ) ] = $t[ $key ];
		}
		$vars['--dev3mm-radius']        = $t['radius'] . 'px';
		$vars['--dev3mm-bar-height']    = $t['bar_height'] . 'px';
		$vars['--dev3mm-font-size']     = $t['font_size'] . 'px';
		$vars['--dev3mm-font-weight']   = (string) $t['font_weight'];
		$vars['--dev3mm-item-padding']  = $t['item_padding'] . 'px';
		$vars['--dev3mm-panel-padding'] = $t['panel_padding'] . 'px';
		$vars['--dev3mm-shadow']        = $shadows[ $t['shadow'] ];
		$vars['--dev3mm-transform']     = $t['uppercase'] ? 'uppercase' : 'none';
		$vars['--dev3mm-letter']        = $t['uppercase'] ? '.06em' : 'normal';
		$vars['--dev3mm-font-family']   = $t['font_family'];
		$css                            = '';
		foreach ( $vars as $k => $v ) {
			$css .= $k . ':' . $v . ';';
		}
		return $css;
	}
}
