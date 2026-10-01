# 9CF File Schema v2

Primary format: JSON. Recommended extension: `.9cf`.

```json
{
  "format": "9cf-post",
  "version": 2,
  "contract_id": "9cf-example-contract",
  "mode": "blank",
  "goal": "",
  "target": {
    "post_id": 123,
    "post_type": "page",
    "title": "Programme Overview"
  },
  "source": {
    "site": "https://example.com/",
    "modified_gmt": "2026-08-18T12:00:00+00:00",
    "wordpress_version": "7.0",
    "latest_revision_id": 456,
    "content_hash": "...",
    "gutenberg_primary": true,
    "acf_available": true,
    "nine_elements_available": true
  },
  "structure_fingerprint": "...",
  "fields": [
    {
      "number": 1,
      "code": "F001",
      "display_label": "001 — Post title",
      "id": "wp:title",
      "section": "Post",
      "provider": "wordpress",
      "label": "Post title",
      "type": "text",
      "value": "",
      "editable": true,
      "ai_fill": true,
      "hint": "Main WordPress title.",
      "baseline_hash": "...",
      "validation": {},
      "source": {"core_key": "title"},
      "bridge": {
        "meta_key": "ninecf_...",
        "acf_type": "text"
      }
    }
  ]
}
```

## Stable identity

`field.id` is authoritative for import. `number`, `code` and `display_label` are present for humans and AI progress reporting.

## Providers

- `wordpress` — core post properties.
- `block` — Gutenberg / 9 Elements field or attribute at a stable block path.
- `taxonomy` — categories/tags/custom taxonomies already registered for the post type.
- `acf` — ACF bridge field.
- `meta` — public/registered WordPress/plugin post meta.
- `plugin` — semantic provider supplied through the 9CF integration API.

## AI editing rule

An AI should edit only `field.value`, unless the user explicitly asks it to alter a structure/settings field. It must preserve the complete field list, IDs, codes, providers, source data and structure fingerprint.


## Schema v2 safety additions

Schema v2 remains backward-compatible with v1 imports and adds:

- `contract_id` for audit/recovery correlation.
- `source.wordpress_version`, `source.latest_revision_id`, and `source.content_hash`.
- Per-field `baseline_hash` for optimistic field-level conflict detection.
- Per-field `validation` metadata when known (enum, required, registered type, min/max/length).
- Safe Apply classifications: safe, protected, conflicted, invalid, unknown, and safely remapped Gutenberg fields. Cross-path remapping requires a stable block identity (anchor or named block); ordinary unanchored blocks are never guessed.
- Provider validation/sanitisation hooks before writes.

AI should still edit `field.value` only and preserve all structural/safety metadata. Fields with `ai_fill=false` are intentionally skipped by the AI import path even if an AI changes them.
