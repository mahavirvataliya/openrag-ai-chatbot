<?php
/**
 * Admin menu + page routing + asset enqueue.
 *
 * @package WPOpenRag\Admin
 */

namespace WPOpenRag\Admin;

use WPOpenRag\Plugin;
use WPOpenRag\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Menu {

	const CAPABILITY = 'manage_options';
	const SLUG       = 'wp-openrag';

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
		add_menu_page( __( 'WP OpenRag', 'wp-openrag' ), __( 'OpenRag', 'wp-openrag' ), self::CAPABILITY, self::SLUG, array( $this, 'render_dashboard' ), $icon, 26 );

		add_submenu_page( self::SLUG, __( 'Dashboard', 'wp-openrag' ), __( 'Dashboard', 'wp-openrag' ), self::CAPABILITY, self::SLUG, array( $this, 'render_dashboard' ) );
		add_submenu_page( self::SLUG, __( 'Knowledge Base', 'wp-openrag' ), __( 'Knowledge Base', 'wp-openrag' ), self::CAPABILITY, self::SLUG . '-kb', array( $this, 'render_kb' ) );
		add_submenu_page( self::SLUG, __( 'Chats', 'wp-openrag' ), __( 'Chats', 'wp-openrag' ), self::CAPABILITY, self::SLUG . '-chats', array( $this, 'render_chats' ) );
		add_submenu_page( self::SLUG, __( 'Settings', 'wp-openrag' ), __( 'Settings', 'wp-openrag' ), self::CAPABILITY, self::SLUG . '-settings', array( $this, 'render_settings' ) );
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'wporag-admin',
			WP_OPENRAG_URL . 'assets/css/admin.css',
			array(),
			WP_OPENRAG_VERSION
		);
		wp_enqueue_script(
			'wporag-admin',
			WP_OPENRAG_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-util' ),
			WP_OPENRAG_VERSION,
			true
		);
		wp_enqueue_media();

		wp_localize_script(
			'wporag-admin',
			'WPOpenRagAdmin',
			array(
				'restUrl' => esc_url_raw( rest_url( WP_OPENRAG_REST_NAMESPACE ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'saving'        => __( 'Saving…', 'wp-openrag' ),
					'saved'         => __( 'Saved', 'wp-openrag' ),
					'processing'    => __( 'Processing…', 'wp-openrag' ),
					'delete'        => __( 'Delete this item?', 'wp-openrag' ),
					'fetching'      => __( 'Fetching…', 'wp-openrag' ),
					'discovering'   => __( 'Discovering tools…', 'wp-openrag' ),
					'indexing'      => __( 'Queued for indexing', 'wp-openrag' ),
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
