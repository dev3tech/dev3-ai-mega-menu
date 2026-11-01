<?php
/**
 * Bootstrap and wiring.
 *
 * @package Dev3\AiMegaMenu
 */

namespace Dev3\AiMegaMenu;

use Dev3\AiMegaMenu\Core3\Core\Assets;
use Dev3\AiMegaMenu\Core3\Core\Privacy;
use Dev3\AiMegaMenu\Core3\Core\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton entry point.
 */
final class Plugin {

	const CRON_HOOK = 'dev3mm_daily';

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Boots once.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		self::register_schema();
		Schema::install( DEV3MM_DB_VERSION );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( 'dev3mm_license_check' ); // Licence check from builds before licensing was removed.
	}

	/**
	 * Plugin tables / settings defaults are registered here so activation and runtime agree.
	 */
	public static function register_schema() {
		add_filter( 'dev3mm_settings_defaults', array( Settings::class, 'defaults' ) );
		add_filter( 'dev3mm_settings_sanitize', array( Settings::class, 'sanitize' ), 10, 3 );
	}

	/**
	 * Hooks.
	 */
	private function boot() {
		load_plugin_textdomain( 'dev3-ai-mega-menu', false, dirname( plugin_basename( DEV3MM_FILE ) ) . '/languages' );
		self::register_schema();
		add_action( 'admin_init', static fn() => Schema::maybe_install( DEV3MM_DB_VERSION ) );
		add_action( self::CRON_HOOK, array( Schema::class, 'prune_usage' ) );
		add_action( 'rest_api_init', array( $this, 'rest' ) );
		Privacy::init();
		add_action( 'init', array( Ai\Prompts_Library::class, 'register' ), 1 );
		add_action( 'init', array( $this, 'register_admin_assets' ) );
		( new Front\Frontend() )->register();
		( new Integrations\Builders() )->register();
		if ( is_admin() ) {
			( new Admin\Admin_Page() )->register();
		}
	}

	/**
	 * Admin bundle + boot data (front-end asset URLs for the live preview iframe, environment flags).
	 */
	public function register_admin_assets() {
		if ( ! is_admin() ) {
			return;
		}
		$version = is_readable( DEV3MM_PATH . 'build/frontend.asset.php' ) ? ( include DEV3MM_PATH . 'build/frontend.asset.php' )['version'] : DEV3MM_VERSION;
		Assets::register(
			array( 'admin' ),
			array(
				'frontendCss' => DEV3MM_URL . 'build/frontend.css?ver=' . $version,
				'frontendJs'  => DEV3MM_URL . 'build/frontend.js?ver=' . $version,
				'homeUrl'     => home_url( '/' ),
				'siteName'    => html_entity_decode( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ), ENT_QUOTES, 'UTF-8' ),
				'woo'         => class_exists( 'WooCommerce' ),
				'blockTheme'  => function_exists( 'wp_is_block_theme' ) && wp_is_block_theme(),
				'navMenusUrl' => admin_url( 'nav-menus.php' ),
				'isRtl'       => is_rtl(),
			)
		);
	}

	/**
	 * REST routes.
	 */
	public function rest() {
		( new Core3\Rest\Core_Controller() )->register_routes();
		( new Rest\Rest_Controller() )->register_routes();
	}
}
