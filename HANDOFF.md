HANDOFF CONTEXT
===============

USER REQUESTS (AS-IS)
---------------------
- "as i can see there are lot of issue with wordpress approval so identify all fixes whats current name and rename with my name like ItihRag AI chat bot that can be easily pass to submissions"
- "also improve performance of current plugin"
- "fetch latest releases and update patch and i will upload new one"
- "push and do it"
- [Pasted WordPress.org Plugin Check report: "Checks complete. 301 errors and 23 warnings found." — TextDomainMismatch x301 expecting 'openrag-ai-chatbot', outdated_tested_upto_header, MissingTranslatorsComment x3, DirectDB warnings on class-chat-controller.php:616/642, NonPrefixedVariableFound in uninstall.php and templates/chatbot-widget.php]
- "what about those errors and warnings"
- "save this chat history and changes in file for other harness"

GOAL
----
Finish answering the Plugin Check errors/warnings accounting and get the plugin through WordPress.org submission under the new slug itih-ai-chatbot.

WORK COMPLETED
--------------
- I audited the whole plugin with two parallel explore agents (WP.org compliance + performance) and read the prior PHPCS reports (openrag-*-php-20260728-*.md in repo root, now stale).
- I found docs/REPLY-wordpress-review.md — the author's draft reply to the WP.org reviewer; the rename to "ItihRag AI Chatbot" was already half-done (uncommitted) in the working tree before this session.
- I completed the rename: ITIH_OPTION_PREFIX 'openrag_' -> 'itih_' with automatic option migration in Plugin::migrate_legacy_options() (hooked via maybe_create_schema on 'init', not admin_init, so frontend sees migrated settings), AS hooks renamed (itih_process_document, itih_index_post, itih_schedule_document, itih_schedule_post), transients (itih_rl_, itih_q_, itih_embedding_dim), localStorage keys (itih_session/itih_secret), shortcode [itih_chat] with [openrag_chat] legacy alias, dead openrag_health_check cron removed.
- I deliberately KEPT: DB table prefix openrag_ (5 tables) and CSS/DOM classes openrag-/ #openrag-widget — documented data-preservation defense in the reviewer reply.
- Security fixes: nonce+capability checks BEFORE any $_POST access in Settings_Page::maybe_save() and KB_Page::handle_wp_index_save(); document-loader local reads restricted to wp_get_upload_dir()['basedir']; SSE catch block emits generic localized error and logs raw message only when general.debug_logging is set; permission_public() simplified to explicit always-true with CSRF rationale comment; invalid %1s placeholders replaced everywhere (interpolated Schema::table() names + phpcs:ignore comments).
- Performance fixes: Schema::supports_native_vector() reads persisted vector_store[mysql_native_vector] option before probing (was CREATE/DROP TABLE per request); MySQL_Store fallback query rewritten to keyset-paginated 500-row batches selecting only id+embedding, normalized query vector, top-k hydration in one IN() query (was ~100MB buffered scan of 5000 rows that silently dropped old chunks); ingestion batch-embeds ~100 texts per API call with per-chunk fallback; chatbot.js defers loadHistory() to first widget open; chats-page uses correlated MIN(a2.id) join (ONLY_FULL_GROUP_BY-safe) and streams CSV export in 1000-row batches; dashboard uses one aggregate query cached 60s in transient itih_dash_stats; new DB indexes chats.role and chats(session_id,id); composer.json switched broken PSR-4 to classmap; Settings::group() memoized per-group.
- JS fixes: admin.js chat-detail modal rebuilt from row data-* attributes (data-chat-id, data-created-at, data-content, data-reply, data-reasoning, data-citations JSON, data-model, data-feedback, data-tokens, data-response-ms) emitted by chats-page.php — replaces a permanently-400 $.getJSON('/history') call; every interpolation escaped via esc() helper; i18n keys added (cancel in CFG.i18n; queuedPosts/reasoning/sources/noData/tokens/ms in A.i18n with translators comments).
- Version bumped 1.0.5 -> 1.1.1 everywhere (main header, ITIH_VERSION, ITIH_DB_VERSION, readme Stable tag, CHANGELOG.md, readme changelog + upgrade notice).
- Fixed all real items from the user's pasted Plugin Check report: Tested up to 7.0 -> 7.1; 3 translators comments added in Admin/class-admin-menu.php; phpcs:ignore comments on chat-controller.php session-secret queries cover WordPress.DB.DirectDatabaseQuery + PreparedSQL.NotPrepared + PluginCheck.Security.DirectDB; $openrag_* variables renamed $itih_* in uninstall.php and templates/chatbot-widget.php.
- Verified everything: php -l clean on all changed PHP, node --check clean on both JS files, zero %1s remaining, zero stray openrag_ registrations (only intentional DB prefix/form-field names/migration code remain).
- Built dist/itih-ai-chatbot-1.1.1.zip (bin/build-release.sh 1.1.1; added --exclude='.omo' to the script after its hidden-file guard caught the tooling dir).
- Committed as 9 atomic commits (repo style: semantic with scopes), pushed main, tagged v1.1.1, pushed tag. GitHub Actions release.yml auto-published the release with the ZIP asset.

CURRENT STATE
-------------
- Working tree clean except untracked .omo/ (orchestration tooling dir — NEVER commit it).
- HEAD = 7f52607 on main, tag v1.1.1 on same commit, remote github.com/mahavirvataliya/openrag-ai-chatbot (repo still has OLD name; WP.org slug requested is itih-ai-chatbot).
- Release v1.1.1 live at https://github.com/mahavirvataliya/openrag-ai-chatbot/releases/tag/v1.1.1 with itih-ai-chatbot-1.1.1.zip asset (workflow-built from tagged source).
- Latest previously published tag was v1.0.5; v1.1.0 was only uploaded to the WP.org review thread, never tagged.
- All Plugin Check items from the user's report are resolved in code; the report itself was generated against an intermediate build installed under the OLD folder name.

PENDING TASKS
-------------
- Deliver the final errors/warnings accounting to the user (the answer, verified but not yet sent when handoff was requested):
  - 301 TextDomainMismatch errors are FALSE POSITIVES: Plugin Check derives the expected text domain from the installed plugin FOLDER name, which is still openrag-ai-chatbot on the test site. They vanish once the new ZIP is installed (creates folder itih-ai-chatbot) or once WP.org hosts it under the new slug. No code change needed or wanted.
  - DirectQuery/NoCaching/UnescapedDBParameter warnings on class-chat-controller.php:616/642 are silenced by the phpcs:ignore prefix comments now on those lines (verified present); direct DB is legitimate there (custom table, one-row secret lookup, no cache appropriate).
  - NonPrefixedVariableFound warnings: fixed ($itih_* renames).
  - outdated_tested_upto_header ERROR: fixed (7.1). MissingTranslatorsComment ERRORS: fixed (3 comments).
- User should delete the old openrag-ai-chatbot folder on the test WP install, install dist/itih-ai-chatbot-1.1.1.zip, re-run Plugin Check to confirm near-zero findings, then upload the ZIP to the WP.org review thread and send the reply text from docs/REPLY-wordpress-review.md.
- Optional: paste richer release notes into the GitHub release UI (gh CLI token lacks contents:write so API edits 404; SSH push works fine).

KEY FILES
---------
- itih-ai-chatbot.php — entry; version 1.1.1, ITIH_OPTION_PREFIX 'itih_', shortcode registrations
- includes/class-plugin.php — migration machinery, hooks wiring, localize arrays (CFG.i18n cancel), is_widget_enabled uses group('chat')
- includes/Database/class-schema.php — cached VECTOR probe, chats role/session_msg indexes, %1s cleanup
- includes/VectorStores/class-mysql-store.php — rewritten batched fallback scan, memoized is_native()
- includes/Ingestion/class-ingestion-pipeline.php — batched embedding loop, renamed itih_ hooks
- includes/Admin/class-chats-page.php — MIN(a2.id) join, batched export, view-button data-* contract consumed by admin.js
- includes/Chat/class-chat-controller.php — nonce/session handling, generic SSE errors, phpcs:ignore'd secret queries
- assets/js/admin.js + assets/js/chatbot.js — escaped data-driven modal; deferred history fetch
- docs/REPLY-wordpress-review.md — reviewer reply draft (updated for completed rename)
- bin/build-release.sh — release packaging (excludes .omo)

IMPORTANT DECISIONS
-------------------
- Rename scope: registration-level identifiers renamed to itih_; DB tables + CSS classes intentionally kept as openrag_ for data continuity and because the reviewer reply already defends them — do NOT "finish" those without asking.
- Option migration runs inside the db-version-mismatch branch (prefix change guarantees it fires once on update) and is hooked to 'init' so frontend requests see migrated settings immediately.
- Embeddings storage stays JSON LONGTEXT — packed-BLOB migration deliberately skipped (add only if user asks for more speed).
- Composer autoload is a classmap (PSR-4 could never resolve class-*.php filenames); bin/build-release.sh runs composer install at build time.
- Commit style detected and used: SEMANTIC with scopes (feat!/fix(scope)/perf/docs/chore/style), Sisyphus attribution footers.

EXPLICIT CONSTRAINTS
--------------------
- None stated verbatim by the user beyond the requests above; standing config: ponytail mode active (minimal diffs, no speculative abstractions).

CONTEXT FOR CONTINUATION
------------------------
- LSP/intelephense reports false "Undefined function" errors for EVERY WordPress function (no WP stubs installed) — ignore them entirely; verify PHP with `php -l` and JS with `node --check`.
- The subagent pool failed mid-session (insufficient balance / model-not-found on all fallbacks); all implementation was done directly in-session instead of delegated.
- gh CLI can push via SSH and create/read releases but PATCHing release notes returns 404 (token lacks contents:write).
- .omo/ is local orchestration state — excluded from builds, must stay untracked.
- The stale audit artifacts openrag-ai-chatbot-openrag-ai-chatbot-php-*.md sit in repo root (tracked, committed long ago); build excludes them — harmless, could be deleted in a cleanup commit if desired.
- WP.org review context: reviewer already flagged naming once (readme 1.1.0 notes the rename); the reply draft promises slug itih-ai-chatbot and documents every external service. Upload ZIP via the "Add your plugin" flow BEFORE replying in the thread.
