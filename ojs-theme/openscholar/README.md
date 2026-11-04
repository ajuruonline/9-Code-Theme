# Open Scholar theme for Open Journal Systems (OJS)

This theme gives a journal the Open Scholar header, footer and look. It is a theme plugin for OJS 3.5.
It changes only the header, the footer and the styles. OJS itself is not changed.

## What it does

- **Header:** a green Open Scholar bar with the Open Scholar cap, the journal name, a search button and the sign-in and register links. Under it is the journal's menu, which is the normal OJS menu.
- **Phone:** the menu opens from a full-width "Menu" bar.
- **Footer:** a green Open Scholar band with the journal name, the journal's own footer text, and the "Powered by Open Journal Systems" mark.
- **Look:** Open Scholar colours, rounded cards and buttons, and a clear focus outline for keyboard users.
- **Built on the default theme,** so every OJS page, form and setting keeps working.

## Install (for a journal)

1. Zip the `openscholar` folder so that the zip contains `openscholar/` at its top level.
2. In OJS, open **Settings → Website → Plugins** (the Plugins tab) and choose **Upload A New Plugin**. Pick the zip and install it.
3. Make the theme active: **Settings → Website → Appearance**, then set **Theme** to **Open Scholar**.

Install it through the Plugins tab. Do not copy it into OJS's core folders.

## Files

| File | Purpose |
| --- | --- |
| `index.php`, `OpenscholarThemePlugin.php` | Theme plugin. Uses the default theme as its parent. |
| `version.xml`, `settings.xml` | Plugin version and default settings. |
| `templates/frontend/components/header.tpl` | Open Scholar header. |
| `templates/frontend/components/footer.tpl` | Open Scholar footer. |
| `styles/openscholar.css` | Open Scholar colours, spacing and phone layout. |
| `images/open-scholar-cap.jpg` | The Open Scholar cap logo. |

## Notes

- Tested: PHP syntax, the plugin XML against OJS's own DTDs, and template tag balance, against the OJS 3.5 source (`stable-3_5_0`).
- Not yet tested: a live journal. Check the header, footer and phone menu on a journal before publishing.
- To change the colours, edit the `:root` block at the top of `styles/openscholar.css`.
