<?php
/**
 * Built-in SVG icon set (assets/icons.json, shared with the React admin).
 *
 * @package Dev3\AiMegaMenu
 */

namespace Dev3\AiMegaMenu\Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Icon lookup, rendering and keyword matching.
 */
class Icons {

	/**
	 * Loaded set.
	 *
	 * @var array|null
	 */
	private static $icons = null;

	/**
	 * All icons: name => { label, keywords, d }.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$icons ) {
			$file = DEV3MM_PATH . 'assets/icons.json';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
			$data        = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array();
			self::$icons = is_array( $data ) ? $data : array();
			/**
			 * Filters the icon set. Each entry: name => [ label, keywords, d (24×24 stroke path) ].
			 *
			 * @param array $icons Icons.
			 */
			self::$icons = (array) apply_filters( 'dev3mm_icons', self::$icons );
		}
		return self::$icons;
	}

	/**
	 * Names.
	 *
	 * @return string[]
	 */
	public static function names() {
		return array_keys( self::all() );
	}

	/**
	 * Whether an icon exists.
	 *
	 * @param string $name Name.
	 * @return bool
	 */
	public static function exists( $name ) {
		return '' !== $name && isset( self::all()[ $name ] );
	}

	/**
	 * Inline SVG markup (decorative, aria-hidden).
	 *
	 * @param string $name  Name.
	 * @param string $css_class CSS class.
	 * @return string
	 */
	public static function svg( $name, $css_class = 'dev3mm__icon' ) {
		$all = self::all();
		if ( ! isset( $all[ $name ]['d'] ) ) {
			return '';
		}
		return '<svg class="' . esc_attr( $css_class ) . '" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' . esc_attr( $all[ $name ]['d'] ) . '"/></svg>';
	}

	/**
	 * Best icon for a label by keyword overlap (the non-AI suggestion), or ''.
	 *
	 * @param string $label Label.
	 * @return string
	 */
	public static function match( $label ) {
		$words = preg_split( '/[^a-z0-9]+/', strtolower( remove_accents( (string) $label ) ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! $words ) {
			return '';
		}
		$best  = '';
		$score = 0;
		foreach ( self::all() as $name => $icon ) {
			$keys = preg_split( '/\s+/', strtolower( (string) ( isset( $icon['keywords'] ) ? $icon['keywords'] : '' ) ), -1, PREG_SPLIT_NO_EMPTY );
			$hit  = 0;
			foreach ( $words as $i => $word ) {
				foreach ( $keys as $k ) {
					$singular = rtrim( $word, 's' );
					if ( $word === $k || ( strlen( $singular ) > 2 && rtrim( $k, 's' ) === $singular ) ) {
						$hit += 0 === $i ? 3 : 2;
						break;
					}
				}
			}
			if ( $hit > $score ) {
				$score = $hit;
				$best  = $name;
			}
		}
		return $best;
	}
}
