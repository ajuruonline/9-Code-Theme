# 9PM AI Authoring Guide — Elementor (Experimental)

Elementor is the specialist route. Gutenberg / 9 Elements remains the recommended day-to-day production layer.

A v4 `.9pm` Elementor wrapper can bind Elementor values to the same **9CF / WordPress-native field contract** used by Post Manager. ACF is optional legacy compatibility.

## Preferred wrapper

```json
{
  "format": "9pm-design",
  "version": 1,
  "name": "Specialist Elementor Page",
  "engine": "elementor",
  "field_contract": [
    {"alias":"name","field":"full_name","field_id":"meta:npm9f_full_name","type":"text","required":true}
  ],
  "settings":{"placement":"replace"},
  "elementor": {
    "version": "0.4",
    "type": "page",
    "page_settings": {},
    "content": []
  }
}
```

A normal Elementor exported JSON file is also accepted, but the `.9pm` wrapper is preferred when portable field bindings are required.

## Dynamic placeholders

Preferred:

```text
{{field:full_name}}
```

Legacy ACF-compatible syntax:

```text
{{acf:full_name}}
```

Both resolve through the current post's stored 9PM bindings. The preferred `field:` syntax does not require ACF.

## Bound fields

Use `field_contract` with stable 9CF field IDs where possible. Do not change the field ID merely to suit the Elementor layout.

## Scope and safety

- The Elementor route applies to the selected post only.
- It does not create global Theme Builder conditions.
- Elementor version/schema differences can affect imported layouts; treat this route as specialist and test on staging.
- Linked-design manual-edit protection remains active.
