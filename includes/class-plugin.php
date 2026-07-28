<?php
/**
 * Main plugin class — singleton that wires everything together.
 *
 * @package OpenRag
 */

namespace OpenRag;

use OpenRag\Admin\Admin_Menu;
use OpenRag\Chat\Chat_Controller;
use OpenRag\Chat\Rate_Limiter;
use OpenRag\Embeddings\Embedding_Manager;
use OpenRag\LLM\LLM_Manager;
use OpenRag\VectorStores\Vector_Store_Manager;
use OpenRag\Ingestion\Ingestion_Pipeline;
use OpenRag\Queue\Background_Processor;
use OpenRag\MCP\MCP_Manager;
use OpenRag\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Resolved service singletons.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Get the singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register_hooks();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		// Load textdomain.
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'openrag-ai-chatbot', false, dirname( OPENRAG_BASENAME ) . '/languages' );
	}

	/**
	 * Register all hooks (admin + frontend + REST).
	 *
	 * @return void
	 */
	private function register_hooks() {
		// Ensure tables exist even if activation hook didn't run (multisite/composer installs).
		add_action( 'admin_init', array( $this, 'maybe_create_schema' ) );

		// REST endpoints.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Eagerly bootstrap the background queue so its do_action handlers exist
		// before any ingestion REST route calls do_action('openrag_schedule_document').
		add_action( 'init', array( $this, 'prime_queue' ), 5 );

		// Frontend widget + assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_footer', array( $this, 'maybe_render_widget' ) );
		add_shortcode( 'openrag_chat', array( $this, 'render_inline_shortcode' ) );

		// WP content auto-indexing.
		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 2 );
		add_action( 'wp_trash_post', array( $this, 'on_trash_post' ) );

		// Action Scheduler cron tick (in case AS not yet initialized).
		add_action( 'admin_init', array( $this, 'init_action_scheduler' ) );
		add_action( 'rest_api_init', array( $this, 'init_action_scheduler' ) );
		add_action( 'plugins_loaded', array( $this, 'init_action_scheduler' ), 5 );

		if ( is_admin() ) {
			Admin_Menu::instance( $this );
		}
	}

	/**
	 * Prime the background processor so its action hooks are registered early.
	 *
	 * @return void
	 */
	public function prime_queue() {
		$this->queue();
	}

	/* ----------------------------------------------------------------------
	 * Lazy service getters
	 * -------------------------------------------------------------------- */

	/**
	 * Schema handler.
	 *
	 * @return Schema
	 */
	public function schema() {
		return $this->service( Schema::class, fn() => new Schema() );
	}

	/**
	 * Embedding manager.
	 *
	 * @return Embedding_Manager
	 */
	public function embeddings() {
		return $this->service( Embedding_Manager::class, fn() => new Embedding_Manager() );
	}

	/**
	 * LLM manager.
	 *
	 * @return LLM_Manager
	 */
	public function llm() {
		return $this->service( LLM_Manager::class, fn() => new LLM_Manager() );
	}

	/**
	 * Vector store manager.
	 *
	 * @return Vector_Store_Manager
	 */
	public function vector_store() {
		return $this->service( Vector_Store_Manager::class, fn() => new Vector_Store_Manager() );
	}

	/**
	 * Ingestion pipeline.
	 *
	 * @return Ingestion_Pipeline
	 */
	public function ingestion() {
		return $this->service( Ingestion_Pipeline::class, fn() => new Ingestion_Pipeline( $this->embeddings(), $this->vector_store() ) );
	}

	/**
	 * Background processor.
	 *
	 * @return Background_Processor
	 */
	public function queue() {
		return $this->service( Background_Processor::class, function () {
			$processor = new Background_Processor( $this->ingestion() );
			$processor->bootstrap();
			return $processor;
		} );
	}

	/**
	 * MCP manager.
	 *
	 * @return MCP_Manager
	 */
	public function mcp() {
		return $this->service( MCP_Manager::class, fn() => new MCP_Manager() );
	}

	/**
	 * Chat controller (instantiated in register_rest_routes).
	 *
	 * @return Chat_Controller
	 */
	public function chat() {
		return $this->service( Chat_Controller::class, function () {
			return new Chat_Controller(
				$this->embeddings(),
				$this->vector_store(),
				$this->llm(),
				$this->mcp(),
				new Rate_Limiter()
			);
		} );
	}

	/**
	 * Resolve a service from cache or factory.
	 *
	 * @template T
	 * @param string   $key     Cache key (class name).
	 * @param callable $factory Factory producing the instance.
	 * @return mixed
	 */
	private function service( $key, $factory ) {
		if ( ! isset( $this->services[ $key ] ) ) {
			$this->services[ $key ] = $factory();
		}
		return $this->services[ $key ];
	}

	/* ----------------------------------------------------------------------
	 * Hook callbacks
	 * -------------------------------------------------------------------- */

	/**
	 * Create schema on admin init if DB version is stale.
	 *
	 * @return void
	 */
	public function maybe_create_schema() {
		$db_version = get_option( OPENRAG_OPTION_PREFIX . 'db_version' );
		if ( OPENRAG_DB_VERSION !== $db_version ) {
			$this->schema()->create();
			update_option( OPENRAG_OPTION_PREFIX . 'db_version', OPENRAG_DB_VERSION );
		}
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		$this->chat()->register_routes();
		$this->ingestion()->register_routes();
		$this->mcp()->register_routes();
	}

	/**
	 * Register scripts and styles (registered, enqueued conditionally).
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'openrag-chatbot',
			OPENRAG_URL . 'assets/css/chatbot.css',
			array(),
			OPENRAG_VERSION
		);
		wp_register_script(
			'openrag-chatbot',
			OPENRAG_URL . 'assets/js/chatbot.js',
			array(),
			OPENRAG_VERSION,
			true
		);
	}

	/**
	 * Render the floating widget on frontend (if enabled).
	 *
	 * @return void
	 */
	public function maybe_render_widget() {
		if ( ! $this->is_widget_enabled() || is_admin() ) {
			return;
		}
		$this->render_widget( false );
	}

	/**
	 * Inline shortcode render.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_inline_shortcode( $atts = array() ) {
		ob_start();
		$this->render_widget( true );
		return ob_get_clean();
	}

	/**
	 * Render widget markup and enqueue assets.
	 *
	 * @param bool $inline Whether this is an inline (shortcode) render.
	 * @return void
	 */
	private function render_widget( $inline ) {
		wp_enqueue_style( 'openrag-chatbot' );
		wp_enqueue_script( 'openrag-chatbot' );

		$settings = Settings::all();
		$cfg = array(
			'restUrl'         => esc_url_raw( rest_url( OPENRAG_REST_NAMESPACE ) ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'botName'         => $settings['chat']['bot_name'],
			'welcome'         => $settings['chat']['welcome_message'],
			'placeholder'     => __( 'Type your message…', 'openrag-ai-chatbot' ),
			'position'        => $settings['chat']['launcher_position'],
			'theme'           => $settings['appearance']['theme'],
			'colors'          => $settings['appearance']['colors'],
			'logo'            => $settings['appearance']['logo'],
			'avatar'          => $settings['appearance']['avatar'],
			'showReasoning'   => ! empty( $settings['chat']['reasoning'] ),
			'showCitations'   => ! empty( $settings['chat']['citations'] ),
			'inline'          => $inline,
			'i18n'            => array(
				'thinking'        => __( 'Thinking…', 'openrag-ai-chatbot' ),
				'sources'         => __( 'Sources', 'openrag-ai-chatbot' ),
				'reasoning'       => __( 'Reasoning', 'openrag-ai-chatbot' ),
				'askAgain'        => __( 'Ask again', 'openrag-ai-chatbot' ),
				'clearChat'       => __( 'Clear chat', 'openrag-ai-chatbot' ),
				'feedbackGood'    => __( 'Good response', 'openrag-ai-chatbot' ),
				'feedbackBad'     => __( 'Needs improvement', 'openrag-ai-chatbot' ),
				'feedbackComment' => __( 'Tell us more (optional)', 'openrag-ai-chatbot' ),
				'send'            => __( 'Send', 'openrag-ai-chatbot' ),
				'poweredBy'       => __( 'Powered by OpenRag AI Chatbot', 'openrag-ai-chatbot' ),
				'errorMessage'    => __( 'Something went wrong. Please try again.', 'openrag-ai-chatbot' ),
				'usingTool'       => __( 'Using tool', 'openrag-ai-chatbot' ),
			),
		);

		wp_localize_script( 'openrag-chatbot', 'OpenRagConfig', $cfg );

		// Inline appearance CSS variables (overrides theme preset).
		echo '<style id="openrag-theme-vars">' . Settings::render_css_vars( $settings['appearance'] ) . '</style>' . "\n";

		// Expose config to the template as a local variable.
		$GLOBALS['OpenRagConfig'] = $cfg;

		require OPENRAG_DIR . 'templates/chatbot-widget.php';

		unset( $GLOBALS['OpenRagConfig'] );
	}

	/**
	 * Determine if the widget should be shown.
	 *
	 * @return bool
	 */
	private function is_widget_enabled() {
		$settings = Settings::all();
		if ( empty( $settings['chat']['widget_enabled'] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Auto-index posts on save (if enabled and post type selected).
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function on_save_post( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || 'publish' !== $post->status ) {
			return;
		}
		$settings = Settings::all();
		if ( empty( $settings['indexing']['auto_index'] ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, $settings['indexing']['post_types'], true ) ) {
			return;
		}
		$this->queue()->schedule_post_index( $post_id );
	}

	/**
	 * Remove a post's chunks from the index when trashed.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function on_trash_post( $post_id ) {
		$this->ingestion()->remove_post( $post_id );
	}

	/**
	 * Initialize Action Scheduler (if bundled).
	 *
	 * @return void
	 */
	public function init_action_scheduler() {
		if ( class_exists( 'ActionScheduler' ) ) {
			return; // Already initialized.
		}
		$as_path = OPENRAG_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
		if ( file_exists( $as_path ) ) {
			require_once $as_path;
		}
	}
}
