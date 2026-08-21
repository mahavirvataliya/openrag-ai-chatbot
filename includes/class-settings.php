<?php
/**
 * Centralized settings access and defaults.
 *
 * @package ItihRag
 */

namespace ItihRag;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	/**
	 * All defaults, keyed by group then option name.
	 *
	 * @var array|null
	 */
	private static $defaults = null;

	/**
	 * Cached merged settings.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Get all defaults in the storage shape.
	 *
	 * Each top-level key is an option name (stored as itih_<key>) whose value
	 * is an associative array of group settings. The "all()" method merges these.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function defaults() {
		if ( null !== self::$defaults ) {
			return self::$defaults;
		}

		self::$defaults = array(

			'general'      => array(
				'enabled'            => '1',
				'default_chat_scope' => 'auto',
				'processing_mode'    => 'background',
				'debug_logging'      => '0',
				'wipe_on_uninstall'  => '0',
			),

			'chat'         => array(
				'widget_enabled'    => '1',
				'bot_name'          => __( 'Assistant', 'itih-ai-chatbot' ),
				'welcome_message'   => __( "Hi! I'm an AI assistant. Ask me anything about the content on this site.", 'itih-ai-chatbot' ),
				'launcher_position' => 'bottom-right',
				'system_prompt'     => __( 'You are a helpful assistant answering questions using the provided knowledge base. When relevant context is given, base your answer on it and cite sources by their number (e.g. [1]). Be concise and accurate. If the answer is not in the context, say so.', 'itih-ai-chatbot' ),
				'temperature'       => '0.3',
				'max_tokens'        => '800',
				'history_turns'     => '6',
				'citations'         => '1',
				'reasoning'         => '0',
				'reasoning_effort'  => 'medium',
				'show_credit'       => '0',
				'rate_limit_window' => '60',
				'rate_limit_max'    => '15',
				'top_k'             => '5',
				'min_similarity'    => '0.35',
			),

			'providers'    => array(
				'llm_provider'         => 'openai',
				'openai_api_key'       => '',
				'openai_base_url'      => 'https://api.openai.com/v1',
				'openai_model'         => 'gpt-4o-mini',
				'groq_api_key'         => '',
				'groq_base_url'        => 'https://api.groq.com/openai/v1',
				'groq_model'           => 'llama-3.3-70b-versatile',
				'compatible_api_key'   => '',
				'compatible_base_url'  => '',
				'compatible_model'     => '',
				'anthropic_api_key'    => '',
				'anthropic_base_url'   => 'https://api.anthropic.com/v1',
				'anthropic_model'      => 'claude-3-5-sonnet-latest',
				'cloudflare_account'   => '',
				'cloudflare_token'     => '',
				'cloudflare_llm_model' => '@cf/meta/llama-3.1-8b-instruct',
			),

			'embeddings'   => array(
				'embedding_provider'  => 'openai',
				'openai_api_key'      => '',
				'openai_model'        => 'text-embedding-3-small',
				'openai_base_url'     => 'https://api.openai.com/v1',
				'compatible_api_key'  => '',
				'compatible_base_url' => '',
				'compatible_model'    => '',
				'cloudflare_account'  => '',
				'cloudflare_token'    => '',
				'cloudflare_model'    => '@cf/baai/bge-base-en-v1.5',
				'ollama_base_url'     => 'http://localhost:11434',
				'ollama_model'        => 'nomic-embed-text',
				'dimensions'          => '0', // 0 = auto-detect.
			),

			'vector_store' => array(
				'engine'              => 'auto', // auto|mysql|cloudflare.
				'mysql_native_vector' => '',     // detected at runtime, '' until probed.
				'cloudflare_account'  => '',
				'cloudflare_token'    => '',
				'cloudflare_index'    => 'itih-ai-chatbot',
			),

			'indexing'     => array(
				'chunk_size'      => '800',
				'chunk_overlap'   => '100',
				'min_chunk_chars' => '40',
				'post_types'      => array( 'post', 'page' ),
				'auto_index'      => '1',
				'top_k'           => '5',
				'min_similarity'  => '0.35',
			),

			'appearance'   => array(
				'theme'  => 'light',
				'logo'   => '',
				'avatar' => '',
				'colors' => array(
					'primary'       => '#3b82f6',
					'header_bg'     => '#1e293b',
					'header_text'   => '#ffffff',
					'bg'            => '#ffffff',
					'text'          => '#0f172a',
					'user_bubble'   => '#3b82f6',
					'user_text'     => '#ffffff',
					'bot_bubble'    => '#f1f5f9',
					'bot_text'      => '#0f172a',
					'launcher'      => '#3b82f6',
					'launcher_icon' => '#ffffff',
				),
			),

			'mcp'          => array(
				'enabled' => '0',
			),
		);

		return self::$defaults;
	}

	/**
	 * Get a single settings group merged with defaults.
	 *
	 * @param string $group Group key.
	 * @return array<string,mixed>
	 */
	public static function group( $group ) {
		if ( isset( self::$cache[ $group ] ) ) {
			return self::$cache[ $group ];
		}
		$defaults = self::defaults();
		$stored   = get_option( ITIH_OPTION_PREFIX . $group, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		if ( ! isset( $defaults[ $group ] ) ) {
			return $stored;
		}
		self::$cache[ $group ] = wp_parse_args( $stored, $defaults[ $group ] );
		return self::$cache[ $group ];
	}

	/**
	 * Get all settings groups merged with defaults.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		self::$cache = array();
		foreach ( array_keys( self::defaults() ) as $group ) {
			self::$cache[ $group ] = self::group( $group );
		}
		return self::$cache;
	}

	/**
	 * Save a single group's values.
	 *
	 * @param string $group  Group key.
	 * @param array  $values Associative values.
	 * @return void
	 */
	public static function save_group( $group, $values ) {
		if ( ! is_array( $values ) ) {
			$values = array();
		}
		update_option( ITIH_OPTION_PREFIX . $group, $values );
		self::$cache = null;
	}

	/**
	 * Clear the in-memory cache.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Render CSS custom properties from appearance settings.
	 *
	 * @param array $appearance The appearance group array.
	 * @return string Raw CSS rules (without selector).
	 */
	public static function render_css_vars( $appearance ) {
		$colors = isset( $appearance['colors'] ) ? $appearance['colors'] : array();
		$vars   = array(
			'--openrag-primary'       => $colors['primary'] ?? '#3b82f6',
			'--openrag-header-bg'     => $colors['header_bg'] ?? '#1e293b',
			'--openrag-header-text'   => $colors['header_text'] ?? '#ffffff',
			'--openrag-bg'            => $colors['bg'] ?? '#ffffff',
			'--openrag-text'          => $colors['text'] ?? '#0f172a',
			'--openrag-user-bubble'   => $colors['user_bubble'] ?? '#3b82f6',
			'--openrag-user-text'     => $colors['user_text'] ?? '#ffffff',
			'--openrag-bot-bubble'    => $colors['bot_bubble'] ?? '#f1f5f9',
			'--openrag-bot-text'      => $colors['bot_text'] ?? '#0f172a',
			'--openrag-launcher'      => $colors['launcher'] ?? '#3b82f6',
			'--openrag-launcher-icon' => $colors['launcher_icon'] ?? '#ffffff',
		);

		$css = ':root, #openrag-widget, .openrag-inline {';
		foreach ( $vars as $name => $value ) {
			$css .= $name . ':' . esc_attr( $value ) . ';';
		}
		$css .= '}';

		return $css;
	}

	/**
	 * The four preset themes for reference / admin selection.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function theme_presets() {
		return array(
			'light'  => array(
				'label'  => __( 'Light', 'itih-ai-chatbot' ),
				'colors' => array(
					'primary'       => '#3b82f6',
					'header_bg'     => '#1e293b',
					'header_text'   => '#ffffff',
					'bg'            => '#ffffff',
					'text'          => '#0f172a',
					'user_bubble'   => '#3b82f6',
					'user_text'     => '#ffffff',
					'bot_bubble'    => '#f1f5f9',
					'bot_text'      => '#0f172a',
					'launcher'      => '#3b82f6',
					'launcher_icon' => '#ffffff',
				),
			),
			'dark'   => array(
				'label'  => __( 'Dark', 'itih-ai-chatbot' ),
				'colors' => array(
					'primary'       => '#60a5fa',
					'header_bg'     => '#0f172a',
					'header_text'   => '#f8fafc',
					'bg'            => '#1e293b',
					'text'          => '#e2e8f0',
					'user_bubble'   => '#2563eb',
					'user_text'     => '#ffffff',
					'bot_bubble'    => '#334155',
					'bot_text'      => '#f1f5f9',
					'launcher'      => '#60a5fa',
					'launcher_icon' => '#0f172a',
				),
			),
			'ocean'  => array(
				'label'  => __( 'Ocean', 'itih-ai-chatbot' ),
				'colors' => array(
					'primary'       => '#0891b2',
					'header_bg'     => '#155e75',
					'header_text'   => '#ecfeff',
					'bg'            => '#f0fdfa',
					'text'          => '#0f3d3e',
					'user_bubble'   => '#0891b2',
					'user_text'     => '#ffffff',
					'bot_bubble'    => '#cffafe',
					'bot_text'      => '#0f3d3e',
					'launcher'      => '#0e7490',
					'launcher_icon' => '#ffffff',
				),
			),
			'sunset' => array(
				'label'  => __( 'Sunset', 'itih-ai-chatbot' ),
				'colors' => array(
					'primary'       => '#ea580c',
					'header_bg'     => '#7c2d12',
					'header_text'   => '#fff7ed',
					'bg'            => '#fffbeb',
					'text'          => '#431407',
					'user_bubble'   => '#ea580c',
					'user_text'     => '#ffffff',
					'bot_bubble'    => '#ffedd5',
					'bot_text'      => '#431407',
					'launcher'      => '#f97316',
					'launcher_icon' => '#ffffff',
				),
			),
		);
	}
}
