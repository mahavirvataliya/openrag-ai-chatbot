<?php
/**
 * Rate limiter — per-IP request counters using transients.
 *
 * @package OpenRag\Chat
 */

namespace OpenRag\Chat;

use OpenRag\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rate_Limiter {

	/**
	 * Check whether the caller may send a request. Increments the counter.
	 *
	 * @param string $ip Caller IP.
	 * @return bool
	 */
	public function allow( $ip = '' ) {
		$ip       = $ip ?: $this->client_ip();
		$settings = Settings::group( 'chat' );
		$window   = max( 5, (int) ( $settings['rate_limit_window'] ?? 60 ) );
		$max      = max( 1, (int) ( $settings['rate_limit_max'] ?? 15 ) );

		$key   = 'openrag_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= $max ) {
			return false;
		}
		$count++;
		set_transient( $key, $count, $window );
		return true;
	}

	/**
	 * Get the requestor's IP (best-effort).
	 *
	 * @return string
	 */
	public function client_ip() {
		$headers = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
		foreach ( $headers as $h ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( ! empty( $_SERVER[ $h ] ) ) {
				// Copy to a fixed-key local so the input sniffs can validate it, then unslash.
				$raw = isset( $_SERVER[ $h ] ) ? (string) wp_unslash( $_SERVER[ $h ] ) : '';
				$raw = trim( explode( ',', $raw )[0] );
				// filter_var() validates the result is a real IP address.
				$ip = filter_var( $raw, FILTER_VALIDATE_IP );
				if ( false !== $ip ) {
					return $ip;
				}
			}
		}
		return '0.0.0.0';
	}

	/**
	 * Device label inferred from the user-agent.
	 *
	 * @param string $ua User-Agent.
	 * @return string
	 */
	public function device( $ua = '' ) {
		$ua = $ua ?: (string) ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' );
		if ( preg_match( '/android|iphone|ipad|mobile/i', $ua ) ) {
			return 'mobile';
		}
		if ( false !== stripos( $ua, 'whatsapp' ) ) {
			return 'whatsapp';
		}
		return 'web';
	}
}
