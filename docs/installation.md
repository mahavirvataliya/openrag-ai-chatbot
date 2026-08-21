---
title: Installation
nav_order: 3
description: Install ItihRag AI Chatbot from a release ZIP, the WordPress.org directory, or from source.
---

# Installation
{: .fs-9 }

{: .fw-300 }
Three ways to install. The release ZIP is recommended for most users.

---

## From a release ZIP (recommended)

1. Download the latest `itih-ai-chatbot-{version}.zip` from the
   [**Releases** page](https://github.com/mahavirvataliya/itih-ai-chatbot/releases).
   *(Release ZIPs ship with `vendor/` pre-built — no Composer required.)*
2. In WordPress: **Plugins → Add New → Upload Plugin → Choose File** → select
   the ZIP → **Install Now**.
3. Activate **ItihRag AI Chatbot**.
4. Go to **ItihRag → Settings** and configure at least one LLM provider and
   one embedding provider.

## From the WordPress.org plugin directory

1. Go to **Plugins → Add New** and search for "ItihRag AI Chatbot".
2. Click **Install Now**, then **Activate**.
3. Go to **ItihRag → Settings** and configure at least one LLM provider and
   one embedding provider.

## From source (for developers)

```bash
git clone https://github.com/mahavirvataliya/itih-ai-chatbot.git itih-ai-chatbot
cd itih-ai-chatbot
composer install --no-dev
```

Then copy/symlink the `itih-ai-chatbot` folder into
`wp-content/plugins/` and activate it.

## Requirements

| Requirement | Minimum | Notes |
|-------------|---------|-------|
| PHP | **8.0** | 8.1+ recommended |
| WordPress | **6.0** | |
| MySQL | **5.7** | MySQL **9.0+** unlocks native `VECTOR` storage & search; otherwise a JSON + PHP fallback is used |
| Embedding provider | one of | OpenAI · OpenAI-compatible · Cloudflare Workers AI · Ollama |
| LLM provider | one of | OpenAI · OpenAI-compatible · Anthropic · Cloudflare Workers AI · Groq · Ollama |

## Uninstalling

Uninstalling is **safe by default** — your data is kept. Enable
**Advanced → Remove data on uninstall** in settings to drop all tables and
options when the plugin is deleted.

---

Once installed, head to the [Quick start]({{ site.baseurl }}/quick-start/) guide.
