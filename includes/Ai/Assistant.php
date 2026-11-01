<?php
/**
 * DEV3 AI helpers for an existing menu: labels & descriptions, icon/image suggestions and promo panels.
 * Every method returns a draft; nothing is saved here.
 *
 * @package Dev3\AiMegaMenu
 */

namespace Dev3\AiMegaMenu\Ai;

use Dev3\AiMegaMenu\Core3\Api\Prompts;
use Dev3\AiMegaMenu\Front\Widgets;
use Dev3\AiMegaMenu\Menu\Icons;
use Dev3\AiMegaMenu\Menu\Item_Config;
use Dev3\AiMegaMenu\Menu\Tree;

defined( 'ABSPATH' ) || exit;

/**
 * Draft producers.
 */
class Assistant {

	/**
	 * Flat items of a menu (optionally limited to ids).
	 *
	 * @param int   $menu_id Menu.
	 * @param int[] $only    Item ids.
	 * @return array[]|\WP_Error
	 */
	private static function flat( $menu_id, array $only = array() ) {
		if ( ! is_nav_menu( (int) $menu_id ) ) {
			return new \WP_Error( 'dev3mm_no_menu', __( 'That menu does not exist.', 'dev3-ai-mega-menu' ), array( 'status' => 404 ) );
		}
		$flat = Tree::flatten( Tree::get( (int) $menu_id ) );
		if ( $only ) {
			$flat = array_values( array_filter( $flat, static fn( $i ) => in_array( (int) $i['id'], $only, true ) ) );
		}
		return array_slice( $flat, 0, 200 );
	}

	/**
	 * Result meta.
	 *
	 * @param array $result Ai result.
	 * @return array
	 */
	private static function meta( array $result ) {
		return array(
			'model'  => isset( $result['model'] ) ? $result['model'] : '',
			'tokens' => isset( $result['usage']['total'] ) ? (int) $result['usage']['total'] : 0,
			'cached' => ! empty( $result['cached'] ),
		);
	}

	/**
	 * AI labels and short descriptions in the site's language.
	 *
	 * @param int   $menu_id Menu.
	 * @param int[] $only    Item ids (empty = all).
	 * @return array|\WP_Error { items: [ { id, title, description, label, new_description, parent } ], language, meta }
	 */
	public static function labels( $menu_id, array $only = array() ) {
		$flat = self::flat( $menu_id, $only );
		if ( is_wp_error( $flat ) ) {
			return $flat;
		}
		if ( ! $flat ) {
			return new \WP_Error( 'dev3mm_empty_menu', __( 'The menu has no items yet.', 'dev3-ai-mega-menu' ), array( 'status' => 400 ) );
		}
		$lines = array();
		$by_id = array();
		foreach ( $flat as $item ) {
			$by_id[ (int) $item['id'] ] = $item;
			$target                     = 'custom' === $item['type'] ? (string) $item['url'] : $item['type_label'] . ' “' . self::object_title( $item ) . '”';
			$lines[]                    = (int) $item['id'] . ' | ' . $item['title'] . ' | ' . ( '' !== $item['parent'] ? $item['parent'] : '-' ) . ' | ' . $target;
		}
		$result = Prompts::run( Prompts_Library::LABELS, array_merge( Prompts_Library::site_vars(), array( 'items' => implode( "\n", $lines ) ) ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$out = array();
		foreach ( isset( $result['data']['items'] ) && is_array( $result['data']['items'] ) ? $result['data']['items'] : array() as $row ) {
			$id = is_array( $row ) && isset( $row['id'] ) ? (int) $row['id'] : 0;
			if ( ! isset( $by_id[ $id ] ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? mb_substr( sanitize_text_field( (string) $row['label'] ), 0, 40 ) : '';
			$out[] = array(
				'id'              => $id,
				'title'           => $by_id[ $id ]['title'],
				'description'     => $by_id[ $id ]['description'],
				'parent'          => $by_id[ $id ]['parent'],
				'depth'           => $by_id[ $id ]['depth'],
				'label'           => '' !== $label ? $label : $by_id[ $id ]['title'],
				'new_description' => isset( $row['description'] ) ? mb_substr( sanitize_text_field( (string) $row['description'] ), 0, 100 ) : '',
			);
			unset( $by_id[ $id ] );
		}
		return array(
			'items'    => $out,
			'language' => Prompts_Library::language(),
			'meta'     => self::meta( $result ),
		);
	}

	/**
	 * Title of the object behind an item.
	 *
	 * @param array $item Flat item.
	 * @return string
	 */
	private static function object_title( array $item ) {
		if ( 'post_type' === $item['type'] ) {
			return wp_strip_all_tags( get_the_title( (int) $item['object_id'] ) );
		}
		if ( 'taxonomy' === $item['type'] ) {
			$term = get_term( (int) $item['object_id'], $item['object'] );
			return $term && ! is_wp_error( $term ) ? $term->name : '';
		}
		return '';
	}

	/**
	 * Icon and image suggestions (DEV3 AI, or keyword matching when AI is off / fails).
	 *
	 * @param int  $menu_id Menu.
	 * @param bool $use_ai  Use DEV3 AI.
	 * @return array|\WP_Error { items: [ { id, title, depth, current_icon, icon, images[] } ], source, meta, ai_error }
	 */
	public static function icons( $menu_id, $use_ai = true ) {
		$flat = self::flat( $menu_id );
		if ( is_wp_error( $flat ) ) {
			return $flat;
		}
		$ai       = array();
		$meta     = array();
		$ai_error = null;
		if ( $use_ai && $flat ) {
			$lines = array();
			foreach ( $flat as $item ) {
				$lines[] = (int) $item['id'] . ' | ' . $item['title'] . ' | ' . ( '' !== $item['parent'] ? $item['parent'] : '-' );
			}
			$result = Prompts::run( Prompts_Library::ICONS, array_merge( Prompts_Library::site_vars(), array( 'items' => implode( "\n", $lines ) ) ) );
			if ( is_wp_error( $result ) ) {
				$ai_error = array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				);
			} else {
				$meta = self::meta( $result );
				foreach ( isset( $result['data']['items'] ) && is_array( $result['data']['items'] ) ? $result['data']['items'] : array() as $row ) {
					if ( is_array( $row ) && isset( $row['id'] ) ) {
						$ai[ (int) $row['id'] ] = $row;
					}
				}
			}
		}
		$out = array();
		foreach ( $flat as $item ) {
			$id    = (int) $item['id'];
			$icon  = isset( $ai[ $id ]['icon'] ) ? sanitize_key( (string) $ai[ $id ]['icon'] ) : '';
			$query = isset( $ai[ $id ]['image_query'] ) ? sanitize_text_field( (string) $ai[ $id ]['image_query'] ) : '';
			if ( ! Icons::exists( $icon ) ) {
				$icon = Icons::match( $item['title'] );
			}
			$out[] = array(
				'id'            => $id,
				'title'         => $item['title'],
				'depth'         => (int) $item['depth'],
				'current_icon'  => isset( $item['config']['icon'] ) ? $item['config']['icon'] : '',
				'current_image' => isset( $item['config']['image_id'] ) ? (int) $item['config']['image_id'] : 0,
				'icon'          => $icon,
				'images'        => self::image_candidates( $item, '' !== $query ? $query : $item['title'] ),
			);
		}
		return array(
			'items'    => $out,
			'source'   => $ai ? 'ai' : 'match',
			'meta'     => $meta,
			'ai_error' => $ai_error,
		);
	}

	/**
	 * Images for an item: the linked object's featured image / category thumbnail, then media matches.
	 *
	 * @param array  $item  Flat item.
	 * @param string $query Search words.
	 * @return array[] { id, url, alt }
	 */
	public static function image_candidates( array $item, $query ) {
		$ids = array();
		if ( 'post_type' === $item['type'] && has_post_thumbnail( (int) $item['object_id'] ) ) {
			$ids[] = (int) get_post_thumbnail_id( (int) $item['object_id'] );
		}
		if ( 'taxonomy' === $item['type'] && 'product_cat' === $item['object'] ) {
			$thumb = (int) get_term_meta( (int) $item['object_id'], 'thumbnail_id', true );
			if ( $thumb ) {
				$ids[] = $thumb;
			}
		}
		if ( '' !== trim( $query ) ) {
			$found = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'post_mime_type' => 'image',
					's'              => $query,
					'numberposts'    => 3,
					'fields'         => 'ids',
				)
			);
			$ids   = array_merge( $ids, array_map( 'intval', $found ) );
		}
		$out = array();
		foreach ( array_slice( array_unique( array_filter( $ids ) ), 0, 4 ) as $id ) {
			$src = wp_get_attachment_image_url( $id, 'thumbnail' );
			if ( $src ) {
				$out[] = array(
					'id'  => $id,
					'url' => $src,
					'alt' => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
				);
			}
		}
		return $out;
	}

	/**
	 * Candidate products for a promo.
	 *
	 * @param array $opts { source, category, product_ids }.
	 * @return array[]
	 */
	public static function promo_candidates( array $opts ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return array();
		}
		$ids = ! empty( $opts['product_ids'] ) ? array_map( 'intval', (array) $opts['product_ids'] ) : Widgets::product_ids(
			Item_Config::sanitize_widget(
				array(
					'type'     => 'products',
					'source'   => isset( $opts['source'] ) ? $opts['source'] : 'sale',
					'category' => isset( $opts['category'] ) ? (int) $opts['category'] : 0,
					'count'    => 8,
				)
			)
		);
		if ( ! $ids && ( ! isset( $opts['source'] ) || 'recent' !== $opts['source'] ) ) {
			$ids = Widgets::product_ids(
				Item_Config::sanitize_widget(
					array(
						'type'   => 'products',
						'source' => 'recent',
						'count'  => 8,
					)
				)
			);
		}
		$out = array();
		foreach ( array_slice( $ids, 0, 12 ) as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$cats  = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'names' ) );
			$out[] = array(
				'id'       => (int) $id,
				'name'     => $product->get_name(),
				'price'    => wp_strip_all_tags( wc_price( (float) $product->get_price() ) ),
				'category' => is_array( $cats ) && $cats ? (string) $cats[0] : '',
				'on_sale'  => $product->is_on_sale(),
				'image'    => (string) wp_get_attachment_image_url( (int) $product->get_image_id(), 'thumbnail' ),
			);
		}
		return $out;
	}

	/**
	 * DEV3 AI promo panel draft.
	 *
	 * @param array $opts { occasion, tone, source, category, product_ids, cta_url }.
	 * @return array|\WP_Error { widget, candidates, meta }
	 */
	public static function promo( array $opts ) {
		$occasion   = isset( $opts['occasion'] ) ? mb_substr( sanitize_text_field( (string) $opts['occasion'] ), 0, 120 ) : '';
		$occasion   = '' !== $occasion ? $occasion : self::season();
		$candidates = self::promo_candidates( $opts );
		$lines      = array();
		foreach ( $candidates as $c ) {
			$lines[] = $c['id'] . ' | ' . $c['name'] . ' | ' . $c['price'] . ' | ' . $c['category'] . ' | ' . ( $c['on_sale'] ? 'yes' : 'no' );
		}
		$result = Prompts::run(
			Prompts_Library::PROMO,
			array_merge(
				Prompts_Library::site_vars(),
				array(
					'occasion' => $occasion,
					'tone'     => isset( $opts['tone'] ) && '' !== $opts['tone'] ? sanitize_text_field( (string) $opts['tone'] ) : 'friendly',
					'offer'    => ! empty( $opts['offer'] ) ? '- The real offer is: ' . mb_substr( sanitize_text_field( (string) $opts['offer'] ), 0, 160 ) . '. Mention it accurately.' : '- No discount is given: do not invent percentages or codes.',
					'products' => $lines ? implode( "\n", $lines ) : '(no products: leave product_ids empty)',
				)
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$data    = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();
		$allowed = wp_list_pluck( $candidates, 'id' );
		$picked  = array_values( array_intersect( array_map( 'intval', isset( $data['product_ids'] ) && is_array( $data['product_ids'] ) ? $data['product_ids'] : array() ), $allowed ) );
		$widget  = Item_Config::sanitize_widget(
			array(
				'type'        => 'promo',
				'badge'       => isset( $data['badge'] ) ? $data['badge'] : '',
				'title'       => isset( $data['title'] ) ? $data['title'] : '',
				'text'        => isset( $data['text'] ) ? $data['text'] : '',
				'cta_label'   => isset( $data['cta_label'] ) ? $data['cta_label'] : '',
				'cta_url'     => self::cta_url( $opts ),
				'product_ids' => array_slice( $picked, 0, 3 ),
				'tone'        => 'brand',
			)
		);
		return array(
			'widget'     => $widget,
			'occasion'   => $occasion,
			'candidates' => $candidates,
			'meta'       => self::meta( $result ),
		);
	}

	/**
	 * Promo template without AI.
	 *
	 * @param array $opts Options (occasion, source, category, product_ids, cta_url).
	 * @return array { widget, candidates }
	 */
	public static function promo_template( array $opts ) {
		$occasion   = isset( $opts['occasion'] ) && '' !== trim( (string) $opts['occasion'] ) ? sanitize_text_field( (string) $opts['occasion'] ) : self::season();
		$candidates = self::promo_candidates( $opts );
		$widget     = Item_Config::sanitize_widget(
			array(
				'type'        => 'promo',
				'badge'       => __( 'Featured', 'dev3-ai-mega-menu' ),
				/* translators: %s: occasion. */
				'title'       => sprintf( __( '%s picks', 'dev3-ai-mega-menu' ), $occasion ),
				'text'        => __( 'Hand-picked favourites for the season. Take a look before they are gone.', 'dev3-ai-mega-menu' ),
				'cta_label'   => __( 'Shop the collection', 'dev3-ai-mega-menu' ),
				'cta_url'     => self::cta_url( $opts ),
				'product_ids' => array_slice( wp_list_pluck( $candidates, 'id' ), 0, 3 ),
				'tone'        => 'brand',
			)
		);
		return array(
			'widget'     => $widget,
			'occasion'   => $occasion,
			'candidates' => $candidates,
		);
	}

	/**
	 * Promo link target.
	 *
	 * @param array $opts Options.
	 * @return string
	 */
	private static function cta_url( array $opts ) {
		if ( ! empty( $opts['cta_url'] ) ) {
			return esc_url_raw( (string) $opts['cta_url'] );
		}
		if ( ! empty( $opts['category'] ) ) {
			$link = get_term_link( (int) $opts['category'], 'product_cat' );
			if ( ! is_wp_error( $link ) ) {
				return $link;
			}
		}
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			return (string) wc_get_page_permalink( 'shop' );
		}
		return home_url( '/' );
	}

	/**
	 * Current season / occasion by month (northern hemisphere default; filterable).
	 *
	 * @return string
	 */
	public static function season() {
		$month   = (int) current_time( 'n' );
		$seasons = array(
			1  => __( 'New Year', 'dev3-ai-mega-menu' ),
			2  => __( "Valentine's Day", 'dev3-ai-mega-menu' ),
			3  => __( 'Spring', 'dev3-ai-mega-menu' ),
			4  => __( 'Spring', 'dev3-ai-mega-menu' ),
			5  => __( 'Early summer', 'dev3-ai-mega-menu' ),
			6  => __( 'Summer', 'dev3-ai-mega-menu' ),
			7  => __( 'Summer', 'dev3-ai-mega-menu' ),
			8  => __( 'Back to school', 'dev3-ai-mega-menu' ),
			9  => __( 'Autumn', 'dev3-ai-mega-menu' ),
			10 => __( 'Autumn', 'dev3-ai-mega-menu' ),
			11 => __( 'Black Friday', 'dev3-ai-mega-menu' ),
			12 => __( 'Holiday season', 'dev3-ai-mega-menu' ),
		);
		/**
		 * Filters the default promo occasion.
		 *
		 * @param string $season Occasion.
		 * @param int    $month  Month (1-12).
		 */
		return (string) apply_filters( 'dev3mm_default_occasion', $seasons[ $month ], $month );
	}

	/**
	 * Inserts an accepted promo widget into a top-level item's mega panel (side column).
	 *
	 * @param int   $item_id Menu item id.
	 * @param array $widget  Widget.
	 * @return array|\WP_Error Saved config.
	 */
	public static function insert_promo( $item_id, array $widget ) {
		$item = get_post( (int) $item_id );
		if ( ! $item || 'nav_menu_item' !== $item->post_type ) {
			return new \WP_Error( 'dev3mm_no_item', __( 'That menu item does not exist.', 'dev3-ai-mega-menu' ), array( 'status' => 404 ) );
		}
		$widget['type'] = 'promo';
		$clean          = Item_Config::sanitize_widget( $widget );
		$config         = Item_Config::get( (int) $item_id );
		if ( 'flyout' === $config['type'] || 'auto' === $config['type'] ) {
			$config['type'] = 'mega';
		}
		// The promo sits in the panel's side column, next to the link columns.
		$config['aside'] = array_slice( array_merge( array( $clean ), $config['aside'] ), 0, 4 );
		return Item_Config::save( (int) $item_id, $config );
	}
}
