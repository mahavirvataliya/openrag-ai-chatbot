<?php
/**
 * Plugin Name:       WP OpenRag
 * Plugin URI:        https://github.com/wp-openrag/wp-openrag
 * Description:       RAG-powered chatbot for WordPress — ingest PDFs, DOCX, URLs & your own posts/pages, embed them with OpenAI/Cloudflare/Ollama, retrieve via MySQL 9 native VECTOR or Cloudflare Vectorize, and answer questions with citations, reasoning, streaming & MCP tool integration.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            WP OpenRag
 * Author URI:        https://github.com/wp-openrag
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-openrag
 * Domain Path:       /languages
 * Network:           false
 *
 * @package WPOpenRag
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'WP_OPENRAG_VERSION', '1.0.0' );
define( 'WP_OPENRAG_FILE', __FILE__ );
define( 'WP_OPENRAG_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_OPENRAG_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_OPENRAG_BASENAME', plugin_basename( __FILE__ ) );
define( 'WP_OPENRAG_DB_VERSION', '1.0.0' );
define( 'WP_OPENRAG_REST_NAMESPACE', 'wporag/v1' );
define( 'WP_OPENRAG_OPTION_PREFIX', 'wporag_' );

// Load composer autoloader if present (PDF parser + Action Scheduler + PSR-4).
if ( file_exists( WP_OPENRAG_DIR . 'vendor/autoload.php' ) ) {
	require WP_OPENRAG_DIR . 'vendor/autoload.php';
}

// Always load our own PSR-4 fallback loader for when composer hasn't been dumped.
require_once WP_OPENRAG_DIR . 'includes/class-autoloader.php';
\WPOpenRag\Autoloader::register();

/**
 * Activation hook — create schema, store defaults, schedule cron.
 */
function wporag_activate() {
	require_once WP_OPENRAG_DIR . 'includes/class-activator.php';
	\WPOpenRag\Activator::activate();
}
register_activation_hook( __FILE__, 'wporag_activate' );

/**
 * Deactivation hook — clear scheduled events.
 */
function wporag_deactivate() {
	require_once WP_OPENRAG_DIR . 'includes/class-activator.php';
	\WPOpenRag\Activator::deactivate();
}
register_deactivation_hook( __FILE__, 'wporag_deactivate' );

/**
 * Boot the plugin once all plugins are loaded.
 */
function wporag_boot() {
	\WPOpenRag\Plugin::instance();
}
add_action( 'plugins_loaded', 'wporag_boot' );
