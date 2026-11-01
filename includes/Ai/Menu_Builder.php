<?php
/**
 * "Build my menu": reads pages, categories and products, then proposes a full mega menu draft
 * (with DEV3 AI, or with a rules-based builder when AI is off). Drafts are applied only on accept.
 *
 * @package Dev3\AiMegaMenu
 */

namespace Dev3\AiMegaMenu\Ai;

use Dev3\AiMegaMenu\Core3\Api\Prompts;
use Dev3\AiMegaMenu\Core3\Core\Settings_Store;
use Dev3\AiMegaMenu\Menu\Icons;
use Dev3\AiMegaMenu\Menu\Tree;

defined( 'ABSPATH' ) || exit;

/**
 * Draft node: { title, ref, layout (mega|flyout|link), icon, description, children[] }.
 * Refs: page:ID, post:ID, category:ID, product_cat:ID, product:ID, home, shop, blog, #.
 */
class Menu_Builder {

	const MAX_NODES = 300;

	/**
	 * Site content the builder may link to.
	 *
	 * @param array $opts { pages, posts_categories, products (bools), max_pages }.
	 * @return array { pages[], categories[], product_cats[], products[], counts }
	 */
	public static function inventory( array $opts = array() ) {
		$opts = array_merge(
			array(
				'pages'      => true,
				'categories' => true,
				'products'   => (bool) Settings_Store::get( 'ai_include_products', true ),
				'max_pages'  => (int) Settings_Store::get( 'ai_max_pages', 400 ),
			),
			$opts
		);
		$inv  = array(
			'pages'        => array(),
			'categories'   => array(),
			'product_cats' => array(),
			'products'     => array(),
		);
		if ( $opts['pages'] ) {
			$skip  = self::utility_page_ids();
			$pages = get_posts(
				array(
					'post_type'              => 'page',
					'post_status'            => 'publish',
					'numberposts'            => max( 20, (int) $opts['max_pages'] ),
					'orderby'                => array(
						'menu_order' => 'ASC',
						'title'      => 'ASC',
					),
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			foreach ( $pages as $page ) {
				$inv['pages'][] = array(
					'id'      => (int) $page->ID,
					'title'   => self::clean( get_the_title( $page ) ),
					'parent'  => (int) $page->post_parent,
					'utility' => in_array( (int) $page->ID, $skip, true ),
				);
			}
		}
		if ( $opts['categories'] ) {
			$inv['categories'] = self::terms( 'category', 200 );
		}
		if ( $opts['products'] && taxonomy_exists( 'product_cat' ) ) {
			$inv['product_cats'] = self::terms( 'product_cat', 200 );
			if ( function_exists( 'wc_get_products' ) ) {
				$ids = wc_get_products(
					array(
						'status'   => 'publish',
						'limit'    => 40,
						'return'   => 'ids',
						'orderby'  => 'meta_value_num',
						'meta_key' => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one bounded query.
						'order'    => 'DESC',
					)
				);
				foreach ( (array) $ids as $id ) {
					$inv['products'][] = array(
						'id'    => (int) $id,
						'title' => self::clean( get_the_title( $id ) ),
						'cats'  => array_map( 'intval', (array) wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'ids' ) ) ),
					);
				}
			}
		}
		$inv['counts'] = array(
			'pages'        => count( $inv['pages'] ),
			'categories'   => count( $inv['categories'] ),
			'product_cats' => count( $inv['product_cats'] ),
			'products'     => count( $inv['products'] ),
		);
		return $inv;
	}

	/**
	 * Terms of a taxonomy (non-empty ones first).
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param int    $limit    Limit.
	 * @return array[]
	 */
	private static function terms( $taxonomy, $limit ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => $limit,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		$out   = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( in_array( $term->slug, array( 'uncategorized', 'uncategorised' ), true ) && ! $term->count ) {
				continue;
			}
			$out[] = array(
				'id'     => (int) $term->term_id,
				'title'  => self::clean( $term->name ),
				'parent' => (int) $term->parent,
				'count'  => (int) $term->count,
			);
		}
		return $out;
	}

	/**
	 * Cart/checkout/account/legal pages (placed last or left out).
	 *
	 * @return int[]
	 */
	public static function utility_page_ids() {
		$ids = array( (int) get_option( 'wp_page_for_privacy_policy' ) );
		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'cart', 'checkout', 'myaccount', 'terms' ) as $page ) {
				$ids[] = (int) wc_get_page_id( $page );
			}
		}
		return array_values( array_filter( $ids, static fn( $id ) => $id > 0 ) );
	}

	/**
	 * Plain text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function clean( $text ) {
		return trim( html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Compact inventory text for the prompt.
	 *
	 * @param array $inv Inventory.
	 * @return string
	 */
	public static function inventory_text( array $inv ) {
		$lines = array( 'PAGES (ref | title | parent ref)' );
		foreach ( $inv['pages'] as $p ) {
			$lines[] = 'page:' . $p['id'] . ' | ' . $p['title'] . ' | ' . ( $p['parent'] ? 'page:' . $p['parent'] : '-' ) . ( $p['utility'] ? ' | utility' : '' );
		}
		if ( $inv['categories'] ) {
			$lines[] = '';
			$lines[] = 'POST CATEGORIES (ref | name | parent ref | posts)';
			foreach ( $inv['categories'] as $t ) {
				$lines[] = 'category:' . $t['id'] . ' | ' . $t['title'] . ' | ' . ( $t['parent'] ? 'category:' . $t['parent'] : '-' ) . ' | ' . $t['count'];
			}
		}
		if ( $inv['product_cats'] ) {
			$lines[] = '';
			$lines[] = 'PRODUCT CATEGORIES (ref | name | parent ref | products)';
			foreach ( $inv['product_cats'] as $t ) {
				$lines[] = 'product_cat:' . $t['id'] . ' | ' . $t['title'] . ' | ' . ( $t['parent'] ? 'product_cat:' . $t['parent'] : '-' ) . ' | ' . $t['count'];
			}
		}
		if ( $inv['products'] ) {
			$lines[] = '';
			$lines[] = 'BESTSELLING PRODUCTS (ref | name | category refs)';
			foreach ( $inv['products'] as $p ) {
				$lines[] = 'product:' . $p['id'] . ' | ' . $p['title'] . ' | ' . implode( ',', array_map( static fn( $c ) => 'product_cat:' . $c, $p['cats'] ) );
			}
		}
		$specials = array( 'home' );
		if ( self::shop_page_id() ) {
			$specials[] = 'shop';
		}
		if ( (int) get_option( 'page_for_posts' ) ) {
			$specials[] = 'blog';
		}
		$lines[] = '';
		$lines[] = 'SPECIAL REFS: ' . implode( ', ', $specials );
		return implode( "\n", $lines );
	}

	/**
	 * WooCommerce shop page id or 0.
	 *
	 * @return int
	 */
	private static function shop_page_id() {
		return function_exists( 'wc_get_page_id' ) ? max( 0, (int) wc_get_page_id( 'shop' ) ) : 0;
	}

	/**
	 * Builds a draft with DEV3 AI.
	 *
	 * @param array $opts { max_top, notes, pages, categories, products }.
	 * @return array|\WP_Error { draft, stats, model, tokens, cached, duration_ms }
	 */
	public static function ai( array $opts ) {
		$inv    = self::inventory( $opts );
		$result = Prompts::run(
			Prompts_Library::BUILD,
			array_merge(
				Prompts_Library::site_vars(),
				array(
					'max_top'   => min( 10, max( 3, (int) ( isset( $opts['max_top'] ) ? $opts['max_top'] : 7 ) ) ),
					'notes'     => isset( $opts['notes'] ) && '' !== trim( (string) $opts['notes'] ) ? "\n- Owner's notes: " . mb_substr( sanitize_textarea_field( (string) $opts['notes'] ), 0, 400 ) : '',
					'inventory' => self::inventory_text( $inv ),
				)
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$draft = self::from_ai( isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array(), $inv );
		if ( ! $draft ) {
			return new \WP_Error( 'dev3mm_empty_draft', __( 'DEV3 AI returned no usable menu items. Try again or use the automatic builder.', 'dev3-ai-mega-menu' ), array( 'status' => 502 ) );
		}
		return array(
			'draft'       => $draft,
			'stats'       => self::stats( $draft ),
			'inventory'   => $inv['counts'],
			'source'      => 'ai',
			'model'       => isset( $result['model'] ) ? $result['model'] : '',
			'tokens'      => isset( $result['usage']['total'] ) ? (int) $result['usage']['total'] : 0,
			'cached'      => ! empty( $result['cached'] ),
			'duration_ms' => isset( $result['duration_ms'] ) ? (int) $result['duration_ms'] : 0,
		);
	}

	/**
	 * Validates the AI output against the inventory and converts it into draft nodes.
	 *
	 * @param array $data AI data.
	 * @param array $inv  Inventory.
	 * @return array[]
	 */
	public static function from_ai( array $data, array $inv ) {
		$valid = self::valid_refs( $inv );
		$count = 0;
		$out   = array();
		foreach ( array_slice( isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array(), 0, 10 ) as $top ) {
			if ( ! is_array( $top ) || $count >= self::MAX_NODES ) {
				continue;
			}
			$groups = array();
			foreach ( array_slice( isset( $top['groups'] ) && is_array( $top['groups'] ) ? $top['groups'] : array(), 0, 6 ) as $g ) {
				if ( ! is_array( $g ) ) {
					continue;
				}
				$links = array();
				foreach ( array_slice( isset( $g['links'] ) && is_array( $g['links'] ) ? $g['links'] : array(), 0, 12 ) as $l ) {
					$node = is_array( $l ) ? self::node( $l, $valid ) : null;
					if ( $node && $count < self::MAX_NODES ) {
						$links[] = $node;
						++$count;
					}
				}
				$group = self::node( $g, $valid, true );
				if ( $group && ( $links || '#' !== $group['ref'] ) ) {
					$group['children'] = $links;
					$groups[]          = $group;
					++$count;
				}
			}
			$node = self::node( $top, $valid, true );
			if ( ! $node || ( '#' === $node['ref'] && ! $groups ) ) {
				continue;
			}
			$type             = isset( $top['type'] ) ? (string) $top['type'] : 'link';
			$node['layout']   = $groups ? ( 'mega' === $type || count( $groups ) > 1 || ! empty( $groups[0]['children'] ) ? 'mega' : 'flyout' ) : 'link';
			$node['children'] = $groups;
			$out[]            = $node;
			++$count;
		}
		return $out;
	}

	/**
	 * Index of allowed refs.
	 *
	 * @param array $inv Inventory.
	 * @return array ref => true
	 */
	public static function valid_refs( array $inv ) {
		$valid = array(
			'home' => true,
			'#'    => true,
		);
		if ( self::shop_page_id() ) {
			$valid['shop'] = true;
		}
		if ( (int) get_option( 'page_for_posts' ) ) {
			$valid['blog'] = true;
		}
		foreach ( $inv['pages'] as $p ) {
			$valid[ 'page:' . $p['id'] ] = true;
		}
		foreach ( $inv['categories'] as $t ) {
			$valid[ 'category:' . $t['id'] ] = true;
		}
		foreach ( $inv['product_cats'] as $t ) {
			$valid[ 'product_cat:' . $t['id'] ] = true;
		}
		foreach ( $inv['products'] as $p ) {
			$valid[ 'product:' . $p['id'] ] = true;
		}
		return $valid;
	}

	/**
	 * One validated node (or null).
	 *
	 * @param array $in        Raw { label, ref, icon, description }.
	 * @param array $valid     Allowed refs.
	 * @param bool  $container Whether "#" is allowed.
	 * @return array|null
	 */
	private static function node( array $in, array $valid, $container = false ) {
		$ref   = isset( $in['ref'] ) ? strtolower( trim( (string) $in['ref'] ) ) : '';
		$label = isset( $in['label'] ) ? mb_substr( sanitize_text_field( (string) $in['label'] ), 0, 40 ) : '';
		if ( '' === $ref || ! isset( $valid[ $ref ] ) || ( '#' === $ref && ! $container ) ) {
			return null;
		}
		if ( '' === $label ) {
			$label = self::ref_title( $ref );
		}
		if ( '' === $label ) {
			return null;
		}
		$icon = isset( $in['icon'] ) ? sanitize_key( (string) $in['icon'] ) : '';
		return array(
			'title'       => $label,
			'ref'         => $ref,
			'layout'      => 'link',
			'icon'        => Icons::exists( $icon ) ? $icon : '',
			'description' => isset( $in['description'] ) ? mb_substr( sanitize_text_field( (string) $in['description'] ), 0, 90 ) : '',
			'children'    => array(),
		);
	}

	/**
	 * Title of the object behind a ref.
	 *
	 * @param string $ref Ref.
	 * @return string
	 */
	public static function ref_title( $ref ) {
		if ( 'home' === $ref ) {
			return __( 'Home', 'dev3-ai-mega-menu' );
		}
		if ( ! preg_match( '/^(page|post|product|category|product_cat):(\d+)$/', (string) $ref, $m ) ) {
			return '';
		}
		if ( in_array( $m[1], array( 'page', 'post', 'product' ), true ) ) {
			return self::clean( get_the_title( (int) $m[2] ) );
		}
		$term = get_term( (int) $m[2], $m[1] );
		return $term && ! is_wp_error( $term ) ? self::clean( $term->name ) : '';
	}

	/**
	 * Rules-based draft (no AI): pages hierarchy, product categories and blog categories.
	 *
	 * @param array $opts { max_top }.
	 * @return array { draft, stats, inventory, source }
	 */
	public static function heuristic( array $opts = array() ) {
		$inv     = self::inventory( $opts );
		$max_top = min( 10, max( 3, (int) ( isset( $opts['max_top'] ) ? $opts['max_top'] : 7 ) ) );
		$draft   = array( self::make( __( 'Home', 'dev3-ai-mega-menu' ), 'home', 'link', 'home' ) );
		$front   = (int) get_option( 'page_on_front' );
		$shop    = self::shop_page_id();
		$blog    = (int) get_option( 'page_for_posts' );

		// Shop: product categories as columns.
		if ( $inv['product_cats'] ) {
			$by_parent = self::by_parent( $inv['product_cats'] );
			$groups    = array();
			foreach ( array_slice( isset( $by_parent[0] ) ? $by_parent[0] : array(), 0, 6 ) as $cat ) {
				$group = self::make( $cat['title'], 'product_cat:' . $cat['id'], 'link', Icons::match( $cat['title'] ) );
				foreach ( array_slice( isset( $by_parent[ $cat['id'] ] ) ? $by_parent[ $cat['id'] ] : array(), 0, 8 ) as $sub ) {
					$group['children'][] = self::make( $sub['title'], 'product_cat:' . $sub['id'], 'link' );
				}
				$groups[] = $group;
			}
			$top             = self::make( __( 'Shop', 'dev3-ai-mega-menu' ), $shop ? 'shop' : '#', 'mega', 'shop' );
			$top['children'] = $groups;
			$draft[]         = $top;
		}

		// Pages: top-level pages with their children.
		$pages      = array_values( array_filter( $inv['pages'], static fn( $p ) => ! $p['utility'] && ! in_array( $p['id'], array( $front, $shop, $blog ), true ) ) );
		$page_tree  = self::by_parent( $pages );
		$leaf_pages = array();
		$contact    = null;
		foreach ( isset( $page_tree[0] ) ? $page_tree[0] : array() as $page ) {
			if ( preg_match( '/contact|kontakt|contacto|contatti/i', $page['title'] ) ) {
				$contact = $page;
				continue;
			}
			$kids = isset( $page_tree[ $page['id'] ] ) ? $page_tree[ $page['id'] ] : array();
			if ( ! $kids ) {
				$leaf_pages[] = $page;
				continue;
			}
			if ( count( $draft ) >= $max_top - 1 ) {
				$leaf_pages[] = $page;
				continue;
			}
			$grand = false;
			$node  = self::make( $page['title'], 'page:' . $page['id'], 'flyout', Icons::match( $page['title'] ) );
			foreach ( array_slice( $kids, 0, 12 ) as $kid ) {
				$child = self::make( $kid['title'], 'page:' . $kid['id'], 'link' );
				foreach ( array_slice( isset( $page_tree[ $kid['id'] ] ) ? $page_tree[ $kid['id'] ] : array(), 0, 10 ) as $g ) {
					$child['children'][] = self::make( $g['title'], 'page:' . $g['id'], 'link' );
					$grand               = true;
				}
				$node['children'][] = $child;
			}
			$node['layout'] = $grand || count( $kids ) > 6 ? 'mega' : 'flyout';
			$draft[]        = $node;
		}

		// Blog categories.
		$cats = array_values( array_filter( $inv['categories'], static fn( $c ) => $c['count'] > 0 ) );
		if ( count( $cats ) >= 2 && count( $draft ) < $max_top - 1 ) {
			$node = self::make( __( 'Blog', 'dev3-ai-mega-menu' ), $blog ? 'blog' : '#', 'flyout', 'news' );
			foreach ( array_slice( $cats, 0, 10 ) as $cat ) {
				$node['children'][] = self::make( $cat['title'], 'category:' . $cat['id'], 'link' );
			}
			$draft[] = $node;
		} elseif ( $blog && count( $draft ) < $max_top - 1 ) {
			$draft[] = self::make( __( 'Blog', 'dev3-ai-mega-menu' ), 'blog', 'link', 'news' );
		}

		// Remaining top-level pages: as links while there is room, the rest under "More".
		$room = $max_top - count( $draft ) - ( $contact ? 1 : 0 );
		if ( count( $leaf_pages ) > $room && $room > 0 ) {
			$direct = array_slice( $leaf_pages, 0, max( 0, $room - 1 ) );
			$more   = self::make( __( 'More', 'dev3-ai-mega-menu' ), '#', 'flyout', '' );
			foreach ( array_slice( $leaf_pages, count( $direct ), 12 ) as $page ) {
				$more['children'][] = self::make( $page['title'], 'page:' . $page['id'], 'link' );
			}
		} else {
			$direct = array_slice( $leaf_pages, 0, max( 0, $room ) );
			$more   = null;
		}
		foreach ( $direct as $page ) {
			$draft[] = self::make( $page['title'], 'page:' . $page['id'], 'link', Icons::match( $page['title'] ) );
		}
		if ( $more && $more['children'] ) {
			$draft[] = $more;
		}
		if ( $contact ) {
			$draft[] = self::make( $contact['title'], 'page:' . $contact['id'], 'link', 'mail' );
		}
		return array(
			'draft'     => $draft,
			'stats'     => self::stats( $draft ),
			'inventory' => $inv['counts'],
			'source'    => 'rules',
		);
	}

	/**
	 * Items grouped by parent.
	 *
	 * @param array $rows Rows with id + parent.
	 * @return array
	 */
	private static function by_parent( array $rows ) {
		$ids = array();
		foreach ( $rows as $r ) {
			$ids[ $r['id'] ] = true;
		}
		$out = array();
		foreach ( $rows as $r ) {
			$parent           = $r['parent'] && isset( $ids[ $r['parent'] ] ) ? $r['parent'] : 0;
			$out[ $parent ][] = $r;
		}
		return $out;
	}

	/**
	 * Draft node factory.
	 *
	 * @param string $title  Title.
	 * @param string $ref    Ref.
	 * @param string $layout Layout.
	 * @param string $icon   Icon.
	 * @return array
	 */
	private static function make( $title, $ref, $layout, $icon = '' ) {
		return array(
			'title'       => mb_substr( (string) $title, 0, 40 ),
			'ref'         => $ref,
			'layout'      => $layout,
			'icon'        => Icons::exists( $icon ) ? $icon : '',
			'description' => '',
			'children'    => array(),
		);
	}

	/**
	 * Draft stats.
	 *
	 * @param array $draft Draft.
	 * @return array
	 */
	public static function stats( array $draft ) {
		return array(
			'top'   => count( $draft ),
			'items' => Tree::count( $draft ),
			'depth' => Tree::depth( $draft ),
			'mega'  => count( array_filter( $draft, static fn( $n ) => 'mega' === $n['layout'] ) ),
		);
	}

	/**
	 * Resolves a ref to nav item fields.
	 *
	 * @param string $ref Ref.
	 * @return array|null { type, object, object_id, url }
	 */
	public static function resolve( $ref ) {
		$ref = (string) $ref;
		if ( 'home' === $ref ) {
			return array(
				'type'      => 'custom',
				'object'    => 'custom',
				'object_id' => 0,
				'url'       => home_url( '/' ),
			);
		}
		if ( '#' === $ref || '' === $ref ) {
			return array(
				'type'      => 'custom',
				'object'    => 'custom',
				'object_id' => 0,
				'url'       => '#',
			);
		}
		if ( 'shop' === $ref ) {
			$ref = self::shop_page_id() ? 'page:' . self::shop_page_id() : '#';
			return self::resolve( $ref );
		}
		if ( 'blog' === $ref ) {
			$blog = (int) get_option( 'page_for_posts' );
			return $blog ? self::resolve( 'page:' . $blog ) : self::resolve( 'home' );
		}
		if ( ! preg_match( '/^(page|post|product|category|product_cat):(\d+)$/', $ref, $m ) ) {
			return null;
		}
		$id = (int) $m[2];
		if ( in_array( $m[1], array( 'page', 'post', 'product' ), true ) ) {
			$post = get_post( $id );
			if ( ! $post || $post->post_type !== $m[1] ) {
				return null;
			}
			return array(
				'type'      => 'post_type',
				'object'    => $m[1],
				'object_id' => $id,
				'url'       => (string) get_permalink( $id ),
			);
		}
		$term = get_term( $id, $m[1] );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}
		return array(
			'type'      => 'taxonomy',
			'object'    => $m[1],
			'object_id' => $id,
			'url'       => (string) get_term_link( $term ),
		);
	}

	/**
	 * Converts draft nodes into builder tree nodes (negative ids = new).
	 *
	 * @param array $draft Draft nodes.
	 * @param int   $next  Next temp id (by reference).
	 * @param int   $depth Depth.
	 * @return array[]
	 */
	public static function to_tree( array $draft, &$next = -1, $depth = 0 ) {
		$out = array();
		foreach ( $draft as $node ) {
			if ( ! is_array( $node ) || $depth > 2 ) {
				continue;
			}
			$target = self::resolve( isset( $node['ref'] ) ? $node['ref'] : '#' );
			if ( ! $target ) {
				continue;
			}
			$layout = isset( $node['layout'] ) ? (string) $node['layout'] : 'link';
			$config = array( 'icon' => isset( $node['icon'] ) ? (string) $node['icon'] : '' );
			if ( 0 === $depth && in_array( $layout, array( 'mega', 'flyout' ), true ) ) {
				$config['type'] = $layout;
			}
			if ( '' !== ( isset( $node['description'] ) ? (string) $node['description'] : '' ) && 1 === $depth ) {
				$config['show_description'] = true;
			}
			$out[] = array_merge(
				$target,
				array(
					'id'          => $next--,
					'title'       => isset( $node['title'] ) ? (string) $node['title'] : '',
					'description' => isset( $node['description'] ) ? (string) $node['description'] : '',
					'config'      => $config,
					'children'    => ! empty( $node['children'] ) && is_array( $node['children'] ) ? self::to_tree( $node['children'], $next, $depth + 1 ) : array(),
				)
			);
		}
		return $out;
	}

	/**
	 * Applies an accepted draft: into a new menu, or replacing / appending to an existing one.
	 *
	 * @param array $draft  Draft nodes.
	 * @param array $target { mode: new|replace|append, name, menu_id, location }.
	 * @return array|\WP_Error { menu_id, tree }
	 */
	public static function apply( array $draft, array $target ) {
		$mode = isset( $target['mode'] ) && in_array( $target['mode'], array( 'new', 'replace', 'append' ), true ) ? $target['mode'] : 'new';
		$tree = self::to_tree( $draft );
		if ( ! $tree ) {
			return new \WP_Error( 'dev3mm_empty_draft', __( 'The draft has no items to add.', 'dev3-ai-mega-menu' ), array( 'status' => 400 ) );
		}
		if ( 'new' === $mode ) {
			$name = isset( $target['name'] ) && '' !== trim( (string) $target['name'] ) ? sanitize_text_field( (string) $target['name'] ) : __( 'Main menu', 'dev3-ai-mega-menu' );
			$base = $name;
			$i    = 2;
			while ( wp_get_nav_menu_object( $name ) ) {
				$name = $base . ' (' . $i . ')';
				++$i;
			}
			$menu_id = wp_create_nav_menu( $name );
			if ( is_wp_error( $menu_id ) ) {
				return $menu_id;
			}
		} else {
			$menu_id = isset( $target['menu_id'] ) ? (int) $target['menu_id'] : 0;
			if ( ! is_nav_menu( $menu_id ) ) {
				return new \WP_Error( 'dev3mm_no_menu', __( 'That menu does not exist.', 'dev3-ai-mega-menu' ), array( 'status' => 404 ) );
			}
			if ( 'append' === $mode ) {
				$tree = array_merge( Tree::get( $menu_id ), $tree );
			}
		}
		$saved = Tree::save( (int) $menu_id, $tree );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$location = isset( $target['location'] ) ? sanitize_key( (string) $target['location'] ) : '';
		if ( '' !== $location && array_key_exists( $location, get_registered_nav_menus() ) ) {
			$locations              = get_nav_menu_locations();
			$locations[ $location ] = (int) $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
		}
		return array(
			'menu_id' => (int) $menu_id,
			'tree'    => $saved,
		);
	}
}
