<?php
/**
 * Background processor — Action Scheduler integration.
 *
 * Two job types:
 *   - itih_process_document  (one per document)
 *   - itih_index_post        (one per WP post)
 *
 * Both call into Ingestion_Pipeline. AS handles retries, batching, and admin UI.
 *
 * @package ItihRag\Queue
 */

namespace ItihRag\Queue;

use ItihRag\Ingestion\Ingestion_Pipeline;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Background_Processor {

	/**
	 * @var Ingestion_Pipeline
	 */
	private $ingestion;

	/**
	 * Hook suffixes.
	 */
	const GROUP       = 'itih-ai-chatbot';
	const HOOK_DOC    = 'itih_process_document';
	const HOOK_POST   = 'itih_index_post';

	public function __construct( Ingestion_Pipeline $ingestion ) {
		$this->ingestion = $ingestion;
	}

	/**
	 * Hook the AS callbacks (called lazily when accessed via Plugin::queue()).
	 *
	 * @return void
	 */
	public function bootstrap() {
		add_action( self::HOOK_DOC, array( $this, 'run_document' ), 10, 1 );
		add_action( self::HOOK_POST, array( $this, 'run_post' ), 10, 1 );

		// Make itih_schedule_document / itih_schedule_post available.
		add_action( 'itih_schedule_document', array( $this, 'schedule_document' ), 10, 1 );
		add_action( 'itih_schedule_post', array( $this, 'schedule_post' ), 10, 1 );
	}

	/**
	 * Schedule (or re-schedule) processing for a document.
	 *
	 * @param int $document_id Document id.
	 * @return void
	 */
	public function schedule_document( $document_id ) {
		$this->ensure_bootstrapped();

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK_DOC, array( (int) $document_id ), self::GROUP );
		} else {
			// Fallback to a one-off WP-Cron if AS isn't loaded.
			if ( ! wp_next_scheduled( self::HOOK_DOC, array( (int) $document_id ) ) ) {
				wp_schedule_single_event( time() + 5, self::HOOK_DOC, array( (int) $document_id ) );
			}
		}
	}

	/**
	 * Schedule indexing for a single post.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public function schedule_post_index( $post_id ) {
		$this->ensure_bootstrapped();

		// First create / update the document row so the job has something to run.
		$this->ensure_post_document( $post_id );

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK_POST, array( (int) $post_id ), self::GROUP );
		} else {
			if ( ! wp_next_scheduled( self::HOOK_POST, array( (int) $post_id ) ) ) {
				wp_schedule_single_event( time() + 5, self::HOOK_POST, array( (int) $post_id ) );
			}
		}
	}

	/**
	 * Ensure the AS hooks are wired (idempotent).
	 *
	 * @return void
	 */
	private $bootstrapped = false;
	private function ensure_bootstrapped() {
		if ( $this->bootstrapped ) {
			return;
		}
		$this->bootstrap();
		$this->bootstrapped = true;
	}

	/**
	 * Create or refresh the document row for a post.
	 *
	 * @param int $post_id Post id.
	 * @return int Document id.
	 */
	private function ensure_post_document( $post_id ) {
		global $wpdb;
		$schema = new \ItihRag\Database\Schema();

		$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
			$wpdb->prepare( 'SELECT id FROM `' . $schema->table( 'documents' ) . '` WHERE post_id = %d', $post_id ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		if ( $existing ) {
			return (int) $existing;
		}

		$post = get_post( $post_id );
		return $this->ingestion->create_document(
			array(
				'type'       => 'post',
				'title'      => $post ? get_the_title( $post ) : '',
				'source_url' => $post ? (string) get_permalink( $post ) : '',
				'post_id'    => $post_id,
				'mime_type'  => 'text/html',
				'status'     => 'pending',
			)
		);
	}

	/**
	 * Run a single document processing job.
	 *
	 * @param int $document_id Document id.
	 * @return void
	 */
	public function run_document( $document_id ) {
		$this->ingestion->process_document( (int) $document_id );
	}

	/**
	 * Run a single post indexing job.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public function run_post( $post_id ) {
		$doc_id = $this->ensure_post_document( (int) $post_id );
		$this->ingestion->process_document( $doc_id );
	}
}
