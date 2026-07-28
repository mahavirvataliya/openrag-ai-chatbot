---
title: Home
nav_order: 1
description: A complete, self-hosted RAG chatbot for WordPress.
---

# OpenRag AI Chatbot
{: .fs-9 }

A complete, **self-hosted RAG (Retrieval-Augmented Generation) chatbot** for
WordPress. Ingest your own documents, links and posts, embed them with the
provider of your choice, retrieve with native MySQL 9 vector search or
Cloudflare Vectorize, and answer visitor questions with **citations, reasoning,
live streaming and MCP tool integration** — all from a customizable,
conflict-free chat widget.
{: .fs-6 .fw-300 }

[Get started](quick-start.html){: .btn .btn-primary .fs-5 .mb-4 .mb-md-0 .mr-2 }
[View on GitHub](https://github.com/mahavirvataliya/openrag-ai-chatbot){: .btn .fs-5 .mb-4 .mb-md-0 }

---

## What it does

OpenRag AI Chatbot turns your WordPress site into a retrieval-augmented-generation
(RAG) assistant. Visitors ask questions through a customizable chat widget; the
plugin retrieves the most relevant chunks from your knowledge base (documents,
URLs, or your own posts and pages), feeds them to the LLM of your choice, and
returns a grounded answer with citations.

Unlike hosted chatbot SaaS plugins, OpenRag AI Chatbot is **fully self-hosted**.
You pick the LLM, the embedding model, and the vector database. There are
**no per-message fees** and **no third-party middleman** — your server talks
directly to the providers you configure.

## Highlights

- **Knowledge base ingestion** — URLs (single or bulk CSV), files (PDF, DOCX,
  TXT, Markdown), and your own WordPress posts & pages.
- **Embeddings** — OpenAI, OpenAI-compatible, Cloudflare Workers AI, or local
  Ollama.
- **Vector stores** — native MySQL 9 `VECTOR` columns (with JSON + PHP fallback),
  or Cloudflare Vectorize.
- **Chat** — SSE streaming, citations as a *Sources* list, reasoning / extended
  thinking, tool calling, per-IP rate limiting, conversation history.
- **LLM providers** — OpenAI, OpenAI-compatible, Anthropic Claude, Cloudflare
  Workers AI, Groq, Ollama.
- **MCP integration** — connect to external MCP servers and expose their tools
  to the chatbot via function calling.
- **Conflict-free widget** — scoped under `#openrag-widget`, `openrag-` class
  prefix, four preset themes, fully customizable.

## Quick start

```text
[openrag_chat]
```

Embed the chatbot inline anywhere with the shortcode above, or enable the
floating widget from **Settings → Chat**. See the [Quick start guide](quick-start.md)
to get a working chatbot in under 5 minutes.

## Requirements

| Requirement | Minimum | Notes |
|-------------|---------|-------|
| PHP | **8.0** | 8.1+ recommended |
| WordPress | **6.0** | |
| MySQL | **5.7** | MySQL **9.0+** unlocks native `VECTOR` storage & search |
| Embedding provider | one of | OpenAI · OpenAI-compatible · Cloudflare Workers AI · Ollama |
| LLM provider | one of | OpenAI · OpenAI-compatible · Anthropic · Cloudflare · Groq · Ollama |

## Next steps

- [Installation](installation.md)
- [Features](features.md)
- [Quick start](quick-start.md)
- [REST API](rest-api.md)
