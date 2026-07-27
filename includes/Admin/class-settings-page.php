<?php
/**
 * Settings page — tabbed, all configuration groups.
 *
 * @package WPOpenRag\Admin
 */

namespace WPOpenRag\Admin;

use WPOpenRag\Database\Schema;
use WPOpenRag\LLM\LLM_Manager;
use WPOpenRag\Embeddings\Embedding_Manager;
use WPOpenRag\Settings;
use WPOpenRag\VectorStores\Vector_Store_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings_Page {

	const NONCE = 'wporag_save_settings';

	/**
	 * Handle a settings save POST.
	 *
	 * @return void
	 */
	public function maybe_save() {
		if ( ! isset( $_POST['wporag_settings'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( self::NONCE );

		$form = wp_unslash( $_POST['wporag_settings'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

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
				'cloudflare_index'    => sanitize_text_field( $v['cloudflare_index'] ?? 'wp-openrag' ),
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
		add_settings_error( 'wporag', 'saved', __( 'Settings saved.', 'wp-openrag' ), 'updated' );
	}

	public function render() {
		$tab   = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
		$tabs  = array(
			'general'     => __( 'General', 'wp-openrag' ),
			'providers'   => __( 'LLM Providers', 'wp-openrag' ),
			'embeddings'  => __( 'Embeddings', 'wp-openrag' ),
			'vector_store'=> __( 'Vector Database', 'wp-openrag' ),
			'indexing'    => __( 'Indexing', 'wp-openrag' ),
			'chat'        => __( 'Chat', 'wp-openrag' ),
			'appearance'  => __( 'Appearance', 'wp-openrag' ),
			'mcp'         => __( 'MCP', 'wp-openrag' ),
		);
		$base  = admin_url( 'admin.php?page=' . Admin_Menu::SLUG . '-settings' );
		$s     = Settings::all();
		?>
		<div class="wrap wporag-admin-wrap">
			<h1><?php esc_html_e( 'WP OpenRag Settings', 'wp-openrag' ); ?></h1>

			<?php settings_errors( 'wporag' ); ?>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $key, $base ) ); ?>" class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</h2>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE ); ?>
				<input type="hidden" name="wporag_settings[_tab]" value="<?php echo esc_attr( $tab ); ?>" />
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

				<?php submit_button( __( 'Save Settings', 'wp-openrag' ) ); ?>
			</form>
		</div>
		<?php
	}

	/* --------------------------------------------------------------------
	 * Tab renderers
	 * ------------------------------------------------------------------ */

	protected function text( $name, $value, $type = 'text', $extra = '' ) {
		return sprintf(
			'<input type="%1$s" name="wporag_settings[%2$s]" value="%3$s" class="regular-text" %4$s />',
			esc_attr( $type ),
			esc_attr( $name ),
			esc_attr( $value ),
			esc_attr( $extra )
		);
	}

	protected function tab_general( $s ) {
		$g = $s['general'];
		?>
		<h2><?php esc_html_e( 'General', 'wp-openrag' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Enable plugin', 'wp-openrag' ); ?></th>
				<td><label><input type="checkbox" name="wporag_settings[general][enabled]" value="1" <?php checked( ! empty( $g['enabled'] ) ); ?> /> <?php esc_html_e( 'Enable WP OpenRag', 'wp-openrag' ); ?></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Processing mode', 'wp-openrag' ); ?></th>
				<td>
					<select name="wporag_settings[general][processing_mode]">
						<option value="background" <?php selected( $g['processing_mode'], 'background' ); ?>><?php esc_html_e( 'Background (Action Scheduler)', 'wp-openrag' ); ?></option>
						<option value="onrequest" <?php selected( $g['processing_mode'], 'onrequest' ); ?>><?php esc_html_e( 'On request (admin AJAX)', 'wp-openrag' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Background is recommended for large files. On-request processes immediately in the browser.', 'wp-openrag' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Debug logging', 'wp-openrag' ); ?></th>
				<td><label><input type="checkbox" name="wporag_settings[general][debug_logging]" value="1" <?php checked( ! empty( $g['debug_logging'] ) ); ?> /> <?php esc_html_e( 'Log to error_log', 'wp-openrag' ); ?></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Remove data on uninstall', 'wp-openrag' ); ?></th>
				<td><label><input type="checkbox" name="wporag_settings[general][wipe_on_uninstall]" value="1" <?php checked( ! empty( $g['wipe_on_uninstall'] ) ); ?> /> <?php esc_html_e( 'Delete all tables and options when the plugin is uninstalled', 'wp-openrag' ); ?></label></td>
			</tr>
		</table>
		<?php
	}

	protected function tab_providers( $s ) {
		$p = $s['providers'];
		$llm = ( new LLM_Manager() )->providers();
		?>
		<h2><?php esc_html_e( 'LLM Provider', 'wp-openrag' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Active provider', 'wp-openrag' ); ?></th>
				<td>
					<select name="wporag_settings[providers][llm_provider]" id="wporag-llm-provider">
						<?php foreach ( $llm as $id => $label ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $p['llm_provider'], $id ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button" id="wporag-fetch-models"><?php esc_html_e( 'Fetch models', 'wp-openrag' ); ?></button>
					<button type="button" class="button" id="wporag-test-connection"><?php esc_html_e( 'Test connection', 'wp-openrag' ); ?></button>
				</td>
			</tr>
		</table>

		<div class="wporag-provider-fields">
			<?php
			$this->provider_fieldset( 'openai', __( 'OpenAI', 'wp-openrag' ), $p, array( 'openai_api_key' => 'API Key', 'openai_base_url' => 'Base URL', 'openai_model' => 'Model' ) );
			$this->provider_fieldset( 'groq', __( 'Groq', 'wp-openrag' ), $p, array( 'groq_api_key' => 'API Key', 'groq_base_url' => 'Base URL', 'groq_model' => 'Model' ) );
			$this->provider_fieldset( 'openai-compatible', __( 'OpenAI-compatible', 'wp-openrag' ), $p, array( 'compatible_api_key' => 'API Key (optional)', 'compatible_base_url' => 'Base URL', 'compatible_model' => 'Model' ) );
			$this->provider_fieldset( 'anthropic', __( 'Anthropic Claude', 'wp-openrag' ), $p, array( 'anthropic_api_key' => 'API Key', 'anthropic_base_url' => 'Base URL', 'anthropic_model' => 'Model' ) );
			$this->provider_fieldset( 'cloudflare', __( 'Cloudflare Workers AI', 'wp-openrag' ), $p, array( 'cloudflare_account' => 'Account ID', 'cloudflare_token' => 'API Token', 'cloudflare_llm_model' => 'Model' ) );
			$this->provider_fieldset( 'ollama', __( 'Ollama (local)', 'wp-openrag' ), $p, array( 'ollama_base_url' => 'Base URL', 'ollama_model' => 'Model' ) );
			?>
		</div>
		<?php
	}

	protected function provider_fieldset( $id, $label, $p, $fields ) {
		echo '<fieldset class="wporag-provider-fieldset" data-provider="' . esc_attr( $id ) . '">';
		echo '<h3>' . esc_html( $label ) . '</h3>';
		echo '<table class="form-table">';
		foreach ( $fields as $key => $field_label ) {
			$is_pass = ( false !== stripos( $key, 'api_key' ) || false !== stripos( $key, 'token' ) );
			$val     = $p[ $key ] ?? '';
			echo '<tr><th>' . esc_html( $field_label ) . '</th><td>';
			echo '<input type="' . ( $is_pass ? 'password' : 'text' ) . '" name="wporag_settings[providers][' . esc_attr( $key ) . ']" value="' . esc_attr( $val ) . '" class="regular-text" autocomplete="new-password" />';
			echo '</td></tr>';
		}
		echo '</table>';
		echo '</fieldset>';
	}

	protected function tab_embeddings( $s ) {
		$e = $s['embeddings'];
		$emb = ( new Embedding_Manager() )->providers();
		?>
		<h2><?php esc_html_e( 'Embedding Provider', 'wp-openrag' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Active provider', 'wp-openrag' ); ?></th>
				<td>
					<select name="wporag_settings[embeddings][embedding_provider]" id="wporag-emb-provider">
						<?php foreach ( $emb as $id => $class ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $e['embedding_provider'], $id ); ?>><?php echo esc_html( $id ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>

		<div class="wporag-provider-fields">
			<?php
			$this->embedding_fieldset( 'openai', 'OpenAI', $e );
			$this->embedding_fieldset( 'openai-compatible', 'OpenAI-compatible', $e );
			$this->embedding_fieldset( 'cloudflare', 'Cloudflare Workers AI', $e );
			$this->embedding_fieldset( 'ollama', 'Ollama', $e );
			?>
		</div>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Dimensions override', 'wp-openrag' ); ?></th>
				<td>
					<input type="number" min="0" name="wporag_settings[embeddings][dimensions]" value="<?php echo esc_attr( $e['dimensions'] ); ?>" />
					<p class="description"><?php esc_html_e( '0 = auto-detect from the model. Set explicitly if your provider does not report dimensions.', 'wp-openrag' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	protected function embedding_fieldset( $id, $label, $e ) {
		echo '<fieldset class="wporag-provider-fieldset" data-provider="' . esc_attr( $id ) . '">';
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
		echo '<input type="' . ( $is_pass ? 'password' : 'text' ) . '" name="wporag_settings[embeddings][' . esc_attr( $key ) . ']" value="' . esc_attr( $e[ $key ] ?? '' ) . '" class="regular-text" autocomplete="new-password" />';
		echo '</td></tr>';
	}

	protected function tab_vector_store( $s ) {
		$v        = $s['vector_store'];
		$schema   = new Schema();
		$native   = $schema->supports_native_vector();
		$version  = $schema->mysql_version();
		$engines  = ( new Vector_Store_Manager() )->stores();
		?>
		<h2><?php esc_html_e( 'Vector Database', 'wp-openrag' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'MySQL capability', 'wp-openrag' ); ?></th>
				<td>
					<?php if ( $native ) : ?>
						<span class="wporag-badge ok"><?php echo esc_html( sprintf( __( 'MySQL %s — native VECTOR type available', 'wp-openrag' ), $version ) ); ?></span>
					<?php else : ?>
						<span class="wporag-badge warn"><?php echo esc_html( sprintf( __( 'MySQL %s — using JSON fallback', 'wp-openrag' ), $version ) ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Engine', 'wp-openrag' ); ?></th>
				<td>
					<select name="wporag_settings[vector_store][engine]">
						<?php foreach ( $engines as $id => $label ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $v['engine'], $id ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Cloudflare account ID', 'wp-openrag' ); ?></th>
				<td><input type="text" name="wporag_settings[vector_store][cloudflare_account]" value="<?php echo esc_attr( $v['cloudflare_account'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Cloudflare API token', 'wp-openrag' ); ?></th>
				<td><input type="password" name="wporag_settings[vector_store][cloudflare_token]" value="<?php echo esc_attr( $v['cloudflare_token'] ); ?>" class="regular-text" autocomplete="new-password" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Vectorize index name', 'wp-openrag' ); ?></th>
				<td>
					<input type="text" name="wporag_settings[vector_store][cloudflare_index]" value="<?php echo esc_attr( $v['cloudflare_index'] ); ?>" class="regular-text" />
					<button type="button" class="button" id="wporag-create-index"><?php esc_html_e( 'Create / verify index', 'wp-openrag' ); ?></button>
					<p class="description"><?php esc_html_e( 'The index is created with the dimension of your active embedding model.', 'wp-openrag' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	protected function tab_indexing( $s ) {
		$i = $s['indexing'];
		$types = get_post_types( array( 'public' => true ), 'objects' );
		?>
		<h2><?php esc_html_e( 'Indexing', 'wp-openrag' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Chunk size (chars)', 'wp-openrag' ); ?></th>
				<td><input type="number" min="100" name="wporag_settings[indexing][chunk_size]" value="<?php echo esc_attr( $i['chunk_size'] ); ?>" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Chunk overlap (chars)', 'wp-openrag' ); ?></th>
				<td><input type="number" min="0" name="wporag_settings[indexing][chunk_overlap]" value="<?php echo esc_attr( $i['chunk_overlap'] ); ?>" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Minimum chunk length', 'wp-openrag' ); ?></th>
				<td><input type="number" min="1" name="wporag_settings[indexing][min_chunk_chars]" value="<?php echo esc_attr( $i['min_chunk_chars'] ); ?>" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Post types to index', 'wp-openrag' ); ?></th>
				<td>
					<?php foreach ( $types as $slug => $obj ) : ?>
						<label><input type="checkbox" name="wporag_settings[indexing][post_types][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $i['post_types'], true ) ); ?> /> <?php echo esc_html( $obj->labels->name ); ?></label><br/>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Auto-index on save', 'wp-openrag' ); ?></th>
				<td><label><input type="checkbox" name="wporag_settings[indexing][auto_index]" value="1" <?php checked( ! empty( $i['auto_index'] ) ); ?> /> <?php esc_html_e( 'Re-index posts automatically when published/updated', 'wp-openrag' ); ?></label></td>
			</tr>
		</table>
		<?php
	}

	protected function tab_chat( $s ) {
		$c = $s['chat'];
		?>
		<h2><?php esc_html_e( 'Chat', 'wp-openrag' ); ?></h2>
		<table class="form-table">
			<tr><th><?php esc_html_e( 'Show widget', 'wp-openrag' ); ?></th><td><label><input type="checkbox" name="wporag_settings[chat][widget_enabled]" value="1" <?php checked( ! empty( $c['widget_enabled'] ) ); ?> /> <?php esc_html_e( 'Display floating chatbot on the site', 'wp-openrag' ); ?></label></td></tr>
			<tr><th><?php esc_html_e( 'Bot name', 'wp-openrag' ); ?></th><td><input type="text" name="wporag_settings[chat][bot_name]" value="<?php echo esc_attr( $c['bot_name'] ); ?>" class="regular-text" /></td></tr>
			<tr><th><?php esc_html_e( 'Welcome message', 'wp-openrag' ); ?></th><td><textarea name="wporag_settings[chat][welcome_message]" rows="3" class="large-text"><?php echo esc_textarea( $c['welcome_message'] ); ?></textarea></td></tr>
			<tr><th><?php esc_html_e( 'Launcher position', 'wp-openrag' ); ?></th><td>
				<select name="wporag_settings[chat][launcher_position]">
					<option value="bottom-right" <?php selected( $c['launcher_position'], 'bottom-right' ); ?>><?php esc_html_e( 'Bottom right', 'wp-openrag' ); ?></option>
					<option value="bottom-left" <?php selected( $c['launcher_position'], 'bottom-left' ); ?>><?php esc_html_e( 'Bottom left', 'wp-openrag' ); ?></option>
				</select>
			</td></tr>
			<tr><th><?php esc_html_e( 'System prompt', 'wp-openrag' ); ?></th><td><textarea name="wporag_settings[chat][system_prompt]" rows="6" class="large-text"><?php echo esc_textarea( $c['system_prompt'] ); ?></textarea></td></tr>
			<tr><th><?php esc_html_e( 'Temperature', 'wp-openrag' ); ?></th><td><input type="number" step="0.1" min="0" max="2" name="wporag_settings[chat][temperature]" value="<?php echo esc_attr( $c['temperature'] ); ?>" /></td></tr>
			<tr><th><?php esc_html_e( 'Max tokens', 'wp-openrag' ); ?></th><td><input type="number" min="1" name="wporag_settings[chat][max_tokens]" value="<?php echo esc_attr( $c['max_tokens'] ); ?>" /></td></tr>
			<tr><th><?php esc_html_e( 'History turns', 'wp-openrag' ); ?></th><td><input type="number" min="0" name="wporag_settings[chat][history_turns]" value="<?php echo esc_attr( $c['history_turns'] ); ?>" /></td></tr>
			<tr><th><?php esc_html_e( 'Citations', 'wp-openrag' ); ?></th><td><label><input type="checkbox" name="wporag_settings[chat][citations]" value="1" <?php checked( ! empty( $c['citations'] ) ); ?> /> <?php esc_html_e( 'Show source citations with answers', 'wp-openrag' ); ?></label></td></tr>
			<tr><th><?php esc_html_e( 'Reasoning', 'wp-openrag' ); ?></th><td>
				<label><input type="checkbox" name="wporag_settings[chat][reasoning]" value="1" <?php checked( ! empty( $c['reasoning'] ) ); ?> /> <?php esc_html_e( 'Enable reasoning / extended thinking', 'wp-openrag' ); ?></label>
				<select name="wporag_settings[chat][reasoning_effort]">
					<option value="low" <?php selected( $c['reasoning_effort'], 'low' ); ?>><?php esc_html_e( 'Low', 'wp-openrag' ); ?></option>
					<option value="medium" <?php selected( $c['reasoning_effort'], 'medium' ); ?>><?php esc_html_e( 'Medium', 'wp-openrag' ); ?></option>
					<option value="high" <?php selected( $c['reasoning_effort'], 'high' ); ?>><?php esc_html_e( 'High', 'wp-openrag' ); ?></option>
					<option value="max" <?php selected( $c['reasoning_effort'], 'max' ); ?>><?php esc_html_e( 'Max', 'wp-openrag' ); ?></option>
				</select>
			</td></tr>
			<tr><th><?php esc_html_e( 'Top K results', 'wp-openrag' ); ?></th><td><input type="number" min="1" name="wporag_settings[chat][top_k]" value="<?php echo esc_attr( $c['top_k'] ); ?>" /></td></tr>
			<tr><th><?php esc_html_e( 'Minimum similarity', 'wp-openrag' ); ?></th><td><input type="number" step="0.01" min="0" max="1" name="wporag_settings[chat][min_similarity]" value="<?php echo esc_attr( $c['min_similarity'] ); ?>" /></td></tr>
			<tr><th><?php esc_html_e( 'Rate limit (window / max)', 'wp-openrag' ); ?></th><td>
				<input type="number" min="1" name="wporag_settings[chat][rate_limit_window]" value="<?php echo esc_attr( $c['rate_limit_window'] ); ?>" style="width:80px" /> <?php esc_html_e( 'seconds', 'wp-openrag' ); ?>
				/
				<input type="number" min="1" name="wporag_settings[chat][rate_limit_max]" value="<?php echo esc_attr( $c['rate_limit_max'] ); ?>" style="width:80px" /> <?php esc_html_e( 'requests', 'wp-openrag' ); ?>
			</td></tr>
		</table>
		<?php
	}

	protected function tab_appearance( $s ) {
		$a = $s['appearance'];
		$presets = Settings::theme_presets();
		?>
		<h2><?php esc_html_e( 'Appearance', 'wp-openrag' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Theme preset', 'wp-openrag' ); ?></th>
				<td>
					<select name="wporag_settings[appearance][theme]" id="wporag-theme-preset">
						<?php foreach ( $presets as $id => $preset ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $a['theme'], $id ); ?>><?php echo esc_html( $preset['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button" id="wporag-apply-preset"><?php esc_html_e( 'Apply preset', 'wp-openrag' ); ?></button>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Logo URL', 'wp-openrag' ); ?></th>
				<td>
					<input type="text" name="wporag_settings[appearance][logo]" value="<?php echo esc_attr( $a['logo'] ); ?>" class="regular-text" id="wporag-logo-url" />
					<button type="button" class="button wporag-media" data-target="wporag-logo-url"><?php esc_html_e( 'Choose', 'wp-openrag' ); ?></button>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Bot avatar URL', 'wp-openrag' ); ?></th>
				<td>
					<input type="text" name="wporag_settings[appearance][avatar]" value="<?php echo esc_attr( $a['avatar'] ); ?>" class="regular-text" id="wporag-avatar-url" />
					<button type="button" class="button wporag-media" data-target="wporag-avatar-url"><?php esc_html_e( 'Choose', 'wp-openrag' ); ?></button>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Custom colors', 'wp-openrag' ); ?></h3>
		<table class="form-table wporag-colors">
			<?php
			$labels = array(
				'primary'      => __( 'Primary', 'wp-openrag' ),
				'header_bg'    => __( 'Header background', 'wp-openrag' ),
				'header_text'  => __( 'Header text', 'wp-openrag' ),
				'bg'           => __( 'Chat background', 'wp-openrag' ),
				'text'         => __( 'Body text', 'wp-openrag' ),
				'user_bubble'  => __( 'User bubble', 'wp-openrag' ),
				'user_text'    => __( 'User text', 'wp-openrag' ),
				'bot_bubble'   => __( 'Bot bubble', 'wp-openrag' ),
				'bot_text'     => __( 'Bot text', 'wp-openrag' ),
				'launcher'     => __( 'Launcher', 'wp-openrag' ),
				'launcher_icon'=> __( 'Launcher icon', 'wp-openrag' ),
			);
			foreach ( $labels as $key => $label ) {
				$value = $a['colors'][ $key ] ?? '#3b82f6';
				echo '<tr><th>' . esc_html( $label ) . '</th><td>';
				echo '<input type="text" class="wporag-color-picker" name="wporag_settings[appearance][colors][' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" data-key="' . esc_attr( $key ) . '" />';
				echo '</td></tr>';
			}
			?>
		</table>

		<script>
			window.wporagThemePresets = <?php echo wp_json_encode( $presets ); ?>;
		</script>
		<?php
	}

	protected function tab_mcp( $s ) {
		$m = $s['mcp'];
		global $wpdb;
		$schema = new Schema();
		$servers = $wpdb->get_results( 'SELECT * FROM `' . $schema->table( 'mcp_servers' ) . '` ORDER BY id ASC' ); // phpcs:ignore WordPress.DB
		?>
		<h2><?php esc_html_e( 'MCP (Model Context Protocol)', 'wp-openrag' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Connect the chatbot to external MCP servers. Their tools become available to the LLM via function calling.', 'wp-openrag' ); ?></p>
		<table class="form-table">
			<tr><th><?php esc_html_e( 'Enable MCP', 'wp-openrag' ); ?></th><td><label><input type="checkbox" name="wporag_settings[mcp][enabled]" value="1" <?php checked( ! empty( $m['enabled'] ) ); ?> /> <?php esc_html_e( 'Expose MCP tools to the chatbot', 'wp-openrag' ); ?></label></td></tr>
		</table>

		<h3><?php esc_html_e( 'Servers', 'wp-openrag' ); ?></h3>
		<table class="widefat striped" id="wporag-mcp-table">
			<thead>
			<tr><th><?php esc_html_e( 'Name', 'wp-openrag' ); ?></th><th><?php esc_html_e( 'URL', 'wp-openrag' ); ?></th><th><?php esc_html_e( 'Transport', 'wp-openrag' ); ?></th><th><?php esc_html_e( 'Enabled', 'wp-openrag' ); ?></th><th><?php esc_html_e( 'Tools', 'wp-openrag' ); ?></th><th><?php esc_html_e( 'Actions', 'wp-openrag' ); ?></th></tr>
			</thead>
			<tbody>
			<?php if ( empty( $servers ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No MCP servers configured.', 'wp-openrag' ); ?></td></tr>
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
							<button class="button button-small wporag-mcp-discover" data-id="<?php echo esc_attr( $srv->id ); ?>"><?php esc_html_e( 'Discover', 'wp-openrag' ); ?></button>
							<button class="button button-small wporag-mcp-toggle" data-id="<?php echo esc_attr( $srv->id ); ?>"><?php echo $srv->enabled ? esc_html__( 'Disable', 'wp-openrag' ) : esc_html__( 'Enable', 'wp-openrag' ); ?></button>
							<button class="button button-small button-link-delete wporag-mcp-delete" data-id="<?php echo esc_attr( $srv->id ); ?>"><?php esc_html_e( 'Delete', 'wp-openrag' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Add server', 'wp-openrag' ); ?></h3>
		<table class="form-table">
			<tr><th><?php esc_html_e( 'Name', 'wp-openrag' ); ?></th><td><input type="text" id="wporag-mcp-name" class="regular-text" /></td></tr>
			<tr><th><?php esc_html_e( 'URL', 'wp-openrag' ); ?></th><td><input type="url" id="wporag-mcp-url" class="regular-text" placeholder="https://example.com/mcp" /></td></tr>
			<tr><th><?php esc_html_e( 'Transport', 'wp-openrag' ); ?></th><td>
				<select id="wporag-mcp-transport">
					<option value="http"><?php esc_html_e( 'Streamable HTTP', 'wp-openrag' ); ?></option>
					<option value="sse"><?php esc_html_e( 'SSE', 'wp-openrag' ); ?></option>
				</select>
			</td></tr>
			<tr><th><?php esc_html_e( 'Authorization header', 'wp-openrag' ); ?></th><td><input type="text" id="wporag-mcp-auth" class="regular-text" placeholder="Bearer ..." /></td></tr>
			<tr><th></th><td><button type="button" class="button button-primary" id="wporag-mcp-add"><?php esc_html_e( 'Add server', 'wp-openrag' ); ?></button></td></tr>
		</table>
		<?php
	}
}
