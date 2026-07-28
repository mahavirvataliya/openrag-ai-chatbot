<?php
/**
 * MCP manager — manages configured MCP servers, exposes REST routes for admin,
 * and provides the chat-time helpers collect_tools() / call_tool().
 *
 * @package OpenRag\MCP
 */

namespace OpenRag\MCP;

use OpenRag\Database\Schema;
use OpenRag\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCP_Manager {

	/**
	 * @var Schema
	 */
	private $schema;

	public function __construct() {
		$this->schema = new Schema();
	}

	/**
	 * All enabled MCP servers.
	 *
	 * @return array<int,object>
	 */
	public function enabled_servers() {
		global $wpdb;
		return $wpdb->get_results( // phpcs:ignore WordPress.DB, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
			'SELECT * FROM `' . $this->schema->table( 'mcp_servers' ) . '` WHERE enabled = 1 ORDER BY id ASC' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Collect tools from all enabled servers, normalized to OpenAI tool schema.
	 *
	 * @return array<int,array{type:string, function:array{name, description, parameters}}>
	 */
	public function collect_tools() {
		$out      = array();
		$servers  = $this->enabled_servers();
		foreach ( $servers as $server ) {
			$tools = $this->server_tools( $server );
			foreach ( $tools as $t ) {
				$out[] = $this->normalize_tool( $server->id, $t );
			}
		}
		return $out;
	}

	/**
	 * Cached tool list for a single server (from mcp_servers.tools_cache).
	 *
	 * @param object $server Server row.
	 * @return array
	 */
	public function server_tools( $server ) {
		if ( ! empty( $server->tools_cache ) ) {
			$cached = json_decode( $server->tools_cache, true );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}
		// Try live discovery.
		$this->discover( (int) $server->id );
		$server = $this->get_server( (int) $server->id );
		return ! empty( $server->tools_cache ) ? ( json_decode( $server->tools_cache, true ) ?: array() ) : array();
	}

	/**
	 * Normalize an MCP tool into OpenAI function-calling schema.
	 *
	 * @param int   $server_id Server id (prefixed to tool name to avoid collisions).
	 * @param array $tool      Raw tool {name, description, inputSchema}.
	 * @return array
	 */
	protected function normalize_tool( $server_id, array $tool ) {
		$name = 'mcp_' . (int) $server_id . '_' . (string) ( $tool['name'] ?? 'tool' );
		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => $name,
				'description' => (string) ( $tool['description'] ?? '' ),
				'parameters'  => (array) ( $tool['inputSchema'] ?? ( $tool['input_schema'] ?? array( 'type' => 'object', 'properties' => new \stdClass() ) ) ),
			),
		);
	}

	/**
	 * Call a tool by its prefixed name (mcp_<server>_<original>).
	 *
	 * @param string $prefixed_name Tool name as exposed to the LLM.
	 * @param string $args_json     JSON-encoded arguments.
	 * @return array|string
	 */
	public function call_tool( $prefixed_name, $args_json = '{}' ) {
		if ( ! preg_match( '/^mcp_(\d+)_(.+)$/', $prefixed_name, $m ) ) {
			return array( 'error' => 'Unknown tool: ' . $prefixed_name );
		}
		$server_id = (int) $m[1];
		$tool_name = $m[2];

		$server = $this->get_server( $server_id );
		if ( ! $server ) {
			return array( 'error' => 'MCP server not found.' );
		}

		$args = json_decode( (string) $args_json, true );
		if ( ! is_array( $args ) ) {
			$args = array();
		}

		try {
			$client = new MCP_Client( $server->url, $server->transport, (string) ( $server->auth_header ?? '' ) );
			$client->initialize();
			$result = $client->call_tool( $tool_name, $args );

			if ( isset( $result['error'] ) ) {
				return $result;
			}
			// Normalize content blocks to a text string.
			$content = $result['content'] ?? array();
			$text    = '';
			if ( is_array( $content ) ) {
				foreach ( $content as $block ) {
					if ( 'text' === ( $block['type'] ?? '' ) ) {
						$text .= $block['text'] . "\n";
					} else {
						$text .= wp_json_encode( $block );
					}
				}
			}
			return trim( $text );
		} catch ( \Throwable $e ) {
			return '[MCP error] ' . $e->getMessage();
		}
	}

	/**
	 * Discover tools from a server and cache them.
	 *
	 * @param int $server_id Server id.
	 * @return array{tools:int, error?:string}
	 */
	public function discover( $server_id ) {
		global $wpdb;
		$server = $this->get_server( $server_id );
		if ( ! $server ) {
			return array( 'tools' => 0, 'error' => 'Server not found.' );
		}

		try {
			$client = new MCP_Client( $server->url, $server->transport, (string) ( $server->auth_header ?? '' ) );
			$init   = $client->initialize();
			if ( isset( $init['error'] ) ) {
				return array( 'tools' => 0, 'error' => $init['error'] );
			}
			$tools = $client->list_tools();
			// Strip input_schema/inputSchema to keep cache lean.
			$lean = array();
			foreach ( $tools as $t ) {
				$lean[] = array(
					'name'        => $t['name'] ?? '',
					'description' => $t['description'] ?? '',
					'inputSchema' => $t['inputSchema'] ?? ( $t['input_schema'] ?? array() ),
				);
			}

			$wpdb->update( // phpcs:ignore WordPress.DB
				$this->schema->table( 'mcp_servers' ),
				array(
					'tools_cache' => wp_json_encode( $lean ),
					'last_sync'   => current_time( 'mysql' ),
				),
				array( 'id' => $server_id )
			);
			return array( 'tools' => count( $lean ) );
		} catch ( \Throwable $e ) {
			return array( 'tools' => 0, 'error' => $e->getMessage() );
		}
	}

	/**
	 * Fetch a single server row.
	 *
	 * @param int $id Server id.
	 * @return object|null
	 */
	public function get_server( $id ) {
		global $wpdb;
		return $wpdb->get_row( // phpcs:ignore WordPress.DB, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
			$wpdb->prepare( 'SELECT * FROM `' . $this->schema->table( 'mcp_servers' ) . '` WHERE id = %d', (int) $id ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/* --------------------------------------------------------------------
	 * REST routes (admin)
	 * ------------------------------------------------------------------ */

	public function register_routes() {
		register_rest_route(
			OPENRAG_REST_NAMESPACE,
			'/mcp/servers',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'rest_list' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'rest_create' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
			)
		);

		register_rest_route(
			OPENRAG_REST_NAMESPACE,
			'/mcp/servers/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'rest_update' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'rest_delete' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
			)
		);

		register_rest_route(
			OPENRAG_REST_NAMESPACE,
			'/mcp/servers/(?P<id>\d+)/discover',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_discover' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);
	}

	public function check_admin( \WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Insufficient permissions.', 'openrag-ai-chatbot' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function rest_list( \WP_REST_Request $request ) {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM `' . $this->schema->table( 'mcp_servers' ) . '` ORDER BY id ASC' ); // phpcs:ignore WordPress.DB, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
		return rest_ensure_response( $rows );
	}

	public function rest_create( \WP_REST_Request $request ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB
			$this->schema->table( 'mcp_servers' ),
			array(
				'name'        => sanitize_text_field( (string) $request->get_param( 'name' ) ),
				'url'         => esc_url_raw( (string) $request->get_param( 'url' ) ),
				'transport'   => sanitize_key( (string) $request->get_param( 'transport' ) ?: 'http' ),
				'auth_header' => sanitize_text_field( (string) $request->get_param( 'auth_header' ) ),
				'enabled'     => (int) ( ! empty( $request->get_param( 'enabled' ) ) ),
				'created_at'  => current_time( 'mysql' ),
			)
		);
		return rest_ensure_response( array( 'id' => (int) $wpdb->insert_id ) );
	}

	public function rest_update( \WP_REST_Request $request ) {
		global $wpdb;
		$id = (int) $request['id'];
		$values = array(
			'name'        => sanitize_text_field( (string) $request->get_param( 'name' ) ),
			'url'         => esc_url_raw( (string) $request->get_param( 'url' ) ),
			'transport'   => sanitize_key( (string) $request->get_param( 'transport' ) ?: 'http' ),
			'auth_header' => sanitize_text_field( (string) $request->get_param( 'auth_header' ) ),
			'enabled'     => (int) ( ! empty( $request->get_param( 'enabled' ) ) ),
		);
		$wpdb->update( $this->schema->table( 'mcp_servers' ), $values, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		return rest_ensure_response( array( 'id' => $id ) );
	}

	public function rest_delete( \WP_REST_Request $request ) {
		global $wpdb;
		$id = (int) $request['id'];
		$wpdb->delete( $this->schema->table( 'mcp_servers' ), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		return rest_ensure_response( array( 'deleted' => $id ) );
	}

	public function rest_discover( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		return rest_ensure_response( $this->discover( $id ) );
	}
}
