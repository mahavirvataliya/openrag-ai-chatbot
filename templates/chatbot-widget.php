<?php
/**
 * Chatbot widget markup.
 *
 * All classes are prefixed wporag- and contained within #wporag-widget (or
 * .wporag-inline for shortcode) to prevent CSS leakage from/into the host site.
 *
 * @package WPOpenRag
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cfg       = $GLOBALS['WPOpenRagConfig'] ?? array(); // populated by Plugin::render_widget via wp_localize_script.
$bot_name  = isset( $cfg['botName'] ) ? esc_html( $cfg['botName'] ) : esc_html__( 'Assistant', 'wp-openrag' );
$welcome   = isset( $cfg['welcome'] ) ? esc_html( $cfg['welcome'] ) : '';
$logo      = isset( $cfg['logo'] ) ? esc_url( $cfg['logo'] ) : '';
$avatar    = isset( $cfg['avatar'] ) ? esc_url( $cfg['avatar'] ) : '';
$position  = isset( $cfg['position'] ) ? sanitize_html_class( $cfg['position'] ) : 'bottom-right';
$is_inline = ! empty( $cfg['inline'] );

$wrapper_class = $is_inline ? 'wporag-inline wporag-inline-' . $position : 'wporag-floating wporag-pos-' . $position;
$root_attr     = $is_inline ? '' : 'data-position="' . esc_attr( $position ) . '"';
?>

<div id="wporag-widget" class="<?php echo esc_attr( $wrapper_class ); ?>" <?php echo $root_attr; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php if ( ! $is_inline ) : ?>
	<button type="button" class="wporag-launcher" aria-label="<?php esc_attr_e( 'Open chat', 'wp-openrag' ); ?>" aria-expanded="false">
		<svg class="wporag-launcher-icon wporag-icon-open" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
		</svg>
		<svg class="wporag-launcher-icon wporag-icon-close" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
			<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
		</svg>
	</button>
	<?php endif; ?>

	<div class="wporag-window" role="dialog" aria-modal="false" aria-label="<?php esc_attr_e( 'Chat window', 'wp-openrag' ); ?>" <?php echo $is_inline ? '' : 'hidden'; ?>>
		<header class="wporag-header">
			<div class="wporag-header-info">
				<?php if ( $avatar || $logo ) : ?>
					<img class="wporag-header-avatar" src="<?php echo $avatar ?: $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" alt="" width="36" height="36" />
				<?php else : ?>
					<div class="wporag-header-avatar wporag-avatar-fallback" aria-hidden="true">
						<span><?php echo esc_html( mb_substr( $bot_name, 0, 1 ) ); ?></span>
					</div>
				<?php endif; ?>
				<div>
					<div class="wporag-header-title"><?php echo $bot_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<div class="wporag-header-status"><span class="wporag-dot"></span><?php esc_html_e( 'Online', 'wp-openrag' ); ?></div>
				</div>
			</div>
			<button type="button" class="wporag-clear" aria-label="<?php esc_attr_e( 'Clear chat', 'wp-openrag' ); ?>" title="<?php esc_attr_e( 'Clear chat', 'wp-openrag' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
				</svg>
			</button>
		</header>

		<div class="wporag-messages" id="wporag-messages" aria-live="polite" aria-atomic="false">
			<?php if ( $welcome ) : ?>
				<div class="wporag-msg wporag-msg-bot">
					<div class="wporag-bubble wporag-bubble-bot"><?php echo $welcome; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				</div>
			<?php endif; ?>
		</div>

		<div class="wporag-composer">
			<form class="wporag-form" autocomplete="off">
				<textarea
					class="wporag-input"
					id="wporag-input"
					rows="1"
					placeholder="<?php echo isset( $cfg['placeholder'] ) ? esc_attr( $cfg['placeholder'] ) : esc_attr__( 'Type your message…', 'wp-openrag' ); ?>"
					aria-label="<?php esc_attr_e( 'Message', 'wp-openrag' ); ?>"
				></textarea>
				<button type="submit" class="wporag-send" aria-label="<?php esc_attr_e( 'Send', 'wp-openrag' ); ?>">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
					</svg>
				</button>
			</form>
			<div class="wporag-credit"><a href="#" rel="nofollow noopener"><?php echo isset( $cfg['i18n']['poweredBy'] ) ? esc_html( $cfg['i18n']['poweredBy'] ) : ''; ?></a></div>
		</div>
	</div>
</div>
