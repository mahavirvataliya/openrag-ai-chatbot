=== ItihRag AI Chatbot ===
Contributors: mahavirvataliya
Tags: chatbot, ai, rag, knowledge-base, openai
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-hosted RAG chatbot for WordPress. Ingest your docs, embed them, and answer questions with citations, reasoning & streaming.

== Description ==

ItihRag AI Chatbot turns your WordPress site into a retrieval-augmented-generation (RAG) assistant. Visitors ask questions through a customizable chat widget; the plugin retrieves the most relevant chunks from your knowledge base (documents, URLs, or your own posts and pages), feeds them to the LLM of your choice, and returns a grounded answer with citations.

Unlike hosted chatbot SaaS plugins, ItihRag AI Chatbot is fully self-hosted. You pick the LLM, the embedding model, and the vector database. There are no per-message fees and no third-party middleman — your server talks directly to the providers you configure.

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
Every widget element is scoped under `#openrag-widget`; every CSS class is prefixed `openrag-`; all theming happens through `--openrag-*` custom properties. No leaked styles, no global selectors.

**Background processing**
Action Scheduler-based queue for large imports, with an on-request (immediate) alternative.

= Block / Shortcode =

Embed the chatbot anywhere with:

`[itih_chat]`

The floating widget can be enabled or disabled from Settings → Chat.

= Privacy =

This plugin sends user questions and (optionally) retrieved knowledge-base content to the LLM and embedding providers you configure. It does not send any data to the plugin authors — there is no telemetry, no call-home, and no analytics. API keys you enter are stored in your WordPress database and are sent only to the provider they belong to.

Operators are responsible for reviewing the privacy practices of their chosen providers and for complying with applicable privacy laws. See the WordPress [Privacy Guidelines](https://developer.wordpress.org/plugins/privacy/). The full list of services this plugin can connect to, what data is sent, and links to each provider's terms and privacy policy are documented below under **External services**.

== External services ==

This plugin is **self-hosted**: all requests are made directly from your WordPress server to the providers you configure. No request is routed through the plugin authors. Connecting to any provider is optional — you choose which providers to enable, and you can run fully offline with a local Ollama instance.

This plugin requires the use of at least one LLM provider and one embedding provider (they may be the same provider). For each service below, you provide your own account credentials; the plugin does not bundle any shared keys. **What is sent, and when:**

* **Chat:** when a visitor asks a question, the question text plus any retrieved knowledge-base context (and the recent conversation history) are sent to the configured LLM provider to generate an answer.
* **Embeddings:** when you ingest a document/URL/post, or when a question is asked (to find relevant content), the text of each chunk is sent to the configured embedding provider to produce vector representations.
* **Vector storage:** vectors are stored in your own MySQL database by default. If you enable Cloudflare Vectorize, vectors are sent to Cloudflare to be indexed there.

The specific services this plugin can connect to:

= OpenAI (LLM and/or embeddings) =

Used for chat completions and/or text embeddings. Sends the visitor's question, retrieved context, and (for ingestion) document text to `https://api.openai.com`. Terms of use: https://openai.com/policies/terms-of-use — Privacy policy: https://openai.com/policies/privacy-policy.

= Anthropic Claude (LLM) =

Used for chat completions. Sends the visitor's question and retrieved context to `https://api.anthropic.com`. Terms: https://www.anthropic.com/legal/terms — Privacy: https://www.anthropic.com/legal/privacy.

= Groq (LLM) =

Used for chat completions (OpenAI-compatible). Sends the visitor's question and retrieved context to `https://api.groq.com`. Terms of use: https://groq.com/terms-of-use (Services agreement: https://console.groq.com/docs/legal/services-agreement) — Privacy policy: https://groq.com/privacy-policy.

= Cloudflare Workers AI (LLM and/or embeddings) =

Used for chat completions and/or text embeddings. Sends the visitor's question, retrieved context, and (for ingestion) document text to `https://api.cloudflare.com`. Website terms of use: https://www.cloudflare.com/website-terms/ — Privacy policy: https://www.cloudflare.com/privacypolicy/.

= Cloudflare Vectorize (vector storage) =

Optional vector index. When enabled, the embedding vectors and their ids are sent to `https://api.cloudflare.com` to be stored and searched. The same Cloudflare terms and privacy policy as above apply: https://www.cloudflare.com/website-terms/ — https://www.cloudflare.com/privacypolicy/.

= Ollama (LLM and/or embeddings, local) =

Runs entirely on your own server. No data leaves your host. See the Ollama documentation: https://ollama.com/.

= OpenAI-compatible / custom endpoints (LLM and/or embeddings) =

You may point the plugin at any OpenAI-compatible endpoint (for example Together AI, Azure OpenAI, vLLM, or LM Studio). In that case, data (questions, context, and document text) is sent to the base URL you configure. Please consult that provider's own terms and privacy policy.

= MCP servers (optional external endpoints) =

If you enable the optional MCP (Model Context Protocol) integration, the plugin connects to the MCP server URL(s) you configure and may forward the visitor's question and retrieved context to those servers as tool-call arguments. MCP servers are operated by you or third parties you choose; review their terms and privacy practices before connecting them.

== Installation ==

= From the WordPress plugin directory (recommended) =

1. Go to *Plugins → Add New* and search for "ItihRag AI Chatbot".
2. Click *Install Now*, then *Activate*.
3. Go to *ItihRag → Settings* and configure at least one LLM provider and one embedding provider.

= Manual upload =

1. Download the ZIP from the *Advanced* section on this page or from the GitHub Releases page.
2. Go to *Plugins → Add New → Upload Plugin*, choose the ZIP, click *Install Now*.
3. Activate **ItihRag AI Chatbot**.
4. Visit *ItihRag → Settings* to configure your providers.

= From source (developers) =

1. `git clone https://github.com/mahavirvataliya/itih-ai-chatbot.git itih-ai-chatbot`
2. `cd itih-ai-chatbot && composer install --no-dev`
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

1. **Dashboard** — overview cards (documents, chunks, chats, feedback), system status showing the active LLM, embedding provider and vector store mode, plus recent activity.
2. **Knowledge Base — Documents & Files** — upload PDF/DOCX/TXT/MD files or paste a URL; documents are listed with live status badges, chunk counts and reindex/delete actions.
3. **Knowledge Base — WordPress Content** — pick which post types (posts, pages, CPTs) become searchable, enable auto-indexing, and bulk-queue existing content for embedding.
4. **Knowledge Base — URLs** — paste one or many URLs (plain list or `title,url` CSV) to fetch, extract readable text, embed and index.
5. **Chats** — paginated list of every conversation with search, date filter, feedback icons (👍/👎), per-row detail view, and one-click CSV export.
6. **Settings — General** — enable/disable the plugin and set global options.
7. **Settings — LLM Providers** — choose between OpenAI, OpenAI-compatible, Anthropic, Cloudflare, Groq, or Ollama. Fetch models and test the connection right from the admin.
8. **Settings — Embeddings** — configure the embedding provider separately from the LLM (OpenAI, OpenAI-compatible, Cloudflare Workers AI, or local Ollama).
9. **Settings — Vector Database** — auto-detected MySQL 9 native VECTOR capability, engine selector (Auto / MySQL / Cloudflare Vectorize), and one-click Vectorize index creation.
10. **Settings — Indexing** — chunk size, overlap and minimum chunk length, plus the post types and auto-index options.
11. **Settings — Chat** — bot name, welcome message, system prompt, citations toggle, reasoning/extended-thinking toggle, top-K, similarity threshold, and rate limiting.
12. **Settings — Appearance** — four preset themes (Light, Dark, Ocean, Sunset) with per-color overrides, plus custom logo and bot avatar upload.
13. **Settings — MCP** — connect to external MCP servers (streamable HTTP or SSE), discover their tools, and enable them for use by the chatbot via function calling.
14. **Frontend chat widget** — floating chat with streaming answers, a collapsible reasoning panel, citations/sources list, and thumbs-up/down feedback.

== Changelog ==

= 1.1.1 =
* Compliance: completed the ItihRag rename — options, scheduled hooks, transients, and the `[itih_chat]` shortcode now use the `itih_` prefix (existing settings are migrated automatically; the internal database table prefix and CSS scoping prefix are unchanged so data is preserved).
* Security: admin settings and knowledge-base forms verify nonces before reading any input.
* Security: local file ingestion is now restricted to the uploads directory.
* Privacy: chat streaming errors no longer expose raw provider exception messages to visitors.
* Fix: removed a dead cron event that fired with no handler.
* Performance: cached the MySQL VECTOR capability probe (no more CREATE/DROP TABLE per request), batched embedding API calls during ingestion, bounded memory in the JSON-fallback vector search, deferred the history REST call until the widget is opened, and optimized admin list queries.

= 1.1.0 =
* Security: chat history and feedback endpoints now require a per-session ownership secret, so a session id alone can no longer read or modify another visitor's chat.
* Security: the per-IP rate limiter no longer trusts client-supplied forwarded headers (X-Forwarded-For, etc.) by default; REMOTE_ADDR is used unless the `itih_trust_forwarded_ip` filter opts in.
* Privacy: the "Powered by" credit on the widget is now opt-in and off by default (Settings → Chat → Credit link).
* Guidelines: the appearance theme-preset data and widget CSS variables are now provided through `wp_localize_script` / `wp_add_inline_style` instead of inline `<script>`/`<style>` tags.
* Compliance: added a full `External services` section documenting every provider, what data is sent, and links to each provider's terms and privacy policy.
* Renamed the plugin (display name, text-domain, namespace, REST namespace) to resolve a directory naming review. The internal database table prefix (`openrag_`) is unchanged so existing data is preserved on update.

= 1.0.5 =
* WordPress.org Plugin Check: resolved all remaining warnings (input sanitization, output escaping, prepared SQL, i18n translators comments, prefixed globals).
* Fixed screenshots for publishing — renamed to the `screenshot-N.png` convention and added captions.
* Hardened release build scripts to strip all hidden files (.DS_Store, editor swaps, VCS metadata) from ZIPs and SVN deployments.
* Removed the discouraged `load_plugin_textdomain()` call (auto-loaded since WP 4.6).
* Removed the invalid `Network: false` plugin header.
* Trimmed readme tags to 5 and shortened the short description to fit the 150-character limit.

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

= 1.1.1 =
Rename completion and performance release: all plugin identifiers now use the `itih_` prefix (settings migrate automatically), plus security hardening and significant chat/ingestion speedups. No action required.

= 1.1.0 =
Security and compliance update: per-session ownership for chat history/feedback, spoofing-resistant rate limiting, opt-in credit link, and documented external services. Includes a minor database migration (adds a session-secret column) that runs automatically.

= 1.0.5 =
Maintenance release — Plugin Check compliance, screenshot publishing fixes, and build hardening. No database changes.

= 1.0.0 =
Initial release.

== Developers ==

The plugin is namespaced under `ItihRag\` with PSR-4 autoloading. The codebase is organized into: `Embeddings`, `LLM`, `VectorStores`, `Ingestion`, `Queue`, `Chat`, `MCP`, `Admin`, and `Database` namespaces. See `README.md` on GitHub for the architecture diagram and REST API reference. Pull requests are welcome.
