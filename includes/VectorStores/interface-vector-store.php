<?php
/**
 * Vector store contract.
 *
 * @package OpenRag\VectorStores
 */

namespace OpenRag\VectorStores;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Vector_Store {

	/**
	 * Store id.
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Human-readable label.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Whether the store is configured and usable.
	 *
	 * @return bool
	 */
	public function is_configured();

	/**
	 * Insert (or replace) a vector for a chunk.
	 *
	 * @param int                  $chunk_id   Local chunk row id.
	 * @param array<int|float>     $vector     Embedding vector.
	 * @param array<string,mixed>  $metadata   Arbitrary metadata (title, url, content, etc.).
	 * @return string The external vector id (empty when stored locally).
	 */
	public function upsert( $chunk_id, array $vector, array $metadata );

	/**
	 * Query the store for the nearest neighbors of a vector.
	 *
	 * @param array<int|float> $vector Query vector.
	 * @param int              $top_k  Number of results.
	 * @param float            $score  Minimum similarity (0..1).
	 * @return array<int,array{chunk_id:int, score:float, content:string, source_url:string, source_title:string}>
	 */
	public function query( array $vector, $top_k = 5, $score = 0.0 );

	/**
	 * Delete all vectors for a document.
	 *
	 * @param int $document_id Document id.
	 * @return void
	 */
	public function delete_document( $document_id );

	/**
	 * Delete a single chunk's vector.
	 *
	 * @param int $chunk_id Chunk id.
	 * @return void
	 */
	public function delete_chunk( $chunk_id );
}
