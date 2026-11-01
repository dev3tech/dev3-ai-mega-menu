<?php
/**
 * Renders an accessible mega menu (WAI disclosure navigation pattern) for a nav menu.
 *
 * @package Dev3\AiMegaMenu
 */

namespace Dev3\AiMegaMenu\Front;

use Dev3\AiMegaMenu\Menu\Icons;
use Dev3\AiMegaMenu\Menu\Item_Config;
use Dev3\AiMegaMenu\Menu\Locations;
use Dev3\AiMegaMenu\Menu\Themes;

defined( 'ABSPATH' ) || exit;

/**
 * Markup: nav.dev3mm > ul.dev3mm__menu > li.dev3mm__item; each item with a submenu gets a toggle
 * button (aria-expanded + aria-controls) next to its link, and panels open on hover/click/keyboard.
 */
class Renderer {

	/**
	 * Instance counter for unique ids.
	 *
	 * @var int
	 */
	private static $instance = 0;

	/**
	 * Current instance prefix.
	 *
	 * @var string
	 */
	private $uid = '';

	/**
	 * Items grouped by parent id.
	 *
	 * @var array
	 */
	private $children = array();

	/**
	 * Location config.
	 *
	 * @var array
	 */
	private $config = array();

	/**
	 * Item configs for unsaved previews (id => config), or null.
	 *
	 * @var array|null
	 */
	private $configs = null;

	/**
	 * Renders a menu.
	 *
	 * @param int    $menu_id   Menu term id.
	 * @param string $location  Location key whose display settings apply.
	 * @param array  $overrides Location setting overrides (+ `tokens` for live previews).
	 * @return string HTML ('' when the menu is empty or missing).
	 */
	public static function render( $menu_id, $location = Locations::EMBED, array $overrides = array() ) {
		return ( new self() )->build( (int) $menu_id, (string) $location, $overrides );
	}

	/**
	 * Renders an unsaved builder tree (admin live preview). Configs come from the tree, not post meta.
	 *
	 * @param array  $tree      Tree nodes ({ id, title, url, target, description, config, children }).
	 * @param string $name      Menu name (aria-label).
	 * @param string $location  Location.
	 * @param array  $overrides Overrides.
	 * @return string
	 */
	public static function render_tree( array $tree, $name, $location = Locations::EMBED, array $overrides = array() ) {
		$self          = new self();
		$self->configs = array();
		$items         = array();
		$next          = 0;
		$orig          = array();
		$walk          = static function ( array $nodes, $parent_id, $depth ) use ( &$walk, &$items, &$next, &$orig, $self ) {
			foreach ( $nodes as $node ) {
				if ( ! is_array( $node ) || $depth > 6 || count( $items ) >= 400 ) {
					continue;
				}
				$id = ++$next;
				$orig[ isset( $node['id'] ) ? (int) $node['id'] : -$id ] = $id;
				$self->configs[ $id ]                                    = Item_Config::sanitize( isset( $node['config'] ) && is_array( $node['config'] ) ? $node['config'] : array() );
				$items[] = (object) array(
					'ID'               => $id,
					'menu_item_parent' => $parent_id,
					'title'            => isset( $node['title'] ) ? sanitize_text_field( (string) $node['title'] ) : '',
					'url'              => isset( $node['url'] ) ? esc_url_raw( (string) $node['url'] ) : '#',
					'target'           => isset( $node['target'] ) && '_blank' === $node['target'] ? '_blank' : '',
					'attr_title'       => '',
					'xfn'              => '',
					'description'      => isset( $node['description'] ) ? sanitize_text_field( (string) $node['description'] ) : '',
					'classes'          => array(),
				);
				if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
					$walk( $node['children'], $id, $depth + 1 );
				}
			}
		};
		$walk( $tree, 0, 0 );
		// Widgets that point at child items use builder ids; remap them to the preview ids.
		foreach ( $self->configs as $cid => $config ) {
			foreach ( $config['rows'] as $r => $row ) {
				foreach ( $row['cols'] as $c => $col ) {
					foreach ( $col['widgets'] as $w => $widget ) {
						if ( 'item' === $widget['type'] ) {
							$self->configs[ $cid ]['rows'][ $r ]['cols'][ $c ]['widgets'][ $w ]['item_id'] = isset( $orig[ $widget['item_id'] ] ) ? $orig[ $widget['item_id'] ] : 0;
						}
					}
				}
			}
		}
		return $self->compose( $items, (string) $name, $location, $overrides );
	}

	/**
	 * Builds the markup.
	 *
	 * @param int    $menu_id   Menu.
	 * @param string $location  Location.
	 * @param array  $overrides Overrides.
	 * @return string
	 */
	private function build( $menu_id, $location, array $overrides ) {
		$menu = wp_get_nav_menu_object( $menu_id );
		if ( ! $menu ) {
			return '';
		}
		$items = wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) );
		if ( ! is_array( $items ) || ! $items ) {
			return '';
		}
		if ( function_exists( '_wp_menu_item_classes_by_context' ) ) {
			_wp_menu_item_classes_by_context( $items );
		}
		$args  = (object) array(
			'menu'           => $menu,
			'theme_location' => Locations::EMBED === $location ? '' : $location,
			'dev3mm'         => true,
		);
		$items = apply_filters( 'wp_nav_menu_objects', $items, $args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook, for compatibility with visibility plugins.
		$html  = $this->compose( (array) $items, $menu->name, $location, $overrides );
		/**
		 * Filters the rendered mega menu HTML.
		 *
		 * @param string $html     Markup.
		 * @param int    $menu_id  Menu id.
		 * @param string $location Location.
		 * @param array  $config   Location config.
		 */
		return '' === $html ? '' : (string) apply_filters( 'dev3mm_menu_html', $html, $menu->term_id, $location, $this->config );
	}

	/**
	 * Item config (preview configs first, then post meta).
	 *
	 * @param int $id Item id.
	 * @return array
	 */
	private function cfg( $id ) {
		return null !== $this->configs && isset( $this->configs[ $id ] ) ? $this->configs[ $id ] : Item_Config::get( $id );
	}

	/**
	 * Composes the nav markup from menu item objects.
	 *
	 * @param array  $items     Items.
	 * @param string $name      Menu name.
	 * @param string $location  Location.
	 * @param array  $overrides Overrides.
	 * @return string
	 */
	private function compose( array $items, $name, $location, array $overrides ) {
		foreach ( $items as $item ) {
			if ( ! empty( $item->_invalid ) ) {
				continue;
			}
			$this->children[ (int) $item->menu_item_parent ][] = $item;
		}
		if ( empty( $this->children[0] ) ) {
			return '';
		}

		$tokens = isset( $overrides['tokens'] ) && is_array( $overrides['tokens'] ) ? $overrides['tokens'] : null;
		unset( $overrides['tokens'] );
		$this->config = Locations::sanitize( array_merge( Locations::get( $location ), $overrides ) );
		if ( null === $tokens ) {
			$theme  = Themes::get( $this->config['theme'] );
			$tokens = $theme['tokens'];
		}
		++self::$instance;
		$this->uid = 'dev3mm-' . self::$instance;
		$c         = $this->config;

		$classes = array(
			'dev3mm',
			'dev3mm--' . $c['orientation'],
			'dev3mm--bp-' . $c['breakpoint'],
			'dev3mm--trigger-' . $c['trigger'],
			'dev3mm--anim-' . $c['animation'],
			'dev3mm--mobile-' . $c['mobile_mode'],
			'dev3mm--side-' . $c['offcanvas_side'],
			'dev3mm--align-' . $c['align'],
			'dev3mm--width-' . $c['panel_width'],
			'dev3mm--theme-' . sanitize_html_class( $c['theme'] ),
			'dev3mm--loc-' . sanitize_html_class( $location ),
		);
		if ( $c['sticky'] ) {
			$classes[] = 'dev3mm--sticky';
		}
		$data   = array(
			'trigger'      => $c['trigger'],
			'delay'        => $c['hover_delay'],
			'bp'           => $c['breakpoint'],
			'sticky'       => $c['sticky'],
			'stickyMobile' => $c['sticky_mobile'],
			'mobile'       => $c['mobile_mode'],
		);
		$label  = '' !== $c['aria_label'] ? $c['aria_label'] : $name;
		$toggle = '' !== $c['toggle_label'] ? $c['toggle_label'] : __( 'Menu', 'dev3-ai-mega-menu' );

		$html  = '<nav id="' . esc_attr( $this->uid ) . '" class="' . esc_attr( implode( ' ', $classes ) ) . '" aria-label="' . esc_attr( $label ) . '" data-dev3mm="' . esc_attr( (string) wp_json_encode( $data ) ) . '" style="' . esc_attr( Themes::css_vars( $tokens ) ) . '">';
		$html .= '<div class="dev3mm__bar"><button type="button" class="dev3mm__toggle" aria-expanded="false" aria-controls="' . esc_attr( $this->uid . '-drawer' ) . '"><span class="dev3mm__burger" aria-hidden="true"><span></span></span><span class="dev3mm__toggle-text">' . esc_html( $toggle ) . '</span></button></div>';
		$html .= '<div class="dev3mm__overlay" data-dev3mm-close></div>';
		$html .= '<div class="dev3mm__drawer" id="' . esc_attr( $this->uid . '-drawer' ) . '">';
		$html .= '<div class="dev3mm__drawer-head"><span class="dev3mm__drawer-title">' . esc_html( $label ) . '</span><button type="button" class="dev3mm__close" data-dev3mm-close aria-label="' . esc_attr__( 'Close menu', 'dev3-ai-mega-menu' ) . '">' . self::x_icon() . '</button></div>';
		$html .= '<ul class="dev3mm__menu">';
		foreach ( $this->children[0] as $item ) {
			$html .= $this->top_item( $item );
		}
		if ( $c['show_search'] ) {
			$html .= '<li class="dev3mm__item dev3mm__item--top dev3mm__item--search">' . Widgets::search_form( array( 'placeholder' => '' ), $this->uid . '-search' ) . '</li>';
		}
		$html .= '</ul></div></nav>';
		return $html;
	}

	/**
	 * A top-level item.
	 *
	 * @param \WP_Post $item Item.
	 * @return string
	 */
	private function top_item( $item ) {
		$id     = (int) $item->ID;
		$config = $this->cfg( $id );
		$kids   = $this->kids( $id );
		$grand  = false;
		foreach ( $kids as $kid ) {
			if ( $this->kids( (int) $kid->ID ) ) {
				$grand = true;
				break;
			}
		}
		$layout   = Item_Config::layout( $config, (bool) $kids, 0, $grand );
		$panel_id = $this->uid . '-p' . $id;
		$classes  = array_merge( array( 'dev3mm__item', 'dev3mm__item--top', 'dev3mm__item--' . $layout ), $this->state_classes( $item, $config ) );
		$width    = 'inherit' === $config['panel_width'] ? '' : ' dev3mm__item--width-' . $config['panel_width'];

		$html = '<li class="' . esc_attr( implode( ' ', $classes ) . $width ) . '">';
		if ( 'link' === $layout ) {
			return $html . '<div class="dev3mm__head">' . $this->link( $item, $config, 'dev3mm__link' ) . '</div></li>';
		}
		$html .= '<div class="dev3mm__head">' . $this->link_with_toggle( $item, $config, $panel_id, 'dev3mm__link' ) . '</div>';
		if ( 'flyout' === $layout ) {
			$html .= $this->dropdown( $kids, $panel_id, 1 );
		} elseif ( 'tabs' === $layout ) {
			$html .= '<div class="dev3mm__panel dev3mm__panel--mega dev3mm__panel--tabs" id="' . esc_attr( $panel_id ) . '"><div class="dev3mm__panel-inner">' . $this->tabs( $item, $kids, $config ) . '</div></div>';
		} else {
			$html .= '<div class="dev3mm__panel dev3mm__panel--mega" id="' . esc_attr( $panel_id ) . '"><div class="dev3mm__panel-inner">' . $this->mega( $item, $kids, $config ) . '</div></div>';
		}
		return $html . '</li>';
	}

	/**
	 * Children of an item.
	 *
	 * @param int $id Item id.
	 * @return \WP_Post[]
	 */
	private function kids( $id ) {
		return isset( $this->children[ $id ] ) ? $this->children[ $id ] : array();
	}

	/**
	 * State + visibility classes.
	 *
	 * @param \WP_Post $item   Item.
	 * @param array    $config Config.
	 * @return string[]
	 */
	private function state_classes( $item, array $config ) {
		$out     = array();
		$classes = (array) $item->classes;
		if ( array_intersect( array( 'current-menu-item', 'current_page_item' ), $classes ) ) {
			$out[] = 'is-current';
		}
		if ( array_intersect( array( 'current-menu-ancestor', 'current-menu-parent', 'current_page_parent', 'current_page_ancestor' ), $classes ) ) {
			$out[] = 'is-current-ancestor';
		}
		if ( $config['hide_on_mobile'] ) {
			$out[] = 'dev3mm--hide-mobile';
		}
		if ( $config['hide_on_desktop'] ) {
			$out[] = 'dev3mm--hide-desktop';
		}
		foreach ( $classes as $class ) {
			if ( '' !== $class && 0 !== strpos( (string) $class, 'menu-item' ) && 0 !== strpos( (string) $class, 'current' ) && 0 !== strpos( (string) $class, 'page_item' ) && 0 !== strpos( (string) $class, 'page-item' ) ) {
				$out[] = sanitize_html_class( (string) $class );
			}
		}
		return $out;
	}

	/**
	 * Whether an item is the current page.
	 *
	 * @param \WP_Post $item Item.
	 * @return bool
	 */
	private static function is_current( $item ) {
		return (bool) array_intersect( array( 'current-menu-item', 'current_page_item' ), (array) $item->classes );
	}

	/**
	 * Whether a link has no real destination.
	 *
	 * @param \WP_Post $item Item.
	 * @return bool
	 */
	private static function is_placeholder( $item ) {
		$url = trim( (string) $item->url );
		return '' === $url || '#' === $url;
	}

	/**
	 * Visual part of an item (icon/image, label, badge, description).
	 *
	 * @param \WP_Post $item       Item.
	 * @param array    $config     Config.
	 * @param bool     $with_image Show the item image (mega groups) instead of a thumbnail.
	 * @return string
	 */
	private function inner( $item, array $config, $with_image = false ) {
		$html = '';
		if ( $config['image_id'] && ! $with_image ) {
			$html .= wp_get_attachment_image(
				$config['image_id'],
				'thumbnail',
				false,
				array(
					'class'   => 'dev3mm__thumb',
					'alt'     => '',
					'loading' => 'lazy',
				)
			);
		} elseif ( $config['icon'] ) {
			$html .= Icons::svg( $config['icon'] );
		}
		$title = (string) apply_filters( 'nav_menu_item_title', $item->title, $item, (object) array(), 0 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
		$text  = '<span class="' . ( $config['hide_label'] ? 'dev3mm__sr' : 'dev3mm__label' ) . '">' . wp_kses( $title, self::inline_tags() ) . '</span>';
		if ( $config['show_description'] && '' !== trim( (string) $item->description ) ) {
			$text = '<span class="dev3mm__text">' . $text . '<span class="dev3mm__desc">' . esc_html( (string) $item->description ) . '</span></span>';
		}
		$html .= $text . self::badge( $config['badge'] );
		return $html;
	}

	/**
	 * Tags allowed in labels.
	 *
	 * @return array
	 */
	private static function inline_tags() {
		return array(
			'em'     => array(),
			'strong' => array(),
			'span'   => array( 'class' => true ),
			'br'     => array(),
		);
	}

	/**
	 * Badge markup.
	 *
	 * @param array $badge Badge config.
	 * @return string
	 */
	public static function badge( array $badge ) {
		if ( empty( $badge['text'] ) ) {
			return '';
		}
		$style = 'custom' === $badge['tone'] && ! empty( $badge['color'] ) ? ' style="' . esc_attr( '--dev3mm-badge-custom:' . $badge['color'] ) . '"' : '';
		return '<span class="dev3mm__badge dev3mm__badge--' . esc_attr( $badge['tone'] ) . '"' . $style . '>' . esc_html( $badge['text'] ) . '</span>';
	}

	/**
	 * A plain link.
	 *
	 * @param \WP_Post $item       Item.
	 * @param array    $config     Config.
	 * @param string   $css_class      Class.
	 * @param bool     $with_image Image mode.
	 * @return string
	 */
	private function link( $item, array $config, $css_class, $with_image = false ) {
		$attrs = ' class="' . esc_attr( $css_class ) . '"';
		if ( self::is_placeholder( $item ) ) {
			return '<span' . $attrs . '>' . $this->inner( $item, $config, $with_image ) . '</span>';
		}
		$attrs .= ' href="' . esc_url( (string) $item->url ) . '"';
		if ( '_blank' === $item->target ) {
			$attrs .= ' target="_blank" rel="noopener"';
		}
		if ( '' !== (string) $item->attr_title ) {
			$attrs .= ' title="' . esc_attr( (string) $item->attr_title ) . '"';
		}
		if ( '' !== (string) $item->xfn ) {
			$attrs .= ' rel="' . esc_attr( (string) $item->xfn ) . '"';
		}
		if ( self::is_current( $item ) ) {
			$attrs .= ' aria-current="page"';
		}
		return '<a' . $attrs . '>' . $this->inner( $item, $config, $with_image ) . '</a>';
	}

	/**
	 * A link plus its submenu toggle, or one button when the item has no destination.
	 *
	 * @param \WP_Post $item     Item.
	 * @param array    $config   Config.
	 * @param string   $panel_id Controlled panel id.
	 * @param string   $css_class    Link class.
	 * @return string
	 */
	private function link_with_toggle( $item, array $config, $panel_id, $css_class ) {
		if ( self::is_placeholder( $item ) ) {
			return '<button type="button" class="' . esc_attr( $css_class . ' dev3mm__link--button' ) . '" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '">' . $this->inner( $item, $config ) . self::chevron() . '</button>';
		}
		/* translators: %s: menu item label. */
		$sr = sprintf( __( '%s submenu', 'dev3-ai-mega-menu' ), wp_strip_all_tags( (string) $item->title ) );
		return $this->link( $item, $config, $css_class ) . '<button type="button" class="dev3mm__sub-toggle" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '">' . self::chevron() . '<span class="dev3mm__sr">' . esc_html( $sr ) . '</span></button>';
	}

	/**
	 * Flyout dropdown (any depth).
	 *
	 * @param \WP_Post[] $items Items.
	 * @param string     $id    Element id.
	 * @param int        $depth Depth.
	 * @return string
	 */
	private function dropdown( array $items, $id, $depth ) {
		$html = '<ul class="dev3mm__panel dev3mm__panel--flyout dev3mm__dropdown" id="' . esc_attr( $id ) . '">';
		foreach ( $items as $item ) {
			$config  = $this->cfg( (int) $item->ID );
			$kids    = $this->kids( (int) $item->ID );
			$classes = array_merge( array( 'dev3mm__item', 'dev3mm__item--sub' ), $kids ? array( 'dev3mm__item--flyout' ) : array(), $this->state_classes( $item, $config ) );
			$html   .= '<li class="' . esc_attr( implode( ' ', $classes ) ) . '"><div class="dev3mm__head">';
			if ( $kids && $depth < 6 ) {
				$sub   = $this->uid . '-p' . (int) $item->ID;
				$html .= $this->link_with_toggle( $item, $config, $sub, 'dev3mm__sublink' ) . '</div>' . $this->dropdown( $kids, $sub, $depth + 1 );
			} else {
				$html .= $this->link( $item, $config, 'dev3mm__sublink' ) . '</div>';
			}
			$html .= '</li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Mega panel: custom rows/columns/widgets, else an automatic column grid of the children.
	 *
	 * @param \WP_Post   $item   Top item.
	 * @param \WP_Post[] $kids   Children.
	 * @param array      $config Config.
	 * @return string
	 */
	private function mega( $item, array $kids, array $config ) {
		$by_id = array();
		foreach ( $kids as $kid ) {
			$by_id[ (int) $kid->ID ] = $kid;
		}
		$html   = '';
		$placed = array();
		if ( $config['rows'] ) {
			foreach ( $config['rows'] as $row ) {
				$html .= '<div class="dev3mm__row">';
				foreach ( $row['cols'] as $col ) {
					$html .= '<div class="dev3mm__col" style="--dev3mm-span:' . (int) $col['span'] . '">';
					foreach ( $col['widgets'] as $widget ) {
						if ( 'item' === $widget['type'] ) {
							if ( isset( $by_id[ $widget['item_id'] ] ) ) {
								$placed[ $widget['item_id'] ] = true;
								$html                        .= $this->group( $by_id[ $widget['item_id'] ] );
							}
							continue;
						}
						$html .= Widgets::render( $widget, $this->uid . '-w' . (int) $item->ID );
					}
					$html .= '</div>';
				}
				$html .= '</div>';
			}
		}
		$rest = array_filter(
			$kids,
			static function ( $kid ) use ( $placed ) {
				return empty( $placed[ (int) $kid->ID ] );
			}
		);
		if ( $rest ) {
			$html .= '<div class="dev3mm__grid" style="--dev3mm-cols:' . (int) $config['columns'] . '">';
			foreach ( $rest as $kid ) {
				$html .= $this->group( $kid );
			}
			$html .= '</div>';
		}
		if ( ! $config['aside'] ) {
			return $html;
		}
		$aside = '';
		foreach ( $config['aside'] as $widget ) {
			$aside .= Widgets::render( $widget, $this->uid . '-a' . (int) $item->ID );
		}
		return '<div class="dev3mm__split"><div class="dev3mm__main">' . $html . '</div>' . ( '' !== $aside ? '<aside class="dev3mm__aside">' . $aside . '</aside>' : '' ) . '</div>';
	}

	/**
	 * A column group: heading link (with optional image) and its children.
	 *
	 * @param \WP_Post $item Second-level item.
	 * @return string
	 */
	private function group( $item ) {
		$config  = $this->cfg( (int) $item->ID );
		$kids    = $this->kids( (int) $item->ID );
		$classes = array_merge( array( 'dev3mm__group' ), $kids ? array() : array( 'dev3mm__group--single' ), $this->state_classes( $item, $config ) );
		$html    = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		if ( $config['image_id'] ) {
			$html .= '<div class="dev3mm__media">' . wp_get_attachment_image(
				$config['image_id'],
				'medium',
				false,
				array(
					'class'   => 'dev3mm__media-img',
					'alt'     => '',
					'loading' => 'lazy',
				)
			) . '</div>';
		}
		$html .= $this->link( $item, $config, 'dev3mm__group-title', true );
		if ( $kids ) {
			$html .= '<ul class="dev3mm__sublist">';
			foreach ( $kids as $kid ) {
				$kid_config = $this->cfg( (int) $kid->ID );
				$html      .= '<li class="' . esc_attr( implode( ' ', array_merge( array( 'dev3mm__subitem' ), $this->state_classes( $kid, $kid_config ) ) ) ) . '">' . $this->link( $kid, $kid_config, 'dev3mm__sublink' ) . '</li>';
			}
			$html .= '</ul>';
		}
		return $html . '</div>';
	}

	/**
	 * Tabs panel: second-level items are tabs; their children fill the tab panel.
	 *
	 * @param \WP_Post   $item   Top item.
	 * @param \WP_Post[] $kids   Children.
	 * @param array      $config Config.
	 * @return string
	 */
	private function tabs( $item, array $kids, array $config ) {
		$base = $this->uid . '-t' . (int) $item->ID;
		$list = '<div class="dev3mm__tablist" role="tablist" aria-orientation="vertical" aria-label="' . esc_attr( wp_strip_all_tags( (string) $item->title ) ) . '">';
		$body = '';
		foreach ( array_values( $kids ) as $i => $kid ) {
			$kid_config = $this->cfg( (int) $kid->ID );
			$tab_id     = $base . '-' . $i;
			$active     = 0 === $i;
			$list      .= '<button type="button" role="tab" class="dev3mm__tab' . ( $active ? ' is-active' : '' ) . '" id="' . esc_attr( $tab_id ) . '" aria-selected="' . ( $active ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $tab_id . '-panel' ) . '" tabindex="' . ( $active ? '0' : '-1' ) . '">' . $this->inner( $kid, $kid_config ) . '</button>';
			$grand      = $this->kids( (int) $kid->ID );
			$body      .= '<div class="dev3mm__tabpanel' . ( $active ? ' is-active' : '' ) . '" role="tabpanel" id="' . esc_attr( $tab_id . '-panel' ) . '" aria-labelledby="' . esc_attr( $tab_id ) . '" tabindex="0">';
			$body      .= '<div class="dev3mm__tabpanel-head">' . $this->link( $kid, $kid_config, 'dev3mm__group-title', true ) . '</div>';
			if ( '' !== trim( (string) $kid->description ) ) {
				$body .= '<p class="dev3mm__tabpanel-desc">' . esc_html( (string) $kid->description ) . '</p>';
			}
			$body .= '<div class="dev3mm__grid" style="--dev3mm-cols:' . (int) max( 1, $config['columns'] - 1 ) . '">';
			foreach ( $grand as $g ) {
				$body .= $this->kids( (int) $g->ID ) ? $this->group( $g ) : '<div class="dev3mm__tablink-wrap">' . $this->link( $g, $this->cfg( (int) $g->ID ), 'dev3mm__tablink' ) . '</div>';
			}
			$body .= '</div>';
			if ( ! self::is_placeholder( $kid ) ) {
				/* translators: %s: tab label. */
				$body .= '<a class="dev3mm__more" href="' . esc_url( (string) $kid->url ) . '">' . esc_html( sprintf( __( 'View all %s', 'dev3-ai-mega-menu' ), wp_strip_all_tags( (string) $kid->title ) ) ) . ' <span aria-hidden="true">&rarr;</span></a>';
			}
			$body .= '</div>';
		}
		return '<div class="dev3mm__tabs">' . $list . '</div><div class="dev3mm__tabpanels">' . $body . '</div></div>';
	}

	/**
	 * Chevron icon.
	 *
	 * @return string
	 */
	private static function chevron() {
		return '<svg class="dev3mm__chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6"/></svg>';
	}

	/**
	 * Close icon.
	 *
	 * @return string
	 */
	private static function x_icon() {
		return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M18 6L6 18M6 6l12 12"/></svg>';
	}

	/**
	 * Resets the id counter (tests).
	 */
	public static function reset() {
		self::$instance = 0;
	}
}
