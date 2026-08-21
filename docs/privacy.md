---
title: Privacy
nav_order: 12
description: What data ItihRag AI Chatbot sends where.
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
- `chats`, `chat_sessions` — conversation history and feedback. Each session
  has a random ownership `secret`; the secret is given only to the visitor who
  started that chat, and is required to view or modify that session's history.
- `mcp_servers` — MCP server configuration and cached tool lists.

Provider API keys you enter in settings are also stored in your WordPress
database (as options) and are sent only to the provider they belong to.

## Removing data

Uninstalling is **safe by default** — your data is kept. Enable
**Advanced → Remove data on uninstall** in settings to drop all tables and
options when the plugin is deleted.

## Provider terms and privacy policies

This plugin is self-hosted: requests go directly from your server to the
provider you configure. Connecting to any provider is optional. Please review
each provider's terms and privacy policy:

- **OpenAI** (LLM/embeddings, `api.openai.com`) —
  [Terms of use](https://openai.com/policies/terms-of-use) ·
  [Privacy policy](https://openai.com/policies/privacy-policy)
- **Anthropic Claude** (LLM, `api.anthropic.com`) —
  [Terms](https://www.anthropic.com/legal/terms) ·
  [Privacy](https://www.anthropic.com/legal/privacy)
- **Groq** (LLM, `api.groq.com`) —
  [Terms of use](https://groq.com/terms-of-use) ·
  [Privacy policy](https://groq.com/privacy-policy)
- **Cloudflare Workers AI** (LLM/embeddings, `api.cloudflare.com`) —
  [Website terms of use](https://www.cloudflare.com/website-terms/) ·
  [Privacy policy](https://www.cloudflare.com/privacypolicy/)
- **Cloudflare Vectorize** (vector storage, `api.cloudflare.com`) — uses the
  same Cloudflare terms and privacy policy as above.
- **Ollama** (local, runs on your own server) —
  [Documentation](https://ollama.com/). No data leaves your host.
- **OpenAI-compatible / custom endpoints** — if you point the plugin at a
  custom OpenAI-compatible endpoint, data is sent to the base URL you configure;
  consult that provider's terms and privacy policy.
- **MCP servers** — if enabled, the plugin connects to the MCP server URL(s)
  you configure and may forward questions/context as tool-call arguments.

## Further reading

- [WordPress plugin privacy guidelines](https://developer.wordpress.org/plugins/privacy/)
