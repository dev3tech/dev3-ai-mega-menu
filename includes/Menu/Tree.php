<?php
/**
 * Reads a WordPress nav menu as a nested tree (with item configs) and saves a tree back.
 *
 * @package Dev3\AiMegaMenu
 */

namespace Dev3\AiMegaMenu\Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Tree node: { id, title, url, type, object, object_id, description, attr_title, target, classes,
 * invalid, config, children[] }. New nodes sent by the builder use negative ids.
 */
class Tree {

	const MAX_ITEMS = 600;

	/**
	 * Flat, ordered items of a menu.
	 *
	 * @param int $menu_id Menu term id.
	 * @return \WP_Post[]
	 */
	public static function items( $menu_id ) {
		$items = wp_get_nav_menu_items( (int) $menu_id, array( 'update_post_term_cache' => false ) );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Nested tree.
	 *
	 * @param int  $menu_id     Menu term id.
	 * @param bool $with_config Include item configs.
	 * @return array[]
	 */
	public static function get( $menu_id, $with_config = true ) {
		$by_parent = array();
		foreach ( self::items( $menu_id ) as $item ) {
			$by_parent[ (int) $item->menu_item_parent ][] = $item;
		}
		return self::branch( $by_parent, 0, $with_config, 0 );
	}

	/**
	 * Builds one level.
	 *
	 * @param array $by_parent   parent id => items.
	 * @param int   $parent_id      Parent id.
	 * @param bool  $with_config Include configs.
	 * @param int   $depth       Depth guard.
	 * @return array[]
	 */
	private static function branch( array $by_parent, $parent_id, $with_config, $depth ) {
		if ( empty( $by_parent[ $parent_id ] ) || $depth > 8 ) {
			return array();
		}
		$out = array();
		foreach ( $by_parent[ $parent_id ] as $item ) {
			$node = self::node( $item, $with_config );
			// Only list children here; the item id is used as the parent key.
			$node['children'] = self::branch( $by_parent, (int) $item->ID, $with_config, $depth + 1 );
			$out[]            = $node;
		}
		return $out;
	}

	/**
	 * One node from a decorated nav menu item.
	 *
	 * @param \WP_Post $item        Item.
	 * @param bool     $with_config Include config.
	 * @return array
	 */
	public static function node( $item, $with_config = true ) {
		$invalid = ! empty( $item->_invalid );
		if ( ! $invalid && 'post_type' === $item->type ) {
			$invalid = 'publish' !== get_post_status( (int) $item->object_id );
		}
		$node = array(
			'id'          => (int) $item->ID,
			'title'       => html_entity_decode( (string) $item->title, ENT_QUOTES, 'UTF-8' ),
			'url'         => (string) $item->url,
			'type'        => (string) $item->type,
			'object'      => (string) $item->object,
			'object_id'   => (int) $item->object_id,
			'type_label'  => (string) $item->type_label,
			'description' => (string) $item->description,
			'attr_title'  => (string) $item->attr_title,
			'target'      => (string) $item->target,
			'classes'     => trim( implode( ' ', array_filter( (array) $item->classes ) ) ),
			'invalid'     => $invalid,
		);
		if ( $with_config ) {
			$node['config'] = Item_Config::get( (int) $item->ID );
		}
		return $node;
	}

	/**
	 * Saves a full tree: updates existing items, creates new ones (negative ids), deletes missing ones.
	 *
	 * @param int   $menu_id Menu term id.
	 * @param array $tree    Nodes.
	 * @return array|\WP_Error Saved tree.
	 */
	public static function save( $menu_id, array $tree ) {
		$menu_id = (int) $menu_id;
		if ( ! is_nav_menu( $menu_id ) ) {
			return new \WP_Error( 'dev3mm_no_menu', __( 'That menu does not exist.', 'dev3-ai-mega-menu' ), array( 'status' => 404 ) );
		}
		if ( self::count( $tree ) > self::MAX_ITEMS ) {
			return new \WP_Error( 'dev3mm_too_many', __( 'A menu can have at most 600 items.', 'dev3-ai-mega-menu' ), array( 'status' => 400 ) );
		}
		$existing = array();
		foreach ( self::items( $menu_id ) as $item ) {
			$existing[ (int) $item->ID ] = true;
		}
		$map      = array();
		$configs  = array();
		$position = 0;
		$result   = self::save_branch( $menu_id, $tree, 0, $existing, $map, $configs, $position, 0 );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		// Second pass: configs (widgets may reference items that were new in this save).
		foreach ( $configs as $item_id => $config ) {
			Item_Config::save( $item_id, self::remap_config( $config, $map ) );
		}
		foreach ( array_keys( $existing ) as $id ) {
			if ( ! in_array( $id, $map, true ) ) {
				wp_delete_post( $id, true );
			}
		}
		/**
		 * Fires after the builder saved a menu.
		 *
		 * @param int $menu_id Menu id.
		 */
		do_action( 'dev3mm_menu_saved', $menu_id );
		return self::get( $menu_id );
	}

	/**
	 * Saves one level.
	 *
	 * @param int   $menu_id  Menu.
	 * @param array $nodes    Nodes.
	 * @param int   $parent_id   Parent db id.
	 * @param array $existing Existing ids.
	 * @param array $map      Client id => db id (by reference).
	 * @param array $configs  Db id => raw config (by reference).
	 * @param int   $position Running position (by reference).
	 * @param int   $depth    Depth.
	 * @return true|\WP_Error
	 */
	private static function save_branch( $menu_id, array $nodes, $parent_id, array $existing, array &$map, array &$configs, &$position, $depth ) {
		if ( $depth > 8 ) {
			return true;
		}
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$client_id = isset( $node['id'] ) ? (int) $node['id'] : 0;
			$db_id     = $client_id > 0 && isset( $existing[ $client_id ] ) ? $client_id : 0;
			++$position;
			$saved = wp_update_nav_menu_item( $menu_id, $db_id, self::item_args( $node, $parent_id, $position ) );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			$map[ 0 !== $client_id ? $client_id : 'pos' . $position ] = (int) $saved;
			if ( isset( $node['config'] ) && is_array( $node['config'] ) ) {
				$configs[ (int) $saved ] = $node['config'];
			} elseif ( ! $db_id ) {
				$configs[ (int) $saved ] = array();
			}
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				$res = self::save_branch( $menu_id, $node['children'], (int) $saved, $existing, $map, $configs, $position, $depth + 1 );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
			}
		}
		return true;
	}

	/**
	 * Args for wp_update_nav_menu_item().
	 *
	 * @param array $node     Node.
	 * @param int   $parent_id   Parent id.
	 * @param int   $position Position.
	 * @return array
	 */
	public static function item_args( array $node, $parent_id, $position ) {
		$type   = isset( $node['type'] ) && in_array( $node['type'], array( 'custom', 'post_type', 'taxonomy', 'post_type_archive' ), true ) ? $node['type'] : 'custom';
		$object = isset( $node['object'] ) ? sanitize_key( (string) $node['object'] ) : '';
		$oid    = isset( $node['object_id'] ) ? max( 0, (int) $node['object_id'] ) : 0;
		if ( 'post_type' === $type && ( ! $oid || ! get_post( $oid ) ) ) {
			$type = 'custom';
		}
		if ( 'taxonomy' === $type && ( ! $oid || ! term_exists( $oid, $object ) ) ) {
			$type = 'custom';
		}
		$args = array(
			'menu-item-title'       => isset( $node['title'] ) ? mb_substr( sanitize_text_field( (string) $node['title'] ), 0, 120 ) : '',
			'menu-item-type'        => $type,
			'menu-item-object'      => 'custom' === $type ? 'custom' : $object,
			'menu-item-object-id'   => 'custom' === $type ? 0 : $oid,
			'menu-item-parent-id'   => (int) $parent_id,
			'menu-item-position'    => (int) $position,
			'menu-item-description' => isset( $node['description'] ) ? mb_substr( sanitize_textarea_field( (string) $node['description'] ), 0, 300 ) : '',
			'menu-item-attr-title'  => isset( $node['attr_title'] ) ? mb_substr( sanitize_text_field( (string) $node['attr_title'] ), 0, 120 ) : '',
			'menu-item-target'      => isset( $node['target'] ) && '_blank' === $node['target'] ? '_blank' : '',
			'menu-item-classes'     => isset( $node['classes'] ) ? implode( ' ', array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $node['classes'], -1, PREG_SPLIT_NO_EMPTY ) ) ) : '',
			'menu-item-status'      => 'publish',
		);
		if ( 'custom' === $type ) {
			$url                   = isset( $node['url'] ) ? trim( (string) $node['url'] ) : '';
			$args['menu-item-url'] = '' === $url || '#' === $url ? '#' : esc_url_raw( $url );
		}
		return $args;
	}

	/**
	 * Replaces client ids in `item` widgets with saved ids.
	 *
	 * @param array $config Raw config.
	 * @param array $map    Client id => db id.
	 * @return array
	 */
	private static function remap_config( array $config, array $map ) {
		if ( empty( $config['rows'] ) || ! is_array( $config['rows'] ) ) {
			return $config;
		}
		foreach ( $config['rows'] as $r => $row ) {
			if ( empty( $row['cols'] ) || ! is_array( $row['cols'] ) ) {
				continue;
			}
			foreach ( $row['cols'] as $c => $col ) {
				if ( empty( $col['widgets'] ) || ! is_array( $col['widgets'] ) ) {
					continue;
				}
				foreach ( $col['widgets'] as $w => $widget ) {
					if ( is_array( $widget ) && isset( $widget['type'], $widget['item_id'] ) && 'item' === $widget['type'] ) {
						$id = (int) $widget['item_id'];
						$config['rows'][ $r ]['cols'][ $c ]['widgets'][ $w ]['item_id'] = isset( $map[ $id ] ) ? $map[ $id ] : max( 0, $id );
					}
				}
			}
		}
		return $config;
	}

	/**
	 * Node count.
	 *
	 * @param array $nodes Nodes.
	 * @return int
	 */
	public static function count( array $nodes ) {
		$n = 0;
		foreach ( $nodes as $node ) {
			++$n;
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				$n += self::count( $node['children'] );
			}
		}
		return $n;
	}

	/**
	 * Maximum depth (1 = only top-level items).
	 *
	 * @param array $nodes Nodes.
	 * @return int
	 */
	public static function depth( array $nodes ) {
		$max = 0;
		foreach ( $nodes as $node ) {
			$max = max( $max, 1 + ( ! empty( $node['children'] ) && is_array( $node['children'] ) ? self::depth( $node['children'] ) : 0 ) );
		}
		return $max;
	}

	/**
	 * Flat list with depth and parent title.
	 *
	 * @param array  $nodes  Nodes.
	 * @param int    $depth  Depth.
	 * @param string $parent_id Parent title.
	 * @return array[]
	 */
	public static function flatten( array $nodes, $depth = 0, $parent_id = '' ) {
		$out = array();
		foreach ( $nodes as $node ) {
			$children       = ! empty( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();
			$flat           = $node;
			$flat['depth']  = $depth;
			$flat['parent'] = $parent_id;
			$flat['kids']   = count( $children );
			unset( $flat['children'] );
			$out[] = $flat;
			$out   = array_merge( $out, self::flatten( $children, $depth + 1, isset( $node['title'] ) ? (string) $node['title'] : '' ) );
		}
		return $out;
	}
}
