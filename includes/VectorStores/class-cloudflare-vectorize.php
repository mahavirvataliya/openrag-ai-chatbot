<?php
/**
 * Cloudflare Vectorize vector store.
 *
 * REST API v2:
 *   POST .../accounts/{account_id}/vectorize/v2/indexes/{index}/insert
 *   POST .../accounts/{account_id}/vectorize/v2/indexes/{index}/query
 *   POST .../accounts/{account_id}/vectorize/v2/indexes/{index}/delete-by-ids
 *   POST .../accounts/{account_id}/vectorize/v2/indexes  (create)
 *
 * Chunks table stores the text/metadata; Vectorize holds the vector + namespace.
 *
 * @package ItihRag\VectorStores
 */

namespace ItihRag\VectorStores;

use ItihRag\Database\Schema;
use ItihRag\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cloudflare_Vectorize implements Vector_Store {

	/**
	 * @var Schema
	 */
	private $schema;

	/**
	 * @var array
	 */
	private $settings;

	public function __construct() {
		$this->schema   = new Schema();
		$this->settings = Settings::group( 'vector_store' );
	}

	public function id() {
		return 'cloudflare';
	}

	public function label() {
		return __( 'Cloudflare Vectorize', 'itih-ai-chatbot' );
	}

	public function is_configured() {
		return ! empty( $this->settings['cloudflare_account'] )
			&& ! empty( $this->settings['cloudflare_token'] )
			&& ! empty( $this->settings['cloudflare_index'] );
	}

	protected function account() {
		return (string) ( $this->settings['cloudflare_account'] ?? '' );
	}

	protected function token() {
		return (string) ( $this->settings['cloudflare_token'] ?? '' );
	}

	protected function index() {
		return rawurlencode( (string) ( $this->settings['cloudflare_index'] ?? 'itih-ai-chatbot' ) );
	}

	protected function api_base() {
		return sprintf(
			'https://api.cloudflare.com/client/v4/accounts/%s/vectorize/v2/indexes',
			$this->account()
		);
	}

	protected function headers() {
		return array(
			'Authorization' => 'Bearer ' . $this->token(),
			'Content-Type'  => 'application/json',
		);
	}

	protected function chunks_table() {
		return $this->schema->table( 'chunks' );
	}

	/**
	 * Ensure the Vectorize index exists; create it with the given dimensions.
	 *
	 * @param int    $dimensions Embedding dimensions.
	 * @param string $metric     Metric ('cosine' default).
	 * @return array API response.
	 * @throws \RuntimeException On error.
	 */
	public function ensure_index( $dimensions, $metric = 'cosine' ) {
		// First check if it already exists.
		$check = wp_remote_get(
			$this->api_base() . '/' . $this->index(),
			array( 'timeout' => 30, 'headers' => $this->headers() )
		);
		$code  = (int) wp_remote_retrieve_response_code( $check );
		if ( $code >= 200 && $code < 300 ) {
			return array( 'exists' => true );
		}

		$response = wp_remote_post(
			$this->api_base(),
			array(
				'timeout' => 60,
				'headers' => $this->headers(),
				'body'    => wp_json_encode(
					array(
						'name'         => $this->settings['cloudflare_index'],
						'config'       => array(
							'dimensions'    => (int) $dimensions,
							'metric'        => $metric,
						),
					)
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $json['success'] ) ) {
			$errs = $json['errors'] ?? array();
			$msg  = is_array( $errs ) && ! empty( $errs[0]['message'] ) ? $errs[0]['message'] : wp_remote_retrieve_body( $response );
			throw new \RuntimeException( 'Create index failed: ' . $msg ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return $json;
	}

	public function upsert( $chunk_id, array $vector, array $metadata ) {
		global $wpdb;

		$vector_id = 'chunk_' . (int) $chunk_id;
		$namespace = isset( $metadata['document_id'] ) ? 'doc_' . (int) $metadata['document_id'] : 'default';

		$response = wp_remote_post(
			$this->api_base() . '/' . $this->index() . '/insert',
			array(
				'timeout' => 60,
				'headers' => $this->headers(),
				'body'    => wp_json_encode(
					array(
						'vectors' => array(
							array(
								'id'        => $vector_id,
								'values'    => array_map( 'floatval', $vector ),
								'namespace' => $namespace,
							),
						),
					)
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Vectorize insert failed: ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $json['success'] ) ) {
			$errs = $json['errors'] ?? array();
			$msg  = is_array( $errs ) && ! empty( $errs[0]['message'] ) ? $errs[0]['message'] : wp_remote_retrieve_body( $response );
			throw new \RuntimeException( 'Vectorize insert error: ' . $msg ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		// Persist the external id on the chunk row.
		$wpdb->update( // phpcs:ignore WordPress.DB
			$this->chunks_table(),
			array( 'vector_id' => $vector_id ),
			array( 'id' => $chunk_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $vector_id;
	}

	public function query( array $vector, $top_k = 5, $score = 0.0 ) {
		global $wpdb;

		$top_k = max( 1, (int) $top_k );

		$response = wp_remote_post(
			$this->api_base() . '/' . $this->index() . '/query',
			array(
				'timeout' => 30,
				'headers' => $this->headers(),
				'body'    => wp_json_encode(
					array(
						'vector'      => array_map( 'floatval', $vector ),
						'topK'        => $top_k,
						'returnMetadata' => 'none',
					)
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Vectorize query failed: ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $json['success'] ) ) {
			$errs = $json['errors'] ?? array();
			$msg  = is_array( $errs ) && ! empty( $errs[0]['message'] ) ? $errs[0]['message'] : wp_remote_retrieve_body( $response );
			throw new \RuntimeException( 'Vectorize query error: ' . $msg ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$matches = $json['result']['matches'] ?? ( $json['result'] ?? array() );
		if ( ! is_array( $matches ) ) {
			$matches = array();
		}

		$ids = array();
		$scores_by_id = array();
		foreach ( $matches as $match ) {
			$vid = $match['id'] ?? '';
			$cid = $this->chunk_id_from_vector_id( $vid );
			if ( $cid > 0 ) {
				$ids[ $cid ] = (float) ( $match['score'] ?? 0 );
				$scores_by_id[ $cid ] = (float) ( $match['score'] ?? 0 );
			}
		}

		if ( empty( $ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$table        = $this->chunks_table();
		// phpcs:ignore WordPress.DB, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders, PluginCheck.Security.DirectDB
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, content, source_url, source_title FROM `{$table}` WHERE id IN ($placeholders)", array_keys( $ids ) ) );

		$out = array();
		foreach ( $rows as $row ) {
			$sim = $scores_by_id[ $row->id ] ?? 0;
			if ( $sim < (float) $score ) {
				continue;
			}
			$out[] = array(
				'chunk_id'     => (int) $row->id,
				'content'      => (string) $row->content,
				'source_url'   => (string) $row->source_url,
				'source_title' => (string) $row->source_title,
				'score'        => $sim,
			);
		}
		return $out;
	}

	public function delete_document( $document_id ) {
		global $wpdb;

		// Collect vector ids for this document's chunks.
		$namespace = 'doc_' . (int) $document_id;
		$table     = $this->chunks_table();
		// phpcs:ignore WordPress.DB, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT vector_id FROM `{$table}` WHERE document_id = %d AND vector_id IS NOT NULL AND vector_id != ''", $document_id ) );
		$this->delete_ids( $ids );

		// Remove chunk rows.
		$wpdb->delete( $table, array( 'document_id' => $document_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
	}

	public function delete_chunk( $chunk_id ) {
		global $wpdb;

		$table = $this->chunks_table();
		$vid   = $wpdb->get_var( $wpdb->prepare( "SELECT vector_id FROM `{$table}` WHERE id = %d", $chunk_id ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB
		if ( $vid ) {
			$this->delete_ids( array( $vid ) );
		}
		$wpdb->delete( $table, array( 'id' => $chunk_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Delete a batch of vector ids from Vectorize.
	 *
	 * @param array<string> $ids Vector ids.
	 * @return void
	 */
	protected function delete_ids( array $ids ) {
		$ids = array_filter( array_map( 'strval', $ids ) );
		if ( empty( $ids ) ) {
			return;
		}
		wp_remote_post(
			$this->api_base() . '/' . $this->index() . '/delete-by-ids',
			array(
				'timeout' => 30,
				'headers' => $this->headers(),
				'body'    => wp_json_encode( array( 'ids' => $ids ) ),
			)
		);
	}

	/**
	 * Parse a chunk id out of a vector id (chunk_NN).
	 *
	 * @param string $vid Vector id.
	 * @return int
	 */
	protected function chunk_id_from_vector_id( $vid ) {
		if ( ! is_string( $vid ) || 0 !== strpos( $vid, 'chunk_' ) ) {
			return 0;
		}
		return (int) substr( $vid, 6 );
	}
}
