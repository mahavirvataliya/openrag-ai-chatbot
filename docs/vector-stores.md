---
title: Vector stores
nav_order: 7
description: MySQL native VECTOR vs. JSON fallback vs. Cloudflare Vectorize.
---

# Vector stores
{: .fs-9 }

{: .fw-300 }
Where your embeddings live and how similarity search is performed.

---

## MySQL (default)

- On **MySQL ≥ 9**, the plugin uses native `VECTOR(n)` columns and
  `DISTANCE(..., 'COSINE')` for fast cosine-distance search. A parse-only
  capability probe at activation avoids false positives.
- On **older MySQL or MariaDB**, it transparently falls back to storing
  embeddings as JSON `LONGTEXT` and scoring them in PHP with cosine
  similarity.

The **Settings → Vector Database** screen shows which mode is active.

### JSON fallback limits

The JSON fallback loads up to **5,000 chunks per query** and scores them in
PHP. For larger knowledge bases, upgrade to **MySQL 9** (native VECTOR) or use
**Cloudflare Vectorize**.

## Cloudflare Vectorize

A hosted vector index with a full REST v2 client: `insert`, `query`,
`delete-by-ids`, `delete-by-source-id`, `create-index`, `get-index`. The text
and metadata stay in your local `chunks` table; only the vectors live in
Vectorize.

The **Create / verify index** button auto-detects the dimension from your
embedding model, so you don't have to look it up.

## Choosing an engine

| Option | Best for | Notes |
|--------|----------|-------|
| **Auto** | Most users | Vectorize when configured, otherwise MySQL. |
| **MySQL** | Self-hosted, fully local | Native VECTOR on MySQL 9; JSON fallback otherwise. |
| **Cloudflare Vectorize** | Serverless scale | Hosted index; one Cloudflare account for everything. |

For a **fully offline** stack, use MySQL + Ollama — no external requests are
made. For a **Cloudflare-only** stack, Workers AI can serve both embeddings and
chat, with Vectorize hosting the index.
