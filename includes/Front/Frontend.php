<?php
/**
 * Front-end wiring: theme locations, shortcode, Gutenberg block, Navigation-block replacement, assets.
 *
 * @package Dev3\AiMegaMenu
 */

namespace Dev3\AiMegaMenu\Front;

use Dev3\AiMegaMenu\Core3\Core\Settings_Store;
use Dev3\AiMegaMenu\Menu\Locations;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks.
 */
class Frontend {

	const HANDLE = 'dev3mm-frontend';

	/**
	 * Navigation blocks replaced on this request.
	 *
	 * @var int
	 */
	private $replaced = 0;

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_assets' ), 5 );
		add_action( 'init', array( $this, 'register_block' ) );
		add_shortcode( 'dev3mm_menu', array( $this, 'shortcode' ) );
		add_filter( 'pre_wp_nav_menu', array( $this, 'pre_wp_nav_menu' ), 20, 2 );
		add_filter( 'render_block_core/navigation', array( $this, 'replace_navigation' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
	}

	/**
	 * Registers the front-end script and stylesheet (vanilla JS, no dependencies).
	 */
	public function register_assets() {
		$asset = DEV3MM_PATH . 'build/frontend.asset.php';
		$meta  = is_readable( $asset ) ? include $asset : array(
			'dependencies' => array(),
			'version'      => DEV3MM_VERSION,
		);
		wp_register_script( self::HANDLE, DEV3MM_URL . 'build/frontend.js', array(), $meta['version'], true );
		if ( function_exists( 'wp_script_add_data' ) ) {
			wp_script_add_data( self::HANDLE, 'strategy', 'defer' );
		}
		wp_register_style( self::HANDLE, DEV3MM_URL . 'build/frontend.css', array(), $meta['version'] );
		wp_style_add_data( self::HANDLE, 'rtl', 'replace' );
	}

	/**
	 * Enqueues assets in the head when a mega menu will render (avoids late styles and layout shift).
	 */
	public function maybe_enqueue() {
		if ( 'always' === Settings_Store::get( 'load_assets', 'auto' ) || self::any_location_active() || 'off' !== Settings_Store::get( 'block_nav_replace', 'off' ) ) {
			self::enqueue();
		}
	}

	/**
	 * Enqueues both assets.
	 */
	public static function enqueue() {
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Whether any enabled theme location has a menu assigned.
	 *
	 * @return bool
	 */
	public static function any_location_active() {
		$assigned = get_nav_menu_locations();
		foreach ( Locations::all() as $location => $config ) {
			if ( ! empty( $config['enabled'] ) && ! empty( $assigned[ $location ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Classic themes: takes over wp_nav_menu() for enabled locations.
	 *
	 * @param string|null $output Short-circuit output.
	 * @param \stdClass   $args   wp_nav_menu args.
	 * @return string|null
	 */
	public function pre_wp_nav_menu( $output, $args ) {
		if ( null !== $output || empty( $args->theme_location ) || ! Locations::enabled( $args->theme_location ) ) {
			return $output;
		}
		/**
		 * Lets a site skip the mega menu for a wp_nav_menu() call.
		 *
		 * @param bool      $use  Whether to render the mega menu.
		 * @param \stdClass $args wp_nav_menu args.
		 */
		if ( ! apply_filters( 'dev3mm_use_for_nav_menu', true, $args ) ) {
			return $output;
		}
		$locations = get_nav_menu_locations();
		$menu_id   = ! empty( $locations[ $args->theme_location ] ) ? (int) $locations[ $args->theme_location ] : 0;
		if ( ! $menu_id ) {
			return $output;
		}
		$html = Renderer::render( $menu_id, $args->theme_location );
		if ( '' === $html ) {
			return $output;
		}
		self::enqueue();
		return $html;
	}

	/**
	 * Block themes: replaces core/navigation output with the mega menu (setting "block_nav_replace").
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public function replace_navigation( $content, $block ) {
		unset( $block );
		$mode    = Settings_Store::get( 'block_nav_replace', 'off' );
		$menu_id = (int) Settings_Store::get( 'block_nav_menu', 0 );
		if ( 'off' === $mode || ! $menu_id || is_admin() || ( 'first' === $mode && $this->replaced > 0 ) ) {
			return $content;
		}
		$html = Renderer::render( $menu_id, Locations::EMBED );
		if ( '' === $html ) {
			return $content;
		}
		++$this->replaced;
		self::enqueue();
		return '<div class="dev3mm-block">' . $html . '</div>';
	}

	/**
	 * Resolves a menu by id, slug or name.
	 *
	 * @param mixed $menu Menu reference.
	 * @return int
	 */
	public static function resolve_menu( $menu ) {
		if ( is_numeric( $menu ) && (int) $menu > 0 ) {
			return (int) $menu;
		}
		$obj = '' !== (string) $menu ? wp_get_nav_menu_object( (string) $menu ) : false;
		return $obj ? (int) $obj->term_id : 0;
	}

	/**
	 * Overrides allowed from blocks/shortcodes/widgets.
	 *
	 * @param array $atts Raw attributes.
	 * @return array
	 */
	public static function overrides( array $atts ) {
		$out = array();
		foreach ( array( 'theme', 'trigger', 'mobile_mode', 'orientation', 'align', 'animation' ) as $key ) {
			if ( isset( $atts[ $key ] ) && '' !== (string) $atts[ $key ] ) {
				$out[ $key ] = sanitize_key( (string) $atts[ $key ] );
			}
		}
		if ( isset( $atts['breakpoint'] ) && '' !== (string) $atts['breakpoint'] ) {
			$out['breakpoint'] = (int) $atts['breakpoint'];
		}
		if ( isset( $atts['sticky'] ) && '' !== (string) $atts['sticky'] ) {
			$out['sticky'] = in_array( strtolower( (string) $atts['sticky'] ), array( '1', 'true', 'yes', 'on' ), true );
		}
		return $out;
	}

	/**
	 * [dev3mm_menu menu="main" location="primary" theme="dark" trigger="click" mobile_mode="offcanvas"].
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts     = shortcode_atts(
			array(
				'menu'        => '',
				'location'    => '',
				'theme'       => '',
				'trigger'     => '',
				'mobile_mode' => '',
				'orientation' => '',
				'align'       => '',
				'animation'   => '',
				'breakpoint'  => '',
				'sticky'      => '',
			),
			is_array( $atts ) ? $atts : array(),
			'dev3mm_menu'
		);
		$location = sanitize_key( (string) $atts['location'] );
		$menu_id  = self::resolve_menu( $atts['menu'] );
		if ( ! $menu_id && '' !== $location ) {
			$assigned = get_nav_menu_locations();
			$menu_id  = isset( $assigned[ $location ] ) ? (int) $assigned[ $location ] : 0;
		}
		if ( ! $menu_id ) {
			return '';
		}
		$html = Renderer::render( $menu_id, '' !== $location ? $location : Locations::EMBED, self::overrides( $atts ) );
		if ( '' !== $html ) {
			self::enqueue();
		}
		return $html;
	}

	/**
	 * Gutenberg block "DEV3 AI Mega Menu" (server-rendered).
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		$editor = DEV3MM_PATH . 'build/block.asset.php';
		if ( is_readable( $editor ) ) {
			$meta = include $editor;
			wp_register_script( 'dev3mm-block-editor', DEV3MM_URL . 'build/block.js', $meta['dependencies'], $meta['version'], true );
			wp_set_script_translations( 'dev3mm-block-editor', 'dev3-ai-mega-menu', DEV3MM_PATH . 'languages' );
			$themes = array();
			foreach ( \Dev3\AiMegaMenu\Menu\Themes::all() as $id => $theme ) {
				$themes[] = array(
					'id'   => $id,
					'name' => $theme['name'],
				);
			}
			wp_add_inline_script( 'dev3mm-block-editor', 'window.dev3mmBlock = ' . wp_json_encode( array( 'themes' => $themes ) ) . ';', 'before' );
		}
		register_block_type(
			'dev3mm/mega-menu',
			array(
				'api_version'     => 2,
				'title'           => __( 'DEV3 AI Mega Menu', 'dev3-ai-mega-menu' ),
				'category'        => 'theme',
				'editor_script'   => is_readable( $editor ) ? 'dev3mm-block-editor' : null,
				'style'           => self::HANDLE,
				'supports'        => array(
					'align' => array( 'wide', 'full' ),
					'html'  => false,
				),
				'attributes'      => array(
					'menuId'     => array(
						'type'    => 'number',
						'default' => 0,
					),
					'theme'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'trigger'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'mobileMode' => array(
						'type'    => 'string',
						'default' => '',
					),
					'align'      => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attrs Attributes.
	 * @return string
	 */
	public function render_block( $attrs ) {
		$menu_id = isset( $attrs['menuId'] ) ? (int) $attrs['menuId'] : 0;
		if ( ! $menu_id ) {
			return current_user_can( 'edit_theme_options' ) && defined( 'REST_REQUEST' ) && REST_REQUEST ? '<p class="dev3mm-placeholder">' . esc_html__( 'Choose a menu in the block settings.', 'dev3-ai-mega-menu' ) . '</p>' : '';
		}
		$html = Renderer::render(
			$menu_id,
			Locations::EMBED,
			self::overrides(
				array(
					'theme'       => isset( $attrs['theme'] ) ? $attrs['theme'] : '',
					'trigger'     => isset( $attrs['trigger'] ) ? $attrs['trigger'] : '',
					'mobile_mode' => isset( $attrs['mobileMode'] ) ? $attrs['mobileMode'] : '',
				)
			)
		);
		if ( '' === $html ) {
			return '';
		}
		self::enqueue();
		$align = ! empty( $attrs['align'] ) ? ' align' . sanitize_html_class( (string) $attrs['align'] ) : '';
		return '<div class="dev3mm-block wp-block-dev3mm-mega-menu' . esc_attr( $align ) . '">' . $html . '</div>';
	}
}
