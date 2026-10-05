# Nine Code app inventory for Data Manager 11.0

Inventory of the 29 Nine Code repositories (October 2026). Every plugin with code was installed in the
disposable test site (`.wp-test`, from the branch listed) and checked with generic discovery and the
`adapters` integration suite. Repositories without code did not block the work; they are listed so the
inventory is complete.

## Apps with code

| App (version, repo / branch) | Data | Data Manager support | Editable | Protected (and why) |
| --- | --- | --- | --- | --- |
| **Open Scholar** 4.6.0 (`open-scholar` / `codex/data-manager-bridge`) | `os_publication` (14 record types), scholar profiles (user meta `_os_*`), `os_scholar` taxonomy | Dedicated provider in the plugin | Every OSCH_Fields field valid for the record's type, title, type, status (Open Scholar publishing rule), scholar allocation, profile fields | `_os_activity_log`, archive record types (`_os_enabled_types`), `os_scholar` terms (derived from allocation) |
| **Conference.lat** 1.7.0 (`conference-lat` / `codex/data-manager-bridge`) | `alp_proceeding` (Conferences), `alp_article` (Conference Notes); post meta only, **no custom tables** | Dedicated provider in the plugin (wraps the Table Manager's validation and writes) | Every Conference Table column and Conference form field, including the speakers, editors, LOC, registration and section JSON repeaters | Conference allocation (`_alp_proceeding_id`), slot (`_alp_slot`), pending participant submissions, PDF checksum, Scholar readiness; creating/deleting Conferences and creating notes stay in the Table Manager. There are no separate delegate, sponsor or venue entities. |
| **9CODE Teaching Player** 1.8.0 (`e-lecture-engine` / `claude/excel-import-mvp`) | `nine_tp_lecture` + `_9tp_*` meta | Bundled adapter | Author, video, description, layout ranges, accent, flags, learning blocks (cleaned by the app's `sanitize_blocks`) | Contrast mode, block theme and onboarding keys (maintained by the app) |
| **9/10 LectureBoard** 2.1.0 (`e-lecture-engine` / `claude/lectureboard-hardening`) | `nine_real_lecture` + `_nine_lb_*` meta; learner state in user meta | Bundled adapter | Video/back URLs, goal, minutes, autoplay, sticky, blocks JSON (stored exactly as typed, must parse) | Learner progress, notes and preferences (`_nine_lb_*` user meta: personal data) |
| **Live Lecture / Google Meeting** 2.5.0 (`e-lecture-engine` / `master`) | `gm_meeting` + `_gm_*` meta | Bundled adapter | Start, duration, intro summary, minutes, recording URL, agenda (repeater) | Host password and code hashes (**secret**); Meet URL (allowed-host check lives in the app editor); reminder subscribers and access lists (personal data); design/appearance keys |
| **9CODE Digital Showglass** 1.1.1 (`e-lecture-engine` / `claude/digital-showglass-v1.1.1`) | `dsg_showglass`, `dsg_item`, `dsg_order`, `dsg_category` | Generic discovery (meta registered with `auth_callback` + `show_in_rest`) | All REST-registered showglass/item meta, ACF fields, categories | WhatsApp webhook URL and token (**secret**); author phone; every order field (customer details, items, reference, delivery status: written by the checkout) |
| **Workshop Lecture** 1.1.1 (`e-lecture-engine` / `claude/workshop-v1.1.1`) | `nls_workshop`; custom table `{prefix}nws_progress` | Generic discovery for workshops; bundled **read-only** custom entity for the progress table | Workshop posts and taxonomies | Workshop structure and lecturer settings (`_nws_*`); all progress records (written by participants' sessions) |
| **9CODE Post Learning Box** 1.4.1 (`e-lecture-engine` / `claude/post-learning-box-v1.4.1`) | `_9plb_*` meta on regular posts; learner notes in user meta | Generic discovery | Post fields | `_9plb_*` keys (private, not registered for REST) and learner notes. To make them editable, register them with `auth_callback` + `show_in_rest`, or ship a provider. |
| **9stagram** 0.7.0 (`9stagram` / `claude/9stagram-baseline-0.7.0`) | `ninestagram_offer`, `ninestagram_order`, offer category; profile user meta | Generic discovery | Offer posts, categories, unprefixed user meta (`ninestagram_*`) | `_ninestagram_*` offer/order meta (private), the order payload |
| **9CODE Ultra Home Archive Page** 1.2.29 (`9flix` / `fix-and-enhance-1.2.25`) | `_nine_uha_*` meta on hub pages | Generic discovery | Page fields | `_nine_uha_*` layout keys (private) |
| **Nine Code theme** 16.1.0 / **Nine Code Data** 11.0.0 (this repo) | Theme presentation meta (`_ncu_*`, registered), forms, data versions | Generic discovery + plugin's own screens | Theme presentation meta registered with `auth_callback` + `show_in_rest` | `_ncu_render*`, `_npm9_*`, `_ncd_*` internal state |

**Archives:** `publicationpress` stores earlier versions of several apps (Google Meeting Engine 1.0, Conference App 1.1,
Publications Author Engine 0.1, 9WhatsApp 0.1, Prompt Engine 1.7–1.9) plus a `temporary-9link` copy.
They are superseded by the apps above. If one is installed, generic discovery lists its post types, and
private keys stay protected. `9-code-brain` contains documentation and environment notes, with no plugin code.

**Repositories without code:**
- `9-code-ultra`, `9-code-ultra-child`, `9-code-ultra-core`, `99-learning-engine`, `9link`, `author-archive-engine`, `author-groups`
- `electure`, `insights`, `live-lecture`, `nine-action-menu`, `nine-backup-manager`, `nine-embed`, `nine-featured-media`
- `nine-gallery`, `nine-learning-navigation`, `nine-learning-speaker`, `nine-toc-side-tab`, `pcg`, `postlink`, `prompt-engine`

## How to move an app from "generic" or "bundled" to a dedicated provider

1. Register the app's provider from the app plugin (see `docs/DATA-PROVIDER-CONTRACT.md`), reusing the
   app's own sanitizers and save routine as Open Scholar and Conference.lat do.
2. Keep field keys `meta:<key>` (or map them in `migrations`). Existing History entries keep working.
3. The bundled adapter steps aside automatically when the app claims its post type or registers a
   provider with the same id (`teaching-player`, `lectureboard`, `live-lecture`, `workshop`).
