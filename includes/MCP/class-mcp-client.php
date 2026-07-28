<?php
/**
 * MCP (Model Context Protocol) client — JSON-RPC over streamable HTTP.
 *
 * Implements the minimal subset:
 *   - initialize / initialized handshake
 *   - tools/list
 *   - tools/call
 *
 * Per the spec, the server returns either a single JSON response or an
 * SSE stream of JSON-RPC messages. We follow a simple, robust approach:
 * POST the request, then if Content-Type is text/event-stream we parse the
 * SSE event matching our request id; otherwise we read the JSON body.
 *
 * @package OpenRag\MCP
 */

namespace OpenRag\MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCP_Client {

	/**
	 * @var string Server base URL.
	 */
	private $url;

	/**
	 * @var string Transport: http or sse.
	 */
	private $transport;

	/**
	 * @var string Optional Authorization header value.
	 */
	private $auth;

	/**
	 * Cached session id returned by the server.
	 *
	 * @var string|null
	 */
	private $session_id = null;

	/**
	 * Monotonic request id counter.
	 *
	 * @var int
	 */
	private $req_id = 0;

	public function __construct( $url, $transport = 'http', $auth = '' ) {
		$this->url       = rtrim( (string) $url, '/' );
		$this->transport = $transport;
		$this->auth      = (string) $auth;
	}

	/**
	 * Initialize the connection (handshake).
	 *
	 * @return array{protocolVersion:string, serverInfo:array}|array{error:string}
	 */
	public function initialize() {
		$result = $this->request(
			'initialize',
			array(
				'protocolVersion' => '2024-11-05',
				'capabilities'    => (object) array(),
				'clientInfo'      => array(
					'name'    => 'openrag-ai-chatbot',
					'version' => OPENRAG_VERSION,
				),
			)
		);
		if ( is_array( $result ) && isset( $result['error'] ) ) {
			return $result;
		}

		// Send initialized notification (no response expected).
		$this->notify( 'notifications/initialized', (object) array() );

		return is_array( $result ) ? $result : array( 'error' => 'Malformed initialize response.' );
	}

	/**
	 * List available tools from the server.
	 *
	 * @return array<int,array{name:string, description:string, inputSchema:array}>
	 */
	public function list_tools() {
		$result = $this->request( 'tools/list', (object) array() );
		if ( ! is_array( $result ) || isset( $result['error'] ) ) {
			return array();
		}
		return is_array( $result['tools'] ?? null ) ? $result['tools'] : array();
	}

	/**
	 * Call a tool by name.
	 *
	 * @param string $name Tool name.
	 * @param array  $args Arguments object.
	 * @return array{content:array, isError?:bool}|array{error:string}
	 */
	public function call_tool( $name, array $args = array() ) {
		return $this->request( 'tools/call', array( 'name' => $name, 'arguments' => (object) $args ) );
	}

	/* --------------------------------------------------------------------
	 * Wire layer
	 * ------------------------------------------------------------------ */

	/**
	 * Send a JSON-RPC request and return its result field.
	 *
	 * @param string       $method Method.
	 * @param array|object $params Params.
	 * @return array|object|string|null
	 */
	protected function request( $method, $params ) {
		$this->req_id++;
		$id    = $this->req_id;
		$body  = array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'method'  => $method,
			'params'  => empty( $params ) ? (object) array() : $params,
		);

		$response = $this->http( $body );
		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response->get_error_message() );
		}

		$payload = $this->extract_response( $response, $id );
		if ( isset( $payload['error'] ) ) {
			return $payload;
		}
		return $payload['result'] ?? null;
	}

	/**
	 * Send a JSON-RPC notification (no id, no result).
	 *
	 * @param string       $method Method.
	 * @param array|object $params Params.
	 * @return void
	 */
	protected function notify( $method, $params ) {
		$body = array(
			'jsonrpc' => '2.0',
			'method'  => $method,
			'params'  => empty( $params ) ? (object) array() : $params,
		);
		$this->http( $body );
	}

	/**
	 * Perform the HTTP POST, capturing session id if returned.
	 *
	 * @param array $body JSON-RPC payload.
	 * @return array|\WP_Error {headers, body, code}
	 */
	protected function http( array $body ) {
		$headers = array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json, text/event-stream',
		);
		if ( '' !== $this->auth ) {
			$headers['Authorization'] = $this->auth;
		}
		if ( null !== $this->session_id ) {
			$headers['Mcp-Session-Id'] = $this->session_id;
		}

		$response = wp_remote_post(
			$this->url,
			array(
				'timeout' => 60,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Capture session id from headers.
		$sid = wp_remote_retrieve_header( $response, 'mcp_session_id' );
		if ( '' !== $sid ) {
			$this->session_id = $sid;
		}

		return array(
			'headers' => $response['headers'] ?? array(),
			'body'    => wp_remote_retrieve_body( $response ),
			'code'    => (int) wp_remote_retrieve_response_code( $response ),
		);
	}

	/**
	 * Pull the JSON-RPC result for our request id from either a plain JSON
	 * response or an SSE stream of responses.
	 *
	 * @param array $response HTTP response.
	 * @param int   $id       Expected request id.
	 * @return array
	 */
	protected function extract_response( array $response, $id ) {
		$body = (string) ( $response['body'] ?? '' );

		// Try plain JSON first.
		$json = json_decode( $body, true );
		if ( is_array( $json ) ) {
			// Single response.
			if ( isset( $json['id'] ) && (int) $json['id'] === $id ) {
				return $json;
			}
			// Batch response.
			foreach ( $json as $msg ) {
				if ( isset( $msg['id'] ) && (int) $msg['id'] === $id ) {
					return $msg;
				}
			}
		}

		// SSE response: parse lines for data: blocks matching our id.
		$lines = preg_split( '/\r\n|\n|\r/', $body );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( 0 !== strpos( $line, 'data:' ) ) {
				continue;
			}
			$data = trim( substr( $line, 5 ) );
			$msg  = json_decode( $data, true );
			if ( is_array( $msg ) && isset( $msg['id'] ) && (int) $msg['id'] === $id ) {
				return $msg;
			}
		}

		return array( 'error' => array( 'message' => 'No matching response from MCP server.' ) );
	}
}
