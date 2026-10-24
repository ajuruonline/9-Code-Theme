# Nine Code settings UI contract

This contract applies to the Theme, Data Manager and every current or future Nine Code app. Its purpose is to keep large settings and data-entry screens readable on a phone.

## Required behaviour

1. Every settings section and tab starts **closed** on every page load. The user chooses what to open.
2. Never use cookies, `localStorage`, URL state or a hard-coded `open` attribute to reopen a section automatically.
3. The first section uses a red marker/background and the next uses green; continue alternating red and green. Colour is a navigation aid, not a status or error signal, so text labels must still explain every section.
4. On screens up to 782px, section controls stack in one column, inputs use the full available width, text is at least 16px and interactive targets are at least 44px high.
5. Every section has a plain-language description. Every field uses its registered provider/field instructions when available. Describe consequences, units and safe defaults rather than repeating the label.
6. Opening one section may close its siblings. Tapping the open section again must be allowed to close it.
7. Use native `<details>/<summary>` where possible. Preserve keyboard operation, visible focus and `aria-expanded` for custom tab controls.

## Standard markup

```html
<div data-ninecode-settings-root>
  <details data-ninecode-settings-section>
    <summary>Publishing</summary>
    <div class="ninecode-settings-body">
      <p class="description">Controls when reviewed content becomes public.</p>
      <label data-ninecode-settings-field>...</label>
    </div>
  </details>
</div>
```

Nine Code Data automatically loads `assets/settings-ui.css` and `assets/settings-ui.js` on Nine Code admin pages. A future plugin with a different page slug can opt in:

```php
add_filter( 'ninecode_settings_ui_should_enqueue', function ( $load, $page ) {
    return $load || 'my-app-settings' === $page;
}, 10, 2 );
```

It may also call `ninecode_data_enqueue_settings_ui()` after confirming the function exists. A standalone plugin must ship equivalent CSS/JavaScript so its screen still works when Data Manager is inactive.

## Data-provider requirement

Provider entities should supply `description` and `notes`, and fields should supply `instructions` or `description`. Data Manager displays those values beside the relevant section or input and includes them in AI/export schemas. Missing explanations are treated as an integration-quality defect, although the field remains discoverable.

## Review checklist

- Load the screen at 390px and desktop width.
- Confirm no section is open after a fresh load or reload.
- Confirm odd/even sections and fields are visually distinct red/green groups.
- Open and close every section by touch, mouse and keyboard.
- Confirm every setting has enough explanation to make a safe choice.
- Run `bin/lint.sh`; its Settings UI contract gate rejects known automatic-open regressions.
