<?php
/**
 * Ingestion pipeline — orchestrates load → chunk → embed → store.
 *
 * Also exposes REST routes for the knowledge-base admin UI.
 *
 * @package ItihRag\Ingestion
 */

namespace ItihRag\Ingestion;

use ItihRag\Database\Schema;
use ItihRag\Embeddings\Embedding_Manager;
use ItihRag\Settings;
use ItihRag\VectorStores\Vector_Store_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ingestion_Pipeline {

	/**
	 * @var Embedding_Manager
	 */
	private $embeddings;

	/**
	 * @var Vector_Store_Manager
	 */
	private $vectors;

	/**
	 * @var Schema
	 */
	private $schema;

	/**
	 * @var Chunker
	 */
	private $chunker;

	/**
	 * @var Document_Loader
	 */
	private $loader;

	public function __construct( Embedding_Manager $embeddings, Vector_Store_Manager $vectors ) {
		$this->embeddings = $embeddings;
		$this->vectors    = $vectors;
		$this->schema     = new Schema();
		$this->chunker    = new Chunker();
		$this->loader     = new Document_Loader();
	}

	/* ----------------------------------------------------------------------
	 * Document lifecycle
	 * -------------------------------------------------------------------- */

	/**
	 * Register a new document (no processing yet).
	 *
	 * @param array $args {type, title, source_url, file_path, post_id, mime_type}.
	 * @return int Document id.
	 */
	public function create_document( array $args ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'type'        => 'url',
				'title'       => '',
				'source_url'  => '',
				'file_path'   => '',
				'post_id'     => null,
				'mime_type'   => '',
				'status'      => 'pending',
				'content_hash'=> '',
			)
		);

		$wpdb->insert( // phpcs:ignore WordPress.DB
			$this->schema->table( 'documents' ),
			array(
				'type'         => $args['type'],
				'title'        => $args['title'],
				'source_url'   => $args['source_url'],
				'file_path'    => $args['file_path'],
				'post_id'      => $args['post_id'],
				'mime_type'    => $args['mime_type'],
				'status'       => $args['status'],
				'content_hash' => $args['content_hash'],
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a document's status.
	 *
	 * @param int    $document_id Document id.
	 * @param string $status      New status.
	 * @param array  $extra       Extra columns.
	 * @return void
	 */
	public function set_status( $document_id, $status, array $extra = array() ) {
		global $wpdb;
		$values = array_merge( array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ), $extra );
		$wpdb->update( // phpcs:ignore WordPress.DB
			$this->schema->table( 'documents' ),
			$values,
			array( 'id' => $document_id )
		);
	}

	/**
	 * Process a document end-to-end: load → chunk → embed → store.
	 *
	 * Designed to run within a single Action Scheduler job or an admin AJAX request.
	 * Embedding requests are batched (~100 texts per call) to avoid timeouts.
	 *
	 * @param int $document_id Document id.
	 * @return array{ok:bool, chunks?:int, error?:string}
	 */
	public function process_document( $document_id ) {
		global $wpdb;

		$doc = $wpdb->get_row( // phpcs:ignore WordPress.DB, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
			$wpdb->prepare(
				'SELECT * FROM `' . $this->schema->table( 'documents' ) . '` WHERE id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$document_id
			),
			ARRAY_A
		);

		if ( ! $doc ) {
			return array( 'ok' => false, 'error' => 'Document not found.' );
		}

		$this->set_status( $document_id, 'processing', array( 'processing_started_at' => current_time( 'mysql' ) ) );

		try {
			$source = $doc['file_path'] ?: $doc['source_url'];
			if ( 'post' === $doc['type'] && ! empty( $doc['post_id'] ) ) {
				$loaded = $this->load_post( (int) $doc['post_id'] );
			} else {
				$loaded = $this->loader->load( $source, $doc['mime_type'] ?? '' );
			}

			if ( ! empty( $loaded['error'] ) ) {
				throw new \RuntimeException( $loaded['error'] );
			}

			$text  = (string) ( $loaded['text'] ?? '' );
			$title = (string) ( $loaded['title'] ?? '' );
			if ( '' === $text ) {
				throw new \RuntimeException( 'No text content extracted.' );
			}

			// Update title if we discovered one.
			if ( '' === $doc['title'] && '' !== $title ) {
				$this->set_status( $document_id, 'processing', array( 'title' => $title ) );
			}

			// Remove any previous chunks (re-index case).
			$this->vectors->store()->delete_document( $document_id );

			$chunks = $this->chunker->split( $text );
			if ( empty( $chunks ) ) {
				throw new \RuntimeException( 'Chunking produced no content.' );
			}

			$source_url = $doc['source_url'];
			$created    = 0;

			// Ensure embedding dimension migration if MySQL native vector and known dim.
			$this->maybe_migrate_vector_dimension();

			// Batch-embed: one HTTP round trip per ~100 texts instead of one per
			// chunk (a 100-chunk PDF was 100 sequential API calls).
			$texts     = wp_list_pluck( $chunks, 'text' );
			$vectors   = array();
			$batch_sz  = 100;
			for ( $i = 0, $total = count( $texts ); $i < $total; $i += $batch_sz ) {
				$batch = array_slice( $texts, $i, $batch_sz );
				try {
					$batch_vectors = $this->embeddings->embed( $batch );
				} catch ( \Throwable $e ) {
					// Provider rejected the batch — fall back to per-text so one
					// bad chunk doesn't fail the whole document.
					$batch_vectors = array();
					foreach ( $batch as $t ) {
						try {
							$batch_vectors[] = $this->embeddings->embed_one( $t );
						} catch ( \Throwable $e2 ) {
							$batch_vectors[] = array();
						}
					}
				}
				foreach ( $batch_vectors as $j => $v ) {
					$vectors[ $i + $j ] = is_array( $v ) ? $v : array();
				}
			}

			foreach ( $chunks as $idx => $chunk ) {
				$vector = isset( $vectors[ $idx ] ) ? $vectors[ $idx ] : array();
				if ( empty( $vector ) ) {
					continue;
				}

				$wpdb->insert( // phpcs:ignore WordPress.DB
					$this->schema->table( 'chunks' ),
					array(
						'document_id'   => $document_id,
						'chunk_index'   => $chunk['index'],
						'content'       => $chunk['text'],
						'source_url'    => $source_url,
						'source_title'  => $title ?: $doc['title'],
						'token_count'   => $chunk['tokens'],
						'embedding_dim' => count( $vector ),
						'created_at'    => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s' )
				);
				$chunk_id = (int) $wpdb->insert_id;

				$this->vectors->store()->upsert(
					$chunk_id,
					$vector,
					array(
						'document_id'  => $document_id,
						'source_url'   => $source_url,
						'source_title' => $title ?: $doc['title'],
					)
				);
				$created++;
			}

			$this->set_status(
				$document_id,
				'completed',
				array(
					'chunk_count'             => $created,
					'processing_completed_at' => current_time( 'mysql' ),
				)
			);

			return array( 'ok' => true, 'chunks' => $created );
		} catch ( \Throwable $e ) {
			$this->set_status(
				$document_id,
				'failed',
				array(
					'error_message'           => $e->getMessage(),
					'processing_completed_at' => current_time( 'mysql' ),
				)
			);
			return array( 'ok' => false, 'error' => $e->getMessage() );
		}
	}

	/**
	 * Load a WordPress post's content as plain text for embedding.
	 *
	 * @param int $post_id Post id.
	 * @return array{text:string, title:string}
	 */
	public function load_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'text' => '', 'title' => '' );
		}
		$title = get_the_title( $post );
		$content = $post->post_content;

		// Apply shortcodes / blocks to render real content.
		$content = apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.

		// Strip HTML tags to plain text but keep paragraph breaks.
		$content = wp_strip_all_tags( $content );
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$url = get_permalink( $post );

		return array(
			'text'  => trim( $title . "\n\n" . $content ),
			'title' => $title,
		);
	}

	/**
	 * Remove all chunks for a document (and its vectors).
	 *
	 * @param int $document_id Document id.
	 * @return void
	 */
	public function remove_document( $document_id ) {
		$this->vectors->store()->delete_document( $document_id );
		global $wpdb;
		$wpdb->delete( $this->schema->table( 'documents' ), array( 'id' => $document_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Remove a post's document + chunks.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public function remove_post( $post_id ) {
		global $wpdb;
		$doc = $wpdb->get_row( // phpcs:ignore WordPress.DB, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
			$wpdb->prepare(
				'SELECT id FROM `' . $this->schema->table( 'documents' ) . '` WHERE post_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$post_id
			)
		);
		if ( $doc ) {
			$this->remove_document( (int) $doc->id );
		}
	}

	/**
	 * Ensure the MySQL chunks.embedding column matches the active embedding dim.
	 *
	 * @return void
	 */
	protected function maybe_migrate_vector_dimension() {
		$store = $this->vectors->store();
		if ( ! ( $store instanceof \ItihRag\VectorStores\MySQL_Store ) || ! $store->is_native() ) {
			return;
		}
		$dim = $this->embeddings->dimensions();
		if ( $dim > 0 ) {
			$this->schema->migrate_vector_column( $this->schema->table( 'chunks' ), $dim );
		}
	}

	/* ----------------------------------------------------------------------
	 * REST routes (admin only)
	 * -------------------------------------------------------------------- */

	public function register_routes() {
		register_rest_route(
			ITIH_REST_NAMESPACE,
			'/documents',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'rest_list_documents' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'rest_create_document' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
			)
		);

		register_rest_route(
			ITIH_REST_NAMESPACE,
			'/documents/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'rest_get_document' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'rest_delete_document' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
			)
		);

		register_rest_route(
			ITIH_REST_NAMESPACE,
			'/documents/(?P<id>\d+)/process',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_process_document' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		register_rest_route(
			ITIH_REST_NAMESPACE,
			'/posts/index',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_index_posts' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);
	}

	public function check_admin( \WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Insufficient permissions.', 'itih-ai-chatbot' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function rest_list_documents( \WP_REST_Request $request ) {
		global $wpdb;
		$type   = sanitize_key( (string) $request->get_param( 'type' ) );
		$where  = '';
		$params = array();
		if ( '' !== $type ) {
			$where  = 'WHERE type = %s';
			$params[] = $type;
		}
		$sql = 'SELECT * FROM `' . $this->schema->table( 'documents' ) . "` $where ORDER BY created_at DESC LIMIT 1000";
		// phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
		return rest_ensure_response( $rows );
	}

	public function rest_create_document( \WP_REST_Request $request ) {
		$type   = sanitize_key( (string) $request->get_param( 'type' ) );
		$title  = sanitize_text_field( (string) $request->get_param( 'title' ) );
		$url    = esc_url_raw( (string) $request->get_param( 'source_url' ) );
		$path   = sanitize_text_field( (string) $request->get_param( 'file_path' ) );
		$mime   = sanitize_text_field( (string) $request->get_param( 'mime_type' ) );
		$queue  = (bool) $request->get_param( 'queue' );

		if ( ! in_array( $type, array( 'url', 'pdf', 'docx', 'txt', 'post' ), true ) ) {
			$type = 'url';
		}

		$doc_id = $this->create_document(
			array(
				'type'       => $type,
				'title'      => $title,
				'source_url' => $url,
				'file_path'  => $path,
				'mime_type'  => $mime,
				'status'     => 'pending',
			)
		);

		if ( $queue && has_action( 'itih_process_document' ) ) {
			do_action( 'itih_schedule_document', $doc_id );
		}

		return rest_ensure_response( array( 'id' => $doc_id, 'queued' => $queue ) );
	}

	public function rest_get_document( \WP_REST_Request $request ) {
		global $wpdb;
		$id = (int) $request['id'];
		$doc = $wpdb->get_row( // phpcs:ignore WordPress.DB, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
			$wpdb->prepare( 'SELECT * FROM `' . $this->schema->table( 'documents' ) . '` WHERE id = %d', $id ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		if ( ! $doc ) {
			return new \WP_Error( 'not_found', __( 'Document not found.', 'itih-ai-chatbot' ), array( 'status' => 404 ) );
		}
		$chunks = $wpdb->get_results( // phpcs:ignore WordPress.DB, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
			$wpdb->prepare(
				'SELECT id, chunk_index, content, source_url, source_title, token_count FROM `' . $this->schema->table( 'chunks' ) . '` WHERE document_id = %d ORDER BY chunk_index', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$id
			)
		);
		return rest_ensure_response( array( 'document' => $doc, 'chunks' => $chunks ) );
	}

	public function rest_delete_document( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		$this->remove_document( $id );
		return rest_ensure_response( array( 'deleted' => $id ) );
	}

	public function rest_process_document( \WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$mode    = sanitize_key( (string) $request->get_param( 'mode' ) );
		$general = Settings::group( 'general' );
		$mode    = $mode ?: (string) ( $general['processing_mode'] ?? 'background' );

		if ( 'background' === $mode && has_action( 'itih_schedule_document' ) ) {
			do_action( 'itih_schedule_document', $id );
			return rest_ensure_response( array( 'id' => $id, 'queued' => true ) );
		}

		// On-request immediate processing.
		$result = $this->process_document( $id );
		return rest_ensure_response( array_merge( array( 'id' => $id ), $result ) );
	}

	public function rest_index_posts( \WP_REST_Request $request ) {
		$post_types = (array) $request->get_param( 'post_types' );
		$post_types = array_map( 'sanitize_key', $post_types );
		if ( empty( $post_types ) ) {
			$indexing = Settings::group( 'indexing' );
			$post_types = $indexing['post_types'] ?? array( 'post', 'page' );
		}

		$query = new \WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$queued = 0;
		foreach ( $query->posts as $post_id ) {
			if ( has_action( 'itih_schedule_post' ) ) {
				do_action( 'itih_schedule_post', $post_id );
				$queued++;
			}
		}

		return rest_ensure_response( array( 'queued' => $queued, 'total' => count( $query->posts ) ) );
	}
}
