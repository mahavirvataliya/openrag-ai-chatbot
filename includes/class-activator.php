<?php
/**
 * Activation / deactivation handler.
 *
 * @package WPOpenRag
 */

namespace WPOpenRag;

use WPOpenRag\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		$schema = new Schema();
		$schema->create();

		// Store default options only if not present (idempotent activation).
		foreach ( Settings::defaults() as $key => $value ) {
			$opt = WP_OPENRAG_OPTION_PREFIX . $key;
			if ( false === get_option( $opt ) ) {
				add_option( $opt, $value );
			}
		}

		update_option( WP_OPENRAG_OPTION_PREFIX . 'db_version', WP_OPENRAG_DB_VERSION );

		// Schedule a periodic re-check cron for stale schema (every 12h).
		if ( ! wp_next_scheduled( 'wporag_health_check' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', 'wporag_health_check' );
		}

		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation (does NOT delete data).
	 *
	 * @return void
	 */
	public static function deactivate() {
		$ts = wp_next_scheduled( 'wporag_health_check' );
		if ( $ts ) {
			wp_unschedule_event( $ts, 'wporag_health_check' );
		}
		flush_rewrite_rules();
	}
}
