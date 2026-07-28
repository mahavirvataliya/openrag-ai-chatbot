---
title: Features
nav_order: 2
description: Everything OpenRag AI Chatbot can do.
---

# Features
{: .fs-9 }

{: .fw-300 }
A self-hosted RAG pipeline for WordPress — ingestion, embeddings, vector search,
chat, providers, MCP, a conflict-free widget, and a full admin UI.

---

## 📚 Knowledge base ingestion

- **Import URLs** — paste one or many (one per line, or `title,url` CSV). Pages
  are fetched, boilerplate is stripped (`<nav>`, `<footer>`, `<script>` …), the
  readable text is extracted, then chunked, embedded and indexed.
- **Import files** — **PDF** (via [`smalot/pdfparser`](https://github.com/smalot/pdfparser)),
  **DOCX** (native `ZipArchive` + `word/document.xml` parser), **TXT**,
  **Markdown**. Upload through the WordPress media library or paste a URL.
- **Index your own WordPress content** — pick which post types (posts, pages,
  custom) become searchable. Auto re-indexes on `save_post`, removes on
  `wp_trash_post`. The permalink is stored as the citation source.

## 🧠 Embeddings & vector search

- Configurable **chunk size, overlap, and minimum length** (sentence-aware
  splitter that preserves formatting — no `sanitize_text_field` mangling).
- Embedding providers: **OpenAI**, **OpenAI-compatible** (Together / Azure /
  vLLM / LM Studio), **Cloudflare Workers AI** (`bge-*`, `bge-m3`), **Ollama**
  (local, `nomic-embed-text`).
- Vector stores:
  - **MySQL** — uses native `VECTOR(n)` columns + `DISTANCE(..., 'COSINE')`
    when **MySQL ≥ 9** is detected; transparently falls back to JSON storage +
    PHP cosine similarity otherwise.
  - **Cloudflare Vectorize** — full REST v2 client (`insert`, `query`,
    `delete-by-ids`, `create-index`). A "Create index" button auto-detects the
    dimension from your embedding model.

## 💬 Chatbot

- **SSE streaming** (fetch + `ReadableStream`) for live, token-by-token answers,
  with a non-streaming `/chat/sync` fallback.
- **Citations** — built from retrieval metadata and appended as a *Sources*
  list. Per-answer toggle in **Chat** settings.
- **Reasoning / extended thinking** — per provider (`reasoning_effort` on
  OpenAI, `thinking.budget_tokens` on Anthropic, `<think>` blocks on
  DeepSeek/Ollama models). Shown in a collapsible UI section.
- **Tool calling** — when an LLM emits tool calls the chatbot executes them in
  a bounded loop.
- **Rate limiting** — per-IP request throttling via transients.
- **Conversation history** — server-side + `localStorage` session continuity.

## 🤖 LLM providers

OpenAI · OpenAI-compatible · **Anthropic Claude** · **Cloudflare Workers AI** ·
**Groq** · **Ollama** (local). Per-provider API key, base URL, model. "Fetch
models" and "Test connection" buttons in the admin. See
[Providers](providers.md) for setup details.

## 🔌 MCP integration (Model Context Protocol — client mode)

- Connect to **external MCP servers** (streamable HTTP or SSE transport) from
  the MCP tab.
- **Discover & cache** each server's tool list.
- Discovered tools are offered to the LLM via **function calling**; the chatbot
  runs a bounded tool-call loop (max 5 iterations), invoking `tools/call` on the
  MCP server and feeding results back into the conversation.
- Tool invocations surface as a `🔧 Using tool: X` badge in the chat stream.

See the [MCP guide](mcp.md) for details.

## 🎨 Frontend widget

- **Conflict-proof** — every element is scoped inside `#openrag-widget`, every
  class is prefixed `openrag-`, all theming happens through `--openrag-*`
  custom properties on the root. No leaked styles, no global selectors.
- Two delivery modes: **floating widget** (auto-injected, toggleable) and
  **`[openrag_chat]` shortcode** for inline embedding.
- **4 preset themes** — Light, Dark, Ocean, Sunset — plus per-color overrides.
- **Logo, bot avatar, bot name, welcome message, launcher position** — all
  configurable.
- Vanilla JS, mobile-responsive, markdown rendering with HTML escaping (no XSS).

## 🛠 Admin UI

- **Dashboard** — totals (documents, chunks, chats, 👍/👎), system status
  (active provider + vector store mode), recent activity.
- **Knowledge Base** — Documents/Files, URLs, WordPress Content tabs. Add
  forms, status badges, per-item reindex/delete, chunk-preview modal, bulk CSV
  URL import.
- **Chats** — paginated list of every conversation with search, date filter,
  feedback icons, a detail modal (message, reply, reasoning, citations) and
  **CSV export**.
- **Settings** — tabbed: General · LLM Providers · Embeddings · Vector Database ·
  Indexing · Chat · Appearance · MCP. Nonces + sanitization throughout.

## ⚙️ Background processing

- **Action Scheduler** (the library WooCommerce ships) — one job per
  document/post, retry and exponential backoff handled automatically.
- **On-request mode** alternative — process immediately via AJAX, ideal for
  small imports.
