<?php
/**
 * Admin menu + page routing + asset enqueue.
 *
 * @package ItihRag\Admin
 */

namespace ItihRag\Admin;

use ItihRag\Plugin;
use ItihRag\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Menu {

	const CAPABILITY = 'manage_options';
	const SLUG       = 'itih-ai-chatbot';

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
		add_menu_page( __( 'ItihRag AI Chatbot', 'itih-ai-chatbot' ), __( 'ItihRag', 'itih-ai-chatbot' ), self::CAPABILITY, self::SLUG, array( $this, 'render_dashboard' ), $icon, 26 );

		add_submenu_page( self::SLUG, __( 'Dashboard', 'itih-ai-chatbot' ), __( 'Dashboard', 'itih-ai-chatbot' ), self::CAPABILITY, self::SLUG, array( $this, 'render_dashboard' ) );
		add_submenu_page( self::SLUG, __( 'Knowledge Base', 'itih-ai-chatbot' ), __( 'Knowledge Base', 'itih-ai-chatbot' ), self::CAPABILITY, self::SLUG . '-kb', array( $this, 'render_kb' ) );
		add_submenu_page( self::SLUG, __( 'Chats', 'itih-ai-chatbot' ), __( 'Chats', 'itih-ai-chatbot' ), self::CAPABILITY, self::SLUG . '-chats', array( $this, 'render_chats' ) );
		add_submenu_page( self::SLUG, __( 'Settings', 'itih-ai-chatbot' ), __( 'Settings', 'itih-ai-chatbot' ), self::CAPABILITY, self::SLUG . '-settings', array( $this, 'render_settings' ) );
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'itih-admin',
			ITIH_URL . 'assets/css/admin.css',
			array(),
			ITIH_VERSION
		);
		wp_enqueue_script(
			'itih-admin',
			ITIH_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-util' ),
			ITIH_VERSION,
			true
		);
		wp_enqueue_media();

		wp_localize_script(
			'itih-admin',
			'ItihRagAdmin',
			array(
				'restUrl'      => esc_url_raw( rest_url( ITIH_REST_NAMESPACE ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'themePresets' => Settings::theme_presets(),
				'i18n'         => array(
					'saving'      => __( 'Saving…', 'itih-ai-chatbot' ),
					'saved'       => __( 'Saved', 'itih-ai-chatbot' ),
					'processing'  => __( 'Processing…', 'itih-ai-chatbot' ),
					'delete'      => __( 'Delete this item?', 'itih-ai-chatbot' ),
					'fetching'    => __( 'Fetching…', 'itih-ai-chatbot' ),
					'discovering' => __( 'Discovering tools…', 'itih-ai-chatbot' ),
					'indexing'    => __( 'Queued for indexing', 'itih-ai-chatbot' ),
					/* translators: %d: number of posts queued. */
					'queuedPosts' => __( 'Queued %d posts.', 'itih-ai-chatbot' ),
					'reasoning'   => __( 'Reasoning', 'itih-ai-chatbot' ),
					'sources'     => __( 'Sources:', 'itih-ai-chatbot' ),
					'noData'      => __( 'No data.', 'itih-ai-chatbot' ),
					/* translators: %d: token count. */
					'tokens'      => __( '%d tokens', 'itih-ai-chatbot' ),
					/* translators: %d: response time in milliseconds. */
					'ms'          => __( '%d ms', 'itih-ai-chatbot' ),
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
