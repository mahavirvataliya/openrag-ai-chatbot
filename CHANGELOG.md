# Changelog

All notable changes to **ItihRag AI Chatbot** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.1] — 2026-08-21

Rename completion, WordPress.org compliance hardening, and performance release.

### Changed
- **Rename completion** — all registration-level identifiers now use the `itih_` prefix: options (`itih_general`, …), Action Scheduler hooks (`itih_process_document`, `itih_index_post`, `itih_schedule_document`, `itih_schedule_post`), transients (`itih_rl_*`, `itih_q_*`, `itih_embedding_dim`), localStorage keys (`itih_session`, `itih_secret`), and the shortcode (now `[itih_chat]`, with `[openrag_chat]` kept as a legacy alias). Existing settings migrate automatically on update. The internal database table prefix (`openrag_`) and CSS scoping prefix are unchanged so data is preserved.
- **Dead cron removed** — the `openrag_health_check` event had no handler; scheduling dropped.

### Security
- Admin settings and knowledge-base forms verify nonces before reading any `$_POST` input (Plugin Check nonce-verification order).
- Local file ingestion is restricted to the uploads directory.
- Chat streaming errors no longer expose raw provider exception messages to visitors; logged server-side only when debug logging is enabled.
- Admin chat-detail modal output is fully escaped (role, citation URLs/titles, model, feedback).
- LICENSE copyright holder corrected to the plugin author.

### Performance
- Cached the MySQL VECTOR capability probe — no more CREATE/DROP TABLE DDL on every request.
- Batched embedding API calls during ingestion (~100 texts per request instead of one per chunk) with per-chunk fallback.
- JSON-fallback vector search rewritten: keyset-paginated batches of id+embedding only (~20× less memory), normalized query vector, top-k hydration in a single query. Older chunks are no longer silently excluded past 5,000 rows.
- Frontend history REST call deferred until the widget is first opened (no request per page view).
- Chats admin list uses a deterministic next-reply join (ONLY_FULL_GROUP_BY-safe); CSV export streams in 1,000-row batches.
- Dashboard stats collapsed into one aggregate query cached for 60s; added `role` and `(session_id, id)` indexes to chats.
- Composer autoloading switched from a broken PSR-4 mapping to an optimized classmap.
- Per-group memoization in the settings layer.

---

## [1.0.5] — 2026-07-28

Maintenance release focused on WordPress.org Plugin Check compliance, screenshot publishing, and release build hardening.

### Changed
- **WordPress.org Plugin Check** — resolved all remaining warnings across the codebase:
  - Input sanitization: `$_SERVER`/`$_REQUEST` access now uses `wp_unslash()` + a sanitization function with fixed string keys.
  - Output escaping: exception messages and SSE output marked correctly; `render_css_vars()` trusted as escaped.
  - Prepared SQL: co-located `phpcs:ignore` comments on every interpolated table-name line; intermediate `$sql` variables inlined into `$wpdb->prepare()`.
  - i18n: added missing `/* translators: ... */` comments.
- **Removed the discouraged `load_plugin_textdomain()` call** — translations are auto-loaded for WordPress.org-hosted plugins since WP 4.6.
- **Removed the invalid `Network: false` plugin header** — the field only accepts `true`.
- **Screenshots** — renamed the 14 captures to the `screenshot-N.png` convention so WordPress.org auto-pairs them with `readme.txt` captions; refreshed the caption list.
- **Readme** — trimmed tags to 5 (limit) and shortened the short description to ≤150 characters.
- **Release builds** — `build-release.sh` and `deploy-wordpress-org.sh` now strip all hidden files (`.DS_Store`, editor swaps, VCS metadata, vendored dotfiles) via unanchored excludes, a pre-package `find` assertion, and a `zip -x` guard; the GitHub Actions `release.yml` fails the workflow if the ZIP contains any hidden file.
- `composer.lock` content-hash re-synced with `composer.json`.

---

## [1.0.0] — 2026-07-27

The first stable, public release.

### Added

#### Knowledge base
- **URL ingestion** — single or bulk (one per line, or `title,url` CSV). Pages are fetched,
  non-content elements (`<script>`, `<style>`, `<nav>`, `<header>`, `<footer>`, `<aside>`,
  `<form>`, `<svg>`) are stripped, and the readable text is extracted via a DOM walker that
  prefers `<main>`/`<article>` blocks.
- **File ingestion** — PDF via `smalot/pdfparser`, DOCX via native `ZipArchive` + a
  `word/document.xml` text extractor (paragraph breaks preserved, title pulled from
  `docProps/core.xml`), TXT and Markdown read directly.
- **WordPress content indexing** — selectable post types (posts / pages / public CPTs),
  auto re-index on `save_post`, automatic removal on `wp_trash_post`, permalink stored as
  the citation source.
- **Sentence-aware chunker** with configurable chunk size, overlap, and minimum length.
  Preserves formatting (no `sanitize_text_field` mangling) and slides an overlap window
  across sentence boundaries.
- **Document lifecycle** — `pending → queued → processing → completed | failed`, with
  `error_message`, `chunk_count`, content hash, and per-document timestamps.

#### Embeddings
- Provider interface with four implementations:
  - **OpenAI** (`text-embedding-3-small/large`, `text-embedding-ada-002`, …).
  - **OpenAI-compatible** (Together, Azure, vLLM, LM Studio, …).
  - **Cloudflare Workers AI** (`@cf/baai/bge-base-en-v1.5`, `bge-m3`, …).
  - **Ollama** (`nomic-embed-text`, `mxbai-embed-large`, `bge-m3`, …).
- Auto dimension detection with a probe (cached in a transient).
- Optional explicit dimension override for providers that don't report one.

#### Vector stores
- **MySQL store** — uses native `VECTOR(n)` columns and `DISTANCE(..., 'COSINE')` when
  **MySQL ≥ 9** is detected at activation (with a parse-only capability probe to avoid
  false positives); falls back to JSON `LONGTEXT` storage + PHP cosine similarity on older
  MySQL / MariaDB.
- **Cloudflare Vectorize store** — REST v2 client (`insert`, `query`, `delete-by-ids`,
  `delete-by-source-id`, `create-index`, `get-index`). Stores the text/metadata locally
  in the chunks table and the vectors in Vectorize.
- **Auto engine selection** — Vectorize when configured, otherwise MySQL.

#### LLM providers
- **OpenAI** and any **OpenAI-compatible** endpoint, plus **Groq** (shared chat-completions
  client; configurable base URL).
- **Anthropic Claude** — `/v1/messages` with system-prompt extraction, message alternation
  enforcement, extended thinking (`thinking.budget_tokens`), and `tool_use` block handling.
- **Cloudflare Workers AI** — `/accounts/{id}/ai/run/{model}` for both streaming and
  non-streaming chat.
- **Ollama** — `/api/chat` with `<think>` block extraction (deepseek-r1 / qwq).
- Per-provider: API key, base URL, model, "Fetch models" and "Test connection" buttons.
- **Reasoning** surfaced separately in normalized events (`reasoning`) so the UI can show
  it in a collapsible panel.

#### Chat
- **REST namespace `itih/v1`**: `POST /chat` (SSE), `POST /chat/sync`, `POST /feedback`,
  `GET/DELETE /history`.
- **RAG pipeline**: query embedding (cached) → vector store `query(top_k, min_score)` →
  numbered context block → optional tools → bounded tool-call loop (max 5 iterations) →
  SSE delta stream → persist assistant turn with citations, reasoning, tool calls, model,
  tokens and response time.
- **Citations** built from retrieval metadata (deduplicated by URL), appended as a
  *Sources* list. Toggleable in **Chat** settings.
- **Per-IP rate limiting** via transients, configurable window and max requests.
- **Sessions** persisted in `chat_sessions`; turns persisted in `chats`.

#### MCP (Model Context Protocol)
- **Client mode** — JSON-RPC 2.0 over streamable HTTP. Implements `initialize` /
  `notifications/initialized` / `tools/list` / `tools/call`, with session-id capture and
  SSE-aware response extraction.
- **Admin UI** to add / edit / delete / enable / disable servers; "Discover" caches each
  server's tool list.
- **Tool exposure** — enabled servers' tools are normalized to OpenAI function-calling
  schema and offered to the LLM; tool invocations are surfaced as a
  `🔧 Using tool: X` badge in the stream.

#### Admin UI
- **Dashboard** — totals (documents, chunks, chats, 👍/👎), system status (active LLM,
  embedding provider, vector store mode including MySQL native VECTOR status), recent activity.
- **Knowledge Base** — Documents/Files, URLs, WordPress Content tabs; status badges, per-item
  reindex/delete, chunk preview modal, "Index all published content" action.
- **Chats** — paginated list with search, date range filter, feedback icons, a detail modal
  (message, reply, reasoning, citations) and **CSV export**.
- **Settings** — tabbed (General · LLM Providers · Embeddings · Vector Database · Indexing ·
  Chat · Appearance · MCP), nonce-protected and sanitized.
- **Admin REST** — `/admin/models`, `/admin/test`, `/vector-store/create-index`.

#### Frontend
- **Floating widget** (auto-injected, toggleable position) and **`[openrag_chat]` shortcode**.
- **4 preset themes** — Light, Dark, Ocean, Sunset — plus per-color overrides
  (primary, header bg, text, user/bot bubble colors, launcher color).
- **Logo, bot avatar, bot name, welcome message** configurable.
- **Conflict-proof CSS**: everything scoped under `#openrag-widget`, all classes prefixed
  `openrag-`, theming via `--openrag-*` custom properties.
- **Vanilla JS** chatbot: SSE stream parsing, typing indicator, markdown rendering with
  HTML-escaping (no XSS), collapsible reasoning panel, citations list, 👍/👎 feedback modal,
  localStorage session, mobile-responsive.

#### Background processing
- **Action Scheduler** integration (one job per document / per post), with a WP-Cron fallback
  when AS isn't available. Retries and backoff handled by AS.
- **On-request mode** — process immediately via AJAX for small imports.

#### Packaging / tooling
- Composer dependencies: `smalot/pdfparser`, `woocommerce/action-scheduler`.
- PSR-4 autoloading with a bundled fallback `Autoloader` class so the plugin works even
  before `composer dump-autoload` is run.
- `uninstall.php` with opt-in data wipe (default: keep data).
- `composer.json`, `composer.lock`.
- GitHub-ready `README.md`, WordPress.org-style `readme.txt`, `CHANGELOG.md`, `LICENSE`.
- Release build script and GitHub Actions release workflow.

### Security
- All admin REST routes require `manage_options`; public routes verify the WordPress REST
  nonce.
- All user input sanitized (`sanitize_text_field`, `sanitize_textarea_field`, `esc_url_raw`,
  `sanitize_key`, `sanitize_hex_color`); all output escaped.
- All SQL uses `$wpdb->prepare()` with the exception of DDL statements, which interpolate
  only sanitized-key values.
- Markdown rendering in the widget HTML-escapes content before applying inline formatting,
  preventing stored/reflected XSS via prompt injection.

### Tested with
- PHP 8.0 – 8.5
- WordPress 6.0 – 6.8
- MySQL 5.7 / 8.x / 9.x, MariaDB 10.x

[Unreleased]: https://github.com/mahavirvataliya/itih-ai-chatbot/compare/v1.0.5...HEAD
[1.0.5]: https://github.com/mahavirvataliya/itih-ai-chatbot/releases/tag/v1.0.5
[1.0.0]: https://github.com/mahavirvataliya/itih-ai-chatbot/releases/tag/v1.0.0
