<?php
/**
 * Admin-only REST routes: model list, connection test, vector index creation.
 *
 * @package WPOpenRag\Admin
 */

namespace WPOpenRag\Admin;

use WPOpenRag\Database\Schema;
use WPOpenRag\Embeddings\Embedding_Manager;
use WPOpenRag\LLM\LLM_Manager;
use WPOpenRag\Plugin;
use WPOpenRag\VectorStores\Vector_Store_Manager;
use WPOpenRag\VectorStores\Cloudflare_Vectorize;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_REST {

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
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	public function register() {
		register_rest_route(
			WP_OPENRAG_REST_NAMESPACE,
			'/admin/models',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_models' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);
		register_rest_route(
			WP_OPENRAG_REST_NAMESPACE,
			'/admin/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test_connection' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);
		register_rest_route(
			WP_OPENRAG_REST_NAMESPACE,
			'/vector-store/create-index',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_index' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);
	}

	public function check_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Insufficient permissions.', 'wp-openrag' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function list_models( \WP_REST_Request $request ) {
		$scope = sanitize_key( (string) $request->get_param( 'scope' ) );
		$scope = $scope ?: 'llm';

		try {
			if ( 'embedding' === $scope ) {
				$models = array();
			} else {
				$models = $this->plugin->llm()->list_models();
			}
		} catch ( \Throwable $e ) {
			return rest_ensure_response( array( 'models' => array(), 'error' => $e->getMessage() ) );
		}

		return rest_ensure_response( array( 'models' => array_values( $models ) ) );
	}

	public function test_connection( \WP_REST_Request $request ) {
		$scope = sanitize_key( (string) $request->get_param( 'scope' ) );
		$scope = $scope ?: 'llm';

		try {
			if ( 'embedding' === $scope ) {
				$vec = $this->plugin->embeddings()->embed_one( 'ping' );
				return rest_ensure_response(
					array(
						'ok'        => ! empty( $vec ),
						'dimensions' => count( $vec ),
					)
				);
			}
			$result = $this->plugin->llm()->chat(
				array(
					array( 'role' => 'system', 'content' => 'Reply with the single word: ok' ),
					array( 'role' => 'user', 'content' => 'ping' ),
				),
				array( 'max_tokens' => 5, 'temperature' => 0 )
			);
			return rest_ensure_response(
				array(
					'ok'    => ! empty( $result['content'] ),
					'model' => $result['model'] ?? '',
					'reply' => $result['content'] ?? '',
				)
			);
		} catch ( \Throwable $e ) {
			return rest_ensure_response( array( 'ok' => false, 'error' => $e->getMessage() ) );
		}
	}

	public function create_index( \WP_REST_Request $request ) {
		$dim = (int) ( $request->get_param( 'dimensions' ) ?: $this->plugin->embeddings()->dimensions() );
		if ( $dim <= 0 ) {
			return rest_ensure_response( array( 'ok' => false, 'error' => __( 'Could not determine embedding dimensions. Configure an embedding provider first.', 'wp-openrag' ) ) );
		}

		$cf = new Cloudflare_Vectorize();
		if ( ! $cf->is_configured() ) {
			return rest_ensure_response( array( 'ok' => false, 'error' => __( 'Cloudflare Vectorize is not configured.', 'wp-openrag' ) ) );
		}

		try {
			$cf->ensure_index( $dim );
			return rest_ensure_response( array( 'ok' => true, 'dimensions' => $dim ) );
		} catch ( \Throwable $e ) {
			return rest_ensure_response( array( 'ok' => false, 'error' => $e->getMessage() ) );
		}
	}
}
