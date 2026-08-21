---
title: RAG Chatbot Plugin for WordPress
nav_title: Home
nav_order: 1
description: Self-hosted RAG chatbot for WordPress. Ingest PDFs, DOCX & URLs, embed with OpenAI or Ollama, and answer with citations, reasoning & streaming.
---

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "ItihRag AI Chatbot",
  "applicationCategory": "WordPressPlugin",
  "operatingSystem": "WordPress 6.0+, PHP 8.0+, MySQL 5.7+",
  "url": "https://mahavirvataliya.github.io/itih-ai-chatbot/",
  "downloadUrl": "https://github.com/mahavirvataliya/itih-ai-chatbot/releases",
  "softwareVersion": "1.0.5",
  "datePublished": "2026-07-27",
  "dateModified": "2026-07-28",
  "description": "A complete, self-hosted RAG (Retrieval-Augmented Generation) chatbot for WordPress. Ingest documents, links and posts; embed with OpenAI, Cloudflare or Ollama; retrieve with MySQL 9 native vector search or Cloudflare Vectorize; and answer visitor questions with citations, reasoning, streaming and MCP tool integration.",
  "offers": {
    "@type": "Offer",
    "price": "0",
    "priceCurrency": "USD"
  },
  "license": "https://www.gnu.org/licenses/gpl-2.0.html",
  "author": {
    "@type": "Person",
    "name": "Mahavir Vataliya",
    "url": "https://github.com/mahavirvataliya"
  },
  "publisher": {
    "@type": "Person",
    "name": "Mahavir Vataliya",
    "url": "https://github.com/mahavirvataliya"
  }
}
</script>

# ItihRag AI Chatbot
{: .fs-9 }

A complete, **self-hosted RAG (Retrieval-Augmented Generation) chatbot** for
WordPress. Ingest your own documents, links and posts, embed them with the
provider of your choice, retrieve with native MySQL 9 vector search or
Cloudflare Vectorize, and answer visitor questions with **citations, reasoning,
live streaming and MCP tool integration** — all from a customizable,
conflict-free chat widget.
{: .fs-6 .fw-300 }

[Get started]({{ site.baseurl }}/quick-start/){: .btn .btn-primary .fs-5 .mb-4 .mb-md-0 .mr-2 }
[View on GitHub](https://github.com/mahavirvataliya/itih-ai-chatbot){: .btn .fs-5 .mb-4 .mb-md-0 }

---

## What it does

ItihRag AI Chatbot turns your WordPress site into a retrieval-augmented-generation
(RAG) assistant. Visitors ask questions through a customizable chat widget; the
plugin retrieves the most relevant chunks from your knowledge base (documents,
URLs, or your own posts and pages), feeds them to the LLM of your choice, and
returns a grounded answer with citations.

Unlike hosted chatbot SaaS plugins, ItihRag AI Chatbot is **fully self-hosted**.
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

## Screenshots

<table class="openrag-screenshots" role="presentation">
  <tr>
    <td align="center">
      <a href="{{ site.baseurl }}/screenshots/">
        <img src="{{ site.baseurl }}/assets/screenshots/screenshot-1.png" alt="Dashboard" width="280"><br>
        <sub>Dashboard</sub>
      </a>
    </td>
    <td align="center">
      <a href="{{ site.baseurl }}/screenshots/">
        <img src="{{ site.baseurl }}/assets/screenshots/screenshot-2.png" alt="Knowledge Base" width="280"><br>
        <sub>Knowledge Base</sub>
      </a>
    </td>
    <td align="center">
      <a href="{{ site.baseurl }}/screenshots/">
        <img src="{{ site.baseurl }}/assets/screenshots/screenshot-14.png" alt="Frontend chat widget" width="280"><br>
        <sub>Frontend chat widget</sub>
      </a>
    </td>
  </tr>
</table>

**[View all 14 screenshots →]({{ site.baseurl }}/screenshots/)**

## Quick start

```text
[openrag_chat]
```

Embed the chatbot inline anywhere with the shortcode above, or enable the
floating widget from **Settings → Chat**. See the [Quick start guide]({{ site.baseurl }}/quick-start/)
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

- [Installation]({{ site.baseurl }}/installation/)
- [Features]({{ site.baseurl }}/features/)
- [Quick start]({{ site.baseurl }}/quick-start/)
- [REST API]({{ site.baseurl }}/rest-api/)
- [Screenshots]({{ site.baseurl }}/screenshots/)
- [FAQ]({{ site.baseurl }}/faq/)
- [Changelog]({{ site.baseurl }}/changelog/)

---

## For AI tools and agents

This documentation is also available as a machine-readable index for LLMs and
coding agents:

**[llms.txt]({{ site.baseurl }}/llms.txt)** — a plain-text summary of the plugin
with links to every page. Fetch `https://mahavirvataliya.github.io/itih-ai-chatbot/llms.txt`
to give an AI assistant a concise, structured overview of ItihRag AI Chatbot.
