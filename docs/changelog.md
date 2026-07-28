---
title: Changelog
nav_order: 13
description: Release history for OpenRag AI Chatbot.
---

# Changelog
{: .fs-9 }

{: .fw-300 }
All notable changes. The canonical source is
[CHANGELOG.md](https://github.com/mahavirvataliya/openrag-ai-chatbot/blob/main/CHANGELOG.md)
in the repository.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

_Nothing yet._

---

## [1.0.5] — 2026-07-28

Maintenance release focused on WordPress.org Plugin Check compliance,
screenshot publishing, and release build hardening.

### Changed

- **WordPress.org Plugin Check** — resolved all remaining warnings across the
  codebase:
  - Input sanitization: `$_SERVER`/`$_REQUEST` access now uses `wp_unslash()`
    + a sanitization function with fixed string keys.
  - Output escaping: exception messages and SSE output marked correctly;
    `render_css_vars()` trusted as escaped.
  - Prepared SQL: co-located `phpcs:ignore` comments on every interpolated
    table-name line; intermediate `$sql` variables inlined into
    `$wpdb->prepare()`.
  - i18n: added missing `/* translators: ... */` comments.
- **Removed the discouraged `load_plugin_textdomain()` call** — translations
  are auto-loaded for WordPress.org-hosted plugins since WP 4.6.
- **Removed the invalid `Network: false` plugin header** — the field only
  accepts `true`.
- **Screenshots** — renamed the 14 captures to the `screenshot-N.png`
  convention so WordPress.org auto-pairs them with `readme.txt` captions;
  refreshed the caption list.
- **Readme** — trimmed tags to 5 (limit) and shortened the short description
  to ≤150 characters.
- **Release builds** — `build-release.sh` and `deploy-wordpress-org.sh` now
  strip all hidden files (`.DS_Store`, editor swaps, VCS metadata, vendored
  dotfiles) via unanchored excludes, a pre-package `find` assertion, and a
  `zip -x` guard; the GitHub Actions `release.yml` fails the workflow if the
  ZIP contains any hidden file.
- `composer.lock` content-hash re-synced with `composer.json`.

---

## [1.0.0] — 2026-07-27

The first stable, public release.

### Added

#### Knowledge base

- **URL ingestion** — single or bulk (one per line, or `title,url` CSV). Pages
  are fetched, non-content elements stripped, readable text extracted via a
  DOM walker that prefers `<main>`/`<article>` blocks.
- **File ingestion** — PDF via `smalot/pdfparser`, DOCX via native `ZipArchive`
  + a `word/document.xml` text extractor, TXT and Markdown read directly.
- **WordPress content indexing** — selectable post types, auto re-index on
  `save_post`, automatic removal on `wp_trash_post`, permalink stored as the
  citation source.
- **Sentence-aware chunker** with configurable chunk size, overlap, and
  minimum length.
- **Document lifecycle** — `pending → queued → processing → completed | failed`.

#### Embeddings

- Providers: OpenAI, OpenAI-compatible, Cloudflare Workers AI, Ollama.
- Auto dimension detection with a probe (cached in a transient).
- Optional explicit dimension override.

#### Vector stores

- **MySQL store** — native `VECTOR(n)` + `DISTANCE(..., 'COSINE')` on
  MySQL ≥ 9; JSON `LONGTEXT` + PHP cosine similarity fallback.
- **Cloudflare Vectorize store** — REST v2 client.
- **Auto engine selection**.

#### LLM providers

- OpenAI, OpenAI-compatible, Groq (shared chat-completions client).
- Anthropic Claude (`/v1/messages`, extended thinking, `tool_use`).
- Cloudflare Workers AI.
- Ollama (`/api/chat`, `<think>` block extraction).
- Reasoning surfaced as separate events.

#### Chat

- REST namespace `openrag/v1`: `POST /chat` (SSE), `POST /chat/sync`,
  `POST /feedback`, `GET/DELETE /history`.
- RAG pipeline with a bounded tool-call loop (max 5 iterations).
- Citations (deduplicated by URL), per-IP rate limiting, sessions.

#### MCP (Model Context Protocol)

- Client mode — JSON-RPC 2.0 over streamable HTTP.
- Admin UI + tool discovery + bounded function-calling loop.

#### Admin UI

- Dashboard, Knowledge Base, Chats (with CSV export), tabbed Settings,
- Admin REST: `/admin/models`, `/admin/test`, `/vector-store/create-index`.

#### Frontend

- Floating widget + `[openrag_chat]` shortcode.
- 4 preset themes + per-color overrides.
- Conflict-proof CSS, vanilla JS, markdown with HTML escaping.

#### Background processing

- Action Scheduler integration (one job per document / per post) with a
  WP-Cron fallback, plus on-request mode.

#### Packaging / tooling

- Composer deps: `smalot/pdfparser`, `woocommerce/action-scheduler`.
- PSR-4 autoloading with a bundled fallback.
- `uninstall.php` with opt-in data wipe.
- Release build script and GitHub Actions release workflow.

### Security

- Admin routes require `manage_options`; public routes verify the REST nonce.
- All input sanitized; all output escaped.
- All SQL uses `$wpdb->prepare()` (except DDL with sanitized-key interpolation).
- Markdown rendering HTML-escapes content before inline formatting.

### Tested with

- PHP 8.0 – 8.5
- WordPress 6.0 – 6.8
- MySQL 5.7 / 8.x / 9.x, MariaDB 10.x

---

[Unreleased]: https://github.com/mahavirvataliya/openrag-ai-chatbot/compare/v1.0.5...HEAD
[1.0.5]: https://github.com/mahavirvataliya/openrag-ai-chatbot/releases/tag/v1.0.5
[1.0.0]: https://github.com/mahavirvataliya/openrag-ai-chatbot/releases/tag/v1.0.0
