<?php
/**
 * Plugin Name:       ItihRag AI Chatbot
 * Plugin URI:        https://github.com/mahavirvataliya/itih-ai-chatbot
 * Description:       RAG-powered chatbot for WordPress — ingest PDFs, DOCX, URLs & your own posts/pages, embed them with OpenAI/Cloudflare/Ollama, retrieve via MySQL 9 native VECTOR or Cloudflare Vectorize, and answer questions with citations, reasoning, streaming & MCP tool integration.
 * Version:           1.1.1
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Mahavir Vataliya
 * Author URI:        https://github.com/mahavirvataliya
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       itih-ai-chatbot
 * Domain Path:       /languages
 *
 * @package ItihRag
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'ITIH_VERSION', '1.1.1' );
define( 'ITIH_FILE', __FILE__ );
define( 'ITIH_DIR', plugin_dir_path( __FILE__ ) );
define( 'ITIH_URL', plugin_dir_url( __FILE__ ) );
define( 'ITIH_BASENAME', plugin_basename( __FILE__ ) );
define( 'ITIH_DB_VERSION', '1.1.1' );
define( 'ITIH_REST_NAMESPACE', 'itih/v1' );
define( 'ITIH_OPTION_PREFIX', 'itih_' );

// Load composer autoloader if present (PDF parser + Action Scheduler + PSR-4).
if ( file_exists( ITIH_DIR . 'vendor/autoload.php' ) ) {
	require ITIH_DIR . 'vendor/autoload.php';
}

// Always load our own PSR-4 fallback loader for when composer hasn't been dumped.
require_once ITIH_DIR . 'includes/class-autoloader.php';
\ItihRag\Autoloader::register();

/**
 * Activation hook — create schema, store defaults, schedule cron.
 */
function itih_activate() {
	require_once ITIH_DIR . 'includes/class-activator.php';
	\ItihRag\Activator::activate();
}
register_activation_hook( __FILE__, 'itih_activate' );

/**
 * Deactivation hook — clear scheduled events.
 */
function itih_deactivate() {
	require_once ITIH_DIR . 'includes/class-activator.php';
	\ItihRag\Activator::deactivate();
}
register_deactivation_hook( __FILE__, 'itih_deactivate' );

/**
 * Boot the plugin once all plugins are loaded.
 */
function itih_boot() {
	\ItihRag\Plugin::instance();
}
add_action( 'plugins_loaded', 'itih_boot' );
