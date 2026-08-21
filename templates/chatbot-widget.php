<?php
/**
 * Chatbot widget markup.
 *
 * All classes are prefixed openrag- and contained within #openrag-widget (or
 * .openrag-inline for shortcode) to prevent CSS leakage from/into the host site.
 *
 * @package ItihRag
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$itih_cfg       = $GLOBALS['ItihRagConfig'] ?? array(); // populated by Plugin::render_widget via wp_localize_script.
$itih_bot_name  = isset( $itih_cfg['botName'] ) ? esc_html( $itih_cfg['botName'] ) : esc_html__( 'Assistant', 'itih-ai-chatbot' );
$itih_welcome   = isset( $itih_cfg['welcome'] ) ? esc_html( $itih_cfg['welcome'] ) : '';
$itih_logo      = isset( $itih_cfg['logo'] ) ? esc_url( $itih_cfg['logo'] ) : '';
$itih_avatar    = isset( $itih_cfg['avatar'] ) ? esc_url( $itih_cfg['avatar'] ) : '';
$itih_position  = isset( $itih_cfg['position'] ) ? sanitize_html_class( $itih_cfg['position'] ) : 'bottom-right';
$itih_is_inline = ! empty( $itih_cfg['inline'] );

$itih_wrapper_class = $itih_is_inline ? 'openrag-inline openrag-inline-' . $itih_position : 'openrag-floating openrag-pos-' . $itih_position;
$itih_root_attr     = $itih_is_inline ? '' : 'data-position="' . esc_attr( $itih_position ) . '"';
?>

<div id="openrag-widget" class="<?php echo esc_attr( $itih_wrapper_class ); ?>" <?php echo $itih_root_attr; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php if ( ! $itih_is_inline ) : ?>
	<button type="button" class="openrag-launcher" aria-label="<?php esc_attr_e( 'Open chat', 'itih-ai-chatbot' ); ?>" aria-expanded="false">
		<svg class="openrag-launcher-icon openrag-icon-open" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
		</svg>
		<svg class="openrag-launcher-icon openrag-icon-close" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
			<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
		</svg>
	</button>
	<?php endif; ?>

	<div class="openrag-window" role="dialog" aria-modal="false" aria-label="<?php esc_attr_e( 'Chat window', 'itih-ai-chatbot' ); ?>" <?php echo $itih_is_inline ? '' : 'hidden'; ?>>
		<header class="openrag-header">
			<div class="openrag-header-info">
				<?php if ( $itih_avatar || $itih_logo ) : ?>
					<img class="openrag-header-avatar" src="<?php echo $itih_avatar ? $itih_avatar : $itih_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" alt="" width="36" height="36" />
				<?php else : ?>
					<div class="openrag-header-avatar openrag-avatar-fallback" aria-hidden="true">
						<span><?php echo esc_html( mb_substr( $itih_bot_name, 0, 1 ) ); ?></span>
					</div>
				<?php endif; ?>
				<div>
					<div class="openrag-header-title"><?php echo $itih_bot_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<div class="openrag-header-status"><span class="openrag-dot"></span><?php esc_html_e( 'Online', 'itih-ai-chatbot' ); ?></div>
				</div>
			</div>
			<button type="button" class="openrag-clear" aria-label="<?php esc_attr_e( 'Clear chat', 'itih-ai-chatbot' ); ?>" title="<?php esc_attr_e( 'Clear chat', 'itih-ai-chatbot' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
				</svg>
			</button>
		</header>

		<div class="openrag-messages" id="openrag-messages" aria-live="polite" aria-atomic="false">
			<?php if ( $itih_welcome ) : ?>
				<div class="openrag-msg openrag-msg-bot">
					<div class="openrag-bubble openrag-bubble-bot"><?php echo $itih_welcome; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				</div>
			<?php endif; ?>
		</div>

		<div class="openrag-composer">
			<form class="openrag-form" autocomplete="off">
				<textarea
					class="openrag-input"
					id="openrag-input"
					rows="1"
					placeholder="<?php echo isset( $itih_cfg['placeholder'] ) ? esc_attr( $itih_cfg['placeholder'] ) : esc_attr__( 'Type your message…', 'itih-ai-chatbot' ); ?>"
					aria-label="<?php esc_attr_e( 'Message', 'itih-ai-chatbot' ); ?>"
				></textarea>
				<button type="submit" class="openrag-send" aria-label="<?php esc_attr_e( 'Send', 'itih-ai-chatbot' ); ?>">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
					</svg>
				</button>
			</form>
			<?php if ( ! empty( $itih_cfg['showCredit'] ) ) : ?>
			<div class="openrag-credit"><a href="https://github.com/mahavirvataliya/itih-ai-chatbot" rel="nofollow noopener" target="_blank"><?php echo isset( $itih_cfg['i18n']['poweredBy'] ) ? esc_html( $itih_cfg['i18n']['poweredBy'] ) : ''; ?></a></div>
			<?php endif; ?>
		</div>
	</div>
</div>
