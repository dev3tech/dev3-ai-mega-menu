<?php
/**
 * Item configs, locations, themes and icons.
 *
 * @package Dev3\AiMegaMenu
 */

require_once __DIR__ . '/helpers.php';

use Dev3\AiMegaMenu\Menu\Icons;
use Dev3\AiMegaMenu\Menu\Item_Config;
use Dev3\AiMegaMenu\Menu\Locations;
use Dev3\AiMegaMenu\Menu\Themes;

class ModelTest extends Dev3mm_TestCase {

	public function test_item_config_sanitises_everything() {
		$c = Item_Config::sanitize(
			array(
				'type'        => 'evil',
				'icon'        => 'not-an-icon',
				'image_id'    => -4,
				'badge'       => array(
					'text'  => '<b>Brand new label</b>',
					'tone'  => 'neon',
					'color' => 'red',
				),
				'columns'     => 99,
				'panel_width' => 'huge',
				'rows'        => array(
					array(
						'cols' => array(
							array(
								'span'    => 40,
								'widgets' => array(
									array( 'type' => 'item', 'item_id' => '12' ),
									array( 'type' => 'unknown' ),
									array( 'type' => 'html', 'content' => '<p>Hi</p><script>alert(1)</script>' ),
									array( 'type' => 'products', 'source' => 'ids', 'ids' => array( 3, 'x', 4 ), 'count' => 50 ),
								),
							),
						),
					),
				),
				'aside'       => array( array( 'type' => 'item', 'item_id' => 3 ), array( 'type' => 'promo', 'title' => 'Hello', 'tone' => 'weird', 'product_ids' => array( 1, 2, 3, 4, 5 ) ) ),
			)
		);
		$this->assertSame( 'auto', $c['type'] );
		$this->assertSame( '', $c['icon'] );
		$this->assertSame( 0, $c['image_id'] );
		$this->assertSame( 'Brand new label', $c['badge']['text'] );
		$this->assertSame( 'new', $c['badge']['tone'] );
		$this->assertSame( '', $c['badge']['color'] );
		$this->assertSame( 6, $c['columns'] );
		$this->assertSame( 'inherit', $c['panel_width'] );
		$col = $c['rows'][0]['cols'][0];
		$this->assertSame( 12, $col['span'] );
		$this->assertCount( 3, $col['widgets'], 'Unknown widget types are dropped' );
		$this->assertSame( 12, $col['widgets'][0]['item_id'] );
		$this->assertStringNotContainsString( '<script', $col['widgets'][1]['content'] );
		$this->assertSame( array( 3, 4 ), $col['widgets'][2]['ids'] );
		$this->assertSame( 8, $col['widgets'][2]['count'] );
		$this->assertCount( 1, $c['aside'], 'Menu-item widgets are not allowed in the side column' );
		$this->assertSame( 'brand', $c['aside'][0]['tone'] );
		$this->assertCount( 5, $c['aside'][0]['product_ids'] );
	}

	public function test_item_config_roundtrip_removes_default_meta() {
		$item = self::factory()->post->create( array( 'post_type' => 'nav_menu_item' ) );
		Item_Config::save( $item, array( 'icon' => 'shop', 'type' => 'mega' ) );
		$this->assertSame( 'shop', Item_Config::get( $item )['icon'] );
		Item_Config::save( $item, array() );
		$this->assertSame( '', get_post_meta( $item, Item_Config::META, true ) );
	}

	public function test_layout_rules() {
		$d = Item_Config::defaults();
		$this->assertSame( 'link', Item_Config::layout( $d, false ) );
		$this->assertSame( 'flyout', Item_Config::layout( $d, true ) );
		$this->assertSame( 'mega', Item_Config::layout( $d, true, 0, true ), 'Auto + grandchildren = mega' );
		$this->assertSame( 'tabs', Item_Config::layout( array_merge( $d, array( 'type' => 'tabs' ) ), true ) );
		$this->assertSame( 'flyout', Item_Config::layout( array_merge( $d, array( 'type' => 'flyout' ) ), true, 0, true ) );
		$this->assertSame( 'mega', Item_Config::layout( array_merge( $d, array( 'aside' => array( array( 'type' => 'promo' ) ) ) ), false ) );
		$this->assertSame( 'flyout', Item_Config::layout( $d, true, 2 ), 'Deeper levels are always flyouts' );
	}

	public function test_locations_defaults_and_sanitising() {
		$this->assertFalse( Locations::enabled( 'primary' ) );
		$this->assertTrue( Locations::get( Locations::EMBED )['enabled'], 'Embeds are always enabled' );
		Locations::save(
			array(
				'primary' => array(
					'enabled'     => 1,
					'trigger'     => 'click',
					'breakpoint'  => 777,
					'hover_delay' => 5000,
					'mobile_mode' => 'offcanvas',
					'align'       => 'nowhere',
				),
			)
		);
		$c = Locations::get( 'primary' );
		$this->assertTrue( Locations::enabled( 'primary' ) );
		$this->assertSame( 'click', $c['trigger'] );
		$this->assertSame( 960, $c['breakpoint'], 'Unknown breakpoints fall back' );
		$this->assertSame( 1000, $c['hover_delay'] );
		$this->assertSame( 'offcanvas', $c['mobile_mode'] );
		$this->assertSame( 'start', $c['align'] );
		Locations::save( array( 'primary' => array( 'sticky' => true ) ) );
		$this->assertSame( 'click', Locations::get( 'primary' )['trigger'], 'Saving merges with the stored config' );
	}

	public function test_themes_presets_custom_and_css_vars() {
		$this->assertArrayHasKey( 'light', Themes::presets() );
		$this->assertArrayHasKey( 'dark', Themes::presets() );
		$saved = Themes::save_custom(
			array(
				array(
					'id'     => 'light',
					'name'   => 'Mine',
					'tokens' => array(
						'bar_bg'    => 'url(evil)',
						'accent'    => '#FF0000',
						'radius'    => 400,
						'shadow'    => 'strong',
						'uppercase' => 1,
						'bar_text'  => 'inherit',
						'font_family' => 'Inter, "Helvetica"; } body { display:none',
					),
				),
			)
		);
		$theme = array_values( $saved )[0];
		$this->assertNotSame( 'light', $theme['id'], 'Presets cannot be overwritten' );
		$this->assertSame( '#ffffff', $theme['tokens']['bar_bg'] );
		$this->assertSame( '#ff0000', $theme['tokens']['accent'] );
		$this->assertSame( 24, $theme['tokens']['radius'] );
		$this->assertSame( 'inherit', $theme['tokens']['bar_text'] );
		$this->assertStringNotContainsString( ';', $theme['tokens']['font_family'] );
		$this->assertStringNotContainsString( '{', $theme['tokens']['font_family'] );
		$css = Themes::css_vars( $theme['tokens'] );
		$this->assertStringContainsString( '--dev3mm-accent:#ff0000;', $css );
		$this->assertStringContainsString( '--dev3mm-radius:24px;', $css );
		$this->assertStringContainsString( '--dev3mm-transform:uppercase;', $css );
		$this->assertSame( 'Light', Themes::get( 'missing' )['name'] );
	}

	public function test_icons_render_and_match() {
		$this->assertGreaterThan( 50, count( Icons::names() ) );
		$this->assertStringContainsString( 'aria-hidden="true"', Icons::svg( 'cart' ) );
		$this->assertSame( '', Icons::svg( 'nope' ) );
		$this->assertSame( 'phone', Icons::match( 'Contact' ) );
		$this->assertSame( 'cart', Icons::match( 'Cart' ) );
		$this->assertSame( 'gift', Icons::match( 'Gift cards' ) );
		$this->assertSame( 'news', Icons::match( 'Blog' ) );
		$this->assertSame( '', Icons::match( 'Qwxyz' ) );
	}
}
