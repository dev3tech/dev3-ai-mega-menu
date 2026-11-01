<?php
/**
 * REST routes: permissions and the main admin flows.
 *
 * @package Dev3\AiMegaMenu
 */

require_once __DIR__ . '/helpers.php';

use Dev3\AiMegaMenu\Menu\Item_Config;
use Dev3\AiMegaMenu\Menu\Locations;
use Dev3\AiMegaMenu\Menu\Themes;
use Dev3\AiMegaMenu\Menu\Tree;

class RestTest extends Dev3mm_TestCase {

	public function set_up() {
		parent::set_up();
		do_action( 'rest_api_init' );
	}

	public function test_routes_require_a_manager_and_a_nonce() {
		$routes = array(
			array( 'GET', 'menus' ),
			array( 'GET', 'overview' ),
			array( 'POST', 'locations' ),
			array( 'POST', 'themes' ),
			array( 'POST', 'preview' ),
			array( 'POST', 'build' ),
			array( 'POST', 'audit' ),
			array( 'POST', 'ai/labels' ),
			array( 'POST', 'ai/icons' ),
			array( 'POST', 'ai/promo' ),
		);
		wp_set_current_user( 0 );
		foreach ( $routes as $r ) {
			$this->assertContains( $this->rest( $r[0], $r[1] )->get_status(), array( 401, 403 ), "Anonymous {$r[0]} {$r[1]}" );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		foreach ( $routes as $r ) {
			$this->assertSame( 403, $this->rest( $r[0], $r[1] )->get_status(), "Editor {$r[0]} {$r[1]}" );
		}
		$this->admin();
		$this->assertSame( 403, $this->rest( 'GET', 'menus', array(), false )->get_status(), 'Admin without nonce' );
		$this->assertSame( 200, $this->rest( 'GET', 'menus' )->get_status() );
	}

	public function test_menu_crud_and_builder_save() {
		$this->admin();
		$created = $this->rest( 'POST', 'menus', array( 'name' => 'Header' ) );
		$this->assertSame( 201, $created->get_status() );
		$id = $created->get_data()['id'];
		$this->assertSame( 400, $this->rest( 'POST', 'menus', array( 'name' => '' ) )->get_status() );

		$tree  = array(
			array(
				'id'       => -1,
				'title'    => 'Shop <script>',
				'url'      => '#',
				'config'   => array( 'type' => 'mega', 'icon' => 'shop' ),
				'children' => array( array( 'id' => -2, 'title' => 'Hats', 'url' => 'https://example.org/hats' ) ),
			),
		);
		$saved = $this->rest( 'POST', "menus/{$id}", array( 'tree' => $tree, 'name' => 'Header menu' ) );
		$this->assertSame( 200, $saved->get_status() );
		$data = $saved->get_data();
		$this->assertSame( 'Header menu', $data['menu']['name'] );
		$this->assertSame( 'Shop', $data['tree'][0]['title'] );
		$this->assertSame( 'mega', $data['tree'][0]['config']['type'] );
		$this->assertSame( 'Hats', $data['tree'][0]['children'][0]['title'] );

		// JSON-string bodies are accepted too.
		$this->assertSame( 200, $this->rest( 'POST', "menus/{$id}", array( 'tree' => wp_json_encode( $data['tree'] ) ) )->get_status() );
		$this->assertSame( 2, Tree::count( Tree::get( $id ) ) );

		$get = $this->rest( 'GET', "menus/{$id}" );
		$this->assertSame( 'Shop', $get->get_data()['tree'][0]['title'] );
		$this->assertSame( 404, $this->rest( 'GET', 'menus/999999' )->get_status() );

		$this->assertSame( 200, $this->rest( 'DELETE', "menus/{$id}" )->get_status() );
		$this->assertFalse( is_nav_menu( $id ) );
	}

	public function test_patch_items_applies_accepted_drafts() {
		$this->admin();
		$m   = $this->make_menu();
		$res = $this->rest(
			'POST',
			"menus/{$m['menu']}/items",
			array(
				'items' => array(
					array( 'id' => $m['ids']['Shop'], 'title' => 'Shop all', 'config' => array( 'icon' => 'bag' ) ),
					array( 'id' => $m['ids']['Shirts'], 'description' => 'Tees and polos' ),
					array( 'id' => 999999, 'title' => 'Ignored' ),
				),
			)
		);
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( 2, $res->get_data()['updated'] );
		$tree = Tree::get( $m['menu'] );
		$this->assertSame( 'Shop all', $tree[0]['title'] );
		$this->assertSame( 'bag', $tree[0]['config']['icon'] );
		$this->assertSame( 'Tees and polos', $tree[0]['children'][0]['children'][0]['description'] );
		$this->assertSame( 9, Tree::count( $tree ), 'Patching never drops items' );
	}

	public function test_locations_and_assignments() {
		$this->admin();
		register_nav_menus( array( 'primary' => 'Primary' ) );
		$m   = $this->make_menu();
		$res = $this->rest(
			'POST',
			'locations',
			array(
				'configs'     => array( 'primary' => array( 'enabled' => true, 'theme' => 'dark', 'mobile_mode' => 'offcanvas' ) ),
				'assignments' => array( 'primary' => $m['menu'], 'not_registered' => $m['menu'] ),
			)
		);
		$this->assertSame( 200, $res->get_status() );
		$this->assertTrue( Locations::enabled( 'primary' ) );
		$this->assertSame( $m['menu'], get_nav_menu_locations()['primary'] );
		$this->assertArrayNotHasKey( 'not_registered', get_nav_menu_locations() );
		$locs = wp_list_pluck( $res->get_data()['locations'], 'location' );
		$this->assertContains( 'primary', $locs );
		$this->assertContains( Locations::EMBED, $locs );
		$this->assertSame( 1, $this->rest( 'GET', 'overview' )->get_data()['active'] );
	}

	public function test_themes_and_preview() {
		$this->admin();
		$res = $this->rest( 'POST', 'themes', array( 'custom' => array( array( 'id' => 'custom-a', 'name' => 'Brand', 'tokens' => array( 'accent' => '#123456' ) ) ) ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( '#123456', Themes::get( 'custom-a' )['tokens']['accent'] );
		$this->assertCount( 5, $res->get_data()['presets'] );

		$m    = $this->make_menu();
		$html = $this->rest( 'POST', 'preview', array( 'menu_id' => $m['menu'], 'tokens' => array( 'accent' => '#abcdef' ), 'config' => array( 'trigger' => 'click' ) ) )->get_data()['html'];
		$this->assertStringContainsString( '--dev3mm-accent:#abcdef', $html );
		$this->assertStringContainsString( 'dev3mm--trigger-click', $html );

		$draft = $this->rest( 'POST', 'preview', array( 'draft' => array( array( 'title' => 'Home', 'ref' => 'home', 'layout' => 'link' ) ) ) )->get_data()['html'];
		$this->assertStringContainsString( '>Home<', $draft );
	}

	public function test_content_picker() {
		$this->admin();
		self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Findable page' ) );
		$rows = $this->rest( 'GET', 'content', array( 'type' => 'page', 'search' => 'Findable' ) )->get_data();
		$this->assertSame( 'Findable page', $rows[0]['title'] );
		$this->assertSame( 'post_type', $rows[0]['type'] );
		$this->assertSame( array(), $this->rest( 'GET', 'content', array( 'type' => 'user' ) )->get_data() );
		$this->assertNotEmpty( $this->rest( 'GET', 'content', array( 'type' => 'category' ) )->get_data() );
	}

	public function test_build_apply_and_promo_routes() {
		$this->admin();
		self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Team' ) );
		$build = $this->rest( 'POST', 'build', array( 'mode' => 'rules', 'max_top' => 5 ) );
		$this->assertSame( 200, $build->get_status() );
		$draft = $build->get_data()['draft'];
		$apply = $this->rest( 'POST', 'build/apply', array( 'draft' => $draft, 'target' => array( 'mode' => 'new', 'name' => 'Built' ) ) );
		$this->assertSame( 201, $apply->get_status() );
		$menu_id = $apply->get_data()['menu_id'];
		$this->assertSame( count( $draft ), count( Tree::get( $menu_id ) ) );
		$this->assertSame( 400, $this->rest( 'POST', 'build/apply', array( 'draft' => array(), 'target' => array( 'mode' => 'new' ) ) )->get_status() );


		$promo = $this->rest( 'POST', 'ai/promo', array( 'ai' => false, 'occasion' => 'Spring' ) );
		$this->assertSame( 200, $promo->get_status() );
		$widget = $promo->get_data()['widget'];
		$top    = Tree::get( $menu_id )[0]['id'];
		$insert = $this->rest( 'POST', "menus/{$menu_id}/promo", array( 'item_id' => $top, 'widget' => $widget ) );
		$this->assertSame( 200, $insert->get_status() );
		$this->assertSame( 'promo', Item_Config::get( $top )['aside'][0]['type'] );
		$this->assertSame( 400, $this->rest( 'POST', "menus/{$menu_id}/promo", array( 'item_id' => 123456, 'widget' => $widget ) )->get_status(), 'Items of other menus are refused' );
	}

	public function test_ai_routes_report_missing_key_cleanly() {
		$this->admin();
		$m   = $this->make_menu();
		$res = $this->rest( 'POST', 'ai/labels', array( 'menu_id' => $m['menu'] ) );
		$this->assertGreaterThanOrEqual( 400, $res->get_status() );
		$this->assertArrayHasKey( 'code', $res->get_data() );
		$this->assertArrayHasKey( 'message', $res->get_data() );
		$icons = $this->rest( 'POST', 'ai/icons', array( 'menu_id' => $m['menu'] ) );
		$this->assertSame( 200, $icons->get_status(), 'Icons fall back to keyword matching without a key' );
		$this->assertSame( 'match', $icons->get_data()['source'] );
		$audit = $this->rest( 'POST', 'audit', array( 'menu_id' => $m['menu'], 'ai' => false ) );
		$this->assertSame( 200, $audit->get_status() );
		$this->assertSame( 404, $this->rest( 'POST', 'audit', array( 'menu_id' => 999999 ) )->get_status() );
	}
}
