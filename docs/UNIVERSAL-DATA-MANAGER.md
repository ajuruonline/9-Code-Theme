# Universal Data Manager (Nine Code Data 11.0)

**9 Data Manager → Data Workspace** is one admin screen, built for phone and desktop, for every data type
on the site: posts, pages, custom post types, taxonomies, users, ACF fields and each Nine Code app's
data. Data Manager discovers and edits that data, but the plugin that owns it validates and writes it
(see [DATA-PROVIDER-CONTRACT.md](DATA-PROVIDER-CONTRACT.md)).

## Who can use it

* Administrators (`manage_options`) and anyone granted `manage_ninecode_data` (given to administrators
  on activation).
* Per record, WordPress capabilities or the owning provider decide: an author with access edits only
  their own posts, cannot reassign authorship, cannot list users and can never publish without
  `publish_posts`.

## What you can do

| Task | How |
| --- | --- |
| Find a data type | Discovery screen, grouped by provider, with a search box. Each card shows editable / protected / ACF field counts. |
| Browse records | Search, status filter (including Trash), sort, page size, pagination. Rows become cards on phones. |
| Edit | Field types include text, rich text (TinyMCE with media), JSON, selects, checkboxes, media and galleries (media library), relationships (search pickers for posts, users and terms), ACF groups, repeaters and flexible content. |
| Protected data | Shown with a lock and the reason (for example "WordPress editing lock" or "Scheduled by the programme builder"). Secrets are never shown. |
| Publish | The status field changes only when **Allow status change** is ticked and you have the publish capability. Providers can add rules: Open Scholar keeps records without Authors as drafts. |
| Bulk edit | Select rows, then **Edit selected**. Each record is validated, records you cannot edit are reported, and the whole edit is one History entry. |
| Export | JSON AI package (records + field schema + editing instructions), XLSX (Data and Guide sheets) or CSV, for selected rows or the current search/filter (up to 5,000 records). Download only; nothing is sent to an AI service. |
| Import | Upload JSON/CSV/XLSX or paste JSON → **Check file** compares it with the site field by field → tick records/fields → **Apply**. Large files are compared and applied in batches with a progress bar. |
| Undo | **History** lists every change (editor, bulk, import, undo). Undo restores the previous values; fields changed again since then are skipped and listed, with an option to overwrite them. |

### What an import can never do silently

* **Create** records: needs "Allow creating records".
* **Trash** records (`_action: trash`): needs "Allow moving records to the trash".
* **Publish, unpublish or schedule**: needs "Allow status changes".
* **Write protected or unknown fields**: refused and listed in the preview.
* **Overwrite a value changed on the site since export**: shown as a conflict and unticked by default.

These actions appear in the preview whether or not they are allowed. A file for another data type is
refused, and so is a file over 50 MB, 20,000 records or 20,000 rows. Spreadsheet cells that start with `=`, `+`, `-` or `@` are
exported with a leading apostrophe (formula-injection guard) and restored on import.

### AI round trip

1. Export **AI package (JSON)** for the records you want help with.
2. Give the file to your AI assistant together with its `instructions`. Data Manager never contacts an AI
   service itself.
3. Import the returned file and review the preview. Only the ticked changes are applied.

Records carry a stable `_ref` (`id`, `revision`, per-field `base` hashes), so the import can detect
conflicts and wrong-type ids.

## Providers on this release

| Provider | Data | Where the adapter lives |
| --- | --- | --- |
| WordPress & plugins (generic) | every post type and taxonomy with UI, users, ACF, registered and discovered meta | Nine Code Data |
| Open Scholar | scholar records (all 14 types) and scholar profiles | Open Scholar 4.6.0 `class-os-data-manager-bridge.php` |
| Conference.lat | Conferences and Conference Notes | Conference.lat 1.7.0 `class-alp-data-manager-bridge.php` |
| Teaching Player, LectureBoard, Live Lecture | editorial fields of each app | Nine Code Data (bundled adapters) |
| Workshop Lecture | participant progress table (read-only) | Nine Code Data (bundled adapter) |

See [DATA-MANAGER-APP-INVENTORY.md](DATA-MANAGER-APP-INVENTORY.md) for every app, what is editable,
what is protected, and why.

## REST API (`ninecode-data/v1`, cookie + `X-WP-Nonce`)

`GET providers` · `GET entities/{p}/{e}` · `GET|POST records/{p}/{e}` · `GET|POST records/{p}/{e}/{id}` ·
`POST records/{p}/{e}/{id}/trash` · `POST validate/{p}/{e}` · `POST bulk/{p}/{e}` · `POST export/{p}/{e}` ·
`POST import/{p}/{e}/stage|preview|apply|discard` · `GET history` · `GET history/{id}` ·
`POST history/{id}/undo` · `GET lookup`

The 11.0-draft routes `ninecode/v1/data/schema`, `ninecode/v1/data/export/{post_type}` and
`ninecode/v1/data/import` remain. The import route only previews.

## Storage

* History: table `{prefix}ncd_history` (created on activation and on admin load after updates; keeps
  the latest 1,000 entries).
* Staged imports: `uploads/ncd-staging/{token}.php`. These are exit-guarded so they are never served,
  even on nginx; they belong to the user who uploaded them and are removed after apply/discard or 24 hours.
* No existing option names, meta keys, post types, taxonomies, REST routes or hooks were changed.
