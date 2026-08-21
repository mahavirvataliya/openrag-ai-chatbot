<?php
/**
 * PSR-4 fallback autoloader.
 *
 * Used when composer autoloader is not dumped. Maps ItihRag\<Sub>\<Name>
 * to includes/<Sub>/class-name.php (WordPress file naming convention).
 *
 * @package ItihRag
 */

namespace ItihRag;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Autoloader {

	/**
	 * Register the autoloader on the SPL stack.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload a class.
	 *
	 * @param string $class FQCN.
	 * @return void
	 */
	public static function autoload( $class ) {
		$prefix = 'ItihRag\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) ); // e.g. "Chat\RagEngine" or "Plugin".
		$parts    = explode( '\\', $relative );
		$name     = array_pop( $parts );
		$path     = implode( '/', $parts );

		$dir      = ITIH_DIR . 'includes/' . ( $path ? $path . '/' : '' );
		$kebab    = self::kebab( $name );

		// Try a small set of filename candidates — the kebab form, plus an
		// acronym-collapsed variant — for both classes and interfaces.
		$candidates = array(
			$dir . 'class-' . $kebab . '.php',
			$dir . 'interface-' . $kebab . '.php',
		);
		// Collapse runs of capitals in acronyms (OpenAI -> openai, MySQL -> mysql).
		if ( preg_match( '/-[a-z]-[a-z]/', $kebab ) ) {
			$collapsed = preg_replace_callback(
				'/(^|-)([a-z])-([a-z])(?![a-z])/',
				function ( $m ) {
					return $m[1] . $m[2] . $m[3];
				},
				$kebab
			);
			// Simpler: just collapse any single-letter chunks back together.
			$collapsed = preg_replace( '/(?:^|(?<=-))[a-z](?:-[a-z])+/', '$0', $kebab );
			$collapsed = preg_replace( '/-([a-z])-/', '$1', $collapsed );
			if ( $collapsed !== $kebab ) {
				$candidates[] = $dir . 'class-' . $collapsed . '.php';
				$candidates[] = $dir . 'interface-' . $collapsed . '.php';
			}
		}

		foreach ( $candidates as $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}

		// Last resort: glob the directory for class-*.php files and match the
		// lowercased name loosely (handles acronym casing differences).
		$lower = str_replace( '_', '-', strtolower( $name ) );
		foreach ( ( glob( $dir . 'class-*.php' ) ?: array() ) as $candidate ) {
			$base = str_replace( array( 'class-', '.php' ), '', basename( $candidate ) );
			if ( $base === $lower || str_replace( '-', '', $base ) === str_replace( '-', '', $lower ) ) {
				require_once $candidate;
				return;
			}
		}
		foreach ( ( glob( $dir . 'interface-*.php' ) ?: array() ) as $candidate ) {
			$base = str_replace( array( 'interface-', '.php' ), '', basename( $candidate ) );
			if ( $base === $lower || str_replace( '-', '', $base ) === str_replace( '-', '', $lower ) ) {
				require_once $candidate;
				return;
			}
		}
	}

	/**
	 * Convert a PascalCase / snake_case class name to kebab-case matching the
	 * WordPress file-naming convention (e.g. OpenAI_Embedding → openai-embedding).
	 *
	 * @param string $name Class name.
	 * @return string
	 */
	private static function kebab( $name ) {
		// Split on underscores first.
		$name = str_replace( '_', ' ', $name );
		// Insert a space before any capital that follows a lowercase/digit.
		$name = preg_replace( '/([a-z0-9])([A-Z])/', '$1 $2', $name );
		// Collapse runs of capitals so "AI Embedding" -> "AI Embedding" stays one word.
		// Then lowercase and join with dashes.
		$words = array_filter( preg_split( '/\s+/', $name ) );
		return strtolower( implode( '-', $words ) );
	}
}
