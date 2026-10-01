=== 9 AI Manager ===
Contributors: 9igeria Online Limited
Tags: ai, workflow, wordpress, acf, plugin manager, gutenberg
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 2.0.0

Human-controlled AI-to-WordPress operating hub and 9 ecosystem communication protocol.

== Description ==
9 AI Manager is the visible bridge between an AI workspace such as ChatGPT/Gemini and WordPress. It deliberately avoids a hidden AI connector: the administrator downloads the current site contract, gives it to AI, receives a file back, previews the proposed changes and explicitly approves them.

Preferred workflow:
1. Click “Check Updates + Download AI Instruction File”.
2. 9 AI Manager refreshes WordPress plugin/theme update information and scans the live installation.
3. Give the .9ai-site.json file to AI with the human instruction and source material.
4. AI returns nine-ai-package/v2 JSON/ZIP for content/site work.
5. Upload the package to the appropriate AI → screen and review the preview.
6. Apply only after approval. Edit the WordPress result normally.

Plugin-code workflow:
1. Open Plugin Integration and click “Download for AI” beside an installed plugin.
2. The generated AI Work Pack contains the current plugin source, the fresh site contract, the plugin’s discovered/native contract and update instructions.
3. Give the work pack plus the coding instruction to AI.
4. AI returns an installable WordPress plugin ZIP preserving the existing plugin folder/main file and increasing the version.
5. Upload it to AI → Plugin Update.
6. 9 AI Manager verifies the target path/version, creates a rollback backup, applies only after approval and records the transaction.

== AI routes ==
- AI → Anything
- AI → Post
- AI → Page
- AI → Category
- AI → Landing Page
- AI → Site / Plugin Settings
- AI → User
- AI → Plugin Update

== Site AI File ==
Every preferred AI-file download runs a fresh readiness scan before export. The v2 manifest contains:
- WordPress/theme version and visible update state
- Installed plugins, versions, active state and visible updates
- Native/automatic 9 AI integration status
- WordPress post types and taxonomies
- ACF schema when available
- registered meta and automatically discovered plugin meta keys
- menu locations
- Gutenberg blocks and shortcodes
- bounded plugin source discovery for post types, taxonomies, settings/option keys, meta keys, blocks, REST namespaces and Elementor widgets
- native integration schemas
- site_fingerprint and manifest_hash so stale/wrong-site AI packages can be rejected

No option values, passwords, API keys, salts or private tokens are exported by the readiness scanner.

== Native Integration API ==
Every future 9 ecosystem plugin release should register a contract with the nine_ai_manager_integrations filter.

Example:

add_filter('nine_ai_manager_integrations', function($items) {
    $items['my_plugin'] = array(
        'plugin_file' => plugin_basename(MY_PLUGIN_FILE),
        'plugin_slug' => 'my-plugin',
        'label' => 'My Plugin',
        'description' => 'What AI can understand/configure.',
        'capabilities' => array('read', 'configure'),
        'schema' => array('known_setting' => 'string'),
        'instructions' => array('Use only declared fields/settings.'),
        'handler' => 'my_safe_ai_handler',
    );
    return $items;
});

Plugins without a native contract are not invisible. 9 AI Manager creates an automatic bounded contract from their installed WordPress/source declarations until a native-ready plugin release is installed.

== Security ==
- manage_options capability required for admin operations.
- Nonce protection on upload, preview/apply, downloads, settings and rollback.
- Fresh site fingerprint validation for nine-ai-package/v2.
- Preview-first workflow.
- ZIP path traversal protection and file-count/size limits.
- Plugin ZIP must preserve the existing plugin folder/main path.
- Existing plugin is backed up before approved AI replacement.
- Option/plugin-setting changes are disabled by default and restricted to known/allowed keys.
- User operations are disabled by default; AI-supplied passwords are ignored.
- Content defaults to draft.
- No secrets are exported in the site readiness contract.

== Changelog ==
= 2.0.0 =
- Added fresh WordPress/plugin/theme readiness scan before AI instruction export.
- Added site_fingerprint and manifest_hash targeting.
- Added plugin inventory with native/automatic integration modes.
- Added bounded source discovery for plugin capabilities.
- Added nine-ai-instruction-file/v2 and nine-ai-package/v2 workflow.
- Added AI → Site / Plugin Settings and AI → User routes.
- Added Plugin Integration dashboard.
- Added per-plugin AI Work Pack containing current source + current site contract.
- Added AI → Plugin Update with path verification, backup, apply and rollback history.
- Added safe media, user and plugin-settings package entities.
- Retained v1 package compatibility.

= 1.0.0 =
- Initial human-controlled AI manager.
- Site AI File / manifest export.
- JSON and ZIP package support.
- AI → Post, Page, Category, Landing Page and Anything routes.
- ACF/meta/taxonomy/featured-media support.
- Menu and restricted option support.
- Extensible plugin integration API.
- History and supported rollback.
