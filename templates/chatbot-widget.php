<?php
/**
 * Chatbot widget markup.
 *
 * All classes are prefixed openrag- and contained within #openrag-widget (or
 * .openrag-inline for shortcode) to prevent CSS leakage from/into the host site.
 *
 * @package OpenRag
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$openrag_cfg       = $GLOBALS['OpenRagConfig'] ?? array(); // populated by Plugin::render_widget via wp_localize_script.
$openrag_bot_name  = isset( $openrag_cfg['botName'] ) ? esc_html( $openrag_cfg['botName'] ) : esc_html__( 'Assistant', 'openrag-ai-chatbot' );
$openrag_welcome   = isset( $openrag_cfg['welcome'] ) ? esc_html( $openrag_cfg['welcome'] ) : '';
$openrag_logo      = isset( $openrag_cfg['logo'] ) ? esc_url( $openrag_cfg['logo'] ) : '';
$openrag_avatar    = isset( $openrag_cfg['avatar'] ) ? esc_url( $openrag_cfg['avatar'] ) : '';
$openrag_position  = isset( $openrag_cfg['position'] ) ? sanitize_html_class( $openrag_cfg['position'] ) : 'bottom-right';
$openrag_is_inline = ! empty( $openrag_cfg['inline'] );

$openrag_wrapper_class = $openrag_is_inline ? 'openrag-inline openrag-inline-' . $openrag_position : 'openrag-floating openrag-pos-' . $openrag_position;
$openrag_root_attr     = $openrag_is_inline ? '' : 'data-position="' . esc_attr( $openrag_position ) . '"';
?>

<div id="openrag-widget" class="<?php echo esc_attr( $openrag_wrapper_class ); ?>" <?php echo $openrag_root_attr; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php if ( ! $openrag_is_inline ) : ?>
	<button type="button" class="openrag-launcher" aria-label="<?php esc_attr_e( 'Open chat', 'openrag-ai-chatbot' ); ?>" aria-expanded="false">
		<svg class="openrag-launcher-icon openrag-icon-open" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
		</svg>
		<svg class="openrag-launcher-icon openrag-icon-close" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
			<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
		</svg>
	</button>
	<?php endif; ?>

	<div class="openrag-window" role="dialog" aria-modal="false" aria-label="<?php esc_attr_e( 'Chat window', 'openrag-ai-chatbot' ); ?>" <?php echo $openrag_is_inline ? '' : 'hidden'; ?>>
		<header class="openrag-header">
			<div class="openrag-header-info">
				<?php if ( $openrag_avatar || $openrag_logo ) : ?>
					<img class="openrag-header-avatar" src="<?php echo $openrag_avatar ?: $openrag_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" alt="" width="36" height="36" />
				<?php else : ?>
					<div class="openrag-header-avatar openrag-avatar-fallback" aria-hidden="true">
						<span><?php echo esc_html( mb_substr( $openrag_bot_name, 0, 1 ) ); ?></span>
					</div>
				<?php endif; ?>
				<div>
					<div class="openrag-header-title"><?php echo $openrag_bot_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<div class="openrag-header-status"><span class="openrag-dot"></span><?php esc_html_e( 'Online', 'openrag-ai-chatbot' ); ?></div>
				</div>
			</div>
			<button type="button" class="openrag-clear" aria-label="<?php esc_attr_e( 'Clear chat', 'openrag-ai-chatbot' ); ?>" title="<?php esc_attr_e( 'Clear chat', 'openrag-ai-chatbot' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
				</svg>
			</button>
		</header>

		<div class="openrag-messages" id="openrag-messages" aria-live="polite" aria-atomic="false">
			<?php if ( $openrag_welcome ) : ?>
				<div class="openrag-msg openrag-msg-bot">
					<div class="openrag-bubble openrag-bubble-bot"><?php echo $openrag_welcome; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				</div>
			<?php endif; ?>
		</div>

		<div class="openrag-composer">
			<form class="openrag-form" autocomplete="off">
				<textarea
					class="openrag-input"
					id="openrag-input"
					rows="1"
					placeholder="<?php echo isset( $openrag_cfg['placeholder'] ) ? esc_attr( $openrag_cfg['placeholder'] ) : esc_attr__( 'Type your message…', 'openrag-ai-chatbot' ); ?>"
					aria-label="<?php esc_attr_e( 'Message', 'openrag-ai-chatbot' ); ?>"
				></textarea>
				<button type="submit" class="openrag-send" aria-label="<?php esc_attr_e( 'Send', 'openrag-ai-chatbot' ); ?>">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
					</svg>
				</button>
			</form>
			<div class="openrag-credit"><a href="#" rel="nofollow noopener"><?php echo isset( $openrag_cfg['i18n']['poweredBy'] ) ? esc_html( $openrag_cfg['i18n']['poweredBy'] ) : ''; ?></a></div>
		</div>
	</div>
</div>
