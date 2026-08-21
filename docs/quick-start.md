---
title: Quick start
nav_order: 4
description: Get a working RAG chatbot on your WordPress site in under 5 minutes.
---

# Quick start
{: .fs-9 }

{: .fw-300 }
Configure providers, ingest some content, and put the chat widget on your site.

---

## 1. Configure an LLM provider

**ItihRag → Settings → LLM Providers** — pick a provider, paste your key,
choose a model. Click **Test connection** to verify.

## 2. Configure an embedding provider

**ItihRag → Settings → Embeddings** — do the same for embeddings.
(Cloudflare's free-tier Workers AI works for both.)

## 3. Choose a vector database

**ItihRag → Settings → Vector Database** — review the detected capability and
pick an engine (or leave on **Auto**). If using Cloudflare Vectorize, click
**Create / verify index**.

See [Vector stores]({{ site.baseurl }}/vector-stores/) for the trade-offs.

## 4. Add content to your knowledge base

**ItihRag → Knowledge Base** — add a URL or upload a PDF, or use the
**WordPress Content** tab to index your existing posts/pages.

## 5. Personalize the chat

**ItihRag → Settings → Chat** — confirm the bot name, welcome message and
launcher position.

## 6. Visit your site

The chat bubble appears in the corner.

---

## Embed the chatbot inline

Embed your chatbot anywhere with the shortcode:

```text
[openrag_chat]
```

The floating widget can be enabled or disabled from **Settings → Chat**.

---

## A fully local / offline setup

You can run the entire stack on your own hardware with no external requests:

1. Install [Ollama](https://ollama.com/) locally and pull a chat model
   (e.g. `ollama pull llama3.1`) and an embedding model
   (e.g. `ollama pull nomic-embed-text`).
2. In **Settings → LLM Providers**, choose **Ollama** and point it at
   `http://localhost:11434`.
3. In **Settings → Embeddings**, choose **Ollama** with the same base URL.
4. In **Settings → Vector Database**, use **MySQL** (no external service).

## A Cloudflare-only setup

Cloudflare Workers AI can serve both embeddings and chat, and Cloudflare
Vectorize can host the vector index — one account for everything:

1. In **Settings → LLM Providers** and **Settings → Embeddings**, choose
   **Cloudflare Workers AI** with your account ID and API token.
2. In **Settings → Vector Database**, choose **Cloudflare Vectorize** and
   click **Create / verify index**.
