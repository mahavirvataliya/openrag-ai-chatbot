<?php
/**
 * Uninstall handler.
 *
 * Drops all plugin tables and deletes all options only when the "Remove data
 * on uninstall" setting (Advanced) is enabled. Otherwise everything is left in
 * place so re-installation restores the prior state.
 *
 * @package WPOpenRag
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Load the option value directly (without booting the plugin).
$wipe = get_option( 'wporag_general', array() );
$wipe = is_array( $wipe ) ? ( ! empty( $wipe['wipe_on_uninstall'] ) ) : false;

if ( ! $wipe ) {
	return;
}

// Drop tables.
$tables = array( 'documents', 'chunks', 'chat_sessions', 'chats', 'mcp_servers' );
foreach ( $tables as $name ) {
	$table = $wpdb->prefix . 'wporag_' . $name;
	$wpdb->query( "DROP TABLE IF EXISTS `$table`" ); // phpcs:ignore WordPress.DB
}

// Delete options.
$option_groups = array( 'general', 'chat', 'providers', 'embeddings', 'vector_store', 'indexing', 'appearance', 'mcp' );
foreach ( $option_groups as $g ) {
	delete_option( 'wporag_' . $g );
}
delete_option( 'wporag_db_version' );

// Clear scheduled events.
	wp_clear_scheduled_hook( 'wporag_health_check' );
