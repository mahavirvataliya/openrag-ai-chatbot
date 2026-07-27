<?php
/**
 * MySQL vector store — native VECTOR(n) when MySQL 9, else JSON + PHP cosine.
 *
 * @package WPOpenRag\VectorStores
 */

namespace WPOpenRag\VectorStores;

use WPOpenRag\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MySQL_Store implements Vector_Store {

	/**
	 * Schema helper.
	 *
	 * @var Schema
	 */
	private $schema;

	public function __construct() {
		$this->schema = new Schema();
	}

	public function id() {
		return 'mysql';
	}

	public function label() {
		return __( 'MySQL', 'wp-openrag' );
	}

	public function is_configured() {
		return true; // Always available.
	}

	/**
	 * Whether native VECTOR type is in use.
	 *
	 * @return bool
	 */
	public function is_native() {
		return (bool) $this->schema->supports_native_vector();
	}

	protected function chunks_table() {
		return $this->schema->table( 'chunks' );
	}

	protected function documents_table() {
		return $this->schema->table( 'documents' );
	}

	public function upsert( $chunk_id, array $vector, array $metadata ) {
		global $wpdb;

		$table     = $this->chunks_table();
		$vector_str = $this->is_native()
			? '[' . implode( ',', array_map( 'floatval', $vector ) ) . ']'
			: wp_json_encode( array_map( 'floatval', $vector ) );

		if ( $this->is_native() ) {
			// Use STRING_TO_VECTOR for MySQL 9.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$table}` SET `embedding` = STRING_TO_VECTOR(%s) WHERE `id` = %d", // phpcs:ignore WordPress.DB
					$vector_str,
					$chunk_id
				)
			);
		} else {
			$wpdb->update( // phpcs:ignore WordPress.DB
				$table,
				array( 'embedding' => $vector_str ),
				array( 'id' => $chunk_id ),
				array( '%s' ),
				array( '%d' )
			);
		}
		return '';
	}

	public function query( array $vector, $top_k = 5, $score = 0.0 ) {
		global $wpdb;

		$table     = $this->chunks_table();
		$top_k     = max( 1, (int) $top_k );
		$min_score = max( 0.0, (float) $score );

		if ( $this->is_native() ) {
			$vec_str = '[' . implode( ',', array_map( 'floatval', $vector ) ) . ']';
			// MySQL 9: DISTANCE() with COSINE returns cosine distance (1 - cosine_sim).
			// similarity = 1 - distance.
			$sql = $wpdb->prepare(
				"SELECT c.id AS chunk_id, c.content, c.source_url, c.source_title,
				        (1 - DISTANCE(c.embedding, STRING_TO_VECTOR(%s), 'COSINE')) AS score
				 FROM `{$table}` c
				 WHERE c.embedding IS NOT NULL
				 HAVING score >= %f
				 ORDER BY score DESC
				 LIMIT %d",
				$vec_str,
				$min_score,
				$top_k
			);
			// phpcs:ignore WordPress.DB
			$rows = $wpdb->get_results( $sql );
		} else {
			// Fallback: load chunks and score in PHP. Pre-filter by recent docs to bound work.
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
				"SELECT c.id AS chunk_id, c.content, c.source_url, c.source_title, c.embedding
				 FROM `{$table}` c
				 WHERE c.embedding IS NOT NULL AND c.embedding != ''
				 ORDER BY c.id DESC
				 LIMIT 5000"
			);
			$scored = array();
			foreach ( $rows as $row ) {
				$vec = json_decode( $row->embedding, true );
				if ( ! is_array( $vec ) || count( $vec ) !== count( $vector ) ) {
					continue;
				}
				$sim = $this->cosine( $vector, $vec );
				if ( $sim >= $min_score ) {
					$scored[] = array(
						'chunk_id'     => (int) $row->chunk_id,
						'content'      => (string) $row->content,
						'source_url'   => (string) $row->source_url,
						'source_title' => (string) $row->source_title,
						'score'        => $sim,
					);
				}
			}
			usort(
				$scored,
				function ( $a, $b ) {
					return $b['score'] <=> $a['score'];
				}
			);
			return array_slice( $scored, 0, $top_k );
		}

		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'chunk_id'     => (int) $row->chunk_id,
				'content'      => (string) $row->content,
				'source_url'   => (string) $row->source_url,
				'source_title' => (string) $row->source_title,
				'score'        => (float) $row->score,
			);
		}
		return $out;
	}

	public function delete_document( $document_id ) {
		global $wpdb;
		$wpdb->delete( // phpcs:ignore WordPress.DB
			$this->chunks_table(),
			array( 'document_id' => (int) $document_id ),
			array( '%d' )
		);
	}

	public function delete_chunk( $chunk_id ) {
		global $wpdb;
		$wpdb->delete( // phpcs:ignore WordPress.DB
			$this->chunks_table(),
			array( 'id' => (int) $chunk_id ),
			array( '%d' )
		);
	}

	/**
	 * Cosine similarity of two equal-length vectors.
	 *
	 * @param array $a Vector a.
	 * @param array $b Vector b.
	 * @return float
	 */
	protected function cosine( array $a, array $b ) {
		$n   = count( $a );
		$dot = 0.0;
		$na  = 0.0;
		$nb  = 0.0;
		for ( $i = 0; $i < $n; $i++ ) {
			$av = (float) $a[ $i ];
			$bv = (float) $b[ $i ];
			$dot += $av * $bv;
			$na  += $av * $av;
			$nb  += $bv * $bv;
		}
		if ( $na <= 0 || $nb <= 0 ) {
			return 0.0;
		}
		return $dot / ( sqrt( $na ) * sqrt( $nb ) );
	}
}
