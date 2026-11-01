<?php
/**
 * Admin menu + React app mount.
 *
 * @package Dev3\AiMegaMenu
 */

namespace Dev3\AiMegaMenu\Admin;

use Dev3\AiMegaMenu\Core3\Core\Assets;
use Dev3\AiMegaMenu\Core3\Core\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Top-level menu page.
 */
class Admin_Page {

	/**
	 * Hook suffix.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DEV3MM_FILE ), array( $this, 'links' ) );
	}

	/**
	 * Menu.
	 */
	public function menu() {
		$this->hook = (string) add_menu_page( DEV3MM_NAME, __( 'DEV3 AI Mega Menu', 'dev3-ai-mega-menu' ), Capabilities::manage(), DEV3MM_SLUG, array( $this, 'render' ), 'dashicons-menu-alt3', 61 );
	}

	/**
	 * Mount point.
	 */
	public function render() {
		// The h1 names the page for screen readers; wp-header-end keeps admin notices above the app.
		echo '<div class="wrap dev3mm-wrap"><h1 class="screen-reader-text">' . esc_html( DEV3MM_NAME ) . '</h1><hr class="wp-header-end"><div id="dev3mm-admin-root" class="dev3mm-root dev3mm-root--app"></div></div>';
	}

	/**
	 * Assets on our page only.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function enqueue( $hook ) {
		if ( $hook === $this->hook ) {
			wp_enqueue_media();
			Assets::enqueue( 'admin' );
			// The live preview uses the real front-end stylesheet and script.
			wp_enqueue_style( \Dev3\AiMegaMenu\Front\Frontend::HANDLE );
		}
	}

	/**
	 * Settings link.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . DEV3MM_SLUG ) ) . '">' . esc_html__( 'Settings', 'dev3-ai-mega-menu' ) . '</a>' );
		return $links;
	}
}
