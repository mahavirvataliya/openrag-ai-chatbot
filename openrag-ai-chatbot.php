<?php
/**
 * Plugin Name:       OpenRag AI Chatbot
 * Plugin URI:        https://github.com/mahavirvataliya/openrag-ai-chatbot
 * Description:       RAG-powered chatbot for WordPress — ingest PDFs, DOCX, URLs & your own posts/pages, embed them with OpenAI/Cloudflare/Ollama, retrieve via MySQL 9 native VECTOR or Cloudflare Vectorize, and answer questions with citations, reasoning, streaming & MCP tool integration.
 * Version:           1.0.5
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Mahavir Vataliya
 * Author URI:        https://github.com/mahavirvataliya
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       openrag-ai-chatbot
 * Domain Path:       /languages
 *
 * @package OpenRag
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'OPENRAG_VERSION', '1.0.5' );
define( 'OPENRAG_FILE', __FILE__ );
define( 'OPENRAG_DIR', plugin_dir_path( __FILE__ ) );
define( 'OPENRAG_URL', plugin_dir_url( __FILE__ ) );
define( 'OPENRAG_BASENAME', plugin_basename( __FILE__ ) );
define( 'OPENRAG_DB_VERSION', '1.0.0' );
define( 'OPENRAG_REST_NAMESPACE', 'openrag/v1' );
define( 'OPENRAG_OPTION_PREFIX', 'openrag_' );

// Load composer autoloader if present (PDF parser + Action Scheduler + PSR-4).
if ( file_exists( OPENRAG_DIR . 'vendor/autoload.php' ) ) {
	require OPENRAG_DIR . 'vendor/autoload.php';
}

// Always load our own PSR-4 fallback loader for when composer hasn't been dumped.
require_once OPENRAG_DIR . 'includes/class-autoloader.php';
\OpenRag\Autoloader::register();

/**
 * Activation hook — create schema, store defaults, schedule cron.
 */
function openrag_activate() {
	require_once OPENRAG_DIR . 'includes/class-activator.php';
	\OpenRag\Activator::activate();
}
register_activation_hook( __FILE__, 'openrag_activate' );

/**
 * Deactivation hook — clear scheduled events.
 */
function openrag_deactivate() {
	require_once OPENRAG_DIR . 'includes/class-activator.php';
	\OpenRag\Activator::deactivate();
}
register_deactivation_hook( __FILE__, 'openrag_deactivate' );

/**
 * Boot the plugin once all plugins are loaded.
 */
function openrag_boot() {
	\OpenRag\Plugin::instance();
}
add_action( 'plugins_loaded', 'openrag_boot' );
