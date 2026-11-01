<?php
/**
 * Per menu item mega-menu settings, stored in the nav_menu_item post meta `_dev3mm_item`.
 *
 * @package Dev3\AiMegaMenu
 */

namespace Dev3\AiMegaMenu\Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Defaults, sanitising and storage for item configs and their panel widgets.
 */
class Item_Config {

	const META = '_dev3mm_item';

	const TYPES = array( 'auto', 'flyout', 'mega', 'tabs' );

	const WIDGETS = array( 'item', 'posts', 'products', 'search', 'html', 'shortcode', 'image', 'promo', 'block' );

	const BADGE_TONES = array( 'new', 'sale', 'hot', 'info', 'custom' );

	/**
	 * Defaults for an item.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'type'             => 'auto',
			'icon'             => '',
			'image_id'         => 0,
			'hide_label'       => false,
			'hide_on_mobile'   => false,
			'hide_on_desktop'  => false,
			'show_description' => false,
			'badge'            => array(
				'text'  => '',
				'tone'  => 'new',
				'color' => '',
			),
			'columns'          => 4,
			'panel_width'      => 'inherit',
			'rows'             => array(),
			'aside'            => array(),
		);
	}

	/**
	 * Stored config merged with defaults.
	 *
	 * @param int $item_id Menu item id.
	 * @return array
	 */
	public static function get( $item_id ) {
		$raw = get_post_meta( (int) $item_id, self::META, true );
		return self::sanitize( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * Saves a config (sanitised). Defaults-only configs remove the meta.
	 *
	 * @param int   $item_id Menu item id.
	 * @param array $config  Config.
	 * @return array Saved config.
	 */
	public static function save( $item_id, array $config ) {
		$clean = self::sanitize( $config );
		if ( self::defaults() === $clean ) {
			delete_post_meta( (int) $item_id, self::META );
		} else {
			update_post_meta( (int) $item_id, self::META, $clean );
		}
		return $clean;
	}

	/**
	 * Sanitises a full item config.
	 *
	 * @param array $in Raw config.
	 * @return array
	 */
	public static function sanitize( array $in ) {
		$d   = self::defaults();
		$out = $d;
		if ( isset( $in['type'] ) && in_array( $in['type'], self::TYPES, true ) ) {
			$out['type'] = $in['type'];
		}
		if ( isset( $in['icon'] ) ) {
			$icon        = sanitize_key( (string) $in['icon'] );
			$out['icon'] = Icons::exists( $icon ) ? $icon : '';
		}
		if ( isset( $in['image_id'] ) ) {
			$out['image_id'] = max( 0, (int) $in['image_id'] );
		}
		foreach ( array( 'hide_label', 'hide_on_mobile', 'hide_on_desktop', 'show_description' ) as $flag ) {
			if ( isset( $in[ $flag ] ) ) {
				$out[ $flag ] = (bool) $in[ $flag ];
			}
		}
		if ( isset( $in['badge'] ) && is_array( $in['badge'] ) ) {
			$badge        = $in['badge'];
			$out['badge'] = array(
				'text'  => isset( $badge['text'] ) ? mb_substr( sanitize_text_field( (string) $badge['text'] ), 0, 16 ) : '',
				'tone'  => isset( $badge['tone'] ) && in_array( $badge['tone'], self::BADGE_TONES, true ) ? $badge['tone'] : 'new',
				'color' => isset( $badge['color'] ) ? (string) sanitize_hex_color( (string) $badge['color'] ) : '',
			);
		}
		if ( isset( $in['columns'] ) ) {
			$out['columns'] = min( 6, max( 1, (int) $in['columns'] ) );
		}
		if ( isset( $in['panel_width'] ) && in_array( $in['panel_width'], array( 'inherit', 'full', 'container', 'auto' ), true ) ) {
			$out['panel_width'] = $in['panel_width'];
		}
		if ( isset( $in['rows'] ) && is_array( $in['rows'] ) ) {
			$out['rows'] = self::sanitize_rows( $in['rows'] );
		}
		if ( isset( $in['aside'] ) && is_array( $in['aside'] ) ) {
			foreach ( array_slice( array_values( $in['aside'] ), 0, 4 ) as $widget ) {
				$clean = is_array( $widget ) ? self::sanitize_widget( $widget ) : null;
				if ( $clean && 'item' !== $clean['type'] ) {
					$out['aside'][] = $clean;
				}
			}
		}
		return $out;
	}

	/**
	 * Panel rows → columns (12-grid spans) → widgets.
	 *
	 * @param array $rows Raw rows.
	 * @return array
	 */
	public static function sanitize_rows( array $rows ) {
		$out = array();
		foreach ( array_slice( array_values( $rows ), 0, 6 ) as $row ) {
			if ( ! is_array( $row ) || empty( $row['cols'] ) || ! is_array( $row['cols'] ) ) {
				continue;
			}
			$cols = array();
			foreach ( array_slice( array_values( $row['cols'] ), 0, 6 ) as $col ) {
				if ( ! is_array( $col ) ) {
					continue;
				}
				$widgets = array();
				foreach ( array_slice( isset( $col['widgets'] ) && is_array( $col['widgets'] ) ? array_values( $col['widgets'] ) : array(), 0, 12 ) as $widget ) {
					$clean = is_array( $widget ) ? self::sanitize_widget( $widget ) : null;
					if ( $clean ) {
						$widgets[] = $clean;
					}
				}
				$cols[] = array(
					'span'    => isset( $col['span'] ) ? min( 12, max( 1, (int) $col['span'] ) ) : 3,
					'widgets' => $widgets,
				);
			}
			if ( $cols ) {
				$out[] = array( 'cols' => $cols );
			}
		}
		return $out;
	}

	/**
	 * Sanitises one widget, or null when the type is unknown.
	 *
	 * @param array $w Raw widget.
	 * @return array|null
	 */
	public static function sanitize_widget( array $w ) {
		$type = isset( $w['type'] ) ? (string) $w['type'] : '';
		if ( ! in_array( $type, self::WIDGETS, true ) ) {
			return null;
		}
		$text  = static function ( $key, $max ) use ( $w ) {
			return isset( $w[ $key ] ) ? mb_substr( sanitize_text_field( (string) $w[ $key ] ), 0, $max ) : '';
		};
		$int   = static function ( $key, $min = 0, $max = PHP_INT_MAX ) use ( $w ) {
			return isset( $w[ $key ] ) ? min( $max, max( $min, (int) $w[ $key ] ) ) : $min;
		};
		$ids   = static function ( $key ) use ( $w ) {
			return isset( $w[ $key ] ) && is_array( $w[ $key ] ) ? array_values( array_slice( array_filter( array_map( 'absint', $w[ $key ] ) ), 0, 12 ) ) : array();
		};
		$title = $text( 'title', 80 );
		switch ( $type ) {
			case 'item':
				return array(
					'type'    => 'item',
					'item_id' => $int( 'item_id' ),
				);
			case 'posts':
				return array(
					'type'       => 'posts',
					'title'      => $title,
					'category'   => $int( 'category' ),
					'count'      => $int( 'count', 1, 8 ),
					'show_image' => ! empty( $w['show_image'] ),
				);
			case 'products':
				$source = isset( $w['source'] ) && in_array( $w['source'], array( 'recent', 'featured', 'sale', 'best', 'category', 'ids' ), true ) ? $w['source'] : 'recent';
				return array(
					'type'     => 'products',
					'title'    => $title,
					'source'   => $source,
					'category' => $int( 'category' ),
					'ids'      => $ids( 'ids' ),
					'count'    => $int( 'count', 1, 8 ),
				);
			case 'search':
				return array(
					'type'        => 'search',
					'title'       => $title,
					'placeholder' => $text( 'placeholder', 60 ),
					'post_type'   => isset( $w['post_type'] ) && in_array( $w['post_type'], array( 'any', 'post', 'page', 'product' ), true ) ? $w['post_type'] : 'any',
				);
			case 'html':
				return array(
					'type'    => 'html',
					'title'   => $title,
					'content' => isset( $w['content'] ) ? mb_substr( wp_kses_post( (string) $w['content'] ), 0, 5000 ) : '',
				);
			case 'shortcode':
				return array(
					'type'    => 'shortcode',
					'title'   => $title,
					'content' => isset( $w['content'] ) ? mb_substr( sanitize_textarea_field( (string) $w['content'] ), 0, 1000 ) : '',
				);
			case 'image':
				return array(
					'type'     => 'image',
					'title'    => $title,
					'image_id' => $int( 'image_id' ),
					'link'     => isset( $w['link'] ) ? esc_url_raw( (string) $w['link'] ) : '',
					'caption'  => $text( 'caption', 120 ),
				);
			case 'promo':
				return array(
					'type'        => 'promo',
					'title'       => $title,
					'text'        => isset( $w['text'] ) ? mb_substr( sanitize_textarea_field( (string) $w['text'] ), 0, 300 ) : '',
					'badge'       => $text( 'badge', 24 ),
					'cta_label'   => $text( 'cta_label', 40 ),
					'cta_url'     => isset( $w['cta_url'] ) ? esc_url_raw( (string) $w['cta_url'] ) : '',
					'image_id'    => $int( 'image_id' ),
					'product_ids' => $ids( 'product_ids' ),
					'tone'        => isset( $w['tone'] ) && in_array( $w['tone'], array( 'brand', 'dark', 'light', 'accent' ), true ) ? $w['tone'] : 'brand',
				);
			case 'block':
				return array(
					'type'     => 'block',
					'title'    => $title,
					'block_id' => $int( 'block_id' ),
				);
		}
		return null;
	}

	/**
	 * Effective layout type for a top-level item.
	 *
	 * @param array $config       Item config.
	 * @param bool  $has_children Whether the item has children.
	 * @param int   $depth        0 for top level.
	 * @param bool  $grandkids    Whether any child has children (auto mode turns those into mega panels).
	 * @return string link|flyout|mega|tabs
	 */
	public static function layout( array $config, $has_children, $depth = 0, $grandkids = false ) {
		$has_panel = ! empty( $config['rows'] ) || ! empty( $config['aside'] );
		if ( $depth > 0 ) {
			return $has_children ? 'flyout' : 'link';
		}
		if ( 'mega' === $config['type'] || 'tabs' === $config['type'] ) {
			return ( $has_children || $has_panel ) ? $config['type'] : 'link';
		}
		if ( 'flyout' === $config['type'] ) {
			return $has_children ? 'flyout' : 'link';
		}
		if ( $has_panel || ( $has_children && $grandkids ) ) {
			return 'mega';
		}
		return $has_children ? 'flyout' : 'link';
	}
}
