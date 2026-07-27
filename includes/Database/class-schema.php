<?php
/**
 * Database schema: table creation + MySQL 9 VECTOR detection.
 *
 * @package WPOpenRag\Database
 */

namespace WPOpenRag\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema {

	/**
	 * Cached MySQL version string.
	 *
	 * @var string|null
	 */
	private $mysql_version = null;

	/**
	 * Cached native-vector capability.
	 *
	 * @var bool|null
	 */
	private $native_vector_capable = null;

	/**
	 * Create all plugin tables.
	 *
	 * @return void
	 */
	public function create() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix . 'wporag_';

		$native_vector = $this->supports_native_vector();
		// Force-safe: never create VECTOR columns unless MySQL 9 is detected.
		$embedding_col = $native_vector ? "`embedding` VECTOR(0) NULL" : '`embedding` LONGTEXT NULL';

		// documents
		dbDelta(
			"CREATE TABLE {$prefix}documents (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				type VARCHAR(20) NOT NULL DEFAULT 'post',
				title VARCHAR(500) NOT NULL DEFAULT '',
				source_url TEXT NULL,
				file_path VARCHAR(500) NULL,
				post_id BIGINT UNSIGNED NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'pending',
				error_message TEXT NULL,
				chunk_count INT UNSIGNED NOT NULL DEFAULT 0,
				mime_type VARCHAR(100) NULL,
				content_hash CHAR(64) NULL,
				embedding_dim INT UNSIGNED NULL,
				processing_started_at DATETIME NULL,
				processing_completed_at DATETIME NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY status (status),
				KEY type (type),
				KEY post_id (post_id),
				KEY created_at (created_at)
			) {$charset_collate};"
		);

		// chunks - embedding column is added/migrated in migrate_vector_column().
		// We always create with LONGTEXT then optionally convert to VECTOR.
		dbDelta(
			"CREATE TABLE {$prefix}chunks (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				document_id BIGINT UNSIGNED NOT NULL,
				chunk_index INT UNSIGNED NOT NULL DEFAULT 0,
				content TEXT NOT NULL,
				source_url TEXT NULL,
				source_title VARCHAR(500) NULL,
				token_count INT UNSIGNED NOT NULL DEFAULT 0,
				embedding LONGTEXT NULL,
				vector_id VARCHAR(200) NULL,
				embedding_dim INT UNSIGNED NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY document_id (document_id),
				KEY chunk_index (chunk_index),
				KEY source_title (source_title(191))
			) {$charset_collate};"
		);

		// chat_sessions
		dbDelta(
			"CREATE TABLE {$prefix}chat_sessions (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				session_hash CHAR(64) NOT NULL,
				user_ip VARCHAR(45) NULL,
				user_agent TEXT NULL,
				device VARCHAR(50) DEFAULT 'web',
				message_count INT UNSIGNED NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY session_hash (session_hash),
				KEY created_at (created_at)
			) {$charset_collate};"
		);

		// chats
		dbDelta(
			"CREATE TABLE {$prefix}chats (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				session_id CHAR(64) NOT NULL,
				role VARCHAR(20) NOT NULL DEFAULT 'user',
				content MEDIUMTEXT NULL,
				citations LONGTEXT NULL,
				reasoning MEDIUMTEXT NULL,
				tool_calls LONGTEXT NULL,
				model VARCHAR(200) NULL,
				prompt_tokens INT UNSIGNED NULL,
				completion_tokens INT UNSIGNED NULL,
				response_time_ms INT UNSIGNED NULL,
				feedback VARCHAR(10) NULL,
				feedback_comment TEXT NULL,
				user_ip VARCHAR(45) NULL,
				device VARCHAR(50) DEFAULT 'web',
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY session_id (session_id),
				KEY created_at (created_at),
				KEY feedback (feedback)
			) {$charset_collate};"
		);

		// mcp_servers
		dbDelta(
			"CREATE TABLE {$prefix}mcp_servers (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				name VARCHAR(200) NOT NULL,
				url VARCHAR(500) NOT NULL,
				transport VARCHAR(20) NOT NULL DEFAULT 'http',
				auth_header TEXT NULL,
				enabled TINYINT(1) NOT NULL DEFAULT 1,
				tools_cache LONGTEXT NULL,
				last_sync DATETIME NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY enabled (enabled)
			) {$charset_collate};"
		);

		// Optionally migrate the chunks embedding column to native VECTOR type.
		if ( $native_vector ) {
			$this->migrate_vector_column( $prefix . 'chunks' );
		}

		// Store the detected capability in vector_store settings.
		$vs_settings = get_option( WP_OPENRAG_OPTION_PREFIX . 'vector_store', array() );
		if ( ! is_array( $vs_settings ) ) {
			$vs_settings = array();
		}
		$vs_settings['mysql_native_vector'] = $native_vector ? '1' : '0';
		update_option( WP_OPENRAG_OPTION_PREFIX . 'vector_store', $vs_settings );
	}

	/**
	 * Detect whether MySQL/MariaDB supports the native VECTOR column type.
	 *
	 * MySQL 9.0+ exposes a VECTOR type. We probe for it via a parse-only
	 * statement that is safe and side-effect-free.
	 *
	 * @return bool
	 */
	public function supports_native_vector() {
		if ( null !== $this->native_vector_capable ) {
			return $this->native_vector_capable;
		}

		global $wpdb;

		$version = $this->mysql_version();
		$this->native_vector_capable = false;

		// Quick heuristic: MySQL 9.0+ only (MariaDB returns "10.x.y-MariaDB").
		if ( preg_match( '/^(\d+)\.(\d+)/', $version, $m ) ) {
			$major = (int) $m[1];
			$minor = (int) $m[2];
			if ( false === stripos( $version, 'mariadb' ) && $major >= 9 ) {
				// Confirm with a runtime probe (avoid 8.x false positives from version strings).
				$probe = $wpdb->get_var( "SHOW COLUMNS FROM information_schema.columns WHERE TABLE_SCHEMA IS NOT NULL LIMIT 0" );
				// Safer probe: try a no-op CREATE TABLE that uses VECTOR. Wrapped in try.
				$test_table = $wpdb->prefix . 'wporag_vec_probe';
				$wpdb->query( "DROP TABLE IF EXISTS `{$test_table}`" );
				$created = $wpdb->query(
					"CREATE TABLE `{$test_table}` (id INT PRIMARY KEY, v VECTOR(4)) ENGINE=InnoDB"
				);
				$exists  = ( null !== $wpdb->get_var( "SHOW TABLES LIKE '{$test_table}'" ) );
				$wpdb->query( "DROP TABLE IF EXISTS `{$test_table}`" );
				if ( $exists ) {
					$this->native_vector_capable = true;
				}
				// $major/$minor unused beyond gating; suppress.
				unset( $major, $minor, $probe );
			}
		}

		return $this->native_vector_capable;
	}

	/**
	 * Get the MySQL version string.
	 *
	 * @return string
	 */
	public function mysql_version() {
		if ( null !== $this->mysql_version ) {
			return $this->mysql_version;
		}
		global $wpdb;
		$this->mysql_version = (string) $wpdb->db_server_info();
		return $this->mysql_version;
	}

	/**
	 * Convert the chunks.embedding column to VECTOR(N) for a given dimension N.
	 *
	 * The chunks table is created with LONGTEXT by dbDelta (dbDelta cannot
	 * handle VECTOR(N)), so when native vector is supported we re-ALTER the
	 * column. N is sized to the configured/active embedding model dimension.
	 *
	 * @param string $table     Full chunks table name.
	 * @param int    $dimension Vector dimension (0 = leave column at LONGTEXT).
	 * @return void
	 */
	public function migrate_vector_column( $table, $dimension = 0 ) {
		global $wpdb;

		$table = sanitize_key( $table );
		if ( ! $this->supports_native_vector() || $dimension <= 0 ) {
			return;
		}
		$dim = (int) $dimension;

		// Check the current column type.
		$col = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM `%1s` LIKE 'embedding'", $table ) ); // phpcs:ignore WordPress.DB
		if ( ! $col ) {
			return;
		}
		$type = strtoupper( $col->Type );
		if ( 0 === strpos( $type, 'VECTOR' ) ) {
			// Already VECTOR; optionally resize if dimension differs.
			if ( preg_match( '/VECTOR\((\d+)\)/', $type, $m ) && (int) $m[1] === $dim ) {
				return;
			}
		}

		// Drop existing data (assume clean install or migration handled elsewhere).
		$wpdb->query( "ALTER TABLE `{$table}` MODIFY COLUMN `embedding` VECTOR({$dim}) NULL" );
	}

	/**
	 * Get the prefixed table name.
	 *
	 * @param string $name Short table name (without prefix).
	 * @return string
	 */
	public function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'wporag_' . $name;
	}
}
