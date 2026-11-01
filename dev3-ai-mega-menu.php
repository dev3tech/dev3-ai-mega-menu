<?php
/**
 * Plugin Name:       DEV3 AI Mega Menu for WordPress
 * Plugin URI:        https://dev3.tech/item/dev3-ai-mega-menu
 * Description:       AI-assisted mega menus: panels, columns, tabs, mobile accordion and off-canvas menus, built and audited with DEV3 AI.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            DEV3 Technologies
 * Author URI:        https://dev3.tech
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dev3-ai-mega-menu
 * Domain Path:       /languages
 *
 * @package Dev3\AiMegaMenu
 */

defined( 'ABSPATH' ) || exit;

define( 'DEV3MM_VERSION', '1.0.0' );
define( 'DEV3MM_FILE', __FILE__ );
define( 'DEV3MM_PATH', plugin_dir_path( __FILE__ ) );
define( 'DEV3MM_URL', plugin_dir_url( __FILE__ ) );
define( 'DEV3MM_SLUG', 'dev3-ai-mega-menu' );
define( 'DEV3MM_NAME', 'DEV3 AI Mega Menu for WordPress' );
define( 'DEV3MM_MIN_PHP', '7.4' );
define( 'DEV3MM_MIN_WP', '6.0' );
define( 'DEV3MM_DB_VERSION', '1.0.0' );

if ( version_compare( PHP_VERSION, DEV3MM_MIN_PHP, '<' ) ) {
	return;
}

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'Dev3\AiMegaMenu\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$file = DEV3MM_PATH . 'includes/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( '\Dev3\AiMegaMenu\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\Dev3\AiMegaMenu\Plugin', 'deactivate' ) );
add_action( 'plugins_loaded', array( '\Dev3\AiMegaMenu\Plugin', 'instance' ) );
