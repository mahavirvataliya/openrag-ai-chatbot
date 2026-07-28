---
title: Publishing guide
nav_order: 14
description: How maintainers publish releases to GitHub and WordPress.org.
---

# Publishing guide

This document covers the **two release channels** for OpenRag AI Chatbot, end-to-end:

1. **GitHub Releases** — source + ready-to-install ZIP for any user.
2. **WordPress.org Plugin Directory** — discoverable to every WordPress user via
   *Plugins → Add New* search.

You don't have to do both, but the WordPress.org directory is what most users
expect when they hear "WordPress plugin". The two channels are complementary:
GitHub is your development home; WordPress.org is your distribution home.

---

## Table of contents

1. [Prerequisites](#prerequisites)
2. [Repository layout for publishing](#repository-layout-for-publishing)
3. [Channel 1 — GitHub Releases](#channel-1--github-releases)
4. [Channel 2 — WordPress.org Plugin Directory](#channel-2--wordpressorg-plugin-directory)
   - [One-time: submit the plugin for review](#one-time-submit-the-plugin-for-review)
   - [Each release: SVN deploy](#each-release-svn-deploy)
5. [Release checklist](#release-checklist)
6. [Versioning policy](#versioning-policy)
7. [Translation workflow](#translation-workflow)
8. [Asset reference](#asset-reference)
9. [Troubleshooting](#troubleshooting)

---

## Prerequisites

Install these once on the maintainer's machine:

```bash
# macOS
brew install subversion composer librsvg wp-cli

# Debian / Ubuntu
sudo apt-get install -y subversion composer librsvg2-bin gettext
# wp-cli: see https://make.wordpress.org/cli/handbook/installing/
```

You also need:

- A **GitHub account** with push rights to the repo.
- For the WordPress.org channel: a **WordPress.org account** and an
  **approved plugin slug** (after review — see below).

---

## Repository layout for publishing

The repository is structured so the same source can drive both channels:

```
openrag-ai-chatbot/
├── openrag-ai-chatbot.php            # Main plugin file (Plugin Name headers + version)
├── readme.txt                # WordPress.org-format readme (required for wp.org)
├── README.md                 # GitHub-format readme
├── CHANGELOG.md              # Full changelog (Keep a Changelog format)
├── LICENSE                   # GPL-2.0 text
├── composer.json             # Dependencies + autoload
├── uninstall.php
├── includes/                 # PHP source (PSR-4 under OpenRag\)
├── templates/                # Frontend templates
├── assets/                   # Built CSS/JS (shipped with plugin)
├── languages/                # .pot/.po/.mo translation files
├── .wordpress-org/           # Files that ONLY go to wp.org SVN, never into the plugin ZIP
│   ├── assets/               # Banner, icon (SVG sources + rasterized PNGs)
│   └── screenshots/          # Screenshot-1.png … (referenced from readme.txt)
├── bin/                      # Maintainer scripts (not shipped in ZIP)
│   ├── build-release.sh            # Build a GitHub release ZIP
│   ├── deploy-wordpress-org.sh     # SVN deploy to wp.org
│   ├── generate-wordpress-assets.sh# Rasterize SVGs to PNGs
│   └── make-pot.sh                 # Generate languages/openrag-ai-chatbot.pot
├── docs/                     # Maintainer docs (not shipped)
│   └── PUBLISHING.md
└── .github/workflows/        # CI + GitHub release automation
    ├── ci.yml
    └── release.yml
```

The two key conventions:

- **`.wordpress-org/`** never ships inside the plugin ZIP. It only goes to SVN.
- **`bin/`** never ships inside the plugin ZIP. It's maintainer tooling.

The build scripts (and the GitHub Actions workflow) honor these exclusions
automatically.

---

## Channel 1 — GitHub Releases

GitHub Releases are the fastest way to ship. They produce a downloadable ZIP
that anyone can install via *Plugins → Add New → Upload Plugin*.

### One-time setup

Nothing required — the `.github/workflows/release.yml` workflow handles
everything on tag push.

### Each release

```bash
# 1. Bump the version constant in openrag-ai-chatbot.php (if not already bumped)
#    define( 'OPENRAG_VERSION', '1.0.1' );

# 2. Update CHANGELOG.md and readme.txt (Stable tag + Changelog section).

# 3. Commit and push to main.
git add -A
git commit -m "Release 1.0.1"
git push origin main

# 4. Tag and push — this triggers the release workflow automatically.
git tag v1.0.1
git push origin v1.0.1
```

The workflow will:

1. Lint every PHP file.
2. Stage a clean build with `composer install --no-dev`.
3. Produce `dist/openrag-ai-chatbot-1.0.1.zip` with the vendor bundle included.
4. Create a GitHub Release named **OpenRag AI Chatbot 1.0.1** with install instructions
   in the body and the ZIP attached.

Verify at `https://github.com/mahavirvataliya/openrag-ai-chatbot/releases`.

### Manual alternative

If you'd rather build locally (e.g., to test before tagging):

```bash
./bin/build-release.sh 1.0.1
gh release create v1.0.1 dist/openrag-ai-chatbot-1.0.1.zip --generate-notes
```

---

## Channel 2 — WordPress.org Plugin Directory

This is a **two-phase** process: a one-time submission + review, then per-release
SVN deploys.

### One-time: submit the plugin for review

WordPress.org requires a manual review of every new plugin. This typically takes
**1–14 days**.

1. **Build a clean ZIP** (no `.git`, no `vendor/` is OK, but `.wordpress-org/`
   and `bin/` must be excluded):

   ```bash
   ./bin/build-release.sh 1.0.0
   ```

2. **Final review of your submission.** Make sure:
   - `openrag-ai-chatbot.php` has all 8 required headers (Plugin Name, Description,
     Version, Author, License, Text Domain, etc.).
   - `readme.txt` has `=== OpenRag AI Chatbot ===` matching your `Plugin Name`, a valid
     `Stable tag`, `Requires at least`, `Tested up to`, `Requires PHP`, `License`,
     and `License URI`.
   - `LICENSE` exists and contains GPL-2.0.
   - All strings are translation-ready (`__()`, `_e()`) — your `readme.txt`
     references the `openrag-ai-chatbot` text domain.
   - No call-home / telemetry / upsell code. WordPress.org rejects these.
   - No external HTTP requests without a clear purpose and disclosure.
   - All SQL uses `$wpdb->prepare()`.

3. **Submit** at <https://wordpress.org/plugins/developers/submit/>.
   - "Plugin Name" = `OpenRag AI Chatbot`.
   - "Plugin Slug" = the URL-safe name you want (lowercase, dashes).
     This becomes your permanent URL: `wordpress.org/plugins/<slug>/`.
   - Upload the ZIP.

4. **Wait for review.** You'll get an email from `plugins@wordpress.org` with
   either approval (and your SVN URL) or specific feedback to address.

5. **After approval**, you'll receive a SVN URL like
   `https://plugins.svn.wordpress.org/openrag-ai-chatbot`. Save it — you'll use it on
   every release.

> ⚠️ **Slug lock-in**: the slug you submit with is permanent. Choose carefully.
> All `bin/deploy-wordpress-org.sh` defaults assume `openrag-ai-chatbot`.

### Each release: SVN deploy

Once your plugin is approved, every release is published by committing to SVN.
The `bin/deploy-wordpress-org.sh` script handles the full flow:

```bash
./bin/deploy-wordpress-org.sh 1.0.1 --tag
```

What it does:

1. Builds a clean production ZIP (`composer install --no-dev`).
2. Checks out the SVN repo to a temp dir.
3. Wipes `trunk/` and copies the build in.
4. Refreshes `/assets/` from `.wordpress-org/assets/` (banner + icon).
5. Optionally creates `/tags/1.0.1` via `svn cp`.
6. Runs `svn add`/`svn rm` to reconcile state.
7. Shows you the `svn st` and prompts for confirmation.
8. Commits with message `Release 1.0.1 (tagged)`.

Within **~15 minutes** WordPress.org rebuilds the public ZIP and your release
goes live at `https://wordpress.org/plugins/openrag-ai-chatbot/`.

#### SVN credentials

Save your WordPress.org username to avoid being prompted every time:

```bash
# Option A: environment variables (for CI / one-off use)
export SVN_USERNAME='your-wporg-username'
export SVN_PASSWORD='your-wporg-password'   # or use an application password

# Option B: svn auth cache (recommended for local dev)
svn ls https://plugins.svn.wordpress.org/openrag-ai-chatbot
# Enter credentials when prompted, choose "no" to plaintext-save question
# unless you're confident in your machine's security.
```

---

## Release checklist

Run through this **every** release, regardless of channel.

### Version & changelog
- [ ] `OPENRAG_VERSION` constant bumped in `openrag-ai-chatbot.php`.
- [ ] `Stable tag:` line in `readme.txt` matches the new version.
- [ ] New `== Changelog ==` entry added to `readme.txt` with `= X.Y.Z =` subsection.
- [ ] New `## [X.Y.Z]` section added to `CHANGELOG.md`, with `[Unreleased]` and
      `[X.Y.Z]` links updated at the bottom.

### Code & assets
- [ ] `./bin/generate-wordpress-assets.sh` re-run if branding changed (regenerates PNGs).
- [ ] `./bin/make-pot.sh` re-run (regenerates `languages/openrag-ai-chatbot.pot`).
- [ ] All new strings wrapped in `__()` / `_e()` / `esc_html__()` etc.
- [ ] `php -l` clean across the tree (`find . -name '*.php' -not -path './vendor/*' -exec php -l {} \;`).
- [ ] Composer lock updated if dependencies changed.

### Smoke test
- [ ] Build the ZIP: `./bin/build-release.sh X.Y.Z`.
- [ ] Install on a clean WordPress site, activate, configure one provider, run a chat.
- [ ] Verify the widget renders, streaming works, citations appear.
- [ ] Verify no PHP warnings/notices in `wp-content/debug.log` with `WP_DEBUG=true`.

### Publish
- [ ] Commit + push to `main`.
- [ ] GitHub: `git tag vX.Y.Z && git push origin vX.Y.Z` (or `gh release create …`).
- [ ] WordPress.org (if applicable): `./bin/deploy-wordpress-org.sh X.Y.Z --tag`.
- [ ] Verify release notes render on GitHub.
- [ ] Verify the wp.org plugin page shows the new version within 15 min.

---

## Versioning policy

OpenRag AI Chatbot follows [Semantic Versioning](https://semver.org/):

| Bump type | When to use |
|-----------|-------------|
| `MAJOR` (`2.0.0`) | Breaking changes (DB schema rewrite, REST API incompatibility, min WP/PHP bumped) |
| `MINOR` (`1.1.0`) | New features, new providers, new admin tabs — backwards compatible |
| `PATCH` (`1.0.1`) | Bug fixes, security patches, doc updates |

Always:

- Update the version in **3 places**: `OPENRAG_VERSION`, `Stable tag:`, and
  the changelog.
- Tag git as `v1.0.1` (with leading `v`), but the WordPress.org SVN tag is just
  `1.0.1` (no `v`).

---

## Translation workflow

The plugin is fully internationalized. The `openrag-ai-chatbot` text domain is loaded
automatically.

### For maintainers (generate the template)

```bash
./bin/make-pot.sh
# → produces languages/openrag-ai-chatbot.pot
```

Commit the updated `.pot` whenever you add or change user-facing strings.

### For translators (add a language)

1. Copy `languages/openrag-ai-chatbot.pot` to `languages/openrag-ai-chatbot-<locale>.po`
   (e.g. `openrag-ai-chatbot-fr_FR.po`).
2. Translate the `msgstr ""` values using Poedit, Lokalise, or any text editor.
3. Compile to `.mo`: `msgfmt openrag-ai-chatbot-fr_FR.po -o openrag-ai-chatbot-fr_FR.mo`.
4. Submit a PR with both files.

The plugin loads the right `.mo` file automatically based on the site's
`WPLANG` setting.

### Translating via WordPress.org

After the plugin is on WordPress.org, translators can use
<https://translate.wordpress.org/projects/wp-plugins/openrag-ai-chatbot> (the GlotPress
system) and the plugin will automatically receive translations without a code
release. This is the recommended path once the plugin is approved.

---

## Asset reference

Files in `.wordpress-org/assets/` and what WordPress.org does with them:

| File | Required | Used for |
|------|----------|----------|
| `icon-128.png` | Recommended | Plugin icon next to the title in search results |
| `icon-256.png` | Optional (retina) | Crisp icon on high-DPI displays |
| `icon.svg` | Optional | Vector source — not displayed by wp.org |
| `banner-772x250.png` | Recommended | Header banner on the plugin page |
| `banner-1544x500.png` | Optional (retina) | Crisp banner on high-DPI displays |
| `screenshot-1.png` … `screenshot-N.png` | Optional | Displayed in the "Screenshots" section; `== Screenshots ==` entries in `readme.txt` are captions |

Naming is **strict** — WordPress.org only auto-discovers files matching these
exact patterns. Don't use `icon-256x256.png` or `banner.png`.

---

## Troubleshooting

### "svn: E170013: Unable to connect to a repository"
You need SVN access. Confirm your WordPress.org account was granted commit
rights to the plugin's SVN repo. SVN access is granted as part of the
review-approval email.

### The plugin ZIP on wp.org didn't update after my SVN commit
WordPress.org caches the build for ~15 minutes. If after an hour it's still
stale, check:

```bash
svn log -l 3 https://plugins.svn.wordpress.org/openrag-ai-chatbot
```

If your commit shows but the page is stale, trigger a rebuild:
<https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/#checking-out/>

### "Plugin zip file is too large" / "exceeds 25MB"
The release ZIP must be under 25 MB. If `vendor/` is bloating it, audit deps
or move heavy ones to a lazy-load pattern. Currently OpenRag AI Chatbot's release ZIP
is ~470 KB.

### Review feedback mentioning "uses curl_exec / file_get_contents"
Reviewers flag direct HTTP calls. OpenRag AI Chatbot uses `wp_remote_*()` everywhere
(the recommended API) — no raw cURL or `file_get_contents` for HTTP. The only
local `file_get_contents` calls are for reading local file paths
(`vendor/autoload.php`, temp DOCX files), which is allowed.

### readme.txt validation fails
Run wp-cli's validator before submitting:

```bash
wp i18n make-pot . --debug  # also surfaces some issues
# or use the online validator: https://wordpress.org/plugins/readme-validator/
```

### "The plugin has not been tested with the latest version of WordPress"
Bump `Tested up to:` in `readme.txt` to the latest major (e.g. `6.8`).
WordPress.org re-checks on each commit.

---

## Summary of the maintainer's release loop

```bash
# Once per release:
$EDITOR openrag-ai-chatbot.php                 # bump OPENRAG_VERSION
$EDITOR readme.txt                     # bump Stable tag + add changelog entry
$EDITOR CHANGELOG.md                   # add changelog section + compare links
./bin/make-pot.sh                      # refresh translations template

git add -A && git commit -m "Release X.Y.Z"
git push origin main

# GitHub:
git tag vX.Y.Z && git push origin vX.Y.Z    # → Actions builds & publishes

# WordPress.org (after first approval):
./bin/deploy-wordpress-org.sh X.Y.Z --tag
```
