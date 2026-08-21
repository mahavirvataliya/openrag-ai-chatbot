---
title: Configuration
nav_order: 5
description: The eight settings tabs and what each one controls.
---

# Configuration
{: .fs-9 }

{: .fw-300 }
ItihRag AI Chatbot is configured from **ItihRag → Settings**, organized into
eight tabs. All forms are nonce-protected and every field is sanitized on save.

---

## General

Enable or disable the plugin globally and set core options. This is also where
the active LLM and embedding provider status is summarized.

## LLM Providers

Choose your chat-completion provider: OpenAI, OpenAI-compatible, Anthropic
Claude, Cloudflare Workers AI, Groq, or Ollama. Each provider has its own API
key, base URL and model fields. Use **Fetch models** to populate the model
dropdown from the provider, and **Test connection** to verify your credentials
before saving. See [Providers]({{ site.baseurl }}/providers/) for per-provider notes.

## Embeddings

Configure the embedding provider **separately** from the LLM. Supported:
OpenAI, OpenAI-compatible, Cloudflare Workers AI, and local Ollama. The
embedding dimension is auto-detected with a probe (cached in a transient); an
explicit dimension override is available for providers that don't report one.

## Vector Database

The screen auto-detects whether your MySQL server is **MySQL ≥ 9** with native
`VECTOR` support. Choose an engine:

- **Auto** — Vectorize when configured, otherwise MySQL.
- **MySQL** — native `VECTOR(n)` columns on MySQL 9, or JSON + PHP fallback on
  older MySQL / MariaDB.
- **Cloudflare Vectorize** — hosted vector index; a **Create / verify index**
  button auto-detects the dimension from your embedding model.

See [Vector stores]({{ site.baseurl }}/vector-stores/) for the full comparison.

## Indexing

Control how content is split into chunks:

- **Chunk size** — target size (in characters) of each chunk.
- **Overlap** — how many characters of overlap between adjacent chunks.
- **Minimum chunk length** — chunks shorter than this are dropped.
- **Post types** — which WordPress post types are eligible for indexing.
- **Auto-index options** — re-index on `save_post`, remove on `wp_trash_post`.

The chunker is **sentence-aware** and preserves formatting (no
`sanitize_text_field` mangling).

## Chat

Behavior and personality of the chatbot:

- **Bot name**, **welcome message**, and a **system prompt**.
- **Citations toggle** — append a *Sources* list built from retrieval metadata.
- **Reasoning / extended thinking toggle** — surface the model's reasoning in a
  collapsible panel.
- **Top-K** — how many chunks to retrieve per query.
- **Similarity threshold** — minimum cosine similarity for a chunk to be used.
- **Rate limiting** — per-IP request window and max requests (via transients).

## Appearance

Skin the frontend widget:

- **4 preset themes** — Light, Dark, Ocean, Sunset.
- **Per-color overrides** — primary, header background, text, user/bot bubble
  colors, launcher color.
- **Custom logo** and **bot avatar** upload.
- **Launcher position** — corner placement of the floating bubble.

Theming happens entirely through `--openrag-*` custom properties on the
`#openrag-widget` root — no leaked styles, no global selectors.

## MCP

Connect to external MCP (Model Context Protocol) servers in client mode. Add,
edit, delete, enable and disable servers; click **Discover** to cache each
server's tool list. Enabled servers' tools are offered to the LLM via function
calling. See the [MCP guide]({{ site.baseurl }}/mcp/).

## Advanced

- **Remove data on uninstall** — opt-in full cleanup. Off by default, so your
  data is preserved when the plugin is deleted.
