# Reply to the WordPress.org Plugin Review

> Paste this into your reply to the review email thread. Keep it short and direct
> (the reviewers re-review the whole plugin, so don't list every change). Upload
> the new `dist/itih-ai-chatbot-1.1.0.zip` via "Add your plugin" **before** replying.

---

Hi,

Thanks for the detailed review. I've addressed every issue and uploaded a new version (1.1.0).

**Name / permalink**
I'd like to rename the plugin to **"ItihRAG AI Chatbot"** ("Itih" = knowledge) and request the permalink/slug **`itih-ai-chatbot`**. I've updated the display name, text-domain, namespace, and REST namespace accordingly. Please reserve this slug for me.

The only remaining `openrag_` strings are the internal database table prefix and a CSS class scoping prefix — these are not brand identifiers and are kept unchanged so existing installs don't lose their data on update. All registration-level identifiers (options, scheduled hooks, transients, and the shortcode, now `[itih_chat]`) use the `itih_` prefix; existing settings migrate automatically on update.

**Inline `<script>` / `<style>`**
These are now enqueued properly: the appearance theme presets are passed via `wp_localize_script()`, and the widget CSS variables via `wp_add_inline_style()`.

**Powered-by / credit link**
The "Powered by" credit is now opt-in and **off by default** (Settings → Chat → Credit link). No attribution is shown unless the admin explicitly enables it.

**Undocumented external services**
I added an `== External services ==` section to readme.txt documenting every provider (OpenAI, Anthropic, Groq, Cloudflare Workers AI, Cloudflare Vectorize, Ollama, custom OpenAI-compatible endpoints, and optional MCP servers): what each service is, what data is sent and when, and links to each provider's terms and privacy policy. The same information is mirrored in the plugin docs.

**REST permission_callback / session ownership**
The `/feedback`, `/history` (GET), and `/history` (DELETE) endpoints now require and verify a per-session ownership secret (a cryptographically random token issued when a chat session starts). A session id alone can no longer read or modify another visitor's chat. `/chat` remains public so the chatbot works for anonymous visitors, but still issues the session secret for later calls.

**Rate limiting**
The per-IP limiter no longer trusts spoofable forwarded headers (X-Forwarded-For, X-Real-IP, CF-Connecting-IP) by default; it uses `REMOTE_ADDR`. A site behind a trusted proxy can opt in via the `itih_trust_forwarded_ip` filter.

Happy to make any further adjustments. Thanks for your time.

Mahavir
