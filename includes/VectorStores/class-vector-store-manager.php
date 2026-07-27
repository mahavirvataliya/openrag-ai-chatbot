<?php
/**
 * Vector store manager — picks the active store based on settings.
 *
 * Engine resolution:
 *   - "cloudflare" → Cloudflare Vectorize (if configured)
 *   - "mysql"      → MySQL store (always available)
 *   - "auto"       → Vectorize if configured, else MySQL
 *
 * @package WPOpenRag\VectorStores
 */

namespace WPOpenRag\VectorStores;

use WPOpenRag\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Vector_Store_Manager {

	/**
	 * @var Vector_Store|null
	 */
	private $store = null;

	/**
	 * Resolve the active store.
	 *
	 * @return Vector_Store
	 */
	public function store() {
		if ( null !== $this->store ) {
			return $this->store;
		}

		$settings = Settings::group( 'vector_store' );
		$engine   = $settings['engine'] ?? 'auto';

		if ( 'cloudflare' === $engine ) {
			$cf = new Cloudflare_Vectorize();
			if ( $cf->is_configured() ) {
				$this->store = $cf;
				return $this->store;
			}
		}

		if ( 'auto' === $engine ) {
			$cf = new Cloudflare_Vectorize();
			if ( $cf->is_configured() ) {
				$this->store = $cf;
				return $this->store;
			}
		}

		$this->store = new MySQL_Store();
		return $this->store;
	}

	/**
	 * Whether the active store is Cloudflare Vectorize.
	 *
	 * @return bool
	 */
	public function is_cloudflare() {
		return $this->store() instanceof Cloudflare_Vectorize;
	}

	/**
	 * Whether MySQL native VECTOR is in use (MySQL store only).
	 *
	 * @return bool
	 */
	public function is_mysql_native() {
		$store = $this->store();
		return ( $store instanceof MySQL_Store ) && $store->is_native();
	}

	/**
	 * All available store descriptions for admin UI.
	 *
	 * @return array<string,string>
	 */
	public function stores() {
		return array(
			'auto'       => __( 'Auto (Vectorize if configured, else MySQL)', 'wp-openrag' ),
			'mysql'      => __( 'MySQL', 'wp-openrag' ),
			'cloudflare' => __( 'Cloudflare Vectorize', 'wp-openrag' ),
		);
	}
}
