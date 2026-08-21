<?php
/**
 * Uninstall handler.
 *
 * Drops all plugin tables and deletes all options only when the "Remove data
 * on uninstall" setting (Advanced) is enabled. Otherwise everything is left in
 * place so re-installation restores the prior state.
 *
 * @package ItihRag
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Load the option value directly (without booting the plugin). Check the
// current itih_ prefix first, then the legacy openrag_ one.
$itih_wipe = get_option( 'itih_general', get_option( 'openrag_general', array() ) );
$itih_wipe = is_array( $itih_wipe ) ? ( ! empty( $itih_wipe['wipe_on_uninstall'] ) ) : false;

if ( ! $itih_wipe ) {
	return;
}

// Drop tables.
$itih_tables = array( 'documents', 'chunks', 'chat_sessions', 'chats', 'mcp_servers' );
foreach ( $itih_tables as $itih_name ) {
	$itih_table = $wpdb->prefix . 'openrag_' . $itih_name;
	$wpdb->query( "DROP TABLE IF EXISTS `$itih_table`" ); // phpcs:ignore WordPress.DB
}

// Delete options (current itih_* prefix plus legacy openrag_* from pre-1.1.1 installs).
$itih_option_groups = array( 'general', 'chat', 'providers', 'embeddings', 'vector_store', 'indexing', 'appearance', 'mcp' );
foreach ( $itih_option_groups as $itih_g ) {
	delete_option( 'itih_' . $itih_g );
	delete_option( 'openrag_' . $itih_g );
}
delete_option( 'itih_db_version' );
delete_option( 'openrag_db_version' );

// Clear legacy scheduled events (pre-1.1.1 installs).
wp_clear_scheduled_hook( 'openrag_health_check' );
