=== WP OpenRag ===
Contributors: wp-openrag
Tags: chatbot, ai, artificial-intelligence, rag, embeddings, vector-search, openai, anthropic, claude, cloudflare, ollama, groq, mcp, knowledge-base, pdf, semantic-search, search, assistant
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A complete, self-hosted RAG chatbot for WordPress. Ingest PDFs, DOCX, URLs and your own posts/pages, embed them with OpenAI/Cloudflare/Ollama, retrieve via native MySQL 9 VECTOR or Cloudflare Vectorize, and answer questions with citations, reasoning, streaming & MCP tool integration.

== Description ==

WP OpenRag turns your WordPress site into a retrieval-augmented-generation (RAG) assistant. Visitors ask questions through a customizable chat widget; the plugin retrieves the most relevant chunks from your knowledge base (documents, URLs, or your own posts and pages), feeds them to the LLM of your choice, and returns a grounded answer with citations.

Unlike hosted chatbot SaaS plugins, WP OpenRag is fully self-hosted. You pick the LLM, the embedding model, and the vector database. There are no per-message fees and no third-party middleman — your server talks directly to the providers you configure.

= Key features =

**Knowledge base ingestion**
* Import URLs (single or bulk, plain list or `title,url` CSV). Boilerplate is stripped, readable text is extracted.
* Import PDF, DOCX, TXT, and Markdown files — upload via the WordPress media library or paste a URL.
* Index your own WordPress posts and pages. Auto re-indexes on publish/update; auto-removes on trash. The permalink is stored as the citation source.

**Embeddings**
* OpenAI, OpenAI-compatible endpoints (Together, Azure, vLLM, LM Studio), Cloudflare Workers AI, or local Ollama.

**Vector storage**
* Native MySQL 9 `VECTOR` columns with cosine-distance search when available; automatic JSON + PHP fallback on older MySQL or MariaDB.
* Optional Cloudflare Vectorize. A "Create index" button auto-detects the dimension from your embedding model.

**Chat**
* Server-Sent Events (SSE) streaming for live, token-by-token answers, with a non-streaming fallback.
* Citations built from retrieval metadata, appended as a Sources list. Toggleable per answer.
* Reasoning / extended thinking (OpenAI `reasoning_effort`, Anthropic `thinking.budget_tokens`, DeepSeek `<think>` blocks). Shown in a collapsible UI panel.
* Per-IP rate limiting. Conversation history persisted server-side and in the browser.

**LLM providers**
OpenAI, OpenAI-compatible, Anthropic Claude, Cloudflare Workers AI, Groq, and Ollama (local).

**MCP (Model Context Protocol) integration**
Connect to external MCP servers in client mode. Their tools become available to the chatbot via function calling, with a bounded tool-call loop.

**Customization**
* Four preset themes (Light, Dark, Ocean, Sunset) plus per-color overrides.
* Custom logo, bot avatar, bot name, welcome message, launcher position.

**Conflict-free UI**
Every widget element is scoped under `#wporag-widget`; every CSS class is prefixed `wporag-`; all theming happens through `--wporag-*` custom properties. No leaked styles, no global selectors.

**Background processing**
Action Scheduler-based queue for large imports, with an on-request (immediate) alternative.

= Block / Shortcode =

Embed the chatbot anywhere with:

`[wp_openrag_chat]`

The floating widget can be enabled or disabled from Settings → Chat.

= Privacy =

This plugin sends user questions and (optionally) retrieved content to the configured LLM and embedding providers. It does not send any data to the plugin authors. Operators are responsible for reviewing the privacy practices of their chosen providers. See [Privacy Guidelines](https://developer.wordpress.org/plugins/privacy/).

== Installation ==

= From the WordPress plugin directory (recommended) =

1. Go to *Plugins → Add New* and search for "WP OpenRag".
2. Click *Install Now*, then *Activate*.
3. Go to *OpenRag → Settings* and configure at least one LLM provider and one embedding provider.

= Manual upload =

1. Download the ZIP from the *Advanced* section on this page or from the GitHub Releases page.
2. Go to *Plugins → Add New → Upload Plugin*, choose the ZIP, click *Install Now*.
3. Activate **WP OpenRag**.
4. Visit *OpenRag → Settings* to configure your providers.

= From source (developers) =

1. `git clone https://github.com/wp-openrag/wp-openrag.git wp-openrag`
2. `cd wp-openrag && composer install --no-dev`
3. Move the folder into `wp-content/plugins/` and activate.

== Frequently Asked Questions ==

= Do I need MySQL 9? =
No. MySQL 9 unlocks native `VECTOR` storage and fast cosine-distance search. On older MySQL or MariaDB, the plugin automatically falls back to storing embeddings as JSON and scoring them in PHP. The Settings → Vector Database screen shows which mode is active.

= Do I need to pay for anything? =
You need an account with at least one LLM/embedding provider. OpenAI, Anthropic, Groq, and Cloudflare are paid (with free tiers); Ollama is fully free and runs locally. Cloudflare Workers AI includes a generous free tier and can serve both embeddings and chat.

= Can I run fully local / offline? =
Yes. Point both the LLM and embedding providers at a local Ollama instance and use the MySQL vector store. No external requests are made.

= Can I use Cloudflare for everything? =
Yes. Cloudflare Workers AI can serve both the embeddings and the chat completion, and Cloudflare Vectorize can host the vector index.

= Is my data sent to the plugin authors? =
No. All requests go directly from your WordPress server to the providers you configure. No telemetry, no call-home, no analytics.

= How are citations built? =
Each retrieved chunk stores the source URL (the permalink for posts/pages, the imported URL for documents). Citations are deduplicated by URL and listed under the answer. The LLM is also instructed to reference source numbers in its text.

= Can I customize the chatbot's appearance? =
Yes — go to Settings → Appearance. Choose one of four preset themes or override individual colors. You can also set a logo, bot avatar, bot name, welcome message, and launcher position.

= Does it work on multisite? =
The plugin activates per-site. Network activation is supported but each site maintains its own knowledge base.

= What are the limits of the JSON fallback? =
The JSON fallback loads up to 5,000 chunks per query and scores them in PHP. For larger knowledge bases, upgrade to MySQL 9 (native VECTOR) or use Cloudflare Vectorize.

== Screenshots ==

1. **Dashboard** — overview cards (documents, chunks, chats, feedback), system status, and recent activity.
2. **Knowledge Base** — import documents, URLs, and WordPress content with live status badges and chunk preview.
3. **Chats** — paginated list of every conversation with search, date filter, feedback icons, and a detail modal.
4. **Settings: LLM Providers** — pick a provider, paste your key, fetch models, and test the connection.
5. **Settings: Vector Database** — auto-detected MySQL 9 capability, engine selector, and Vectorize index creation.
6. **Settings: Appearance** — four preset themes plus per-color overrides, logo, and bot avatar.
7. **Frontend chat widget** — streaming answers with reasoning panel, citations, and feedback.

(Screenshot images live in `.wordpress-org/screenshots/` of the GitHub repository.)

== Changelog ==

= 1.0.0 =
* Initial public release.
* Knowledge base ingestion: URLs, PDF, DOCX, TXT, MD, and WordPress posts/pages.
* Embeddings: OpenAI, OpenAI-compatible, Cloudflare Workers AI, Ollama.
* Vector stores: MySQL (native VECTOR on MySQL 9, JSON fallback) and Cloudflare Vectorize.
* LLM providers: OpenAI, OpenAI-compatible, Anthropic, Cloudflare Workers AI, Groq, Ollama.
* Chat: SSE streaming, citations, reasoning, tool calling, per-IP rate limiting, history.
* MCP integration (client mode) with tool discovery and bounded function-calling loop.
* Admin UI: Dashboard, Knowledge Base, Chats (with CSV export), tabbed Settings.
* Frontend widget (floating + shortcode) with 4 themes and full color customization.
* Background processing via Action Scheduler, plus on-request mode.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Developers ==

The plugin is namespaced under `WPOpenRag\` with PSR-4 autoloading. The codebase is organized into: `Embeddings`, `LLM`, `VectorStores`, `Ingestion`, `Queue`, `Chat`, `MCP`, `Admin`, and `Database` namespaces. See `README.md` on GitHub for the architecture diagram and REST API reference. Pull requests are welcome.
