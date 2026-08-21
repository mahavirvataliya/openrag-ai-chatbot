---
title: MCP integration
nav_order: 9
description: Connect external MCP servers and expose their tools to the chatbot.
---

# MCP integration
{: .fs-9 }

{: .fw-300 }
Model Context Protocol — client mode. Connect to external MCP servers and let
the chatbot use their tools via function calling.

---

## What it does

- Connect to **external MCP servers** (streamable HTTP or SSE transport) from
  the **Settings → MCP** tab.
- **Discover & cache** each server's tool list (click **Discover**).
- Discovered tools are normalized to an OpenAI function-calling schema and
  offered to the LLM.
- The chatbot runs a **bounded tool-call loop** (max 5 iterations), invoking
  `tools/call` on the MCP server and feeding the results back into the
  conversation.
- Tool invocations surface as a `🔧 Using tool: X` badge in the chat stream.

## Protocol details

The MCP client implements JSON-RPC 2.0 over streamable HTTP:

- `initialize`
- `notifications/initialized`
- `tools/list`
- `tools/call`

Session-id capture and SSE-aware response extraction are handled automatically,
so both streamable-HTTP and SSE transports work.

## Adding a server

1. Go to **ItihRag → Settings → MCP**.
2. Click **Add server** and enter the server's URL (and any auth required).
3. Click **Discover** to fetch and cache the tool list.
4. Enable the server. Its tools are now available to the chatbot.

## Admin REST endpoints

| Method | Path | Purpose |
|--------|------|---------|
| `GET` / `POST` | `/mcp/servers` | List / add MCP servers |
| `POST` / `DELETE` | `/mcp/servers/<id>` | Update / delete a server |
| `POST` | `/mcp/servers/<id>/discover` | Refresh a server's tool list |

All MCP endpoints require the `manage_options` capability.
