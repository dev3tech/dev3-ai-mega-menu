<?php
/**
 * Panel widgets: posts, products, search, HTML, shortcodes, images, promo blocks and reusable blocks.
 *
 * @package Dev3\AiMegaMenu
 */

namespace Dev3\AiMegaMenu\Front;

defined( 'ABSPATH' ) || exit;

/**
 * Widget renderers (input is already sanitised by Item_Config).
 */
class Widgets {

	/**
	 * Renders a widget.
	 *
	 * @param array  $w   Widget.
	 * @param string $uid Unique id prefix.
	 * @return string
	 */
	public static function render( array $w, $uid = 'dev3mm-w' ) {
		static $n = 0;
		++$n;
		switch ( $w['type'] ) {
			case 'posts':
				$body = self::posts( $w );
				break;
			case 'products':
				$body = self::products( $w );
				break;
			case 'search':
				$body = self::search_form( $w, $uid . '-s' . $n );
				break;
			case 'html':
				$body = '<div class="dev3mm__html">' . wp_kses_post( $w['content'] ) . '</div>';
				break;
			case 'shortcode':
				$body = '<div class="dev3mm__html">' . do_shortcode( $w['content'] ) . '</div>';
				break;
			case 'image':
				$body = self::image( $w );
				break;
			case 'promo':
				$body = self::promo( $w );
				break;
			case 'block':
				$body = self::block( $w );
				break;
			default:
				$body = '';
		}
		/**
		 * Filters a rendered panel widget.
		 *
		 * @param string $body Markup.
		 * @param array  $w    Widget.
		 */
		$body = (string) apply_filters( 'dev3mm_widget_html', $body, $w );
		if ( '' === $body ) {
			return '';
		}
		$title = ! empty( $w['title'] ) && 'promo' !== $w['type'] ? '<p class="dev3mm__widget-title">' . esc_html( $w['title'] ) . '</p>' : '';
		return '<div class="dev3mm__widget dev3mm__widget--' . esc_attr( $w['type'] ) . '">' . $title . $body . '</div>';
	}

	/**
	 * Recent posts.
	 *
	 * @param array $w Widget.
	 * @return string
	 */
	private static function posts( array $w ) {
		$args = array(
			'numberposts'      => $w['count'],
			'post_status'      => 'publish',
			'suppress_filters' => false,
		);
		if ( $w['category'] ) {
			$args['category'] = $w['category'];
		}
		$posts = get_posts( $args );
		if ( ! $posts ) {
			return '';
		}
		$html = '<ul class="dev3mm__cards">';
		foreach ( $posts as $post ) {
			$img   = $w['show_image'] && has_post_thumbnail( $post ) ? get_the_post_thumbnail(
				$post,
				'thumbnail',
				array(
					'class'   => 'dev3mm__card-img',
					'alt'     => '',
					'loading' => 'lazy',
				)
			) : '';
			$html .= '<li class="dev3mm__card"><a class="dev3mm__card-link" href="' . esc_url( get_permalink( $post ) ) . '">' . $img . '<span class="dev3mm__card-body"><span class="dev3mm__card-title">' . esc_html( get_the_title( $post ) ) . '</span><span class="dev3mm__card-meta">' . esc_html( get_the_date( '', $post ) ) . '</span></span></a></li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Product ids for a products widget.
	 *
	 * @param array $w Widget.
	 * @return int[]
	 */
	public static function product_ids( array $w ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}
		$args = array(
			'status'     => 'publish',
			'limit'      => $w['count'],
			'return'     => 'ids',
			'visibility' => 'catalog',
		);
		switch ( $w['source'] ) {
			case 'ids':
				if ( ! $w['ids'] ) {
					return array();
				}
				$args['include'] = $w['ids'];
				$args['orderby'] = 'include';
				break;
			case 'featured':
				$args['featured'] = true;
				break;
			case 'sale':
				$sale = function_exists( 'wc_get_product_ids_on_sale' ) ? wc_get_product_ids_on_sale() : array();
				if ( ! $sale ) {
					return array();
				}
				$args['include'] = array_map( 'intval', $sale );
				break;
			case 'best':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- WooCommerce's own sales counter.
				$args['order']    = 'DESC';
				break;
			case 'category':
				$term = $w['category'] ? get_term( $w['category'], 'product_cat' ) : null;
				if ( ! $term || is_wp_error( $term ) ) {
					return array();
				}
				$args['category'] = array( $term->slug );
				break;
			default:
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
		}
		$ids = wc_get_products( $args );
		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	/**
	 * Product cards.
	 *
	 * @param array $w Widget.
	 * @return string
	 */
	private static function products( array $w ) {
		return self::product_cards( self::product_ids( $w ) );
	}

	/**
	 * Product mini cards for ids.
	 *
	 * @param int[] $ids Ids.
	 * @return string
	 */
	public static function product_cards( array $ids ) {
		if ( ! $ids || ! function_exists( 'wc_get_product' ) ) {
			return '';
		}
		$html = '<ul class="dev3mm__cards dev3mm__cards--products">';
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! $product->is_visible() ) {
				continue;
			}
			$img   = $product->get_image(
				'woocommerce_thumbnail',
				array(
					'class'   => 'dev3mm__card-img',
					'alt'     => '',
					'loading' => 'lazy',
				)
			);
			$html .= '<li class="dev3mm__card"><a class="dev3mm__card-link" href="' . esc_url( $product->get_permalink() ) . '">' . $img . '<span class="dev3mm__card-body"><span class="dev3mm__card-title">' . esc_html( $product->get_name() ) . '</span><span class="dev3mm__card-meta dev3mm__price">' . wp_kses_post( $product->get_price_html() ) . '</span></span></a></li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Search form.
	 *
	 * @param array  $w  Widget.
	 * @param string $id Input id.
	 * @return string
	 */
	public static function search_form( array $w, $id ) {
		$placeholder = ! empty( $w['placeholder'] ) ? $w['placeholder'] : __( 'Search…', 'dev3-ai-mega-menu' );
		$type        = isset( $w['post_type'] ) && 'any' !== $w['post_type'] ? $w['post_type'] : '';
		$html        = '<form role="search" method="get" class="dev3mm__search" action="' . esc_url( home_url( '/' ) ) . '">';
		$html       .= '<label class="dev3mm__sr" for="' . esc_attr( $id ) . '">' . esc_html__( 'Search for:', 'dev3-ai-mega-menu' ) . '</label>';
		$html       .= '<input class="dev3mm__search-input" type="search" id="' . esc_attr( $id ) . '" name="s" placeholder="' . esc_attr( $placeholder ) . '" autocomplete="off">';
		if ( '' !== $type ) {
			$html .= '<input type="hidden" name="post_type" value="' . esc_attr( $type ) . '">';
		}
		$html .= '<button class="dev3mm__search-btn" type="submit"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M11 19a8 8 0 100-16 8 8 0 000 16zM21 21l-4.3-4.3"/></svg><span class="dev3mm__sr">' . esc_html__( 'Search', 'dev3-ai-mega-menu' ) . '</span></button></form>';
		return $html;
	}

	/**
	 * Image (optionally linked).
	 *
	 * @param array $w Widget.
	 * @return string
	 */
	private static function image( array $w ) {
		if ( ! $w['image_id'] ) {
			return '';
		}
		$img = wp_get_attachment_image(
			$w['image_id'],
			'medium_large',
			false,
			array(
				'class'   => 'dev3mm__image',
				'loading' => 'lazy',
				'alt'     => $w['caption'] ? '' : (string) get_post_meta( $w['image_id'], '_wp_attachment_image_alt', true ),
			)
		);
		if ( ! $img ) {
			return '';
		}
		$caption = $w['caption'] ? '<figcaption class="dev3mm__caption">' . esc_html( $w['caption'] ) . '</figcaption>' : '';
		$inner   = $w['link'] ? '<a class="dev3mm__image-link" href="' . esc_url( $w['link'] ) . '">' . $img . $caption . '</a>' : $img . $caption;
		return '<figure class="dev3mm__figure">' . $inner . '</figure>';
	}

	/**
	 * Promo block (AI-generated or written by hand).
	 *
	 * @param array $w Widget.
	 * @return string
	 */
	private static function promo( array $w ) {
		$html = '<div class="dev3mm__promo dev3mm__promo--' . esc_attr( $w['tone'] ) . '">';
		if ( $w['image_id'] ) {
			$html .= wp_get_attachment_image(
				$w['image_id'],
				'medium_large',
				false,
				array(
					'class'   => 'dev3mm__promo-img',
					'alt'     => '',
					'loading' => 'lazy',
				)
			);
		}
		$html .= '<div class="dev3mm__promo-body">';
		if ( $w['badge'] ) {
			$html .= '<span class="dev3mm__promo-badge">' . esc_html( $w['badge'] ) . '</span>';
		}
		if ( $w['title'] ) {
			$html .= '<p class="dev3mm__promo-title">' . esc_html( $w['title'] ) . '</p>';
		}
		if ( $w['text'] ) {
			$html .= '<p class="dev3mm__promo-text">' . esc_html( $w['text'] ) . '</p>';
		}
		$html .= self::product_cards( $w['product_ids'] );
		if ( $w['cta_label'] && $w['cta_url'] ) {
			$html .= '<a class="dev3mm__promo-cta" href="' . esc_url( $w['cta_url'] ) . '">' . esc_html( $w['cta_label'] ) . '</a>';
		}
		return $html . '</div></div>';
	}

	/**
	 * Reusable block (synced pattern).
	 *
	 * @param array $w Widget.
	 * @return string
	 */
	private static function block( array $w ) {
		$post = $w['block_id'] ? get_post( $w['block_id'] ) : null;
		if ( ! $post || 'wp_block' !== $post->post_type || 'publish' !== $post->post_status ) {
			return '';
		}
		return '<div class="dev3mm__html">' . do_blocks( $post->post_content ) . '</div>';
	}
}
