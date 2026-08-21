<?php
/**
 * Settings page — tabbed, all configuration groups.
 *
 * @package ItihRag\Admin
 */

namespace ItihRag\Admin;

use ItihRag\Database\Schema;
use ItihRag\LLM\LLM_Manager;
use ItihRag\Embeddings\Embedding_Manager;
use ItihRag\Settings;
use ItihRag\VectorStores\Vector_Store_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings_Page {

	const NONCE = 'openrag_save_settings';

	/**
	 * Handle a settings save POST.
	 *
	 * @return void
	 */
	public function maybe_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( self::NONCE );
		if ( ! isset( $_POST['openrag_settings'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence guard; nonce verified above.
			return;
		}

		$form = wp_unslash( $_POST['openrag_settings'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		// General.
		Settings::save_group(
			'general',
			array(
				'enabled'           => empty( $form['general']['enabled'] ) ? '0' : '1',
				'default_chat_scope'=> sanitize_key( $form['general']['default_chat_scope'] ?? 'auto' ),
				'processing_mode'   => sanitize_key( $form['general']['processing_mode'] ?? 'background' ),
				'debug_logging'     => empty( $form['general']['debug_logging'] ) ? '0' : '1',
				'wipe_on_uninstall' => empty( $form['general']['wipe_on_uninstall'] ) ? '0' : '1',
			)
		);

		// Chat.
		Settings::save_group(
			'chat',
			array(
				'widget_enabled'    => empty( $form['chat']['widget_enabled'] ) ? '0' : '1',
				'bot_name'          => sanitize_text_field( $form['chat']['bot_name'] ?? '' ),
				'welcome_message'   => sanitize_textarea_field( $form['chat']['welcome_message'] ?? '' ),
				'launcher_position' => sanitize_key( $form['chat']['launcher_position'] ?? 'bottom-right' ),
				'system_prompt'     => sanitize_textarea_field( $form['chat']['system_prompt'] ?? '' ),
				'temperature'       => (string) max( 0, min( 2, (float) ( $form['chat']['temperature'] ?? 0.3 ) ) ),
				'max_tokens'        => (string) max( 1, (int) ( $form['chat']['max_tokens'] ?? 800 ) ),
				'history_turns'     => (string) max( 0, (int) ( $form['chat']['history_turns'] ?? 6 ) ),
				'citations'         => empty( $form['chat']['citations'] ) ? '0' : '1',
				'reasoning'         => empty( $form['chat']['reasoning'] ) ? '0' : '1',
				'reasoning_effort'  => sanitize_key( $form['chat']['reasoning_effort'] ?? 'medium' ),
				'show_credit'       => empty( $form['chat']['show_credit'] ) ? '0' : '1',
				'rate_limit_window' => (string) max( 1, (int) ( $form['chat']['rate_limit_window'] ?? 60 ) ),
				'rate_limit_max'    => (string) max( 1, (int) ( $form['chat']['rate_limit_max'] ?? 15 ) ),
				'top_k'             => (string) max( 1, (int) ( $form['chat']['top_k'] ?? 5 ) ),
				'min_similarity'    => (string) max( 0, min( 1, (float) ( $form['chat']['min_similarity'] ?? 0.35 ) ) ),
			)
		);

		// Providers (LLM + embeddings keys).
		$p = $form['providers'] ?? array();
		Settings::save_group(
			'providers',
			array(
				'llm_provider'        => sanitize_key( $p['llm_provider'] ?? 'openai' ),
				'openai_api_key'      => sanitize_text_field( $p['openai_api_key'] ?? '' ),
				'openai_base_url'     => esc_url_raw( $p['openai_base_url'] ?? 'https://api.openai.com/v1' ),
				'openai_model'        => sanitize_text_field( $p['openai_model'] ?? '' ),
				'groq_api_key'        => sanitize_text_field( $p['groq_api_key'] ?? '' ),
				'groq_base_url'       => esc_url_raw( $p['groq_base_url'] ?? 'https://api.groq.com/openai/v1' ),
				'groq_model'          => sanitize_text_field( $p['groq_model'] ?? '' ),
				'compatible_api_key'  => sanitize_text_field( $p['compatible_api_key'] ?? '' ),
				'compatible_base_url' => esc_url_raw( $p['compatible_base_url'] ?? '' ),
				'compatible_model'    => sanitize_text_field( $p['compatible_model'] ?? '' ),
				'anthropic_api_key'   => sanitize_text_field( $p['anthropic_api_key'] ?? '' ),
				'anthropic_base_url'  => esc_url_raw( $p['anthropic_base_url'] ?? 'https://api.anthropic.com/v1' ),
				'anthropic_model'     => sanitize_text_field( $p['anthropic_model'] ?? '' ),
				'cloudflare_account'  => sanitize_text_field( $p['cloudflare_account'] ?? '' ),
				'cloudflare_token'    => sanitize_text_field( $p['cloudflare_token'] ?? '' ),
				'cloudflare_llm_model'=> sanitize_text_field( $p['cloudflare_llm_model'] ?? '' ),
				'ollama_base_url'     => esc_url_raw( $p['ollama_base_url'] ?? 'http://localhost:11434' ),
				'ollama_model'        => sanitize_text_field( $p['ollama_model'] ?? '' ),
			)
		);

		$e = $form['embeddings'] ?? array();
		Settings::save_group(
			'embeddings',
			array(
				'embedding_provider' => sanitize_key( $e['embedding_provider'] ?? 'openai' ),
				'openai_api_key'     => sanitize_text_field( $e['openai_api_key'] ?? '' ),
				'openai_base_url'    => esc_url_raw( $e['openai_base_url'] ?? 'https://api.openai.com/v1' ),
				'openai_model'       => sanitize_text_field( $e['openai_model'] ?? 'text-embedding-3-small' ),
				'compatible_api_key' => sanitize_text_field( $e['compatible_api_key'] ?? '' ),
				'compatible_base_url'=> esc_url_raw( $e['compatible_base_url'] ?? '' ),
				'compatible_model'   => sanitize_text_field( $e['compatible_model'] ?? '' ),
				'cloudflare_account' => sanitize_text_field( $e['cloudflare_account'] ?? '' ),
				'cloudflare_token'   => sanitize_text_field( $e['cloudflare_token'] ?? '' ),
				'cloudflare_model'   => sanitize_text_field( $e['cloudflare_model'] ?? '@cf/baai/bge-base-en-v1.5' ),
				'ollama_base_url'    => esc_url_raw( $e['ollama_base_url'] ?? 'http://localhost:11434' ),
				'ollama_model'       => sanitize_text_field( $e['ollama_model'] ?? 'nomic-embed-text' ),
				'dimensions'         => (string) max( 0, (int) ( $e['dimensions'] ?? 0 ) ),
			)
		);

		$v = $form['vector_store'] ?? array();
		$prev = Settings::group( 'vector_store' );
		Settings::save_group(
			'vector_store',
			array(
				'engine'              => sanitize_key( $v['engine'] ?? 'auto' ),
				'mysql_native_vector' => $prev['mysql_native_vector'] ?? '', // runtime-detected.
				'cloudflare_account'  => sanitize_text_field( $v['cloudflare_account'] ?? '' ),
				'cloudflare_token'    => sanitize_text_field( $v['cloudflare_token'] ?? '' ),
				'cloudflare_index'    => sanitize_text_field( $v['cloudflare_index'] ?? 'itih-ai-chatbot' ),
			)
		);

		$i = $form['indexing'] ?? array();
		Settings::save_group(
			'indexing',
			array(
				'chunk_size'      => (string) max( 100, (int) ( $i['chunk_size'] ?? 800 ) ),
				'chunk_overlap'   => (string) max( 0, (int) ( $i['chunk_overlap'] ?? 100 ) ),
				'min_chunk_chars' => (string) max( 1, (int) ( $i['min_chunk_chars'] ?? 40 ) ),
				'post_types'      => isset( $i['post_types'] ) ? array_map( 'sanitize_key', (array) $i['post_types'] ) : array( 'post', 'page' ),
				'auto_index'      => empty( $i['auto_index'] ) ? '0' : '1',
			)
		);

		$a = $form['appearance'] ?? array();
		$ap_colors = isset( $a['colors'] ) && is_array( $a['colors'] ) ? $a['colors'] : array();
		$safe_colors = array();
		foreach ( $ap_colors as $ck => $cv ) {
			$safe_colors[ sanitize_key( $ck ) ] = sanitize_hex_color( $cv ) ?: '#3b82f6';
		}
		Settings::save_group(
			'appearance',
			array(
				'theme'  => sanitize_key( $a['theme'] ?? 'light' ),
				'logo'   => esc_url_raw( $a['logo'] ?? '' ),
				'avatar' => esc_url_raw( $a['avatar'] ?? '' ),
				'colors' => $safe_colors,
			)
		);

		$m = $form['mcp'] ?? array();
		Settings::save_group(
			'mcp',
			array(
				'enabled' => empty( $m['enabled'] ) ? '0' : '1',
			)
		);

		Settings::flush_cache();
		add_settings_error( 'openrag', 'saved', __( 'Settings saved.', 'itih-ai-chatbot' ), 'updated' );
	}

	public function render() {
		$tab   = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
		$tabs  = array(
			'general'     => __( 'General', 'itih-ai-chatbot' ),
			'providers'   => __( 'LLM Providers', 'itih-ai-chatbot' ),
			'embeddings'  => __( 'Embeddings', 'itih-ai-chatbot' ),
			'vector_store'=> __( 'Vector Database', 'itih-ai-chatbot' ),
			'indexing'    => __( 'Indexing', 'itih-ai-chatbot' ),
			'chat'        => __( 'Chat', 'itih-ai-chatbot' ),
			'appearance'  => __( 'Appearance', 'itih-ai-chatbot' ),
			'mcp'         => __( 'MCP', 'itih-ai-chatbot' ),
		);
		$base  = admin_url( 'admin.php?page=' . Admin_Menu::SLUG . '-settings' );
		$s     = Settings::all();
		?>
		<div class="wrap openrag-admin-wrap">
			<h1><?php esc_html_e( 'ItihRag AI Chatbot Settings', 'itih-ai-chatbot' ); ?></h1>

			<?php settings_errors( 'openrag' ); ?>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $key, $base ) ); ?>" class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</h2>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE ); ?>
				<input type="hidden" name="openrag_settings[_tab]" value="<?php echo esc_attr( $tab ); ?>" />
				<table class="form-table" role="presentation"><tbody><tr><td>
				<?php
				switch ( $tab ) {
					case 'providers':
						$this->tab_providers( $s );
						break;
					case 'embeddings':
						$this->tab_embeddings( $s );
						break;
					case 'vector_store':
						$this->tab_vector_store( $s );
						break;
					case 'indexing':
						$this->tab_indexing( $s );
						break;
					case 'chat':
						$this->tab_chat( $s );
						break;
					case 'appearance':
						$this->tab_appearance( $s );
						break;
					case 'mcp':
						$this->tab_mcp( $s );
						break;
					case 'general':
					default:
						$this->tab_general( $s );
						break;
				}
				?>
				</td></tr></tbody></table>

				<?php submit_button( __( 'Save Settings', 'itih-ai-chatbot' ) ); ?>
			</form>
		</div>
		<?php
	}

	/* --------------------------------------------------------------------
	 * Tab renderers
	 * ------------------------------------------------------------------ */

	protected function text( $name, $value, $type = 'text', $extra = '' ) {
		return sprintf(
			'<input type="%1$s" name="openrag_settings[%2$s]" value="%3$s" class="regular-text" %4$s />',
			esc_attr( $type ),
			esc_attr( $name ),
			esc_attr( $value ),
			esc_attr( $extra )
		);
	}

	protected function tab_general( $s ) {
		$g = $s['general'];
		?>
		<h2><?php esc_html_e( 'General', 'itih-ai-chatbot' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Enable plugin', 'itih-ai-chatbot' ); ?></th>
				<td><label><input type="checkbox" name="openrag_settings[general][enabled]" value="1" <?php checked( ! empty( $g['enabled'] ) ); ?> /> <?php esc_html_e( 'Enable ItihRag AI Chatbot', 'itih-ai-chatbot' ); ?></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Processing mode', 'itih-ai-chatbot' ); ?></th>
				<td>
					<select name="openrag_settings[general][processing_mode]">
						<option value="background" <?php selected( $g['processing_mode'], 'background' ); ?>><?php esc_html_e( 'Background (Action Scheduler)', 'itih-ai-chatbot' ); ?></option>
						<option value="onrequest" <?php selected( $g['processing_mode'], 'onrequest' ); ?>><?php esc_html_e( 'On request (admin AJAX)', 'itih-ai-chatbot' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Background is recommended for large files. On-request processes immediately in the browser.', 'itih-ai-chatbot' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Debug logging', 'itih-ai-chatbot' ); ?></th>
				<td><label><input type="checkbox" name="openrag_settings[general][debug_logging]" value="1" <?php checked( ! empty( $g['debug_logging'] ) ); ?> /> <?php esc_html_e( 'Log to error_log', 'itih-ai-chatbot' ); ?></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Remove data on uninstall', 'itih-ai-chatbot' ); ?></th>
				<td><label><input type="checkbox" name="openrag_settings[general][wipe_on_uninstall]" value="1" <?php checked( ! empty( $g['wipe_on_uninstall'] ) ); ?> /> <?php esc_html_e( 'Delete all tables and options when the plugin is uninstalled', 'itih-ai-chatbot' ); ?></label></td>
			</tr>
		</table>
		<?php
	}

	protected function tab_providers( $s ) {
		$p = $s['providers'];
		$llm = ( new LLM_Manager() )->providers();
		?>
		<h2><?php esc_html_e( 'LLM Provider', 'itih-ai-chatbot' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Active provider', 'itih-ai-chatbot' ); ?></th>
				<td>
					<select name="openrag_settings[providers][llm_provider]" id="openrag-llm-provider">
						<?php foreach ( $llm as $id => $label ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $p['llm_provider'], $id ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button" id="openrag-fetch-models"><?php esc_html_e( 'Fetch models', 'itih-ai-chatbot' ); ?></button>
					<button type="button" class="button" id="openrag-test-connection"><?php esc_html_e( 'Test connection', 'itih-ai-chatbot' ); ?></button>
				</td>
			</tr>
		</table>

		<div class="openrag-provider-fields">
			<?php
			$this->provider_fieldset( 'openai', __( 'OpenAI', 'itih-ai-chatbot' ), $p, array( 'openai_api_key' => 'API Key', 'openai_base_url' => 'Base URL', 'openai_model' => 'Model' ) );
			$this->provider_fieldset( 'groq', __( 'Groq', 'itih-ai-chatbot' ), $p, array( 'groq_api_key' => 'API Key', 'groq_base_url' => 'Base URL', 'groq_model' => 'Model' ) );
			$this->provider_fieldset( 'openai-compatible', __( 'OpenAI-compatible', 'itih-ai-chatbot' ), $p, array( 'compatible_api_key' => 'API Key (optional)', 'compatible_base_url' => 'Base URL', 'compatible_model' => 'Model' ) );
			$this->provider_fieldset( 'anthropic', __( 'Anthropic Claude', 'itih-ai-chatbot' ), $p, array( 'anthropic_api_key' => 'API Key', 'anthropic_base_url' => 'Base URL', 'anthropic_model' => 'Model' ) );
			$this->provider_fieldset( 'cloudflare', __( 'Cloudflare Workers AI', 'itih-ai-chatbot' ), $p, array( 'cloudflare_account' => 'Account ID', 'cloudflare_token' => 'API Token', 'cloudflare_llm_model' => 'Model' ) );
			$this->provider_fieldset( 'ollama', __( 'Ollama (local)', 'itih-ai-chatbot' ), $p, array( 'ollama_base_url' => 'Base URL', 'ollama_model' => 'Model' ) );
			?>
		</div>
		<?php
	}

	protected function provider_fieldset( $id, $label, $p, $fields ) {
		echo '<fieldset class="openrag-provider-fieldset" data-provider="' . esc_attr( $id ) . '">';
		echo '<h3>' . esc_html( $label ) . '</h3>';
		echo '<table class="form-table">';
		foreach ( $fields as $key => $field_label ) {
			$is_pass = ( false !== stripos( $key, 'api_key' ) || false !== stripos( $key, 'token' ) );
			$val     = $p[ $key ] ?? '';
			echo '<tr><th>' . esc_html( $field_label ) . '</th><td>';
			echo '<input type="' . ( $is_pass ? 'password' : 'text' ) . '" name="openrag_settings[providers][' . esc_attr( $key ) . ']" value="' . esc_attr( $val ) . '" class="regular-text" autocomplete="new-password" />';
			echo '</td></tr>';
		}
		echo '</table>';
		echo '</fieldset>';
	}

	protected function tab_embeddings( $s ) {
		$e = $s['embeddings'];
		$emb = ( new Embedding_Manager() )->providers();
		?>
		<h2><?php esc_html_e( 'Embedding Provider', 'itih-ai-chatbot' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Active provider', 'itih-ai-chatbot' ); ?></th>
				<td>
					<select name="openrag_settings[embeddings][embedding_provider]" id="openrag-emb-provider">
						<?php foreach ( $emb as $id => $class ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $e['embedding_provider'], $id ); ?>><?php echo esc_html( $id ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>

		<div class="openrag-provider-fields">
			<?php
			$this->embedding_fieldset( 'openai', 'OpenAI', $e );
			$this->embedding_fieldset( 'openai-compatible', 'OpenAI-compatible', $e );
			$this->embedding_fieldset( 'cloudflare', 'Cloudflare Workers AI', $e );
			$this->embedding_fieldset( 'ollama', 'Ollama', $e );
			?>
		</div>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Dimensions override', 'itih-ai-chatbot' ); ?></th>
				<td>
					<input type="number" min="0" name="openrag_settings[embeddings][dimensions]" value="<?php echo esc_attr( $e['dimensions'] ); ?>" />
					<p class="description"><?php esc_html_e( '0 = auto-detect from the model. Set explicitly if your provider does not report dimensions.', 'itih-ai-chatbot' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	protected function embedding_fieldset( $id, $label, $e ) {
		echo '<fieldset class="openrag-provider-fieldset" data-provider="' . esc_attr( $id ) . '">';
		echo '<h3>' . esc_html( $label ) . '</h3>';
		echo '<table class="form-table">';
		switch ( $id ) {
			case 'openai':
				$this->emb_row( 'openai_api_key', 'API Key', $e, true );
				$this->emb_row( 'openai_base_url', 'Base URL', $e );
				$this->emb_row( 'openai_model', 'Model', $e );
				break;
			case 'openai-compatible':
				$this->emb_row( 'compatible_api_key', 'API Key (optional)', $e, true );
				$this->emb_row( 'compatible_base_url', 'Base URL', $e );
				$this->emb_row( 'compatible_model', 'Model', $e );
				break;
			case 'cloudflare':
				$this->emb_row( 'cloudflare_account', 'Account ID', $e );
				$this->emb_row( 'cloudflare_token', 'API Token', $e, true );
				$this->emb_row( 'cloudflare_model', 'Model', $e );
				break;
			case 'ollama':
				$this->emb_row( 'ollama_base_url', 'Base URL', $e );
				$this->emb_row( 'ollama_model', 'Model', $e );
				break;
		}
		echo '</table>';
		echo '</fieldset>';
	}

	protected function emb_row( $key, $label, $e, $is_pass = false ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td>';
		echo '<input type="' . ( $is_pass ? 'password' : 'text' ) . '" name="openrag_settings[embeddings][' . esc_attr( $key ) . ']" value="' . esc_attr( $e[ $key ] ?? '' ) . '" class="regular-text" autocomplete="new-password" />';
		echo '</td></tr>';
	}

	protected function tab_vector_store( $s ) {
		$v        = $s['vector_store'];
		$schema   = new Schema();
		$native   = $schema->supports_native_vector();
		$version  = $schema->mysql_version();
		$engines  = ( new Vector_Store_Manager() )->stores();
		?>
		<h2><?php esc_html_e( 'Vector Database', 'itih-ai-chatbot' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'MySQL capability', 'itih-ai-chatbot' ); ?></th>
				<td>
					<?php if ( $native ) : ?>
						<?php /* translators: %s: MySQL version number. */ ?>
						<span class="openrag-badge ok"><?php echo esc_html( sprintf( __( 'MySQL %s — native VECTOR type available', 'itih-ai-chatbot' ), $version ) ); ?></span>
					<?php else : ?>
						<?php /* translators: %s: MySQL version number. */ ?>
						<span class="openrag-badge warn"><?php echo esc_html( sprintf( __( 'MySQL %s — using JSON fallback', 'itih-ai-chatbot' ), $version ) ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Engine', 'itih-ai-chatbot' ); ?></th>
				<td>
					<select name="openrag_settings[vector_store][engine]">
						<?php foreach ( $engines as $id => $label ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $v['engine'], $id ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Cloudflare account ID', 'itih-ai-chatbot' ); ?></th>
				<td><input type="text" name="openrag_settings[vector_store][cloudflare_account]" value="<?php echo esc_attr( $v['cloudflare_account'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Cloudflare API token', 'itih-ai-chatbot' ); ?></th>
				<td><input type="password" name="openrag_settings[vector_store][cloudflare_token]" value="<?php echo esc_attr( $v['cloudflare_token'] ); ?>" class="regular-text" autocomplete="new-password" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Vectorize index name', 'itih-ai-chatbot' ); ?></th>
				<td>
					<input type="text" name="openrag_settings[vector_store][cloudflare_index]" value="<?php echo esc_attr( $v['cloudflare_index'] ); ?>" class="regular-text" />
					<button type="button" class="button" id="openrag-create-index"><?php esc_html_e( 'Create / verify index', 'itih-ai-chatbot' ); ?></button>
					<p class="description"><?php esc_html_e( 'The index is created with the dimension of your active embedding model.', 'itih-ai-chatbot' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	protected function tab_indexing( $s ) {
		$i = $s['indexing'];
		$types = get_post_types( array( 'public' => true ), 'objects' );
		?>
		<h2><?php esc_html_e( 'Indexing', 'itih-ai-chatbot' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Chunk size (chars)', 'itih-ai-chatbot' ); ?></th>
				<td><input type="number" min="100" name="openrag_settings[indexing][chunk_size]" value="<?php echo esc_attr( $i['chunk_size'] ); ?>" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Chunk overlap (chars)', 'itih-ai-chatbot' ); ?></th>
				<td><input type="number" min="0" name="openrag_settings[indexing][chunk_overlap]" value="<?php echo esc_attr( $i['chunk_overlap'] ); ?>" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Minimum chunk length', 'itih-ai-chatbot' ); ?></th>
				<td><input type="number" min="1" name="openrag_settings[indexing][min_chunk_chars]" value="<?php echo esc_attr( $i['min_chunk_chars'] ); ?>" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Post types to index', 'itih-ai-chatbot' ); ?></th>
				<td>
					<?php foreach ( $types as $slug => $obj ) : ?>
						<label><input type="checkbox" name="openrag_settings[indexing][post_types][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $i['post_types'], true ) ); ?> /> <?php echo esc_html( $obj->labels->name ); ?></label><br/>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Auto-index on save', 'itih-ai-chatbot' ); ?></th>
				<td><label><input type="checkbox" name="openrag_settings[indexing][auto_index]" value="1" <?php checked( ! empty( $i['auto_index'] ) ); ?> /> <?php esc_html_e( 'Re-index posts automatically when published/updated', 'itih-ai-chatbot' ); ?></label></td>
			</tr>
		</table>
		<?php
	}

	protected function tab_chat( $s ) {
		$c = $s['chat'];
		?>
		<h2><?php esc_html_e( 'Chat', 'itih-ai-chatbot' ); ?></h2>
		<table class="form-table">
			<tr><th><?php esc_html_e( 'Show widget', 'itih-ai-chatbot' ); ?></th><td><label><input type="checkbox" name="openrag_settings[chat][widget_enabled]" value="1" <?php checked( ! empty( $c['widget_enabled'] ) ); ?> /> <?php esc_html_e( 'Display floating chatbot on the site', 'itih-ai-chatbot' ); ?></label></td></tr>
			<tr><th><?php esc_html_e( 'Bot name', 'itih-ai-chatbot' ); ?></th><td><input type="text" name="openrag_settings[chat][bot_name]" value="<?php echo esc_attr( $c['bot_name'] ); ?>" class="regular-text" /></td></tr>
			<tr><th><?php esc_html_e( 'Welcome message', 'itih-ai-chatbot' ); ?></th><td><textarea name="openrag_settings[chat][welcome_message]" rows="3" class="large-text"><?php echo esc_textarea( $c['welcome_message'] ); ?></textarea></td></tr>
			<tr><th><?php esc_html_e( 'Launcher position', 'itih-ai-chatbot' ); ?></th><td>
				<select name="openrag_settings[chat][launcher_position]">
					<option value="bottom-right" <?php selected( $c['launcher_position'], 'bottom-right' ); ?>><?php esc_html_e( 'Bottom right', 'itih-ai-chatbot' ); ?></option>
					<option value="bottom-left" <?php selected( $c['launcher_position'], 'bottom-left' ); ?>><?php esc_html_e( 'Bottom left', 'itih-ai-chatbot' ); ?></option>
				</select>
			</td></tr>
			<tr><th><?php esc_html_e( 'System prompt', 'itih-ai-chatbot' ); ?></th><td><textarea name="openrag_settings[chat][system_prompt]" rows="6" class="large-text"><?php echo esc_textarea( $c['system_prompt'] ); ?></textarea></td></tr>
			<tr><th><?php esc_html_e( 'Temperature', 'itih-ai-chatbot' ); ?></th><td><input type="number" step="0.1" min="0" max="2" name="openrag_settings[chat][temperature]" value="<?php echo esc_attr( $c['temperature'] ); ?>" /></td></tr>
			<tr><th><?php esc_html_e( 'Max tokens', 'itih-ai-chatbot' ); ?></th><td><input type="number" min="1" name="openrag_settings[chat][max_tokens]" value="<?php echo esc_attr( $c['max_tokens'] ); ?>" /></td></tr>
			<tr><th><?php esc_html_e( 'History turns', 'itih-ai-chatbot' ); ?></th><td><input type="number" min="0" name="openrag_settings[chat][history_turns]" value="<?php echo esc_attr( $c['history_turns'] ); ?>" /></td></tr>
			<tr><th><?php esc_html_e( 'Citations', 'itih-ai-chatbot' ); ?></th><td><label><input type="checkbox" name="openrag_settings[chat][citations]" value="1" <?php checked( ! empty( $c['citations'] ) ); ?> /> <?php esc_html_e( 'Show source citations with answers', 'itih-ai-chatbot' ); ?></label></td></tr>
			<tr><th><?php esc_html_e( 'Credit link', 'itih-ai-chatbot' ); ?></th><td><label><input type="checkbox" name="openrag_settings[chat][show_credit]" value="1" <?php checked( ! empty( $c['show_credit'] ) ); ?> /> <?php esc_html_e( 'Show a "Powered by ItihRAG AI Chatbot" link under the widget', 'itih-ai-chatbot' ); ?></label>
				<p class="description"><?php esc_html_e( 'Off by default. Attribution is only shown when you explicitly enable it.', 'itih-ai-chatbot' ); ?></p>
			</td></tr>
			<tr><th><?php esc_html_e( 'Reasoning', 'itih-ai-chatbot' ); ?></th><td>
				<label><input type="checkbox" name="openrag_settings[chat][reasoning]" value="1" <?php checked( ! empty( $c['reasoning'] ) ); ?> /> <?php esc_html_e( 'Enable reasoning / extended thinking', 'itih-ai-chatbot' ); ?></label>
				<select name="openrag_settings[chat][reasoning_effort]">
					<option value="low" <?php selected( $c['reasoning_effort'], 'low' ); ?>><?php esc_html_e( 'Low', 'itih-ai-chatbot' ); ?></option>
					<option value="medium" <?php selected( $c['reasoning_effort'], 'medium' ); ?>><?php esc_html_e( 'Medium', 'itih-ai-chatbot' ); ?></option>
					<option value="high" <?php selected( $c['reasoning_effort'], 'high' ); ?>><?php esc_html_e( 'High', 'itih-ai-chatbot' ); ?></option>
					<option value="max" <?php selected( $c['reasoning_effort'], 'max' ); ?>><?php esc_html_e( 'Max', 'itih-ai-chatbot' ); ?></option>
				</select>
			</td></tr>
			<tr><th><?php esc_html_e( 'Top K results', 'itih-ai-chatbot' ); ?></th><td><input type="number" min="1" name="openrag_settings[chat][top_k]" value="<?php echo esc_attr( $c['top_k'] ); ?>" /></td></tr>
			<tr><th><?php esc_html_e( 'Minimum similarity', 'itih-ai-chatbot' ); ?></th><td><input type="number" step="0.01" min="0" max="1" name="openrag_settings[chat][min_similarity]" value="<?php echo esc_attr( $c['min_similarity'] ); ?>" /></td></tr>
			<tr><th><?php esc_html_e( 'Rate limit (window / max)', 'itih-ai-chatbot' ); ?></th><td>
				<input type="number" min="1" name="openrag_settings[chat][rate_limit_window]" value="<?php echo esc_attr( $c['rate_limit_window'] ); ?>" style="width:80px" /> <?php esc_html_e( 'seconds', 'itih-ai-chatbot' ); ?>
				/
				<input type="number" min="1" name="openrag_settings[chat][rate_limit_max]" value="<?php echo esc_attr( $c['rate_limit_max'] ); ?>" style="width:80px" /> <?php esc_html_e( 'requests', 'itih-ai-chatbot' ); ?>
			</td></tr>
		</table>
		<?php
	}

	protected function tab_appearance( $s ) {
		$a = $s['appearance'];
		$presets = Settings::theme_presets();
		?>
		<h2><?php esc_html_e( 'Appearance', 'itih-ai-chatbot' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Theme preset', 'itih-ai-chatbot' ); ?></th>
				<td>
					<select name="openrag_settings[appearance][theme]" id="openrag-theme-preset">
						<?php foreach ( $presets as $id => $preset ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $a['theme'], $id ); ?>><?php echo esc_html( $preset['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button" id="openrag-apply-preset"><?php esc_html_e( 'Apply preset', 'itih-ai-chatbot' ); ?></button>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Logo URL', 'itih-ai-chatbot' ); ?></th>
				<td>
					<input type="text" name="openrag_settings[appearance][logo]" value="<?php echo esc_attr( $a['logo'] ); ?>" class="regular-text" id="openrag-logo-url" />
					<button type="button" class="button openrag-media" data-target="openrag-logo-url"><?php esc_html_e( 'Choose', 'itih-ai-chatbot' ); ?></button>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Bot avatar URL', 'itih-ai-chatbot' ); ?></th>
				<td>
					<input type="text" name="openrag_settings[appearance][avatar]" value="<?php echo esc_attr( $a['avatar'] ); ?>" class="regular-text" id="openrag-avatar-url" />
					<button type="button" class="button openrag-media" data-target="openrag-avatar-url"><?php esc_html_e( 'Choose', 'itih-ai-chatbot' ); ?></button>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Custom colors', 'itih-ai-chatbot' ); ?></h3>
		<table class="form-table openrag-colors">
			<?php
			$labels = array(
				'primary'      => __( 'Primary', 'itih-ai-chatbot' ),
				'header_bg'    => __( 'Header background', 'itih-ai-chatbot' ),
				'header_text'  => __( 'Header text', 'itih-ai-chatbot' ),
				'bg'           => __( 'Chat background', 'itih-ai-chatbot' ),
				'text'         => __( 'Body text', 'itih-ai-chatbot' ),
				'user_bubble'  => __( 'User bubble', 'itih-ai-chatbot' ),
				'user_text'    => __( 'User text', 'itih-ai-chatbot' ),
				'bot_bubble'   => __( 'Bot bubble', 'itih-ai-chatbot' ),
				'bot_text'     => __( 'Bot text', 'itih-ai-chatbot' ),
				'launcher'     => __( 'Launcher', 'itih-ai-chatbot' ),
				'launcher_icon'=> __( 'Launcher icon', 'itih-ai-chatbot' ),
			);
			foreach ( $labels as $key => $label ) {
				$value = $a['colors'][ $key ] ?? '#3b82f6';
				echo '<tr><th>' . esc_html( $label ) . '</th><td>';
				echo '<input type="text" class="openrag-color-picker" name="openrag_settings[appearance][colors][' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" data-key="' . esc_attr( $key ) . '" />';
				echo '</td></tr>';
			}
			?>
			</table>
			<?php
			// Theme presets are provided to admin.js via wp_localize_script( 'itih-admin' )
			// as ItihRagAdmin.themePresets — no inline <script> needed.
		}

	protected function tab_mcp( $s ) {
		$m = $s['mcp'];
		global $wpdb;
		$schema = new Schema();
		$servers = $wpdb->get_results( 'SELECT * FROM `' . $schema->table( 'mcp_servers' ) . '` ORDER BY id ASC' ); // phpcs:ignore WordPress.DB, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
		?>
		<h2><?php esc_html_e( 'MCP (Model Context Protocol)', 'itih-ai-chatbot' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Connect the chatbot to external MCP servers. Their tools become available to the LLM via function calling.', 'itih-ai-chatbot' ); ?></p>
		<table class="form-table">
			<tr><th><?php esc_html_e( 'Enable MCP', 'itih-ai-chatbot' ); ?></th><td><label><input type="checkbox" name="openrag_settings[mcp][enabled]" value="1" <?php checked( ! empty( $m['enabled'] ) ); ?> /> <?php esc_html_e( 'Expose MCP tools to the chatbot', 'itih-ai-chatbot' ); ?></label></td></tr>
		</table>

		<h3><?php esc_html_e( 'Servers', 'itih-ai-chatbot' ); ?></h3>
		<table class="widefat striped" id="openrag-mcp-table">
			<thead>
			<tr><th><?php esc_html_e( 'Name', 'itih-ai-chatbot' ); ?></th><th><?php esc_html_e( 'URL', 'itih-ai-chatbot' ); ?></th><th><?php esc_html_e( 'Transport', 'itih-ai-chatbot' ); ?></th><th><?php esc_html_e( 'Enabled', 'itih-ai-chatbot' ); ?></th><th><?php esc_html_e( 'Tools', 'itih-ai-chatbot' ); ?></th><th><?php esc_html_e( 'Actions', 'itih-ai-chatbot' ); ?></th></tr>
			</thead>
			<tbody>
			<?php if ( empty( $servers ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No MCP servers configured.', 'itih-ai-chatbot' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $servers as $srv ) : ?>
					<?php $tools_count = ! empty( $srv->tools_cache ) ? count( json_decode( $srv->tools_cache, true ) ?: array() ) : 0; ?>
					<tr data-id="<?php echo esc_attr( $srv->id ); ?>">
						<td><?php echo esc_html( $srv->name ); ?></td>
						<td><code><?php echo esc_html( $srv->url ); ?></code></td>
						<td><?php echo esc_html( $srv->transport ); ?></td>
						<td><?php echo $srv->enabled ? '✓' : '—'; ?></td>
						<td><?php echo esc_html( $tools_count ); ?></td>
						<td>
							<button class="button button-small openrag-mcp-discover" data-id="<?php echo esc_attr( $srv->id ); ?>"><?php esc_html_e( 'Discover', 'itih-ai-chatbot' ); ?></button>
							<button class="button button-small openrag-mcp-toggle" data-id="<?php echo esc_attr( $srv->id ); ?>"><?php echo $srv->enabled ? esc_html__( 'Disable', 'itih-ai-chatbot' ) : esc_html__( 'Enable', 'itih-ai-chatbot' ); ?></button>
							<button class="button button-small button-link-delete openrag-mcp-delete" data-id="<?php echo esc_attr( $srv->id ); ?>"><?php esc_html_e( 'Delete', 'itih-ai-chatbot' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Add server', 'itih-ai-chatbot' ); ?></h3>
		<table class="form-table">
			<tr><th><?php esc_html_e( 'Name', 'itih-ai-chatbot' ); ?></th><td><input type="text" id="openrag-mcp-name" class="regular-text" /></td></tr>
			<tr><th><?php esc_html_e( 'URL', 'itih-ai-chatbot' ); ?></th><td><input type="url" id="openrag-mcp-url" class="regular-text" placeholder="https://example.com/mcp" /></td></tr>
			<tr><th><?php esc_html_e( 'Transport', 'itih-ai-chatbot' ); ?></th><td>
				<select id="openrag-mcp-transport">
					<option value="http"><?php esc_html_e( 'Streamable HTTP', 'itih-ai-chatbot' ); ?></option>
					<option value="sse"><?php esc_html_e( 'SSE', 'itih-ai-chatbot' ); ?></option>
				</select>
			</td></tr>
			<tr><th><?php esc_html_e( 'Authorization header', 'itih-ai-chatbot' ); ?></th><td><input type="text" id="openrag-mcp-auth" class="regular-text" placeholder="Bearer ..." /></td></tr>
			<tr><th></th><td><button type="button" class="button button-primary" id="openrag-mcp-add"><?php esc_html_e( 'Add server', 'itih-ai-chatbot' ); ?></button></td></tr>
		</table>
		<?php
	}
}
