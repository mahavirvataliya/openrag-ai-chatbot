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
		// Read each header with a fixed string key so the input sniffs can verify
		// unslash + sanitization. filter_var() below then validates a real IP.
		$candidates = array(
			isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : '',
			isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : '',
			isset( $_SERVER['HTTP_X_REAL_IP'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) ) : '',
			isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
		);
		foreach ( $candidates as $raw ) {
			if ( '' === $raw ) {
				continue;
			}
			$ip = filter_var( trim( explode( ',', $raw )[0] ), FILTER_VALIDATE_IP );
			if ( false !== $ip ) {
				return $ip;
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
