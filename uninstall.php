<?php
/**
 * Uninstall: removes data only when "delete data on uninstall" is enabled.
 *
 * @package Dev3\AiMegaMenu
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$dev3mm_settings = get_option( 'dev3mm_settings', array() );
if ( empty( $dev3mm_settings['delete_on_uninstall'] ) ) {
	return;
}
global $wpdb;
foreach ( array( 'dev3mm_usage', 'dev3mm_core_cache', 'dev3mm_core_jobs', 'dev3mm_core_embeddings' ) as $dev3mm_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$dev3mm_table}" );
}
foreach ( array( 'dev3mm_settings', 'dev3mm_license', 'dev3mm_db_version', 'dev3mm_locations', 'dev3mm_themes' ) as $dev3mm_option ) {
	delete_option( $dev3mm_option );
}
delete_post_meta_by_key( '_dev3mm_item' );
delete_transient( 'dev3mm_models' );
delete_site_transient( 'dev3mm_update_info' );
wp_unschedule_hook( 'dev3mm_run_job' );
