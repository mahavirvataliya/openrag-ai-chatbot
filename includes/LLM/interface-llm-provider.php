<?php
/**
 * LLM provider contract.
 *
 * @package OpenRag\LLM
 */

namespace OpenRag\LLM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalized message shape used across providers.
 *
 * @var array{
 *   role?: string,
 *   content?: string,
 *   reasoning?: string,
 *   tool_calls?: array,
 * }
 */

interface LLM_Provider {

	/**
	 * Provider key.
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Human-readable name.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Whether the provider is configured.
	 *
	 * @return bool
	 */
	public function is_configured();

	/**
	 * Whether this provider supports streaming.
	 *
	 * @return bool
	 */
	public function supports_streaming();

	/**
	 * Whether this provider supports tool/function calling.
	 *
	 * @return bool
	 */
	public function supports_tools();

	/**
	 * Whether this provider supports reasoning/thinking trace.
	 *
	 * @return bool
	 */
	public function supports_reasoning();

	/**
	 * Send a non-streaming chat request.
	 *
	 * @param array $messages List of normalized messages.
	 * @param array $opts     {model, temperature, max_tokens, reasoning_effort, tools, stream_callback}.
	 * @return array{content: string, reasoning: string, tool_calls: array, prompt_tokens: int, completion_tokens: int, model: string}
	 */
	public function chat( array $messages, array $opts = array() );

	/**
	 * Stream a chat request.
	 *
	 * Yields associative arrays:
	 *   ['type'=>'delta','content'=>string]
	 *   ['type'=>'reasoning','content'=>string]
	 *   ['type'=>'tool_call', ...]
	 *   ['type'=>'done', 'usage'=>[...], 'model'=>string]
	 *
	 * @param array    $messages Messages.
	 * @param array    $opts     Options.
	 * @return \Generator
	 */
	public function stream( array $messages, array $opts = array() );

	/**
	 * Fetch the list of available models for this provider.
	 *
	 * @return array<string> Model IDs.
	 */
	public function list_models();
}
