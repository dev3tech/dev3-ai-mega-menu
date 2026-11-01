<?php
/**
 * Front-end markup: accessibility attributes, layouts, widgets and integrations.
 *
 * @package Dev3\AiMegaMenu
 */

require_once __DIR__ . '/helpers.php';

use Dev3\AiMegaMenu\Core3\Core\Settings_Store;
use Dev3\AiMegaMenu\Front\Frontend;
use Dev3\AiMegaMenu\Front\Renderer;
use Dev3\AiMegaMenu\Front\Widgets;
use Dev3\AiMegaMenu\Integrations\Builders;
use Dev3\AiMegaMenu\Menu\Item_Config;
use Dev3\AiMegaMenu\Menu\Locations;

class RenderTest extends Dev3mm_TestCase {

	private function dom( $html ) {
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		return new DOMXPath( $doc );
	}

	public function test_three_level_mega_menu_is_accessible() {
		$m    = $this->make_menu();
		Item_Config::save( $m['ids']['Shop'], array( 'badge' => array( 'text' => 'Sale', 'tone' => 'sale' ), 'icon' => 'shop' ) );
		$html = Renderer::render( $m['menu'], Locations::EMBED, array( 'aria_label' => 'Main' ) );
		$x    = $this->dom( $html );

		$nav = $x->query( '//nav[contains(@class,"dev3mm")]' );
		$this->assertSame( 1, $nav->length );
		$this->assertSame( 'Main', $nav->item( 0 )->getAttribute( 'aria-label' ) );
		$this->assertStringContainsString( '--dev3mm-accent', $nav->item( 0 )->getAttribute( 'style' ) );

		// Every submenu control is a button with aria-expanded=false and aria-controls pointing at an existing panel.
		$controls = $x->query( '//button[@aria-controls and contains(@class,"sub-toggle") or contains(@class,"link--button")]' );
		$this->assertGreaterThanOrEqual( 2, $controls->length );
		foreach ( $controls as $btn ) {
			$this->assertSame( 'false', $btn->getAttribute( 'aria-expanded' ) );
			$this->assertSame( 1, $x->query( '//*[@id="' . $btn->getAttribute( 'aria-controls' ) . '"]' )->length );
			$this->assertNotSame( '', trim( $btn->textContent ), 'Toggle buttons have an accessible name' );
		}

		// Shop has grandchildren → automatic mega panel with Men/Women columns and their links.
		$shop = $x->query( '//li[contains(@class,"dev3mm__item--mega")]' );
		$this->assertSame( 1, $shop->length );
		$this->assertSame( 2, $x->query( './/div[contains(@class,"dev3mm__group")]', $shop->item( 0 ) )->length );
		$this->assertSame( 3, $x->query( './/a[contains(@class,"dev3mm__sublink")]', $shop->item( 0 ) )->length );
		$this->assertSame( 1, $x->query( './/span[contains(@class,"dev3mm__badge--sale")]', $shop->item( 0 ) )->length );
		$this->assertSame( 1, $x->query( './/svg[@aria-hidden="true" and contains(@class,"dev3mm__icon")]', $shop->item( 0 ) )->length );

		// About has children only → flyout dropdown. Contact is a plain link.
		$this->assertSame( 1, $x->query( '//ul[contains(@class,"dev3mm__dropdown")]' )->length );
		$this->assertSame( 1, $x->query( '//li[contains(@class,"dev3mm__item--link")]' )->length );
		// The mobile toggle controls the drawer.
		$toggle = $x->query( '//button[contains(@class,"dev3mm__toggle")]' )->item( 0 );
		$this->assertSame( $x->query( '//div[contains(@class,"dev3mm__drawer")]' )->item( 0 )->getAttribute( 'id' ), $toggle->getAttribute( 'aria-controls' ) );
	}

	public function test_tabs_layout_and_custom_rows() {
		$m = $this->make_menu();
		Item_Config::save( $m['ids']['Shop'], array( 'type' => 'tabs' ) );
		$x = $this->dom( Renderer::render( $m['menu'] ) );
		$this->assertSame( 1, $x->query( '//*[@role="tablist"]' )->length );
		$tabs = $x->query( '//button[@role="tab"]' );
		$this->assertSame( 2, $tabs->length );
		$this->assertSame( 'true', $tabs->item( 0 )->getAttribute( 'aria-selected' ) );
		$this->assertSame( '-1', $tabs->item( 1 )->getAttribute( 'tabindex' ) );
		$this->assertSame( 1, $x->query( '//*[@id="' . $tabs->item( 1 )->getAttribute( 'aria-controls' ) . '" and @role="tabpanel"]' )->length );

		Item_Config::save(
			$m['ids']['Shop'],
			array(
				'type'  => 'mega',
				'rows'  => array(
					array(
						'cols' => array(
							array( 'span' => 4, 'widgets' => array( array( 'type' => 'item', 'item_id' => $m['ids']['Women'] ) ) ),
							array( 'span' => 8, 'widgets' => array( array( 'type' => 'html', 'title' => 'Note', 'content' => '<p>Free <strong>shipping</strong></p>' ), array( 'type' => 'search', 'placeholder' => 'Find it' ) ) ),
						),
					),
				),
				'aside' => array(
					array( 'type' => 'promo', 'title' => 'Autumn picks', 'text' => 'New in.', 'cta_label' => 'Shop now', 'cta_url' => 'https://example.org/sale' ),
				),
			)
		);
		Renderer::reset();
		$html = Renderer::render( $m['menu'] );
		$x    = $this->dom( $html );
		$this->assertStringContainsString( '--dev3mm-span:8', $html );
		$this->assertSame( 1, $x->query( '//aside[contains(@class,"dev3mm__aside")]//a[@href="https://example.org/sale"]' )->length );
		$this->assertSame( 1, $x->query( '//form[@role="search"]//input[@placeholder="Find it"]' )->length );
		$this->assertSame( 1, $x->query( '//label[@for=//input[@type="search"]/@id]' )->length, 'Search input is labelled' );
		$this->assertStringContainsString( '<strong>shipping</strong>', $html );
		// Women placed in a row; Men is not placed and still renders (automatic grid), so nothing is lost.
		$titles = array();
		foreach ( $x->query( '//a[contains(@class,"dev3mm__group-title")]' ) as $a ) {
			$titles[] = trim( $a->textContent );
		}
		$this->assertSame( array( 'Women', 'Men' ), $titles );
	}

	public function test_hidden_label_stays_available_to_screen_readers_and_current_page() {
		$m = $this->make_menu();
		Item_Config::save( $m['ids']['Contact'], array( 'hide_label' => true, 'icon' => 'phone', 'hide_on_mobile' => true ) );
		$this->go_to( get_permalink( $m['page'] ) );
		$x = $this->dom( Renderer::render( $m['menu'] ) );
		$this->assertSame( 1, $x->query( '//span[@class="dev3mm__sr" and text()="Contact"]' )->length );
		$this->assertSame( 1, $x->query( '//li[contains(@class,"dev3mm--hide-mobile")]' )->length );
		$current = $x->query( '//a[@aria-current="page"]' );
		$this->assertSame( 1, $current->length );
		$this->assertStringContainsString( 'About', $current->item( 0 )->textContent );
	}

	public function test_placeholder_items_render_as_a_single_button() {
		$menu = wp_create_nav_menu( 'Placeholder' );
		$top  = wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => 'More', 'menu-item-url' => '#', 'menu-item-status' => 'publish' ) );
		wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => 'FAQ', 'menu-item-url' => 'https://example.org/faq', 'menu-item-status' => 'publish', 'menu-item-parent-id' => $top ) );
		$x   = $this->dom( Renderer::render( $menu ) );
		$btn = $x->query( '//button[contains(@class,"dev3mm__link--button")]' );
		$this->assertSame( 1, $btn->length );
		$this->assertStringContainsString( 'More', $btn->item( 0 )->textContent );
		$this->assertSame( 0, $x->query( '//a[@href="#"]' )->length );
	}

	public function test_empty_or_missing_menus_render_nothing() {
		$this->assertSame( '', Renderer::render( 999999 ) );
		$this->assertSame( '', Renderer::render( wp_create_nav_menu( 'Empty' ) ) );
	}

	public function test_location_settings_drive_classes_and_data() {
		$m = $this->make_menu();
		Locations::save(
			array(
				'primary' => array(
					'enabled'     => true,
					'theme'       => 'dark',
					'trigger'     => 'click',
					'breakpoint'  => 1024,
					'mobile_mode' => 'offcanvas',
					'sticky'      => true,
					'show_search' => true,
				),
			)
		);
		$html = Renderer::render( $m['menu'], 'primary' );
		foreach ( array( 'dev3mm--bp-1024', 'dev3mm--trigger-click', 'dev3mm--mobile-offcanvas', 'dev3mm--sticky', 'dev3mm--theme-dark', 'dev3mm__item--search' ) as $class ) {
			$this->assertStringContainsString( $class, $html );
		}
		$this->assertStringContainsString( '#111827', $html, 'Dark preset tokens are applied' );
		$this->assertStringContainsString( '&quot;trigger&quot;:&quot;click&quot;', $html );
	}

	public function test_takes_over_enabled_theme_locations_only() {
		register_nav_menus( array( 'primary' => 'Primary', 'footer' => 'Footer' ) );
		$m = $this->make_menu();
		set_theme_mod( 'nav_menu_locations', array( 'primary' => $m['menu'], 'footer' => $m['menu'] ) );
		Locations::save( array( 'primary' => array( 'enabled' => true ) ) );
		$primary = wp_nav_menu( array( 'theme_location' => 'primary', 'echo' => false ) );
		$footer  = wp_nav_menu( array( 'theme_location' => 'footer', 'echo' => false ) );
		$this->assertStringContainsString( 'class="dev3mm ', $primary );
		$this->assertStringNotContainsString( 'dev3mm', $footer );
		$this->assertTrue( wp_style_is( Frontend::HANDLE, 'enqueued' ) );
		add_filter( 'dev3mm_use_for_nav_menu', '__return_false' );
		$this->assertStringNotContainsString( 'class="dev3mm ', wp_nav_menu( array( 'theme_location' => 'primary', 'echo' => false ) ) );
		remove_filter( 'dev3mm_use_for_nav_menu', '__return_false' );
	}

	public function test_shortcode_block_and_navigation_replacement() {
		$m    = $this->make_menu();
		$menu = wp_get_nav_menu_object( $m['menu'] );
		$this->assertStringContainsString( 'dev3mm--theme-brand', do_shortcode( '[dev3mm_menu menu="' . $menu->slug . '" theme="brand" trigger="click"]' ) );
		$this->assertSame( '', do_shortcode( '[dev3mm_menu menu="nope"]' ) );

		$block = render_block(
			array(
				'blockName'    => 'dev3mm/mega-menu',
				'attrs'        => array( 'menuId' => $m['menu'], 'mobileMode' => 'offcanvas' ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
		$this->assertStringContainsString( 'wp-block-dev3mm-mega-menu', $block );
		$this->assertStringContainsString( 'dev3mm--mobile-offcanvas', $block );

		Settings_Store::update( array( 'block_nav_replace' => 'first', 'block_nav_menu' => $m['menu'] ) );
		Settings_Store::flush();
		$frontend = new Frontend();
		$first    = $frontend->replace_navigation( '<nav class="wp-block-navigation"></nav>', array() );
		$second   = $frontend->replace_navigation( '<nav class="wp-block-navigation"></nav>', array() );
		$this->assertStringContainsString( 'class="dev3mm ', $first );
		$this->assertStringContainsString( 'wp-block-navigation', $second, '"first" replaces only the first Navigation block' );
	}

	public function test_render_tree_previews_unsaved_trees() {
		$html = Renderer::render_tree(
			array(
				array(
					'id'       => -1,
					'title'    => 'Shop',
					'url'      => '/shop',
					'config'   => array( 'type' => 'mega', 'rows' => array( array( 'cols' => array( array( 'span' => 6, 'widgets' => array( array( 'type' => 'item', 'item_id' => -2 ) ) ) ) ) ) ),
					'children' => array(
						array(
							'id'       => -2,
							'title'    => 'Kids',
							'url'      => '/kids',
							'children' => array( array( 'id' => -3, 'title' => 'Toys', 'url' => '/toys' ) ),
						),
					),
				),
			),
			'Preview'
		);
		$this->assertStringContainsString( 'aria-label="Preview"', $html );
		$this->assertStringContainsString( '--dev3mm-span:6', $html );
		$this->assertStringContainsString( '>Kids<', $html );
		$this->assertStringContainsString( '>Toys<', $html );
		$this->assertSame( 1, substr_count( $html, '>Kids<' ), 'Placed items are not repeated in the automatic grid' );
	}

	public function test_widgets_posts_and_reusable_blocks() {
		$cat = self::factory()->category->create( array( 'name' => 'News' ) );
		self::factory()->post->create( array( 'post_title' => 'Hello mega world', 'post_category' => array( $cat ) ) );
		$html = Widgets::render( Item_Config::sanitize_widget( array( 'type' => 'posts', 'title' => 'Latest', 'category' => $cat, 'count' => 2 ) ) );
		$this->assertStringContainsString( 'Hello mega world', $html );
		$this->assertStringContainsString( 'dev3mm__widget-title', $html );
		$block = self::factory()->post->create( array( 'post_type' => 'wp_block', 'post_content' => '<!-- wp:paragraph --><p>Synced!</p><!-- /wp:paragraph -->' ) );
		$this->assertStringContainsString( 'Synced!', Widgets::render( array( 'type' => 'block', 'title' => '', 'block_id' => $block ) ) );
		$this->assertSame( '', Widgets::render( array( 'type' => 'block', 'title' => '', 'block_id' => $cat ) ) );
	}

	public function test_products_widget_with_woocommerce() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			$this->markTestSkipped( 'WooCommerce is not installed in this environment.' );
		}
		$product = new WC_Product_Simple();
		$product->set_name( 'Wool Scarf' );
		$product->set_regular_price( '30' );
		$product->set_sale_price( '20' );
		$product->set_status( 'publish' );
		$id = $product->save();
		$html = Widgets::render( Item_Config::sanitize_widget( array( 'type' => 'products', 'source' => 'ids', 'ids' => array( $id ), 'count' => 3 ) ) );
		$this->assertStringContainsString( 'Wool Scarf', $html );
		$this->assertStringContainsString( 'dev3mm__price', $html );
		$this->assertContains( $id, Widgets::product_ids( Item_Config::sanitize_widget( array( 'type' => 'products', 'source' => 'sale', 'count' => 5 ) ) ) );
	}

	public function test_page_builder_adapters() {
		$map = Builders::wpbakery_map();
		$this->assertSame( 'dev3mm_menu', $map['base'] );
		$this->assertSame( array( 'menu', 'theme', 'trigger', 'mobile_mode' ), wp_list_pluck( $map['params'], 'param_name' ) );
		require_once __DIR__ . '/stubs/elementor.php';
		$manager = new Dev3mm_Fake_Widgets_Manager();
		( new Builders() )->elementor( $manager );
		$this->assertCount( 1, $manager->widgets );
		$widget = $manager->widgets[0];
		$this->assertSame( 'dev3mm_mega_menu', $widget->get_name() );
		$this->assertSame( 'DEV3 AI Mega Menu', $widget->get_title() );
		$this->assertContains( Frontend::HANDLE, $widget->get_style_depends() );
	}
}
