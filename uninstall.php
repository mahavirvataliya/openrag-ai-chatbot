<?php
/**
 * Uninstall handler.
 *
 * Drops all plugin tables and deletes all options only when the "Remove data
 * on uninstall" setting (Advanced) is enabled. Otherwise everything is left in
 * place so re-installation restores the prior state.
 *
 * @package OpenRag
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Load the option value directly (without booting the plugin).
$openrag_wipe = get_option( 'openrag_general', array() );
$openrag_wipe = is_array( $openrag_wipe ) ? ( ! empty( $openrag_wipe['wipe_on_uninstall'] ) ) : false;

if ( ! $openrag_wipe ) {
	return;
}

// Drop tables.
$openrag_tables = array( 'documents', 'chunks', 'chat_sessions', 'chats', 'mcp_servers' );
foreach ( $openrag_tables as $openrag_name ) {
	$openrag_table = $wpdb->prefix . 'openrag_' . $openrag_name;
	$wpdb->query( "DROP TABLE IF EXISTS `$openrag_table`" ); // phpcs:ignore WordPress.DB
}

// Delete options.
$openrag_option_groups = array( 'general', 'chat', 'providers', 'embeddings', 'vector_store', 'indexing', 'appearance', 'mcp' );
foreach ( $openrag_option_groups as $openrag_g ) {
	delete_option( 'openrag_' . $openrag_g );
}
delete_option( 'openrag_db_version' );

// Clear scheduled events.
wp_clear_scheduled_hook( 'openrag_health_check' );
