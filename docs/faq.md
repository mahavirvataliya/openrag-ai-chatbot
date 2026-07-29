---
title: FAQ
nav_order: 10
description: Frequently asked questions about OpenRag AI Chatbot.
---

# Frequently asked questions
{: .fs-9 }

---

### Do I need MySQL 9?

No. MySQL 9 unlocks native `VECTOR` storage and fast cosine-distance search.
On older MySQL or MariaDB, the plugin automatically falls back to storing
embeddings as JSON and scoring them in PHP. The **Settings → Vector Database**
screen shows which mode is active.

### Do I need to pay for anything?

You need an account with at least one LLM/embedding provider. OpenAI,
Anthropic, Groq, and Cloudflare are paid (with free tiers); Ollama is fully
free and runs locally. Cloudflare Workers AI includes a generous free tier and
can serve both embeddings and chat.

### Can I run fully local / offline?

Yes. Point both the LLM and embedding providers at a local Ollama instance and
use the MySQL vector store. No external requests are made.

### Can I use Cloudflare for everything?

Yes. Cloudflare Workers AI can serve both the embeddings and the chat
completion, and Cloudflare Vectorize can host the vector index.

### Is my data sent to the plugin authors?

No. All requests go directly from your WordPress server to the providers you
configure. No telemetry, no call-home, no analytics. See
[Privacy]({{ site.baseurl }}/privacy/).

### How are citations built?

Each retrieved chunk stores the source URL (the permalink for posts/pages, the
imported URL for documents). Citations are deduplicated by URL and listed
under the answer. The LLM is also instructed to reference source numbers in its
text.

### Can I customize the chatbot's appearance?

Yes — go to **Settings → Appearance**. Choose one of four preset themes
(Light, Dark, Ocean, Sunset) or override individual colors. You can also set a
logo, bot avatar, bot name, welcome message, and launcher position.

### Does it work on multisite?

The plugin activates per-site. Network activation is supported but each site
maintains its own knowledge base.

### What are the limits of the JSON fallback?

The JSON fallback loads up to 5,000 chunks per query and scores them in PHP.
For larger knowledge bases, upgrade to MySQL 9 (native VECTOR) or use
Cloudflare Vectorize.
