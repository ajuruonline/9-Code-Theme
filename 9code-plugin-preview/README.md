# 9code Plugin Preview App

If the user says **install 9code Plugin previewer**, do that before any other work on the plugin.

This folder is the previewer. It shows design, settings screens, and simple links. It does not run PHP or plugin JavaScript, and it is not a WordPress install.

## Install into a plugin repo

1. Copy this whole folder into the repository as `9code-plugin-preview/`.
2. Do not replace the plugin's PHP, readme, or existing files.
3. Read the plugin's settings screen, shortcode, widget, and public markup.
4. Write that markup into both of these, keeping them the same:
   - `9code-plugin-preview/preview.json`
   - the `<script type="application/json" id="embedded-preview">` block in `9code-plugin-preview/index.html`
5. Tell the user to open `9code-plugin-preview/index.html`.
6. In the chat, describe the Index and the Admin screen, and paste the `preview.json` you wrote.

Opening the file from disk uses the embedded block. A local server uses `preview.json`. Update both so either way shows the same plugin.

## preview.json

```json
{
  "nine": 1,
  "plugins": [
    {
      "name": "Settings",
      "slot": "admin-page",
      "active": true,
      "html": "<h1>Settings</h1>",
      "css": ""
    }
  ]
}
```

`pressframe: 1` is also accepted. Use at most 24 plugins.

| Slot | Where it prints |
|---|---|
| `announcement` | Index, under the masthead |
| `after-title` | Index, under the essay title |
| `after-content` | Index, end of the essay |
| `sidebar` | Index sidebar |
| `footer` | Index footer |
| `admin-notice` | Top of the admin desk |
| `admin-page` | Its own admin screen |
| `dashboard-widget` | Admin dashboard |

## Rules for the markup

- A control needs class `button`. The blue one needs `button button-primary`. If the class is missing, the preview outlines it.
- A link that starts with `#` stays on the page. Any other link is shown and does not leave the preview.
- Do not put `<script>` in the HTML. It is stripped.
- Keep CSS as plain selectors. Skip `@media` and `@import`.
- Do not invent a WordPress install, a database, or a passing test. Say the preview is design and settings only.

## What to say back

After installing or updating the preview, answer with:

- the path `9code-plugin-preview/index.html`
- which slots you filled
- one sentence on what the Index shows and one on what Admin shows
