# 9PM AI Authoring Guide — Gutenberg / 9 Elements

## Purpose

Create a portable `.9pm` presentation recipe for one WordPress post/page/CPT. In v4 the data contract is **9CF / WordPress-native fields**, not ACF. ACF can still appear as a legacy provider on older sites, but it is not required.

Use the **9PM Field Design Brief** or a numbered `.9cf` export supplied by the user. Do not rename or invent stable 9CF field IDs.

## Required top-level structure

```json
{
  "format": "9pm-design",
  "version": 1,
  "name": "Design name",
  "description": "Short purpose",
  "engine": "blocks",
  "field_contract": [],
  "settings": {
    "placement": "replace",
    "template": "default"
  },
  "sections": []
}
```

Legacy files using `acf_contract` are still accepted.

## Field contract

Use a stable `field_id` whenever it is available. `field` is the human alias used in the design recipe.

```json
{
  "alias": "full_name",
  "field": "full_name",
  "field_id": "meta:npm9f_full_name",
  "type": "text",
  "required": true
}
```

Possible providers include WordPress core, 9PM Site Fields/native meta, semantic plugin fields, taxonomies and legacy ACF.

## Data versus presentation

- `.9cf` = information/content contract.
- `.9pm` = presentation recipe.
- Gutenberg / 9 Elements = normal editable output.
- ACF = optional legacy/specialist provider only.
- Elementor = optional specialist design route.

Do not move content into a different field merely to achieve a visual effect.

## Available 9 Elements

Use these element types when available:

1. `heading`
2. `paragraph`
3. `text`
4. `image`
5. `audio`
6. `video`
7. `list`
8. `icon-list`
9. `tabs`

9PM composition elements also include `gallery`, `button`, `separator`, `spacer`, plus Gutenberg Groups/Columns.

## Design language

Prefer a small number of strong mobile-first sections:

- `hero` for identity or the primary outcome.
- `card` for bounded information.
- `grid` for comparable repeated items.
- `columns` for a deliberate desktop split that collapses naturally on mobile.
- `tabs` for several long related bodies of text.
- `icon-list` for concise qualifications, benefits or features.

Avoid making every field its own card. Group related information.

## Example

```json
{
  "format": "9pm-design",
  "version": 1,
  "name": "Academic Profile",
  "description": "Mobile-first profile using native 9CF fields.",
  "engine": "blocks",
  "field_contract": [
    {"alias":"photo","field":"photo","field_id":"meta:npm9f_photo","type":"image","required":false},
    {"alias":"name","field":"full_name","field_id":"meta:npm9f_full_name","type":"text","required":true},
    {"alias":"title","field":"academic_title","field_id":"meta:npm9f_academic_title","type":"text","required":false},
    {"alias":"bio","field":"biography","field_id":"meta:npm9f_biography","type":"richtext","required":false}
  ],
  "settings":{"placement":"replace","template":"default"},
  "sections":[
    {
      "layout":"hero",
      "tone":"feature",
      "elements":[
        {"type":"image","source":"photo","preset":"inherit","hide_if_empty":true},
        {"type":"heading","source":"name","level":1,"preset":"inherit"},
        {"type":"paragraph","source":"title","preset":"inherit","hide_if_empty":true}
      ]
    },
    {
      "layout":"card",
      "title":{"value":"About"},
      "elements":[
        {"type":"paragraph","source":"bio","preset":"inherit","hide_if_empty":true}
      ]
    }
  ]
}
```

## Safety

- Preserve field IDs and aliases exactly.
- Do not create scripts, PHP, shortcodes or executable code inside the design package.
- Hide empty optional sections rather than printing placeholders.
- Prefer theme/9 Elements inheritance over hard-coded site styling.
