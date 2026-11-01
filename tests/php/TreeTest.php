<?php
/**
 * Reading and saving menu trees.
 *
 * @package Dev3\AiMegaMenu
 */

require_once __DIR__ . '/helpers.php';

use Dev3\AiMegaMenu\Menu\Item_Config;
use Dev3\AiMegaMenu\Menu\Tree;

class TreeTest extends Dev3mm_TestCase {

	public function test_reads_a_nested_tree_with_configs() {
		$m    = $this->make_menu();
		$tree = Tree::get( $m['menu'] );
		$this->assertSame( array( 'Shop', 'About', 'Contact' ), wp_list_pluck( $tree, 'title' ) );
		$this->assertSame( array( 'Men', 'Women' ), wp_list_pluck( $tree[0]['children'], 'title' ) );
		$this->assertSame( array( 'Shirts', 'Pants' ), wp_list_pluck( $tree[0]['children'][0]['children'], 'title' ) );
		$this->assertSame( 'auto', $tree[0]['config']['type'] );
		$this->assertSame( 3, Tree::depth( $tree ) );
		$this->assertSame( 9, Tree::count( $tree ) );
		$flat = Tree::flatten( $tree );
		$this->assertSame( 2, $flat[2]['depth'] );
		$this->assertSame( 'Men', $flat[2]['parent'] );
	}

	public function test_saves_new_reordered_and_deleted_items_and_remaps_widgets() {
		$m    = $this->make_menu();
		$tree = Tree::get( $m['menu'] );
		// Move Contact first, delete About, add a new mega item with a new child placed by an "item" widget.
		$contact = $tree[2];
		$shop    = $tree[0];
		$new     = array(
			'id'       => -5,
			'title'    => 'Deals',
			'url'      => '#',
			'type'     => 'custom',
			'config'   => array(
				'type' => 'mega',
				'rows' => array( array( 'cols' => array( array( 'span' => 6, 'widgets' => array( array( 'type' => 'item', 'item_id' => -6 ) ) ) ) ) ),
				'icon' => 'percent',
			),
			'children' => array(
				array(
					'id'    => -6,
					'title' => 'Clearance',
					'url'   => 'https://example.org/clearance',
					'type'  => 'custom',
				),
			),
		);
		$saved = Tree::save( $m['menu'], array( $contact, $shop, $new ) );
		$this->assertIsArray( $saved );
		$this->assertSame( array( 'Contact', 'Shop', 'Deals' ), wp_list_pluck( $saved, 'title' ) );
		$this->assertNull( get_post( $m['ids']['About'] ), 'Removed items are deleted' );
		$this->assertNull( get_post( $m['ids']['Team'] ) );
		$deals     = $saved[2];
		$clearance = $deals['children'][0];
		$this->assertGreaterThan( 0, $deals['id'] );
		$this->assertSame( 'percent', $deals['config']['icon'] );
		$this->assertSame( $clearance['id'], $deals['config']['rows'][0]['cols'][0]['widgets'][0]['item_id'], 'New-item references are remapped to saved ids' );
		$this->assertSame( '#', $deals['url'] );
		$this->assertSame( $m['ids']['Contact'], $saved[0]['id'], 'Existing items keep their ids' );
	}

	public function test_rejects_missing_menus_and_huge_trees() {
		$this->assertWPError( Tree::save( 999999, array() ) );
		$m    = $this->make_menu();
		$many = array_fill(
			0,
			601,
			array(
				'title' => 'x',
				'url'   => '#',
			)
		);
		$this->assertSame( 'dev3mm_too_many', Tree::save( $m['menu'], $many )->get_error_code() );
	}

	public function test_post_type_items_fall_back_to_custom_when_target_is_missing() {
		$args = Tree::item_args(
			array(
				'type'      => 'post_type',
				'object'    => 'page',
				'object_id' => 987654,
				'title'     => 'Gone',
				'url'       => 'https://example.org/gone',
			),
			0,
			1
		);
		$this->assertSame( 'custom', $args['menu-item-type'] );
		$this->assertSame( 'https://example.org/gone', $args['menu-item-url'] );
		$this->assertSame( 'publish', $args['menu-item-status'] );
	}

	public function test_flags_unpublished_targets_as_invalid() {
		$m = $this->make_menu();
		wp_update_post(
			array(
				'ID'          => $m['page'],
				'post_status' => 'draft',
			)
		);
		$tree = Tree::get( $m['menu'] );
		$this->assertTrue( $tree[1]['invalid'] );
		$this->assertFalse( $tree[0]['invalid'] );
	}
}
