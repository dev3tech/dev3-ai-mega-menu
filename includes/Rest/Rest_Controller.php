<?php
/**
 * Plugin REST routes (dev3mm/v1): menus, builder, locations, styles, previews and DEV3 AI drafts.
 *
 * @package Dev3\AiMegaMenu
 */

namespace Dev3\AiMegaMenu\Rest;

use Dev3\AiMegaMenu\Ai\Assistant;
use Dev3\AiMegaMenu\Ai\Audit;
use Dev3\AiMegaMenu\Ai\Menu_Builder;
use Dev3\AiMegaMenu\Core3\Api\Ai;
use Dev3\AiMegaMenu\Core3\Core\Settings_Store;
use Dev3\AiMegaMenu\Core3\Rest\Base_Controller;
use Dev3\AiMegaMenu\Front\Renderer;
use Dev3\AiMegaMenu\Menu\Item_Config;
use Dev3\AiMegaMenu\Menu\Locations;
use Dev3\AiMegaMenu\Menu\Themes;
use Dev3\AiMegaMenu\Menu\Tree;

defined( 'ABSPATH' ) || exit;

/**
 * Routes. Every route needs the manage capability plus a REST nonce. AI routes only return drafts;
 * the apply routes save what the admin accepted.
 */
class Rest_Controller extends Base_Controller {

	/**
	 * Registers routes.
	 */
	public function register_routes() {
		$manage = array( $this, 'can_manage' );
		$id     = '(?P<id>\d+)';
		$routes = array(
			'/overview'                => array( 'GET', 'overview' ),
			'/menus'                   => array(
				array( 'GET', 'menus' ),
				array( 'POST', 'create_menu' ),
			),
			'/menus/' . $id            => array(
				array( 'GET', 'get_menu' ),
				array( 'POST', 'save_menu' ),
				array( 'DELETE', 'delete_menu' ),
			),
			'/menus/' . $id . '/items' => array( 'POST', 'patch_items' ),
			'/menus/' . $id . '/promo' => array( 'POST', 'insert_promo' ),
			'/locations'               => array(
				array( 'GET', 'locations' ),
				array( 'POST', 'save_locations' ),
			),
			'/themes'                  => array(
				array( 'GET', 'themes' ),
				array( 'POST', 'save_themes' ),
			),
			'/preview'                 => array( 'POST', 'preview' ),
			'/content'                 => array( 'GET', 'content' ),
			'/build'                   => array( 'POST', 'build' ),
			'/build/apply'             => array( 'POST', 'apply_build' ),
			'/audit'                   => array( 'POST', 'audit' ),
			'/ai/labels'               => array( 'POST', 'labels' ),
			'/ai/icons'                => array( 'POST', 'icons' ),
			'/ai/promo'                => array( 'POST', 'promo' ),
		);
		foreach ( $routes as $path => $defs ) {
			$defs = is_string( $defs[0] ) ? array( $defs ) : $defs;
			$args = array();
			foreach ( $defs as $def ) {
				$args[] = array(
					'methods'             => $def[0],
					'callback'            => array( $this, $def[1] ),
					'permission_callback' => $manage,
				);
			}
			register_rest_route( self::NS, $path, $args );
		}
	}

	/**
	 * Body param as array.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string           $key     Key.
	 * @return array
	 */
	private static function arr( \WP_REST_Request $request, $key ) {
		$value = $request->get_param( $key );
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : array();
		}
		return is_array( $value ) ? $value : array();
	}

	/**
	 * GET /overview: dashboard numbers.
	 *
	 * @return \WP_REST_Response
	 */
	public function overview() {
		$menus    = wp_get_nav_menus();
		$mega     = 0;
		$items    = 0;
		$with_ai  = 0;
		$assigned = get_nav_menu_locations();
		foreach ( $menus as $menu ) {
			foreach ( Tree::items( $menu->term_id ) as $item ) {
				++$items;
				if ( 0 === (int) $item->menu_item_parent ) {
					$config = Item_Config::get( (int) $item->ID );
					if ( in_array( $config['type'], array( 'mega', 'tabs' ), true ) || $config['rows'] || $config['aside'] ) {
						++$mega;
					}
					if ( $config['aside'] ) {
						++$with_ai;
					}
				}
			}
		}
		$active = 0;
		foreach ( get_registered_nav_menus() as $location => $label ) {
			unset( $label );
			if ( Locations::enabled( $location ) && ! empty( $assigned[ $location ] ) ) {
				++$active;
			}
		}
		return $this->respond(
			array(
				'menus'           => count( $menus ),
				'items'           => $items,
				'mega_items'      => $mega,
				'promos'          => $with_ai,
				'locations'       => count( get_registered_nav_menus() ),
				'active'          => $active,
				'block_theme'     => function_exists( 'wp_is_block_theme' ) && wp_is_block_theme(),
				'block_replace'   => Settings_Store::get( 'block_nav_replace', 'off' ),
				'woocommerce'     => class_exists( 'WooCommerce' ),
				'elementor'       => did_action( 'elementor/loaded' ) > 0,
				'wpbakery'        => defined( 'WPB_VC_VERSION' ),
				'themes'          => count( Themes::all() ),
				'last_audit'      => Settings_Store::get( 'last_audit', array() ),
				'ai'              => Ai::status(),
				'theme_name'      => wp_get_theme()->get( 'Name' ),
				'customize_url'   => admin_url( 'nav-menus.php' ),
				'site_editor_url' => admin_url( 'site-editor.php' ),
			)
		);
	}

	/**
	 * Menu summary.
	 *
	 * @param \WP_Term $menu Menu.
	 * @return array
	 */
	private static function menu_row( $menu ) {
		$locations  = array();
		$registered = get_registered_nav_menus();
		foreach ( get_nav_menu_locations() as $location => $menu_id ) {
			if ( (int) $menu_id === (int) $menu->term_id && isset( $registered[ $location ] ) ) {
				$locations[] = array(
					'location' => $location,
					'label'    => $registered[ $location ],
					'enabled'  => Locations::enabled( $location ),
				);
			}
		}
		return array(
			'id'        => (int) $menu->term_id,
			'name'      => $menu->name,
			'slug'      => $menu->slug,
			'count'     => (int) $menu->count,
			'locations' => $locations,
			'is_block'  => (int) Settings_Store::get( 'block_nav_menu', 0 ) === (int) $menu->term_id,
		);
	}

	/**
	 * GET /menus.
	 *
	 * @return \WP_REST_Response
	 */
	public function menus() {
		return $this->respond( array_map( array( __CLASS__, 'menu_row' ), wp_get_nav_menus() ) );
	}

	/**
	 * POST /menus { name }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function create_menu( \WP_REST_Request $request ) {
		$name = trim( sanitize_text_field( (string) $request->get_param( 'name' ) ) );
		if ( '' === $name ) {
			return $this->respond( new \WP_Error( 'dev3mm_name', __( 'Give the menu a name.', 'dev3-ai-mega-menu' ), array( 'status' => 400 ) ) );
		}
		$id = wp_create_nav_menu( mb_substr( $name, 0, 80 ) );
		if ( is_wp_error( $id ) ) {
			$id->add_data( array( 'status' => 400 ) );
			return $this->respond( $id );
		}
		return $this->respond( self::menu_row( wp_get_nav_menu_object( $id ) ), 201 );
	}

	/**
	 * GET /menus/<id>.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_menu( \WP_REST_Request $request ) {
		$menu = wp_get_nav_menu_object( (int) $request['id'] );
		if ( ! $menu ) {
			return $this->respond( new \WP_Error( 'dev3mm_no_menu', __( 'That menu does not exist.', 'dev3-ai-mega-menu' ), array( 'status' => 404 ) ) );
		}
		return $this->respond(
			array(
				'menu' => self::menu_row( $menu ),
				'tree' => Tree::get( $menu->term_id ),
			)
		);
	}

	/**
	 * POST /menus/<id> { tree, name }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_menu( \WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$name = trim( sanitize_text_field( (string) $request->get_param( 'name' ) ) );
		if ( '' !== $name && is_nav_menu( $id ) ) {
			$renamed = wp_update_nav_menu_object( $id, array( 'menu-name' => mb_substr( $name, 0, 80 ) ) );
			if ( is_wp_error( $renamed ) ) {
				$renamed->add_data( array( 'status' => 400 ) );
				return $this->respond( $renamed );
			}
		}
		$tree = Tree::save( $id, self::arr( $request, 'tree' ) );
		if ( is_wp_error( $tree ) ) {
			return $this->respond( $tree );
		}
		return $this->respond(
			array(
				'menu' => self::menu_row( wp_get_nav_menu_object( $id ) ),
				'tree' => $tree,
			)
		);
	}

	/**
	 * DELETE /menus/<id>.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_menu( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! is_nav_menu( $id ) ) {
			return $this->respond( new \WP_Error( 'dev3mm_no_menu', __( 'That menu does not exist.', 'dev3-ai-mega-menu' ), array( 'status' => 404 ) ) );
		}
		$deleted = wp_delete_nav_menu( $id );
		if ( is_wp_error( $deleted ) ) {
			return $this->respond( $deleted );
		}
		return $this->respond( array( 'deleted' => true ) );
	}

	/**
	 * POST /menus/<id>/items { items: [ { id, title?, description?, config? (partial) } ] }:
	 * applies accepted AI drafts (labels, icons, audit fixes) to existing items.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function patch_items( \WP_REST_Request $request ) {
		$menu_id = (int) $request['id'];
		if ( ! is_nav_menu( $menu_id ) ) {
			return $this->respond( new \WP_Error( 'dev3mm_no_menu', __( 'That menu does not exist.', 'dev3-ai-mega-menu' ), array( 'status' => 404 ) ) );
		}
		$tree    = Tree::get( $menu_id );
		$patches = array();
		foreach ( array_slice( self::arr( $request, 'items' ), 0, 300 ) as $patch ) {
			if ( is_array( $patch ) && isset( $patch['id'] ) ) {
				$patches[ (int) $patch['id'] ] = $patch;
			}
		}
		$updated = 0;
		$tree    = self::patch_branch( $tree, $patches, $updated );
		$saved   = Tree::save( $menu_id, $tree );
		if ( is_wp_error( $saved ) ) {
			return $this->respond( $saved );
		}
		return $this->respond(
			array(
				'updated' => $updated,
				'tree'    => $saved,
			)
		);
	}

	/**
	 * Applies patches recursively.
	 *
	 * @param array $nodes   Nodes.
	 * @param array $patches id => patch.
	 * @param int   $updated Count (by reference).
	 * @return array
	 */
	private static function patch_branch( array $nodes, array $patches, &$updated ) {
		foreach ( $nodes as $i => $node ) {
			if ( isset( $patches[ $node['id'] ] ) ) {
				$p = $patches[ $node['id'] ];
				if ( isset( $p['title'] ) && '' !== trim( (string) $p['title'] ) ) {
					$nodes[ $i ]['title'] = (string) $p['title'];
				}
				if ( isset( $p['description'] ) ) {
					$nodes[ $i ]['description'] = (string) $p['description'];
				}
				if ( isset( $p['config'] ) && is_array( $p['config'] ) ) {
					$nodes[ $i ]['config'] = array_merge( $node['config'], $p['config'] );
				}
				++$updated;
			}
			if ( ! empty( $node['children'] ) ) {
				$nodes[ $i ]['children'] = self::patch_branch( $node['children'], $patches, $updated );
			}
		}
		return $nodes;
	}

	/**
	 * GET /locations.
	 *
	 * @return \WP_REST_Response
	 */
	public function locations() {
		return $this->respond(
			array(
				'locations'   => Locations::describe(),
				'menus'       => array_map( array( __CLASS__, 'menu_row' ), wp_get_nav_menus() ),
				'themes'      => array_values(
					array_map(
						static fn( $t ) => array(
							'id'   => $t['id'],
							'name' => $t['name'],
						),
						Themes::all()
					)
				),
				'breakpoints' => Locations::BREAKPOINTS,
				'block_theme' => function_exists( 'wp_is_block_theme' ) && wp_is_block_theme(),
			)
		);
	}

	/**
	 * POST /locations { configs: { location: config }, assignments: { location: menu_id } }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_locations( \WP_REST_Request $request ) {
		Locations::save( self::arr( $request, 'configs' ) );
		$assignments = self::arr( $request, 'assignments' );
		if ( $assignments ) {
			$registered = get_registered_nav_menus();
			$current    = get_nav_menu_locations();
			foreach ( $assignments as $location => $menu_id ) {
				if ( isset( $registered[ $location ] ) ) {
					$menu_id              = (int) $menu_id;
					$current[ $location ] = $menu_id && is_nav_menu( $menu_id ) ? $menu_id : 0;
				}
			}
			set_theme_mod( 'nav_menu_locations', $current );
		}
		return $this->locations();
	}

	/**
	 * GET /themes.
	 *
	 * @return \WP_REST_Response
	 */
	public function themes() {
		return $this->respond(
			array(
				'presets' => array_values( Themes::presets() ),
				'custom'  => array_values( Themes::custom() ),
				'base'    => Themes::base_tokens(),
			)
		);
	}

	/**
	 * POST /themes { custom: [ { id, name, tokens } ] }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_themes( \WP_REST_Request $request ) {
		Themes::save_custom( self::arr( $request, 'custom' ) );
		return $this->themes();
	}

	/**
	 * POST /preview { menu_id, location, config, tokens }: real front-end markup for the admin preview.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function preview( \WP_REST_Request $request ) {
		$menu_id  = (int) $request->get_param( 'menu_id' );
		$location = sanitize_key( (string) $request->get_param( 'location' ) );
		$location = '' !== $location ? $location : Locations::EMBED;
		$config   = self::arr( $request, 'config' );
		$tokens   = self::arr( $request, 'tokens' );
		if ( $tokens ) {
			$config['tokens'] = Themes::sanitize_tokens( $tokens );
		}
		$tree  = self::arr( $request, 'tree' );
		$draft = self::arr( $request, 'draft' );
		if ( $draft ) {
			$tree = Menu_Builder::to_tree( $draft );
		}
		if ( $tree ) {
			$menu = wp_get_nav_menu_object( $menu_id );
			return $this->respond( array( 'html' => Renderer::render_tree( $tree, $menu ? $menu->name : '', $location, $config ) ) );
		}
		return $this->respond( array( 'html' => Renderer::render( $menu_id, $location, $config ) ) );
	}

	/**
	 * GET /content?type=&search=: pickers for new items, widgets and promos.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function content( \WP_REST_Request $request ) {
		$type   = sanitize_key( (string) $request->get_param( 'type' ) );
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$limit  = min( 50, max( 5, (int) ( $request->get_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 20 ) ) );
		$out    = array();
		if ( in_array( $type, array( 'category', 'product_cat', 'post_tag' ), true ) ) {
			if ( ! taxonomy_exists( $type ) ) {
				return $this->respond( array() );
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $type,
					'hide_empty' => false,
					'number'     => $limit,
					'search'     => $search,
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				$out[] = array(
					'id'     => (int) $term->term_id,
					'title'  => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
					'type'   => 'taxonomy',
					'object' => $type,
					'url'    => (string) get_term_link( $term ),
					'meta'   => (int) $term->count,
				);
			}
			return $this->respond( $out );
		}
		$post_types = array( 'page', 'post', 'product', 'wp_block', 'attachment' );
		if ( ! in_array( $type, $post_types, true ) || ! post_type_exists( $type ) ) {
			return $this->respond( array() );
		}
		$posts = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'attachment' === $type ? 'inherit' : 'publish',
				'post_mime_type' => 'attachment' === $type ? 'image' : '',
				'numberposts'    => $limit,
				's'              => $search,
				'orderby'        => '' === $search ? 'date' : 'relevance',
			)
		);
		foreach ( $posts as $post ) {
			$row = array(
				'id'     => (int) $post->ID,
				'title'  => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				'type'   => 'post_type',
				'object' => $type,
				'url'    => 'attachment' === $type ? (string) wp_get_attachment_url( $post->ID ) : (string) get_permalink( $post ),
			);
			if ( 'attachment' === $type ) {
				$row['thumb'] = (string) wp_get_attachment_image_url( $post->ID, 'thumbnail' );
			}
			$out[] = $row;
		}
		return $this->respond( $out );
	}

	/**
	 * POST /build { mode: ai|rules, max_top, notes, pages, categories, products }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function build( \WP_REST_Request $request ) {
		$opts = array(
			'max_top' => (int) ( $request->get_param( 'max_top' ) ? $request->get_param( 'max_top' ) : 7 ),
			'notes'   => (string) $request->get_param( 'notes' ),
		);
		foreach ( array( 'pages', 'categories', 'products' ) as $flag ) {
			if ( null !== $request->get_param( $flag ) ) {
				$opts[ $flag ] = rest_sanitize_boolean( $request->get_param( $flag ) );
			}
		}
		$result = 'rules' === $request->get_param( 'mode' ) ? Menu_Builder::heuristic( $opts ) : Menu_Builder::ai( $opts );
		return $this->respond( $result );
	}

	/**
	 * POST /build/apply { draft, target: { mode, name, menu_id, location } }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function apply_build( \WP_REST_Request $request ) {
		$result = Menu_Builder::apply( self::arr( $request, 'draft' ), self::arr( $request, 'target' ) );
		if ( is_wp_error( $result ) ) {
			return $this->respond( $result );
		}
		$result['menu'] = self::menu_row( wp_get_nav_menu_object( $result['menu_id'] ) );
		return $this->respond( $result, 201 );
	}

	/**
	 * POST /audit { menu_id, ai }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function audit( \WP_REST_Request $request ) {
		return $this->respond( Audit::run( (int) $request->get_param( 'menu_id' ), rest_sanitize_boolean( $request->get_param( 'ai' ) ) ) );
	}

	/**
	 * POST /ai/labels { menu_id, item_ids }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function labels( \WP_REST_Request $request ) {
		return $this->respond( Assistant::labels( (int) $request->get_param( 'menu_id' ), array_map( 'intval', self::arr( $request, 'item_ids' ) ) ) );
	}

	/**
	 * POST /ai/icons { menu_id, ai }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function icons( \WP_REST_Request $request ) {
		$use_ai = null === $request->get_param( 'ai' ) ? true : rest_sanitize_boolean( $request->get_param( 'ai' ) );
		return $this->respond( Assistant::icons( (int) $request->get_param( 'menu_id' ), $use_ai && Ai::enabled() ) );
	}

	/**
	 * POST /ai/promo { occasion, tone, offer, source, category, product_ids, cta_url, ai }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function promo( \WP_REST_Request $request ) {
		$opts   = array(
			'occasion'    => (string) $request->get_param( 'occasion' ),
			'tone'        => (string) $request->get_param( 'tone' ),
			'offer'       => (string) $request->get_param( 'offer' ),
			'source'      => sanitize_key( (string) $request->get_param( 'source' ) ),
			'category'    => (int) $request->get_param( 'category' ),
			'product_ids' => array_map( 'intval', self::arr( $request, 'product_ids' ) ),
			'cta_url'     => (string) $request->get_param( 'cta_url' ),
		);
		$use_ai = null === $request->get_param( 'ai' ) ? true : rest_sanitize_boolean( $request->get_param( 'ai' ) );
		return $this->respond( $use_ai ? Assistant::promo( $opts ) : Assistant::promo_template( $opts ) );
	}

	/**
	 * POST /menus/<id>/promo { item_id, widget }: saves an accepted promo into a mega panel.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function insert_promo( \WP_REST_Request $request ) {
		$item_id = (int) $request->get_param( 'item_id' );
		$ids     = wp_list_pluck( Tree::items( (int) $request['id'] ), 'ID' );
		if ( ! in_array( $item_id, array_map( 'intval', $ids ), true ) ) {
			return $this->respond( new \WP_Error( 'dev3mm_no_item', __( 'Pick a top-level item of this menu.', 'dev3-ai-mega-menu' ), array( 'status' => 400 ) ) );
		}
		$config = Assistant::insert_promo( $item_id, self::arr( $request, 'widget' ) );
		if ( is_wp_error( $config ) ) {
			return $this->respond( $config );
		}
		return $this->respond(
			array(
				'config' => $config,
				'tree'   => Tree::get( (int) $request['id'] ),
			)
		);
	}
}
