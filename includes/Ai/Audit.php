<?php
/**
 * Menu UX audit: rules-based checks (always available) plus a DEV3 AI review of labels and grouping.
 *
 * @package Dev3\AiMegaMenu
 */

namespace Dev3\AiMegaMenu\Ai;

use Dev3\AiMegaMenu\Core3\Api\Prompts;
use Dev3\AiMegaMenu\Core3\Core\Settings_Store;
use Dev3\AiMegaMenu\Menu\Tree;

defined( 'ABSPATH' ) || exit;

/**
 * Issue: { id, severity (high|medium|low), code, item_id, title, detail, source (rules|ai), fix: { type: rename|add_link|none, label, ref } }.
 */
class Audit {

	const WEIGHTS = array(
		'high'   => 15,
		'medium' => 7,
		'low'    => 3,
	);

	const VAGUE = array( 'click here', 'more', 'misc', 'miscellaneous', 'stuff', 'other', 'others', 'links', 'page', 'new page', 'sample page', 'untitled', 'menu item', 'item', 'info', 'things', 'general', 'resources' );

	/**
	 * Runs the audit for a menu.
	 *
	 * @param int  $menu_id Menu id.
	 * @param bool $with_ai Add the DEV3 AI review.
	 * @return array|\WP_Error { score, issues[], counts, ai: { used, error, model, tokens } }
	 */
	public static function run( $menu_id, $with_ai = false ) {
		if ( ! is_nav_menu( (int) $menu_id ) ) {
			return new \WP_Error( 'dev3mm_no_menu', __( 'That menu does not exist.', 'dev3-ai-mega-menu' ), array( 'status' => 404 ) );
		}
		$tree   = Tree::get( (int) $menu_id );
		$issues = self::rules( $tree );
		$ai     = array(
			'used'  => false,
			'error' => null,
		);
		if ( $with_ai ) {
			$extra = self::ai( $tree, $issues );
			if ( is_wp_error( $extra ) ) {
				$ai['error'] = array(
					'code'    => $extra->get_error_code(),
					'message' => $extra->get_error_message(),
				);
			} else {
				$ai     = array(
					'used'   => true,
					'error'  => null,
					'model'  => $extra['model'],
					'tokens' => $extra['tokens'],
					'cached' => $extra['cached'],
				);
				$issues = array_merge( $issues, $extra['issues'] );
			}
		}
		$order = array(
			'high'   => 0,
			'medium' => 1,
			'low'    => 2,
		);
		usort( $issues, static fn( $a, $b ) => $order[ $a['severity'] ] <=> $order[ $b['severity'] ] );
		$score  = self::score( $issues );
		$result = array(
			'menu_id' => (int) $menu_id,
			'score'   => $score,
			'grade'   => $score >= 90 ? 'great' : ( $score >= 70 ? 'good' : ( $score >= 50 ? 'fair' : 'poor' ) ),
			'issues'  => array_values( $issues ),
			'counts'  => array(
				'items' => Tree::count( $tree ),
				'top'   => count( $tree ),
				'depth' => Tree::depth( $tree ),
			),
			'ai'      => $ai,
		);
		Settings_Store::update(
			array(
				'last_audit' => array(
					'menu_id' => (int) $menu_id,
					'score'   => $score,
					'issues'  => count( $issues ),
					'at'      => gmdate( 'c' ),
				),
			)
		);
		return $result;
	}

	/**
	 * Score from issues.
	 *
	 * @param array $issues Issues.
	 * @return int
	 */
	public static function score( array $issues ) {
		$penalty = 0;
		foreach ( $issues as $issue ) {
			$penalty += self::WEIGHTS[ $issue['severity'] ];
		}
		return max( 0, 100 - $penalty );
	}

	/**
	 * Issue factory.
	 *
	 * @param string $severity Severity.
	 * @param string $code     Code.
	 * @param int    $item_id  Item id.
	 * @param string $title    Title.
	 * @param string $detail   Detail.
	 * @param array  $fix      Fix.
	 * @param string $source   Source.
	 * @return array
	 */
	private static function issue( $severity, $code, $item_id, $title, $detail, array $fix = array(), $source = 'rules' ) {
		return array(
			'id'       => $code . '-' . (int) $item_id . '-' . substr( md5( $title ), 0, 6 ),
			'severity' => $severity,
			'code'     => $code,
			'item_id'  => (int) $item_id,
			'title'    => $title,
			'detail'   => $detail,
			'source'   => $source,
			'fix'      => array_merge(
				array(
					'type'  => 'none',
					'label' => '',
					'ref'   => '',
				),
				$fix
			),
		);
	}

	/**
	 * Rules-based checks.
	 *
	 * @param array $tree Menu tree.
	 * @return array[]
	 */
	public static function rules( array $tree ) {
		$issues = array();
		if ( ! $tree ) {
			return array( self::issue( 'high', 'empty', 0, __( 'The menu is empty', 'dev3-ai-mega-menu' ), __( 'Add items or use “Build my menu”.', 'dev3-ai-mega-menu' ) ) );
		}
		$depth = Tree::depth( $tree );
		if ( $depth > 3 ) {
			/* translators: %d: depth. */
			$issues[] = self::issue( 'high', 'too_deep', 0, sprintf( __( 'The menu is %d levels deep', 'dev3-ai-mega-menu' ), $depth ), __( 'Visitors rarely find items below the third level. Flatten deep branches into mega-menu columns.', 'dev3-ai-mega-menu' ) );
		}
		if ( count( $tree ) > 8 ) {
			/* translators: %d: number of items. */
			$issues[] = self::issue( 'medium', 'too_many_top', 0, sprintf( __( '%d top-level items', 'dev3-ai-mega-menu' ), count( $tree ) ), __( 'Aim for 7 or fewer top-level items so the bar fits and is easy to scan. Group the rest.', 'dev3-ai-mega-menu' ) );
		}
		$flat = Tree::flatten( $tree );
		$seen = array();
		$urls = array();
		foreach ( $flat as $item ) {
			$label = trim( (string) $item['title'] );
			$id    = (int) $item['id'];
			$lower = strtolower( $label );
			if ( ! empty( $item['invalid'] ) ) {
				/* translators: %s: label. */
				$issues[] = self::issue( 'high', 'broken_link', $id, sprintf( __( '“%s” links to missing content', 'dev3-ai-mega-menu' ), $label ), __( 'The page or category it points to was deleted, trashed or unpublished.', 'dev3-ai-mega-menu' ) );
			}
			if ( 'custom' === $item['type'] && in_array( trim( (string) $item['url'] ), array( '', '#' ), true ) && ! $item['kids'] && empty( $item['config']['rows'] ) ) {
				/* translators: %s: label. */
				$issues[] = self::issue( 'high', 'dead_end', $id, sprintf( __( '“%s” goes nowhere', 'dev3-ai-mega-menu' ), $label ), __( 'It has no link and no submenu. Give it a destination or remove it.', 'dev3-ai-mega-menu' ) );
			}
			if ( '' === $label ) {
				$issues[] = self::issue( 'high', 'no_label', $id, __( 'An item has no label', 'dev3-ai-mega-menu' ), __( 'Screen readers and visitors need a text label.', 'dev3-ai-mega-menu' ) );
			} elseif ( in_array( $lower, self::VAGUE, true ) ) {
				/* translators: %s: label. */
				$issues[] = self::issue( 'medium', 'unclear_label', $id, sprintf( __( '“%s” is a vague label', 'dev3-ai-mega-menu' ), $label ), __( 'Say what visitors will find there.', 'dev3-ai-mega-menu' ) );
			} elseif ( mb_strlen( $label ) > 28 ) {
				/* translators: %s: label. */
				$issues[] = self::issue( 'low', 'long_label', $id, sprintf( __( '“%s” is long', 'dev3-ai-mega-menu' ), $label ), __( 'Labels over 28 characters wrap and slow scanning. Shorten it.', 'dev3-ai-mega-menu' ) );
			}
			if ( ! empty( $item['config']['hide_label'] ) && empty( $item['config']['icon'] ) && empty( $item['config']['image_id'] ) ) {
				/* translators: %s: label. */
				$issues[] = self::issue( 'high', 'invisible', $id, sprintf( __( '“%s” is hidden', 'dev3-ai-mega-menu' ), $label ), __( 'The label is hidden and there is no icon or image, so sighted visitors see nothing.', 'dev3-ai-mega-menu' ) );
			}
			if ( $item['kids'] > 12 ) {
				/* translators: 1: label, 2: count. */
				$issues[] = self::issue( 'medium', 'crowded', $id, sprintf( __( '“%1$s” has %2$d links', 'dev3-ai-mega-menu' ), $label, $item['kids'] ), __( 'Split long lists into groups of 5–10 links.', 'dev3-ai-mega-menu' ) );
			}
			$key = $item['depth'] . '|' . $item['parent'] . '|' . $lower;
			if ( '' !== $lower && isset( $seen[ $key ] ) ) {
				/* translators: %s: label. */
				$issues[] = self::issue( 'medium', 'duplicate', $id, sprintf( __( '“%s” appears twice in the same group', 'dev3-ai-mega-menu' ), $label ), __( 'Merge or rename one of them.', 'dev3-ai-mega-menu' ) );
			}
			$seen[ $key ] = true;
			$url          = untrailingslashit( (string) $item['url'] );
			if ( '' !== $url && '#' !== $url && 'custom' !== $item['type'] ) {
				$urls[ $url ][] = $label;
			}
		}
		foreach ( $tree as $top ) {
			$config = isset( $top['config'] ) ? $top['config'] : array();
			if ( isset( $config['type'] ) && in_array( $config['type'], array( 'mega', 'tabs' ), true ) && count( $top['children'] ) === 1 && empty( $config['rows'] ) ) {
				/* translators: %s: label. */
				$issues[] = self::issue( 'low', 'thin_panel', (int) $top['id'], sprintf( __( '“%s” is a mega panel with one group', 'dev3-ai-mega-menu' ), $top['title'] ), __( 'Use a simple dropdown, or add more groups or a promo block.', 'dev3-ai-mega-menu' ) );
			}
		}
		foreach ( $urls as $labels ) {
			if ( count( $labels ) > 2 ) {
				/* translators: %s: label. */
				$issues[] = self::issue( 'low', 'repeated_target', 0, sprintf( __( '“%s” is linked more than twice', 'dev3-ai-mega-menu' ), $labels[0] ), __( 'Repeated links add noise. Keep the most useful one.', 'dev3-ai-mega-menu' ) );
			}
		}
		return array_merge( $issues, self::missing( $flat ) );
	}

	/**
	 * Important pages that are not in the menu.
	 *
	 * @param array $flat Flat items.
	 * @return array[]
	 */
	private static function missing( array $flat ) {
		$linked = array();
		$home   = false;
		foreach ( $flat as $item ) {
			if ( 'post_type' === $item['type'] ) {
				$linked[ (int) $item['object_id'] ] = true;
			}
			if ( untrailingslashit( (string) $item['url'] ) === untrailingslashit( home_url( '/' ) ) ) {
				$home = true;
			}
		}
		$front = (int) get_option( 'page_on_front' );
		if ( $front && isset( $linked[ $front ] ) ) {
			$home = true;
		}
		$out = array();
		if ( ! $home ) {
			$out[] = self::issue(
				'low',
				'missing_link',
				0,
				__( 'No link to the home page', 'dev3-ai-mega-menu' ),
				__( 'Many visitors expect a Home item (or a linked logo).', 'dev3-ai-mega-menu' ),
				array(
					'type'  => 'add_link',
					'label' => __( 'Home', 'dev3-ai-mega-menu' ),
					'ref'   => 'home',
				)
			);
		}
		$contact = get_posts(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'numberposts' => 1,
				's'           => 'contact',
				'fields'      => 'ids',
			)
		);
		if ( $contact && ! isset( $linked[ (int) $contact[0] ] ) ) {
			$out[] = self::issue(
				'medium',
				'missing_link',
				0,
				__( 'The contact page is not in the menu', 'dev3-ai-mega-menu' ),
				__( 'Visitors look for a way to get in touch in the main navigation.', 'dev3-ai-mega-menu' ),
				array(
					'type'  => 'add_link',
					'label' => wp_strip_all_tags( get_the_title( (int) $contact[0] ) ),
					'ref'   => 'page:' . (int) $contact[0],
				)
			);
		}
		$shop = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
		if ( $shop > 0 && 'publish' === get_post_status( $shop ) && ! isset( $linked[ $shop ] ) ) {
			$has_shop_terms = false;
			foreach ( $flat as $item ) {
				if ( 'product_cat' === $item['object'] ) {
					$has_shop_terms = true;
					break;
				}
			}
			if ( ! $has_shop_terms ) {
				$out[] = self::issue(
					'medium',
					'missing_link',
					0,
					__( 'The shop is not in the menu', 'dev3-ai-mega-menu' ),
					__( 'Add the shop page or product categories so visitors can browse products.', 'dev3-ai-mega-menu' ),
					array(
						'type'  => 'add_link',
						'label' => wp_strip_all_tags( get_the_title( $shop ) ),
						'ref'   => 'page:' . $shop,
					)
				);
			}
		}
		return $out;
	}

	/**
	 * DEV3 AI review.
	 *
	 * @param array $tree  Tree.
	 * @param array $known Rule issues found already.
	 * @return array|\WP_Error { issues, model, tokens, cached }
	 */
	public static function ai( array $tree, array $known ) {
		$flat  = Tree::flatten( $tree );
		$lines = array();
		$ids   = array();
		foreach ( $flat as $item ) {
			$ids[ (int) $item['id'] ] = $item;
			$target                   = 'custom' === $item['type'] ? (string) wp_parse_url( (string) $item['url'], PHP_URL_PATH ) : $item['object'];
			$lines[]                  = str_repeat( '  ', (int) $item['depth'] ) . '[' . (int) $item['id'] . '] ' . $item['title'] . ' -> ' . ( '' === $target ? '#' : $target );
		}
		$linked = array();
		foreach ( $flat as $item ) {
			if ( 'post_type' === $item['type'] ) {
				$linked[ (int) $item['object_id'] ] = true;
			}
		}
		$candidates = array();
		$pages      = get_posts(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'numberposts' => 80,
				'orderby'     => 'menu_order',
				'order'       => 'ASC',
			)
		);
		$valid      = array( 'home' => true );
		foreach ( $pages as $page ) {
			if ( ! isset( $linked[ (int) $page->ID ] ) ) {
				$candidates[]                       = 'page:' . (int) $page->ID . ' | ' . wp_strip_all_tags( get_the_title( $page ) );
				$valid[ 'page:' . (int) $page->ID ] = true;
			}
		}
		$result = Prompts::run(
			Prompts_Library::AUDIT,
			array_merge(
				Prompts_Library::site_vars(),
				array(
					'menu'       => implode( "\n", $lines ),
					'candidates' => $candidates ? implode( "\n", array_slice( $candidates, 0, 60 ) ) : '(none)',
					'known'      => $known ? implode( '; ', array_map( static fn( $i ) => $i['title'], $known ) ) : '(none)',
				)
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$issues = array();
		foreach ( isset( $result['data']['issues'] ) && is_array( $result['data']['issues'] ) ? $result['data']['issues'] : array() as $raw ) {
			if ( ! is_array( $raw ) || empty( $raw['title'] ) ) {
				continue;
			}
			$severity = isset( $raw['severity'] ) && isset( self::WEIGHTS[ $raw['severity'] ] ) ? $raw['severity'] : 'low';
			$code     = isset( $raw['code'] ) ? sanitize_key( (string) $raw['code'] ) : 'other';
			$item_id  = isset( $raw['item_id'] ) && isset( $ids[ (int) $raw['item_id'] ] ) ? (int) $raw['item_id'] : 0;
			$fix      = array();
			$label    = isset( $raw['suggested_label'] ) ? mb_substr( sanitize_text_field( (string) $raw['suggested_label'] ), 0, 40 ) : '';
			$ref      = isset( $raw['suggested_ref'] ) ? strtolower( trim( (string) $raw['suggested_ref'] ) ) : '';
			if ( $item_id && '' !== $label && $label !== $ids[ $item_id ]['title'] ) {
				$fix = array(
					'type'  => 'rename',
					'label' => $label,
				);
			} elseif ( '' !== $ref && isset( $valid[ $ref ] ) ) {
				$fix = array(
					'type'  => 'add_link',
					'label' => '' !== $label ? $label : Menu_Builder::ref_title( $ref ),
					'ref'   => $ref,
				);
			}
			$issues[] = self::issue( $severity, $code, $item_id, mb_substr( sanitize_text_field( (string) $raw['title'] ), 0, 90 ), mb_substr( sanitize_text_field( (string) ( isset( $raw['detail'] ) ? $raw['detail'] : '' ) ), 0, 240 ), $fix, 'ai' );
		}
		return array(
			'issues' => $issues,
			'model'  => isset( $result['model'] ) ? $result['model'] : '',
			'tokens' => isset( $result['usage']['total'] ) ? (int) $result['usage']['total'] : 0,
			'cached' => ! empty( $result['cached'] ),
		);
	}
}
