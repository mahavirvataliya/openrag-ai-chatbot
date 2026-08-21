<?php
/**
 * Document loaders — extract clean text from PDF / DOCX / TXT / MD / HTML.
 *
 * @package ItihRag\Ingestion
 */

namespace ItihRag\Ingestion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Document_Loader {

	/**
	 * Load text content from a local file path or remote URL.
	 *
	 * @param string $source  Absolute path or URL.
	 * @param string $mime    Optional mime hint.
	 * @return array{text:string, title:string, error?:string}
	 */
	public function load( $source, $mime = '' ) {
		$source = (string) $source;
		if ( '' === $source ) {
			return array(
				'text'  => '',
				'title' => '',
				'error' => 'Empty source.',
			);
		}

		$ext  = strtolower( pathinfo( wp_parse_url( $source, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		$mime = $mime ? $mime : $this->guess_mime( $ext );

		try {
			switch ( $mime ) {
				case 'application/pdf':
					return $this->load_pdf( $source );

				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				case 'application/msword':
					return $this->load_docx( $source );

				case 'text/html':
					return $this->load_html( $source );

				case 'text/plain':
				case 'text/markdown':
				default:
					return $this->load_text( $source );
			}
		} catch ( \Throwable $e ) {
			return array(
				'text'  => '',
				'title' => '',
				'error' => $e->getMessage(),
			);
		}
	}

	/**
	 * Guess mime from extension.
	 *
	 * @param string $ext Extension without dot.
	 * @return string
	 */
	protected function guess_mime( $ext ) {
		$map = array(
			'pdf'      => 'application/pdf',
			'docx'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'doc'      => 'application/msword',
			'html'     => 'text/html',
			'htm'      => 'text/html',
			'txt'      => 'text/plain',
			'md'       => 'text/markdown',
			'markdown' => 'text/markdown',
		);
		return $map[ $ext ] ?? 'text/plain';
	}

	/**
	 * Read PDF text using smalot/pdfparser (if available).
	 *
	 * @param string $source Path or URL.
	 * @return array{text:string, title:string}
	 */
	protected function load_pdf( $source ) {
		if ( ! class_exists( '\\Smalot\\PdfParser\\Parser' ) ) {
			return array(
				'text'  => '',
				'title' => '',
				'error' => 'PDF parser library not installed.',
			);
		}

		$data = $this->fetch_bytes( $source );
		if ( is_wp_error( $data ) || ! $data ) {
			return array(
				'text'  => '',
				'title' => '',
				'error' => is_wp_error( $data ) ? $data->get_error_message() : 'Could not read PDF.',
			);
		}

		$parser = new \Smalot\PdfParser\Parser();
		$pdf    = $parser->parseContent( $data );
		$text   = $pdf->getText();

		$title = '';
		try {
			$details = $pdf->getDetails();
			if ( is_array( $details ) && ! empty( $details['Title'] ) ) {
				$title = (string) $details['Title'];
			}
		} catch ( \Throwable $e ) {
			// Title optional.
		}

		return array(
			'text'  => (string) $text,
			'title' => $title,
		);
	}

	/**
	 * Read DOCX text via ZipArchive + word/document.xml.
	 *
	 * @param string $source Path or URL.
	 * @return array{text:string, title:string}
	 */
	protected function load_docx( $source ) {
		$tmp = $this->local_temp( $source );
		if ( is_wp_error( $tmp ) ) {
			return array(
				'text'  => '',
				'title' => '',
				'error' => $tmp->get_error_message(),
			);
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			return array(
				'text'  => '',
				'title' => '',
				'error' => 'ZipArchive not available.',
			);
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $tmp ) ) {
			$this->cleanup_temp( $tmp );
			return array(
				'text'  => '',
				'title' => '',
				'error' => 'Could not open DOCX archive.',
			);
		}

		$xml  = $zip->getFromName( 'word/document.xml' );
		$core = $zip->getFromName( 'docProps/core.xml' );
		$zip->close();
		$this->cleanup_temp( $tmp );

		if ( false === $xml ) {
			return array(
				'text'  => '',
				'title' => '',
				'error' => 'DOCX missing word/document.xml.',
			);
		}

		// Strip XML tags, preserving paragraph breaks.
		$xml  = str_replace( '</w:p>', "\n\n", $xml );
		$xml  = preg_replace( '/<w:tab[^>]*\/?>/', "\t", $xml );
		$text = wp_strip_all_tags( $xml );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Extract title from core.xml (dc:title).
		$title = '';
		if ( $core && preg_match( '#<dc:title[^>]*>(.*?)</dc:title>#is', $core, $m ) ) {
			$title = trim( $m[1] );
		}

		return array(
			'text'  => trim( $text ),
			'title' => $title,
		);
	}

	/**
	 * Load HTML, strip boilerplate, return readable text.
	 *
	 * @param string $source Path or URL.
	 * @return array{text:string, title:string}
	 */
	public function load_html( $source ) {
		$raw = $this->fetch( $source );
		if ( is_wp_error( $raw ) ) {
			return array(
				'text'  => '',
				'title' => '',
				'error' => $raw->get_error_message(),
			);
		}

		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		// mb_convert_encoding keeps UTF-8 multibyte intact.
		$doc->loadHTML( '<?xml encoding="UTF-8">' . mb_convert_encoding( $raw, 'HTML-ENTITIES', 'UTF-8' ) );
		libxml_clear_errors();

		// Title.
		$title  = '';
		$titles = $doc->getElementsByTagName( 'title' );
		if ( $titles->length > 0 ) {
			$title = trim( $titles->item( 0 )->textContent );
		}

		// Drop non-content elements.
		foreach ( array( 'script', 'style', 'noscript', 'nav', 'header', 'footer', 'aside', 'form', 'svg' ) as $tag ) {
			$nodes     = $doc->getElementsByTagName( $tag );
			$to_remove = array();
			foreach ( $nodes as $node ) {
				$to_remove[] = $node;
			}
			foreach ( $to_remove as $node ) {
				$node->parentNode->removeChild( $node ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMNode native property.
			}
		}

		// Prefer <main> or <article> if present.
		$root = null;
		foreach ( array( 'main', 'article' ) as $tag ) {
			$candidates = $doc->getElementsByTagName( $tag );
			if ( $candidates->length > 0 ) {
				$root = $candidates->item( 0 );
				break;
			}
		}
		if ( ! $root ) {
			$body = $doc->getElementsByTagName( 'body' )->item( 0 );
			$root = $body ? $body : $doc;
		}

		$text = $this->dom_to_text( $root );
		return array(
			'text'  => trim( $text ),
			'title' => $title,
		);
	}

	/**
	 * Convert a DOM node to plain text with line breaks for block elements.
	 *
	 * @param \DOMNode $node Root node.
	 * @return string
	 */
	protected function dom_to_text( \DOMNode $node ) {
		$block_tags = array( 'p', 'div', 'br', 'li', 'tr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre' );

		$text = '';
		foreach ( $node->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMNode native property.
			if ( XML_TEXT_NODE === $child->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMNode native property.
				$text .= $child->nodeValue; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMNode native property.
				continue;
			}
			if ( XML_ELEMENT_NODE !== $child->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMNode native property.
				continue;
			}
			$tag   = strtolower( $child->nodeName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMNode native property.
			$inner = $this->dom_to_text( $child );
			if ( in_array( $tag, $block_tags, true ) ) {
				$text .= "\n" . $inner . "\n";
			} else {
				$text .= $inner;
			}
		}
		// Collapse whitespace.
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );
		return $text;
	}

	/**
	 * Load a plain-text or markdown file.
	 *
	 * @param string $source Path or URL.
	 * @return array{text:string, title:string}
	 */
	protected function load_text( $source ) {
		$raw = $this->fetch( $source );
		if ( is_wp_error( $raw ) ) {
			return array(
				'text'  => '',
				'title' => '',
				'error' => $raw->get_error_message(),
			);
		}
		$title = '';
		// Markdown H1 as title.
		if ( preg_match( '/^#\s+(.+)$/m', $raw, $m ) ) {
			$title = trim( $m[1] );
		}
		return array(
			'text'  => trim( $raw ),
			'title' => $title,
		);
	}

	/**
	 * Fetch raw text content from a path or URL.
	 *
	 * @param string $source Path or URL.
	 * @return string|\WP_Error
	 */
	protected function fetch( $source ) {
		if ( $this->is_remote( $source ) ) {
			$response = wp_remote_get(
				$source,
				array(
					'timeout'     => 60,
					'redirection' => 5,
				)
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code < 200 || $code >= 300 ) {
				return new \WP_Error( 'fetch_failed', 'HTTP ' . $code . ' fetching ' . $source );
			}
			return wp_remote_retrieve_body( $response );
		}
		// Restrict local reads to the uploads directory — the path originates
		// from an admin-supplied parameter and must never escape wp-content/uploads.
		$uploads = wp_get_upload_dir();
		if ( 0 !== strpos( $source, $uploads['basedir'] ) ) {
			return new \WP_Error( 'forbidden_path', 'File is outside the uploads directory: ' . $source );
		}
		if ( ! is_readable( $source ) ) {
			return new \WP_Error( 'unreadable', 'File not readable: ' . $source );
		}
		return (string) file_get_contents( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents -- local uploads read; WP_Filesystem offers no advantage for read-only string loads.
	}

	/**
	 * Fetch raw bytes (used for binary PDFs).
	 *
	 * @param string $source Path or URL.
	 * @return string|\WP_Error
	 */
	protected function fetch_bytes( $source ) {
		return $this->fetch( $source );
	}

	/**
	 * Download a remote file to a local temp path.
	 *
	 * @param string $source URL.
	 * @return string|\WP_Error Temp path.
	 */
	protected function local_temp( $source ) {
		if ( ! $this->is_remote( $source ) ) {
			return $source;
		}
		$response = wp_remote_get(
			$source,
			array(
				'timeout'     => 60,
				'redirection' => 5,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error( 'fetch_failed', 'HTTP ' . $code );
		}
		$body = wp_remote_retrieve_body( $response );
		$tmp  = wp_tempnam( basename( $source ) );
		if ( ! $tmp ) {
			return new \WP_Error( 'temp_fail', 'Could not create temp file.' );
		}
		file_put_contents( $tmp, $body );
		return $tmp;
	}

	/**
	 * Remove a temp file.
	 *
	 * @param string $path Temp path.
	 * @return void
	 */
	protected function cleanup_temp( $path ) {
		if ( $path && file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Whether a source is a remote URL.
	 *
	 * @param string $source Source.
	 * @return bool
	 */
	protected function is_remote( $source ) {
		return (bool) preg_match( '#^https?://#i', $source );
	}
}
