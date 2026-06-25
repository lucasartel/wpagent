# WPAgent

WPAgent is a free WordPress plugin for building AI agents with local knowledge, user memory, WordPress AI / Connectors, and an optional OpenRouter fallback.

It is designed for WordPress projects that need more than a single chatbot widget: each agent can have its own prompt, model preference, training documents, guest access rules, memory settings, token limits, and optional action workflows.

## Highlights

- Multiple configurable agents through a private `WPAgent Agents` post type.
- `[wpagent_chat agent="agent-slug"]` shortcode for front-end chat.
- Optional floating public and admin assistants.
- Persistent conversation history for logged-in users.
- Optional per-agent user profile memory.
- Training document uploads and chunk indexing for TXT, Markdown, CSV, JSON, HTML, DOCX, and best-effort PDF extraction.
- Retrieval-augmented prompts with keyword search and optional OpenRouter embeddings.
- WordPress AI / Connectors as the recommended provider layer.
- Direct OpenRouter requests as an optional built-in provider or fallback.
- Per-agent token limits for day, week, and month.
- Admin token usage and email action reports.
- Optional periodic site-care tasks through WP-Cron.
- Optional WordPress Abilities API bridge for admin actions with human confirmation.
- Optional email proposals that only send after explicit user confirmation.

## Installation

1. Download or build a release zip.
2. Upload the `wpagent` folder to `/wp-content/plugins/`.
3. Activate WPAgent in the WordPress admin.
4. Configure a provider:
   - Recommended: install the official WordPress AI plugin and configure a provider under `Settings > Connectors`.
   - Optional fallback: add an OpenRouter API key under `WPAgent > Settings`.
5. Create an agent under `WPAgent > Agents`.
6. Add the chat shortcode to a page or post.

```text
[wpagent_chat agent="default"]
```

Or use a custom agent slug:

```text
[wpagent_chat agent="educadoria" title="Assistente Educadoria"]
```

## Local Development

This workspace is prepared for WordPress Playground.

```bash
npm install
npm run wp:start
```

The Playground blueprint installs and activates:

- WordPress AI
- AI Provider for OpenRouter
- WPAgent mounted from `./wpagent`

Admin login in the local Playground blueprint:

- Username: `admin`
- Password: `password`

## Scripts

```bash
npm run lint:php
npm run build:zip
```

`npm run lint:php` runs lightweight plugin sanity checks.

`npm run build:zip` creates an installable plugin zip in `dist/` and stages `readme.txt` and `LICENSE` inside the packaged `wpagent` folder.

## Architecture

- `wpagent/wpagent.php`: plugin bootstrap.
- `wpagent/includes/class-wpagent-plugin.php`: wires services and WordPress hooks.
- `wpagent/includes/class-wpagent-agents.php`: multi-agent configuration, post type, shortcode metadata, and training uploads.
- `wpagent/includes/class-wpagent-ai-client.php`: WordPress AI Client and OpenRouter adapter.
- `wpagent/includes/class-wpagent-document-indexer.php`: extracts text and chunks large training documents.
- `wpagent/includes/class-wpagent-embeddings.php`: generates chunk embeddings and runs semantic search.
- `wpagent/includes/class-wpagent-periodic-tasks.php`: schedules conservative site-care tasks through WP-Cron.
- `wpagent/includes/class-wpagent-prompt-builder.php`: combines system prompt, indexed knowledge, memory, and recent interactions.
- `wpagent/includes/class-wpagent-repository.php`: persistence for interactions, conversations, memory, sources, chunks, embeddings, and email events.
- `wpagent/includes/class-wpagent-rest-controller.php`: REST endpoints for chat, conversations, profile, admin abilities, and email actions.
- `wpagent/includes/class-wpagent-shortcode.php`: front-end and floating chat UI.
- `wpagent/includes/class-wpagent-admin-abilities.php`: WordPress Abilities API bridge for confirmed admin actions.
- `wpagent/includes/class-wpagent-email-actions.php`: confirmed email proposals and queued sends.

## Large Document Training

WPAgent stores training material in source and chunk tables instead of saving entire extracted documents in post meta. At chat time, it searches indexed chunks for the current user message and sends only the most relevant snippets to the model.

PDF uploads are supported as best-effort extraction. If a PDF has no selectable text or extraction quality is poor, WPAgent marks the source as insufficient and excludes those chunks from chat context. For production sites, use clean TXT/Markdown input or connect an OCR pipeline with the `wpagent_extract_pdf_text` filter.

## Security and Privacy

WPAgent follows the WordPress plugin security model:

- Agent configuration is restricted to administrators through `manage_options`.
- The admin assistant is only rendered and callable by administrators.
- Public chat is available only for agents explicitly configured to allow guests and is rate-limited for visitors.
- OpenRouter API keys are not printed back into the settings form; leaving the field blank preserves the saved key.
- Administrative actions use WordPress capability checks and require human confirmation in the chat UI.
- Email actions require a signed proposal and explicit user confirmation before `wp_mail()` is called.
- `pdftotext` through `shell_exec` is disabled by default. To opt in for a controlled server, define `WPAGENT_ALLOW_PDFTOTEXT_EXEC` as true or use the `wpagent_allow_pdftotext_exec` filter.

WPAgent can store conversation history, memory, user profile content, token usage, document metadata, and email event records. Site owners should disclose this in their privacy policy and review privacy export/erase requirements before production use.

## Roadmap

- Add first-class OCR support for scanned PDFs.
- Add explicit memory review, edit, and delete screens.
- Add privacy export/erase integration for LGPD/GDPR.
- Add an approval queue for periodic tasks that need human confirmation.
- Add WordPress Coding Standards / PHPCS checks.
- Prepare a WordPress.org submission workflow.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
