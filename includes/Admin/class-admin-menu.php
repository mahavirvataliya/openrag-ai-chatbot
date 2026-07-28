<?php
/**
 * Admin menu + page routing + asset enqueue.
 *
 * @package OpenRag\Admin
 */

namespace OpenRag\Admin;

use OpenRag\Plugin;
use OpenRag\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Menu {

	const CAPABILITY = 'manage_options';
	const SLUG       = 'openrag-ai-chatbot';

	/**
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * @var self|null
	 */
	private static $instance = null;

	public static function instance( Plugin $plugin ) {
		if ( null === self::$instance ) {
			self::$instance = new self( $plugin );
		}
		return self::$instance;
	}

	private function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		// Register admin-only REST routes.
		Admin_REST::instance( $plugin );
	}

	public function menu() {
		$icon = 'dashicons-format-chat';
		add_menu_page( __( 'OpenRag AI Chatbot', 'openrag-ai-chatbot' ), __( 'OpenRag', 'openrag-ai-chatbot' ), self::CAPABILITY, self::SLUG, array( $this, 'render_dashboard' ), $icon, 26 );

		add_submenu_page( self::SLUG, __( 'Dashboard', 'openrag-ai-chatbot' ), __( 'Dashboard', 'openrag-ai-chatbot' ), self::CAPABILITY, self::SLUG, array( $this, 'render_dashboard' ) );
		add_submenu_page( self::SLUG, __( 'Knowledge Base', 'openrag-ai-chatbot' ), __( 'Knowledge Base', 'openrag-ai-chatbot' ), self::CAPABILITY, self::SLUG . '-kb', array( $this, 'render_kb' ) );
		add_submenu_page( self::SLUG, __( 'Chats', 'openrag-ai-chatbot' ), __( 'Chats', 'openrag-ai-chatbot' ), self::CAPABILITY, self::SLUG . '-chats', array( $this, 'render_chats' ) );
		add_submenu_page( self::SLUG, __( 'Settings', 'openrag-ai-chatbot' ), __( 'Settings', 'openrag-ai-chatbot' ), self::CAPABILITY, self::SLUG . '-settings', array( $this, 'render_settings' ) );
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'openrag-admin',
			OPENRAG_URL . 'assets/css/admin.css',
			array(),
			OPENRAG_VERSION
		);
		wp_enqueue_script(
			'openrag-admin',
			OPENRAG_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-util' ),
			OPENRAG_VERSION,
			true
		);
		wp_enqueue_media();

		wp_localize_script(
			'openrag-admin',
			'OpenRagAdmin',
			array(
				'restUrl' => esc_url_raw( rest_url( OPENRAG_REST_NAMESPACE ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'saving'        => __( 'Saving…', 'openrag-ai-chatbot' ),
					'saved'         => __( 'Saved', 'openrag-ai-chatbot' ),
					'processing'    => __( 'Processing…', 'openrag-ai-chatbot' ),
					'delete'        => __( 'Delete this item?', 'openrag-ai-chatbot' ),
					'fetching'      => __( 'Fetching…', 'openrag-ai-chatbot' ),
					'discovering'   => __( 'Discovering tools…', 'openrag-ai-chatbot' ),
					'indexing'      => __( 'Queued for indexing', 'openrag-ai-chatbot' ),
				),
			)
		);
	}

	public function render_dashboard() {
		$view = new Dashboard_Page( $this->plugin );
		$view->render();
	}

	public function render_kb() {
		$view = new KB_Page( $this->plugin );
		$view->render();
	}

	public function render_chats() {
		$view = new Chats_Page();
		$view->render();
	}

	public function render_settings() {
		// Save handler (POST, nonce-protected).
		$page = new Settings_Page();
		$page->maybe_save();
		$page->render();
	}
}
