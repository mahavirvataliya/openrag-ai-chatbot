<?php
/**
 * MySQL vector store — native VECTOR(n) when MySQL 9, else JSON + PHP cosine.
 *
 * @package ItihRag\VectorStores
 */

namespace ItihRag\VectorStores;

use ItihRag\Database\Schema;

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

	/**
	 * Memoized native-VECTOR capability.
	 *
	 * @var bool|null
	 */
	private $native;

	public function __construct() {
		$this->schema = new Schema();
	}

	public function id() {
		return 'mysql';
	}

	public function label() {
		return __( 'MySQL', 'itih-ai-chatbot' );
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
		if ( null === $this->native ) {
			$this->native = (bool) $this->schema->supports_native_vector();
		}
		return $this->native;
	}

	protected function chunks_table() {
		return $this->schema->table( 'chunks' );
	}

	protected function documents_table() {
		return $this->schema->table( 'documents' );
	}

	public function upsert( $chunk_id, array $vector, array $metadata ) {
		global $wpdb;

		$table      = $this->chunks_table();
		$native     = $this->is_native();
		$vector_str = $native
			? '[' . implode( ',', array_map( 'floatval', $vector ) ) . ']'
			: wp_json_encode( array_map( 'floatval', $vector ) );

		if ( $native ) {
			// Use STRING_TO_VECTOR for MySQL 9.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB
			$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET `embedding` = STRING_TO_VECTOR(%s) WHERE `id` = %d", $vector_str, $chunk_id ) );
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
			// MySQL 9: DISTANCE() with COSINE returns cosine distance (1 - cosine_sim). similarity = 1 - distance.
			// phpcs:ignore WordPress.DB, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT c.id AS chunk_id, c.content, c.source_url, c.source_title, (1 - DISTANCE(c.embedding, STRING_TO_VECTOR(%s), 'COSINE')) AS score FROM `{$table}` c WHERE c.embedding IS NOT NULL HAVING score >= %f ORDER BY score DESC LIMIT %d", $vec_str, $min_score, $top_k ) );
		} else {
			// Fallback: score in PHP. Keyset-paginated batches of (id, embedding)
			// only — loading full content for every row peaked at ~100MB of memory
			// on large knowledge bases. Content is hydrated for the top-k afterwards.
			$qnorm = 0.0;
			foreach ( $vector as $qv ) {
				$qnorm += (float) $qv * (float) $qv;
			}
			$qnorm = sqrt( $qnorm );
			if ( $qnorm <= 0 ) {
				return array();
			}

			$candidates = array();
			$last_id    = 0;
			while ( true ) {
				// phpcs:ignore WordPress.DB, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT c.id AS chunk_id, c.embedding FROM `{$table}` c WHERE c.id > %d AND c.embedding IS NOT NULL AND c.embedding != '' ORDER BY c.id ASC LIMIT 500", $last_id ) );
				if ( empty( $rows ) ) {
					break;
				}
				foreach ( $rows as $row ) {
					$last_id = (int) $row->chunk_id;
					$vec     = json_decode( $row->embedding, true );
					if ( ! is_array( $vec ) || count( $vec ) !== count( $vector ) ) {
						continue;
					}
					$dot   = 0.0;
					$vnorm = 0.0;
					for ( $i = 0, $n = count( $vec ); $i < $n; $i++ ) {
						$bv     = (float) $vec[ $i ];
						$dot   += (float) $vector[ $i ] * $bv;
						$vnorm += $bv * $bv;
					}
					if ( $vnorm <= 0 ) {
						continue;
					}
					$sim = $dot / ( $qnorm * sqrt( $vnorm ) );
					if ( $sim < $min_score ) {
						continue;
					}
					$candidates[] = array(
						'chunk_id' => (int) $row->chunk_id,
						'score'    => $sim,
					);
				}
				if ( count( $rows ) < 500 ) {
					break;
				}
			}

			if ( empty( $candidates ) ) {
				return array();
			}
			usort(
				$candidates,
				function ( $a, $b ) {
					return $b['score'] <=> $a['score'];
				}
			);
			$candidates = array_slice( $candidates, 0, $top_k );

			// Hydrate content/source for just the top-k chunks.
			$ids          = wp_list_pluck( $candidates, 'chunk_id' );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB
			$hydrated = $wpdb->get_results( $wpdb->prepare( "SELECT c.id AS chunk_id, c.content, c.source_url, c.source_title FROM `{$table}` c WHERE c.id IN ( {$placeholders} )", $ids ) );
			$by_id    = array();
			foreach ( $hydrated as $h ) {
				$by_id[ (int) $h->chunk_id ] = $h;
			}

			$out = array();
			foreach ( $candidates as $cand ) {
				if ( ! isset( $by_id[ $cand['chunk_id'] ] ) ) {
					continue;
				}
				$h     = $by_id[ $cand['chunk_id'] ];
				$out[] = array(
					'chunk_id'     => $cand['chunk_id'],
					'content'      => (string) $h->content,
					'source_url'   => (string) $h->source_url,
					'source_title' => (string) $h->source_title,
					'score'        => $cand['score'],
				);
			}
			return $out;
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
}
