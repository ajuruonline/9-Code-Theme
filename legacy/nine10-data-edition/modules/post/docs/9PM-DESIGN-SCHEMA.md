# 9PM Design File Schema — Version 1

A `.9pm` file describes how existing WordPress/9CF fields should be presented. It does **not** create a parallel database and it does not require ACF.

## Core contract

```json
{
  "format": "9pm-design",
  "version": 1,
  "name": "Human-readable design name",
  "description": "What the design is for",
  "engine": "blocks",
  "field_contract": [],
  "settings": {},
  "sections": []
}
```

`engine`:

- `blocks` — Gutenberg + 9 Elements. Recommended production route.
- `elementor` — experimental per-post Elementor JSON route.

For backward compatibility, v4 also accepts the older `acf_contract` key.

## Field contract

```json
"field_contract": [
  {
    "alias": "name",
    "field": "full_name",
    "field_id": "meta:npm9f_full_name",
    "type": "text",
    "required": true
  }
]
```

Matching order in v4:

1. Exact stable `field_id` when supplied.
2. Legacy ACF key when supplied and ACF is present.
3. Exact field alias/name.
4. Alias equal to a discovered WordPress/9CF field.
5. Administrator manual mapping.

## Block engine settings

```json
"settings": {
  "placement": "replace",
  "template": "default"
}
```

`placement`: `replace`, `prepend`, or `append`.

Linked designs may rebuild when their bound field values are saved through 9 Post Manager. v4 preserves the manual-edit detection from v2.0.1: if Gutenberg/Elementor was manually changed after the last generated output, automatic rebuild is blocked until the administrator deliberately rebuilds or detaches the design.

## Supported section layouts

- `stack`
- `card`
- `hero`
- `grid`
- `columns`

Useful tones include `card`, `soft`, `outline`, `feature`.

## Content elements

9 Elements:

- `heading`
- `paragraph`
- `text`
- `image`
- `audio`
- `video`
- `list`
- `icon-list`
- `tabs`

9PM composition helpers:

- `gallery`
- `button`
- `separator`
- `spacer`

## Portable placeholders for Elementor

Preferred v4 syntax:

```text
{{field:full_name}}
```

Legacy syntax remains accepted:

```text
{{acf:full_name}}
```

The alias is resolved through the stored 9PM field bindings, so the source can be a Site Field, WordPress/native meta, semantic plugin field or legacy ACF field.
