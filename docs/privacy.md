---
title: Privacy
nav_order: 12
description: What data OpenRag AI Chatbot sends where.
---

# Privacy
{: .fs-9 }

---

This plugin sends user questions and (optionally) retrieved content to the
configured LLM and embedding providers. **It does not send any data to the
plugin authors.**

Operators are responsible for reviewing the privacy practices of their chosen
providers.

## What is sent, and to whom

- **User questions** → your configured **LLM provider** (for chat completion).
- **Retrieved content** (chunks from your knowledge base) → your configured
  **LLM provider** (as RAG context).
- **Text to be embedded** (queries and ingested documents) → your configured
  **embedding provider**.

All requests go **directly** from your WordPress server to the providers you
configure. No telemetry, no call-home, no analytics.

## What is stored

In your own WordPress database (prefixed `{$wpdb->prefix}openrag_`):

- `documents`, `chunks` — your ingested knowledge base and its embeddings.
- `chats`, `chat_sessions` — conversation history and feedback.
- `mcp_servers` — MCP server configuration and cached tool lists.

## Removing data

Uninstalling is **safe by default** — your data is kept. Enable
**Advanced → Remove data on uninstall** in settings to drop all tables and
options when the plugin is deleted.

## Further reading

- [WordPress plugin privacy guidelines](https://developer.wordpress.org/plugins/privacy/)
- Your chosen providers' privacy policies (OpenAI, Anthropic, Cloudflare,
  Groq, Ollama, etc.).
