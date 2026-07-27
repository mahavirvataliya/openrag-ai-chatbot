<?php
/**
 * LLM manager — factory + cached active provider.
 *
 * @package WPOpenRag\LLM
 */

namespace WPOpenRag\LLM;

use WPOpenRag\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Manager {

	/**
	 * @var LLM_Provider|null
	 */
	private $provider = null;

	/**
	 * Resolved active provider.
	 *
	 * @return LLM_Provider
	 */
	public function provider() {
		if ( null !== $this->provider ) {
			return $this->provider;
		}

		$settings = Settings::group( 'providers' );
		$id       = $settings['llm_provider'] ?? 'openai';

		switch ( $id ) {
			case 'groq':
				$this->provider = new OpenAI_LLM(
					'groq',
					__( 'Groq', 'wp-openrag' ),
					array(
						'base_url' => $settings['groq_base_url'] ?? 'https://api.groq.com/openai/v1',
						'api_key'  => $settings['groq_api_key'] ?? '',
						'model'    => $settings['groq_model'] ?? 'llama-3.3-70b-versatile',
					)
				);
				break;

			case 'openai-compatible':
				$this->provider = new OpenAI_LLM(
					'openai-compatible',
					__( 'OpenAI-compatible', 'wp-openrag' ),
					array(
						'base_url' => $settings['compatible_base_url'] ?? '',
						'api_key'  => $settings['compatible_api_key'] ?? '',
						'model'    => $settings['compatible_model'] ?? '',
					)
				);
				break;

			case 'anthropic':
				$this->provider = new Anthropic_LLM( $settings );
				break;

			case 'cloudflare':
				$this->provider = new Cloudflare_LLM( $settings );
				break;

			case 'ollama':
				$this->provider = new Ollama_LLM(
					array(
						'ollama_base_url' => $settings['ollama_base_url'] ?? 'http://localhost:11434',
						'ollama_model'    => $settings['ollama_model'] ?? 'llama3.1',
					)
				);
				break;

			case 'openai':
			default:
				$this->provider = new OpenAI_LLM(
					'openai',
					__( 'OpenAI', 'wp-openrag' ),
					array(
						'base_url' => $settings['openai_base_url'] ?? 'https://api.openai.com/v1',
						'api_key'  => $settings['openai_api_key'] ?? '',
						'model'    => $settings['openai_model'] ?? 'gpt-4o-mini',
					)
				);
				break;
		}

		return $this->provider;
	}

	/**
	 * List all available provider ids.
	 *
	 * @return array<string,string>
	 */
	public function providers() {
		return array(
			'openai'            => __( 'OpenAI', 'wp-openrag' ),
			'openai-compatible' => __( 'OpenAI-compatible', 'wp-openrag' ),
			'anthropic'         => __( 'Anthropic Claude', 'wp-openrag' ),
			'cloudflare'        => __( 'Cloudflare Workers AI', 'wp-openrag' ),
			'groq'              => __( 'Groq', 'wp-openrag' ),
			'ollama'            => __( 'Ollama (local)', 'wp-openrag' ),
		);
	}

	/**
	 * Non-streaming chat.
	 *
	 * @param array $messages Messages.
	 * @param array $opts     Options.
	 * @return array
	 */
	public function chat( array $messages, array $opts = array() ) {
		return $this->provider()->chat( $messages, $opts );
	}

	/**
	 * Streaming chat generator.
	 *
	 * @param array $messages Messages.
	 * @param array $opts     Options.
	 * @return \Generator
	 */
	public function stream( array $messages, array $opts = array() ) {
		return $this->provider()->stream( $messages, $opts );
	}

	/**
	 * Fetch the active provider's model list.
	 *
	 * @return array<string>
	 */
	public function list_models() {
		return $this->provider()->list_models();
	}
}
