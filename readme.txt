=== WPAgent ===
Contributors: wpagent
Tags: ai, chatbot, agents, rag, openrouter
Requires at least: 7.0
Tested up to: 7.0.1
Requires PHP: 7.4
Stable tag: 0.5.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create AI agents for WordPress with per-agent prompts, training documents, user memory, WordPress AI Connectors, and OpenRouter fallback.

== Description ==

WPAgent lets WordPress site owners create personalized AI agents that can answer through a front-end shortcode, use uploaded training documents as context, remember user-specific information, and work with WordPress AI / Connectors or OpenRouter.

The plugin is designed for projects that need more than a single API-key chatbot. Each agent has its own slug, prompt, provider settings, training sources, context limits, guest access rules, and optional features such as user profiles, email proposals, embeddings, and admin-only assistants.

= Core features =

* Private WPAgent Agents post type for multiple agent configurations.
* `[wpagent_chat agent="agent-slug"]` shortcode for front-end chat.
* Persistent conversation history for logged-in users.
* Optional user profile memory per agent.
* Training document uploads and chunk indexing for TXT, Markdown, CSV, JSON, HTML, DOCX, and best-effort PDF extraction.
* Optional semantic search with OpenRouter embeddings processed in WP-Cron batches.
* WordPress AI / Connectors as the recommended provider layer.
* Direct OpenRouter support as an optional fallback/provider.
* Token usage tracking and per-agent day/week/month limits.
* Optional public floating assistant and admin-only floating assistant.
* Optional email proposals that send only after explicit user confirmation.
* Optional admin actions through the WordPress Abilities API with human confirmation before execution.

= Important notes =

PDF extraction is best-effort. For scanned PDFs or files with poor embedded text, paste a clean TXT/Markdown version into the manual training field or connect an OCR pipeline with the `wpagent_extract_pdf_text` filter.

Administrative actions and email actions are never executed automatically from a model response. WPAgent extracts a proposal and shows a confirmation button in the chat UI.

== Installation ==

1. Upload the `wpagent` folder to `/wp-content/plugins/`.
2. Activate WPAgent in the WordPress admin.
3. Configure a provider in the official WordPress AI plugin under Settings > Connectors, or configure an OpenRouter key under WPAgent > Settings.
4. Create an agent under WPAgent > Agents.
5. Add the agent shortcode to a page or post.

Example:

`[wpagent_chat agent="default"]`

== Frequently Asked Questions ==

= Does WPAgent require the official WordPress AI plugin? =

No. WordPress AI / Connectors is the recommended provider layer, but WPAgent can also call OpenRouter directly when an OpenRouter API key is configured.

= Can visitors use the chat without logging in? =

Yes, but only for agents that explicitly allow guest chat. Guest chat is rate-limited.

= Does WPAgent store personal data? =

Yes. Depending on configuration, WPAgent can store conversation history, profile memory, training-source metadata, token usage, and email event records. Site owners should disclose this in their privacy policy and review privacy export/erase requirements before production use.

= Can the AI change WordPress content automatically? =

No. Admin abilities require an administrator, WordPress capability checks, and explicit confirmation in the UI before execution.

== Changelog ==

= 0.5.0 =
* New: recurring and sequenced (drip) email scheduling per agent.
* The agent proposes schedules from its instructions; the user confirms in the chat; WordPress Cron handles the sends with AI-generated content per send.
* Per-agent enable flag, admin panel for schedules and subscribers, 1-click unsubscribe link, and consent tracking.

= 0.4.15 =
* Fix garbled accented characters (mojibake) in Portuguese/Spanish interface strings.
* Add per-agent default chat theme (light/dark) option in the agent editor.
* Handle chat timeout and server error pages with localized messages.

= 0.4.14 =
* Current open source preparation release.
* Includes multi-agent configuration, REST chat, memory, document indexing, embeddings, reports, floating assistants, email proposals, and admin ability proposals.
