---
title: Providers
nav_order: 6
description: Configuring LLM and embedding providers.
---

# Providers
{: .fs-9 }

{: .fw-300 }
ItihRag AI Chatbot talks directly to the providers you configure. Pick one LLM
provider for chat and one embedding provider for vectors — they don't have to
be the same.

---

## LLM providers

| Provider | Notes |
|----------|-------|
| **OpenAI** | The canonical chat-completions API. Supports `reasoning_effort`. |
| **OpenAI-compatible** | Any endpoint that speaks the OpenAI chat format — Together, Azure, vLLM, LM Studio. Configurable base URL. |
| **Anthropic Claude** | `/v1/messages` with system-prompt extraction, message alternation, extended thinking (`thinking.budget_tokens`), and `tool_use` handling. |
| **Cloudflare Workers AI** | `/accounts/{id}/ai/run/{model}` for both streaming and non-streaming chat. Generous free tier. |
| **Groq** | Shares the OpenAI-compatible chat client with a configurable base URL. Very fast inference. |
| **Ollama** | `/api/chat` running locally. Extracts `<think>` blocks (deepseek-r1 / qwq). Fully free. |

Every provider exposes:

- **API key** (not needed for Ollama).
- **Base URL** (where applicable).
- **Model** — type it in, or click **Fetch models** to populate the dropdown
  from the provider.
- **Test connection** — verifies your credentials before you save.

## Embedding providers

| Provider | Example models |
|----------|----------------|
| **OpenAI** | `text-embedding-3-small`, `text-embedding-3-large`, `text-embedding-ada-002` |
| **OpenAI-compatible** | Together, Azure, vLLM, LM Studio |
| **Cloudflare Workers AI** | `@cf/baai/bge-base-en-v1.5`, `bge-m3` |
| **Ollama** | `nomic-embed-text`, `mxbai-embed-large`, `bge-m3` |

The embedding dimension is auto-detected with a probe (cached in a transient).
An explicit dimension override is available for providers that don't report one.

## Reasoning / extended thinking

Reasoning is surfaced as a separate event stream so the UI can render it in a
collapsible panel:

- **OpenAI** — `reasoning_effort`.
- **Anthropic** — `thinking.budget_tokens`.
- **DeepSeek / Ollama models** — `<think>` block extraction.

Toggle the panel from **Settings → Chat → Reasoning**.

## Pricing

You need an account with at least one LLM/embedding provider.

- **OpenAI, Anthropic, Groq, Cloudflare** are paid with free tiers.
- **Ollama** is fully free and runs locally.
- **Cloudflare Workers AI** includes a generous free tier and can serve both
  embeddings and chat.

See the [Quick start]({{ site.baseurl }}/quick-start/) for a fully local (Ollama) or fully
Cloudflare setup.
