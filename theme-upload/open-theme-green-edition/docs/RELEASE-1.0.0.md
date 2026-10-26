# Open Theme Green Edition 1.0.0

First release under the new name. Continues from Open Scholar Theme 20.2.6. The folder and slug stay
`nine-code`, so it installs over 20.2.6 in place and keeps all settings, records and archives.

## Changes

- **Name and version:** "Open Theme Green Edition" 1.0.0. The internal upgrade version continues from
  the 20.x series (`NCU_CORE_VERSION` 20.2.7), so upgrade routines still run on existing sites.
- **Edit button on the side rail:** a pencil opens the editor for whatever is on screen: a post,
  page, blog index, category, tag or author. It appears only when the user may edit that item.
- **No passwords in exports:** the "confirm your password" prompt is removed from quick exports and
  archive downloads, and the option to include detected passwords, API keys and tokens is gone.
  Exports still need administrator permission and a CSRF check. Password hashes, session
  tokens and application passwords were already never exported.
- **Exports that cannot crash the site:** whole-site and media quick exports run as the resumable
  chunked job when shared-hosting safe mode is on (the default). Any export error returns a notice
  instead of a white screen.
- **Interface:** a final visual layer (`assets/css/green-edition.css`) adds a grid-and-glow
  workspace hero, monospace instrument labels, glass app tiles with a neon edge, neon focus rings and a
  glass side rail that is slimmer and translucent on phones. Reduced-motion users get no animation.

## Fixes

- The Data & Recovery screen always warned that "a separate backup plugin owns the backup engine",
  even with no such plugin. The check now runs before the bundled engine loads.
- The Data & Recovery overview threw a JavaScript error (`reading 'split'`) because it read a field
  that exists only on the Advanced Export tab.
- The side rail showed the same document icon for Dashboard and Edit. They now have a gauge and a pencil.

## Verified

On a test WordPress install, desktop (1280px) and phone (390px):
- Dashboard, Data & Recovery and a front-end page load with HTTP 200 and no JavaScript errors.
- A posts export downloads a valid ZIP with no password prompt.
- A users export contains no password hashes.
- All changed PHP files pass `php -l`.

Not verified: import of a full-site package, and Google Drive upload (no Drive account in the test
environment).
