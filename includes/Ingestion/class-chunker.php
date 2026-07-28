<?php
/**
 * Sentence-aware text chunker with overlap.
 *
 * Unlike the reference plugins which mangle content with sanitize_text_field()
 * and have no overlap, this preserves formatting and slides a window over
 * sentences so that adjacent chunks share context.
 *
 * @package OpenRag\Ingestion
 */

namespace OpenRag\Ingestion;

use OpenRag\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chunker {

	/**
	 * Split text into chunks.
	 *
	 * @param string      $text    Source text.
	 * @param int|null    $size    Target chunk size (chars). Null = from settings.
	 * @param int|null    $overlap Overlap size (chars). Null = from settings.
	 * @param int         $min     Minimum chunk length to keep.
	 * @return array<int,array{text:string, index:int, tokens:int}>
	 */
	public function split( $text, $size = null, $overlap = null, $min = null ) {
		$settings = Settings::group( 'indexing' );

		$size    = $size ?? (int) ( $settings['chunk_size'] ?? 800 );
		$overlap = $overlap ?? (int) ( $settings['chunk_overlap'] ?? 100 );
		$min     = $min ?? (int) ( $settings['min_chunk_chars'] ?? 40 );

		$text = $this->normalize( (string) $text );
		if ( '' === $text ) {
			return array();
		}

		$size    = max( 100, $size );
		$overlap = max( 0, min( $overlap, (int) ( $size * 0.5 ) ) );
		$min     = max( 1, $min );

		// Split into paragraphs first, then sentences.
		$paragraphs = preg_split( '/\n{2,}/', $text );
		$sentences  = array();
		foreach ( $paragraphs as $p ) {
			$p = trim( $p );
			if ( '' === $p ) {
				continue;
			}
			// Split on sentence enders but keep them.
			$parts = preg_split( '/(?<=[.!?。!？])\s+/', $p );
			foreach ( $parts as $s ) {
				$s = trim( $s );
				if ( '' === $s ) {
					continue;
				}
				// If a single sentence is longer than the chunk size, hard-split it.
				if ( mb_strlen( $s ) > $size ) {
					$pieces = str_split( $s, $size );
					foreach ( $pieces as $piece ) {
						$sentences[] = trim( $piece );
					}
				} else {
					$sentences[] = $s;
				}
			}
		}

		// Greedily pack sentences into chunks of ~$size, with $overlap between them.
		$chunks    = array();
		$current   = '';
		$current_len = 0;
		$index     = 0;

		foreach ( $sentences as $sentence ) {
			$slen = mb_strlen( $sentence );

			if ( $current_len + $slen + 1 > $size && $current_len >= $min ) {
				// Emit current chunk.
				$chunks[] = $this->make_chunk( $current, $index++ );

				// Build overlap from the tail of current.
				if ( $overlap > 0 && $current_len > $overlap ) {
					$tail        = mb_substr( $current, -$overlap );
					$current     = $tail . ' ' . $sentence;
					$current_len = mb_strlen( $current );
				} else {
					$current     = $sentence;
					$current_len = $slen;
				}
			} else {
				$current     = '' === $current ? $sentence : $current . ' ' . $sentence;
				$current_len = mb_strlen( $current );
			}
		}

		if ( $current_len >= $min ) {
			$chunks[] = $this->make_chunk( $current, $index++ );
		}

		return $chunks;
	}

	/**
	 * Normalize whitespace and control chars while preserving formatting.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	protected function normalize( $text ) {
		// Strip NUL and other control chars except newline/tab.
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );
		// Collapse runs of spaces/tabs.
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		// Normalize newlines.
		$text = preg_replace( '/\r\n?/', "\n", $text );
		// Trim trailing spaces on each line.
		$lines = array_map( 'rtrim', explode( "\n", $text ) );
		$text  = implode( "\n", $lines );
		return trim( $text );
	}

	/**
	 * Build a chunk record with a rough token estimate.
	 *
	 * @param string $text   Chunk text.
	 * @param int    $index  Chunk index.
	 * @return array{text:string, index:int, tokens:int}
	 */
	protected function make_chunk( $text, $index ) {
		$text  = trim( $text );
		$tokens = (int) ceil( mb_strlen( $text ) / 4 ); // ~4 chars per token heuristic.
		return array(
			'text'   => $text,
			'index'  => $index,
			'tokens' => $tokens,
		);
	}
}
