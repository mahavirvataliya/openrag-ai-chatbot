<?php
/**
 * Activation / deactivation handler.
 *
 * @package ItihRag
 */

namespace ItihRag;

use ItihRag\Database\Schema;

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
			$opt = ITIH_OPTION_PREFIX . $key;
			if ( false === get_option( $opt ) ) {
				add_option( $opt, $value );
			}
		}

		update_option( ITIH_OPTION_PREFIX . 'db_version', ITIH_DB_VERSION );

		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation (does NOT delete data).
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
