---
title: REST API
nav_order: 8
description: The openrag/v1 REST namespace — endpoints, methods, and auth.
---

# REST API
{: .fs-9 }

{: .fw-300 }
Namespace: `openrag/v1` — base URL: `/wp-json/openrag/v1`.

---

## Authentication

- **Public endpoints** accept the standard WordPress REST nonce via the
  `X-WP-Nonce` header (passed automatically by the bundled widget).
- **Admin endpoints** require the `manage_options` capability.

## Endpoints

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `POST`   | `/chat`                   | public (nonce) | SSE-streaming chat |
| `POST`   | `/chat/sync`              | public (nonce) | Non-streaming chat |
| `POST`   | `/feedback`               | public (nonce) | 👍/👎 feedback on a message |
| `GET`    | `/history`                | public (nonce) | Session-scoped history |
| `DELETE` | `/history`                | public (nonce) | Clear session history |
| `GET` / `POST` | `/documents`     | admin | List / create documents |
| `GET` / `DELETE` | `/documents/<id>` | admin | Get / delete a document |
| `POST`   | `/documents/<id>/process` | admin | Queue or process immediately |
| `POST`   | `/posts/index`            | admin | Bulk-index WordPress posts |
| `GET` / `POST` | `/mcp/servers`      | admin | List / add MCP servers |
| `POST` / `DELETE` | `/mcp/servers/<id>` | admin | Update / delete a server |
| `POST`   | `/mcp/servers/<id>/discover` | admin | Refresh a server's tool list |
| `GET`    | `/admin/models`           | admin | List active provider's models |
| `POST`   | `/admin/test`             | admin | Test active provider connection |
| `POST`   | `/vector-store/create-index` | admin | Create/verify Vectorize index |

## The chat flow

`POST /chat` runs the RAG pipeline:

1. Embed the incoming query (cached).
2. `vector_store.query(top_k, min_score)` for the most relevant chunks.
3. Assemble a numbered context block (+ optional tools).
4. Run a **bounded tool-call loop** (max 5 iterations) if the LLM emits calls.
5. Stream assistant deltas via **SSE**.
6. Persist the assistant turn with citations, reasoning, tool calls, model,
   tokens and response time.

`POST /chat/sync` does the same but returns the complete response as JSON
instead of a stream — useful when SSE isn't available.

## Citations

Citations are built from retrieval metadata, **deduplicated by URL**, and
appended as a *Sources* list. The LLM is also instructed to reference source
numbers in its text. The permalink is used as the source for posts/pages; the
imported URL is used for documents.

## Database tables

All tables are prefixed with `{$wpdb->prefix}openrag_`:

| Table | Holds |
|-------|-------|
| `documents` | Knowledge-base source records (`pdf`, `url`, `post`, `docx`, `txt`) |
| `chunks` | Chunked content + embedding (`VECTOR(n)` on MySQL 9, JSON `LONGTEXT` otherwise) + citation source |
| `chats` | Every chat turn (user + assistant), citations JSON, reasoning, tool calls, model, tokens, response time, feedback |
| `chat_sessions` | Anonymous session tracking |
| `mcp_servers` | MCP server config + cached tool lists |

Uninstalling is safe by default. Enable **Advanced → Remove data on uninstall**
in settings to drop all tables and options when the plugin is deleted.
