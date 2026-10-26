# Open Theme 2.0.0 (launch)

Renamed from "Open Theme Green Edition" to **Open Theme**. Version 1.0 was the pre-launch build; 2.0.0 is the launch version.
The folder and slug stay `nine-code`, so it installs over 1.x in place and keeps settings and records.

## Since 1.0.x

- Quick Actions: All Posts and All Pages, a list for every taxonomy, grouped sections with search, up to 60 actions (1.0.1).
- Exports never ask for a password and never include passwords or API keys (1.0.0).
- Edit button on the side rail for the current page, post, category, tag or author (1.0.0).
- Visual layer `assets/css/open-theme.css` (1.0.0).
- Fixed: a false "separate backup plugin owns the engine" warning, and a JavaScript error on Data & Recovery (1.0.0).

Internal upgrade schema continues from the 20.x series (`NCU_CORE_VERSION` 20.2.7), so upgrade routines still run.

Verification: docs/ULTRON-RABBIT-2.0.0.md.

## 2.0.1 — Post editor

- The editor's top bar keeps only Back, Add block, Undo, Redo, Settings, Save and View. Everything else
  (Elementor, third-party plugin buttons, Preview, Document overview, Options) moves into one branded
  editor menu, opened by the site icon at the top right. It opens to half the screen and closes when pressed again.
- Meta boxes are accordions. All start closed, and each has a one-line plain-language explanation.
- Form-style cards for the meta boxes (rounded, bordered, system font).

Not yet done: a full form-style redesign of the block canvas itself, and a branded version of Gutenberg's
own ⋮ options panel (it opens from the menu, but keeps Gutenberg's styling).
