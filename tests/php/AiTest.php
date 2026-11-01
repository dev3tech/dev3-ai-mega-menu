<?php
/**
 * DEV3 AI features with Gemini mocked: build my menu, labels, audit, promo, icons.
 *
 * @package Dev3\AiMegaMenu
 */

require_once __DIR__ . '/helpers.php';

use Dev3\AiMegaMenu\Ai\Assistant;
use Dev3\AiMegaMenu\Ai\Audit;
use Dev3\AiMegaMenu\Ai\Menu_Builder;
use Dev3\AiMegaMenu\Ai\Prompts_Library;
use Dev3\AiMegaMenu\Core3\Api\Prompts;
use Dev3\AiMegaMenu\Core3\Core\Settings_Store;
use Dev3\AiMegaMenu\Menu\Item_Config;
use Dev3\AiMegaMenu\Menu\Tree;

class AiTest extends Dev3mm_TestCase {

	/**
	 * A small site: Services (Design, Build), About, Contact, Privacy + 2 categories.
	 *
	 * @return array
	 */
	private function site() {
		$p        = static fn( $title, $parent = 0 ) => self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => $title,
				'post_parent' => $parent,
			)
		);
		$services = $p( 'Services' );
		$ids      = array(
			'services' => $services,
			'design'   => $p( 'Design', $services ),
			'build'    => $p( 'Build', $services ),
			'about'    => $p( 'About' ),
			'contact'  => $p( 'Contact' ),
			'privacy'  => $p( 'Privacy Policy' ),
		);
		update_option( 'wp_page_for_privacy_policy', $ids['privacy'] );
		$ids['news'] = self::factory()->category->create( array( 'name' => 'News' ) );
		$ids['tips'] = self::factory()->category->create( array( 'name' => 'Tips' ) );
		self::factory()->post->create( array( 'post_category' => array( $ids['news'] ) ) );
		self::factory()->post->create( array( 'post_category' => array( $ids['tips'] ) ) );
		return $ids;
	}

	public function test_prompts_are_registered_with_versions_and_schemas() {
		$versions = Prompts::versions();
		foreach ( array( Prompts_Library::BUILD, Prompts_Library::LABELS, Prompts_Library::AUDIT, Prompts_Library::PROMO, Prompts_Library::ICONS ) as $id ) {
			$this->assertArrayHasKey( $id, $versions );
			$prompt = Prompts::get( $id );
			$this->assertNotEmpty( $prompt['schema'] );
		}
		$build = Prompts::get( Prompts_Library::BUILD );
		$this->assertSame( 'heavy', $build['profile'], 'Menu building uses the heavy profile (thinking 2048, 150 s)' );
		$this->assertSame( 40, $build['schema']['properties']['items']['items']['properties']['label']['maxLength'] );
		add_filter(
			'dev3mm_prompt_' . Prompts_Library::LABELS,
			static function ( $p ) {
				$p['version'] = '9';
				return $p;
			}
		);
		$this->assertSame( '9', Prompts::versions()[ Prompts_Library::LABELS ] );
	}

	public function test_language_follows_setting_then_locale() {
		$this->assertStringContainsString( 'English', Prompts_Library::language() );
		Settings_Store::update( array( 'ai_language' => 'German' ) );
		Settings_Store::flush();
		$this->assertSame( 'German', Prompts_Library::language() );
	}

	public function test_inventory_and_rules_based_builder() {
		$ids = $this->site();
		$inv = Menu_Builder::inventory();
		$this->assertSame( 6, $inv['counts']['pages'] );
		$this->assertGreaterThanOrEqual( 2, $inv['counts']['categories'] );
		$text = Menu_Builder::inventory_text( $inv );
		$this->assertStringContainsString( 'page:' . $ids['design'] . ' | Design | page:' . $ids['services'], $text );
		$this->assertStringContainsString( 'page:' . $ids['privacy'] . ' | Privacy Policy | - | utility', $text );

		$result = Menu_Builder::heuristic( array( 'max_top' => 7 ) );
		$titles = wp_list_pluck( $result['draft'], 'title' );
		$this->assertSame( 'Home', $titles[0] );
		$this->assertContains( 'Services', $titles );
		$this->assertContains( 'Blog', $titles );
		$this->assertSame( 'Contact', end( $titles ), 'Contact goes last' );
		$this->assertNotContains( 'Privacy Policy', $titles, 'Utility pages are left out' );
		$services = $result['draft'][ array_search( 'Services', $titles, true ) ];
		$this->assertSame( array( 'Build', 'Design' ), wp_list_pluck( $services['children'], 'title' ), 'Ordered by menu order, then title' );
		$this->assertSame( 'rules', $result['source'] );
		$this->assertLessThanOrEqual( 7, $result['stats']['top'] );
	}

	public function test_ai_builder_validates_refs_and_shapes_the_draft() {
		$ids = $this->site();
		$this->with_key();
		$this->responses = array(
			$this->json_response(
				array(
					'items' => array(
						array( 'label' => 'Home', 'ref' => 'home', 'type' => 'link', 'icon' => 'home' ),
						array(
							'label'  => 'What we do',
							'ref'    => 'page:' . $ids['services'],
							'type'   => 'mega',
							'icon'   => 'briefcase',
							'groups' => array(
								array(
									'label'       => 'Design',
									'ref'         => 'page:' . $ids['design'],
									'description' => 'Brand and UX',
									'links'       => array(
										array( 'label' => 'Build', 'ref' => 'page:' . $ids['build'] ),
										array( 'label' => 'Invented', 'ref' => 'page:999999' ),
									),
								),
								array( 'label' => 'Blog topics', 'ref' => 'category:' . $ids['news'] ),
							),
						),
						array( 'label' => 'Ghost', 'ref' => 'page:888888', 'type' => 'link' ),
						array( 'label' => 'Empty container', 'ref' => '#', 'type' => 'mega' ),
						array( 'label' => '', 'ref' => 'page:' . $ids['contact'], 'type' => 'link', 'icon' => 'not-real' ),
					),
				)
			),
		);
		$result = Menu_Builder::ai( array( 'max_top' => 6, 'notes' => 'We are an agency' ) );
		$this->assertIsArray( $result );
		$this->assertSame( 'ai', $result['source'] );
		$prompt = $this->last_prompt();
		$this->assertStringContainsString( 'At most 6 top-level items', $prompt );
		$this->assertStringContainsString( 'We are an agency', $prompt );
		$this->assertStringContainsString( 'page:' . $ids['design'], $prompt );
		$body = $this->requests[0]['body'];
		$this->assertSame( 2048, $body['generationConfig']['thinkingConfig']['thinkingBudget'] );
		$this->assertArrayHasKey( 'responseSchema', $body['generationConfig'] );

		$titles = wp_list_pluck( $result['draft'], 'title' );
		$this->assertSame( array( 'Home', 'What we do', 'Contact' ), $titles, 'Unknown refs and empty containers are dropped; empty labels use the page title' );
		$mega = $result['draft'][1];
		$this->assertSame( 'mega', $mega['layout'] );
		$this->assertSame( array( 'Build' ), wp_list_pluck( $mega['children'][0]['children'], 'title' ) );
		$this->assertSame( 'Brand and UX', $mega['children'][0]['description'] );
		$this->assertSame( '', $result['draft'][2]['icon'], 'Unknown icons are removed' );

		// Accept: create a new menu and assign a location.
		register_nav_menus( array( 'primary' => 'Primary' ) );
		$applied = Menu_Builder::apply( $result['draft'], array( 'mode' => 'new', 'name' => 'AI menu', 'location' => 'primary' ) );
		$this->assertIsArray( $applied );
		$tree = Tree::get( $applied['menu_id'] );
		$this->assertSame( $titles, wp_list_pluck( $tree, 'title' ) );
		$this->assertSame( 'mega', $tree[1]['config']['type'] );
		$this->assertSame( 'briefcase', $tree[1]['config']['icon'] );
		$this->assertTrue( $tree[1]['children'][0]['config']['show_description'] );
		$this->assertSame( 'post_type', $tree[1]['type'] );
		$this->assertSame( $ids['services'], $tree[1]['object_id'] );
		$this->assertSame( home_url( '/' ), $tree[0]['url'] );
		$this->assertSame( $applied['menu_id'], get_nav_menu_locations()['primary'] );
		// Same name again gets a suffix instead of failing.
		$again = Menu_Builder::apply( $result['draft'], array( 'mode' => 'new', 'name' => 'AI menu' ) );
		$this->assertSame( 'AI menu (2)', wp_get_nav_menu_object( $again['menu_id'] )->name );
	}

	public function test_builder_handles_a_200_page_50_category_site_quickly() {
		$parents = array();
		for ( $i = 0; $i < 20; $i++ ) {
			$parents[] = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => "Section {$i}" ) );
		}
		for ( $i = 0; $i < 180; $i++ ) {
			self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => "Page {$i}", 'post_parent' => $parents[ $i % 20 ] ) );
		}
		for ( $i = 0; $i < 50; $i++ ) {
			self::factory()->category->create( array( 'name' => "Topic {$i}" ) );
		}
		$this->with_key();
		$this->responses = array(
			$this->json_response(
				array(
					'items' => array_map(
						static fn( $id ) => array( 'label' => get_the_title( $id ), 'ref' => 'page:' . $id, 'type' => 'link' ),
						array_slice( $parents, 0, 7 )
					),
				)
			),
		);
		$start  = microtime( true );
		$result = Menu_Builder::ai( array( 'max_top' => 7 ) );
		$this->assertLessThan( 10, microtime( true ) - $start, 'Inventory + validation stay fast (the AI call itself is mocked)' );
		$this->assertSame( 200, $result['inventory']['pages'] );
		$this->assertGreaterThanOrEqual( 50, $result['inventory']['categories'] );
		$this->assertCount( 7, $result['draft'] );
		$this->assertLessThan( 60000, strlen( $this->last_prompt() ), 'The prompt stays compact' );
		$rules = Menu_Builder::heuristic( array( 'max_top' => 7 ) );
		$this->assertLessThanOrEqual( 7, $rules['stats']['top'] );
		$this->assertLessThanOrEqual( 3, $rules['stats']['depth'] );
	}

	public function test_ai_errors_are_returned_and_ai_off_blocks_calls() {
		$this->site();
		$this->assertWPError( Menu_Builder::ai( array() ), 'No key = error, not a crash' );
		$this->with_key();
		Settings_Store::update( array( 'ai_enabled' => false ) );
		Settings_Store::flush();
		$err = Menu_Builder::ai( array() );
		$this->assertSame( 'dev3mm_ai_disabled', $err->get_error_code() );
		$this->assertCount( 0, $this->requests );
		$this->assertIsArray( Menu_Builder::heuristic(), 'The rules-based builder works with AI off' );
	}

	public function test_labels_return_drafts_for_known_items_only() {
		$m = $this->make_menu();
		$this->with_key();
		$this->responses = array(
			$this->json_response(
				array(
					'items' => array(
						array( 'id' => $m['ids']['Shop'], 'label' => 'Shop all', 'description' => 'Browse every category' ),
						array( 'id' => 424242, 'label' => 'Hacker', 'description' => 'x' ),
					),
				)
			),
		);
		$res = Assistant::labels( $m['menu'] );
		$this->assertCount( 1, $res['items'] );
		$this->assertSame( 'Shop all', $res['items'][0]['label'] );
		$this->assertSame( 'Shop', $res['items'][0]['title'], 'Current label is kept for the review' );
		$this->assertStringContainsString( 'English', $res['language'] );
		$this->assertStringContainsString( $m['ids']['Shirts'] . ' | Shirts | Men', $this->last_prompt() );
		$this->assertSame( 'Shop', Tree::get( $m['menu'] )[0]['title'], 'Nothing is saved by the draft' );
	}

	public function test_rule_audit_finds_real_problems() {
		$m = $this->make_menu();
		self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Contact the team' ) );
		$deep = wp_update_nav_menu_item( $m['menu'], 0, array( 'menu-item-title' => 'Stuff', 'menu-item-url' => '#', 'menu-item-status' => 'publish', 'menu-item-parent-id' => $m['ids']['Shirts'] ) );
		wp_update_nav_menu_item( $m['menu'], 0, array( 'menu-item-title' => 'An extremely long navigation label here', 'menu-item-url' => 'https://example.org/x', 'menu-item-status' => 'publish' ) );
		Item_Config::save( $m['ids']['Team'], array( 'hide_label' => true ) );
		$res   = Audit::run( $m['menu'], false );
		$codes = wp_list_pluck( $res['issues'], 'code' );
		foreach ( array( 'too_deep', 'unclear_label', 'dead_end', 'long_label', 'invisible', 'missing_link' ) as $code ) {
			$this->assertContains( $code, $codes, "Expected {$code}" );
		}
		$this->assertSame( 'high', $res['issues'][0]['severity'], 'Sorted by severity' );
		$this->assertLessThan( 100, $res['score'] );
		$this->assertSame( $res['score'], Settings_Store::get( 'last_audit' )['score'] );
		$missing = array_values( array_filter( $res['issues'], static fn( $i ) => 'missing_link' === $i['code'] && 'add_link' === $i['fix']['type'] ) );
		$this->assertNotEmpty( $missing );
		$this->assertContains( $deep, wp_list_pluck( $res['issues'], 'item_id' ) );
	}

	public function test_ai_audit_merges_validated_suggestions() {
		$m     = $this->make_menu();
		$extra = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Pricing' ) );
		$this->with_key();
		$this->responses = array(
			$this->json_response(
				array(
					'score'  => 70,
					'issues' => array(
						array( 'severity' => 'medium', 'code' => 'unclear_label', 'item_id' => $m['ids']['Shop'], 'title' => 'Shop is vague', 'detail' => 'Say what you sell.', 'suggested_label' => 'Clothing' ),
						array( 'severity' => 'medium', 'code' => 'missing_link', 'item_id' => 0, 'title' => 'Pricing is missing', 'detail' => 'Visitors look for prices.', 'suggested_ref' => 'page:' . $extra ),
						array( 'severity' => 'critical', 'code' => 'missing_link', 'item_id' => 0, 'title' => 'Bad ref', 'detail' => 'x', 'suggested_ref' => 'page:31337' ),
					),
				)
			),
		);
		$res = Audit::run( $m['menu'], true );
		$this->assertTrue( $res['ai']['used'] );
		$ai = array_values( array_filter( $res['issues'], static fn( $i ) => 'ai' === $i['source'] ) );
		$this->assertCount( 3, $ai );
		$by = array_column( $ai, null, 'title' );
		$this->assertSame( array( 'type' => 'rename', 'label' => 'Clothing', 'ref' => '' ), $by['Shop is vague']['fix'] );
		$this->assertSame( 'page:' . $extra, $by['Pricing is missing']['fix']['ref'] );
		$this->assertSame( 'none', $by['Bad ref']['fix']['type'], 'Unknown refs are not offered as fixes' );
		$this->assertSame( 'low', $by['Bad ref']['severity'] );
		$this->assertStringContainsString( '[' . $m['ids']['Shirts'] . '] Shirts', $this->last_prompt() );
	}

	public function test_ai_audit_failure_still_returns_rule_results() {
		$m = $this->make_menu();
		$this->with_key();
		$this->responses = array( array( 500, array( 'error' => array( 'message' => 'boom' ) ) ) );
		$res             = Audit::run( $m['menu'], true );
		$this->assertFalse( $res['ai']['used'] );
		$this->assertNotEmpty( $res['ai']['error'] );
		$this->assertIsArray( $res['issues'] );
	}

	public function test_icons_use_ai_with_keyword_fallback() {
		$m = $this->make_menu();
		$res = Assistant::icons( $m['menu'], false );
		$this->assertSame( 'match', $res['source'] );
		$by = array_column( $res['items'], null, 'title' );
		$this->assertSame( 'phone', $by['Contact']['icon'] );
		$this->assertSame( 'shop', $by['Shop']['icon'] );

		$this->with_key();
		$this->responses = array(
			$this->json_response(
				array(
					'items' => array(
						array( 'id' => $m['ids']['Shop'], 'icon' => 'bag', 'image_query' => 'fashion' ),
						array( 'id' => $m['ids']['Team'], 'icon' => 'evil-icon' ),
					),
				)
			),
		);
		$res = Assistant::icons( $m['menu'], true );
		$this->assertSame( 'ai', $res['source'] );
		$by = array_column( $res['items'], null, 'title' );
		$this->assertSame( 'bag', $by['Shop']['icon'] );
		$this->assertSame( 'users', $by['Team']['icon'], 'Invalid AI icons fall back to keyword matching' );
	}

	public function test_promo_draft_template_and_insert() {
		$m = $this->make_menu();
		$template = Assistant::promo_template( array( 'occasion' => 'Black Friday' ) );
		$this->assertSame( 'promo', $template['widget']['type'] );
		$this->assertStringContainsString( 'Black Friday', $template['widget']['title'] );

		$this->with_key();
		$this->responses = array(
			$this->json_response(
				array(
					'badge'       => 'Limited time',
					'title'       => 'Cosy autumn layers',
					'text'        => 'Knits and coats picked for colder days.',
					'cta_label'   => 'Shop autumn',
					'product_ids' => array( 999999 ),
				)
			),
		);
		$draft = Assistant::promo( array( 'occasion' => 'Autumn', 'offer' => '20% off coats', 'cta_url' => 'https://example.org/autumn' ) );
		$this->assertSame( 'Cosy autumn layers', $draft['widget']['title'] );
		$this->assertSame( array(), $draft['widget']['product_ids'], 'Product ids not in the candidate list are dropped' );
		$this->assertSame( 'https://example.org/autumn', $draft['widget']['cta_url'] );
		$this->assertStringContainsString( '20% off coats', $this->last_prompt() );
		$this->assertSame( 0, (int) get_post_meta( $m['ids']['Shop'], Item_Config::META, true ), 'Nothing saved before accept' );

		$config = Assistant::insert_promo( $m['ids']['Shop'], $draft['widget'] );
		$this->assertSame( 'mega', $config['type'] );
		$this->assertSame( 'Cosy autumn layers', $config['aside'][0]['title'] );
		$this->assertWPError( Assistant::insert_promo( 123456, $draft['widget'] ) );
	}

	public function test_promo_candidates_with_woocommerce() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			$this->markTestSkipped( 'WooCommerce is not installed in this environment.' );
		}
		$p = new WC_Product_Simple();
		$p->set_name( 'Rain Jacket' );
		$p->set_regular_price( '90' );
		$p->set_sale_price( '70' );
		$p->set_status( 'publish' );
		$id = $p->save();
		$this->with_key();
		$this->responses = array( $this->json_response( array( 'title' => 'Stay dry', 'text' => 'Ready for rain.', 'cta_label' => 'Shop', 'product_ids' => array( $id, 5555 ) ) ) );
		$draft = Assistant::promo( array( 'source' => 'sale' ) );
		$this->assertSame( array( $id ), $draft['widget']['product_ids'] );
		$this->assertTrue( $draft['candidates'][0]['on_sale'] );
		$this->assertStringContainsString( 'Rain Jacket', $this->last_prompt() );
	}
}
