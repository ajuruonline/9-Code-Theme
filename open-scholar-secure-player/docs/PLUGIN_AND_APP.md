# Plugin and web apps: what each one is, and where to do things

## The three pieces
| Piece | What it is | Who uses it |
|---|---|---|
| **WordPress plugin** (`Open_Scholar_Secure_Player_Plugin_v1.1.0.zip`) | Installs inside the Open Scholar WordPress site. Adds routes, shortcodes, an admin menu and a tutorial post. | Site visitors (player), signed-in editors (maker) |
| **Open Scholar Secure Player** (`index.html`, the web app) | Stand-alone app that opens ordinary files, modules and protected lessons. Installs to a device with the cap icon and works offline. | Learners, on any device |
| **Open Scholar Secure File Maker** (`maker.html`, the web app) | Stand-alone app that makes protected lesson files (.scholar) and password files (.scholarpass). | Publishers only |

The plugin does not replace the apps. It serves the same two pages from inside the site.

## Differences at a glance
| | WordPress plugin | Web app (Player / Maker) |
|---|---|---|
| Needs WordPress | Yes | No |
| Where it lives | Your site: `/secure-scholar/` and `/secure-scholar/owner/` | Any web address, or installed on the device |
| Opens in its own window with the cap icon | No (it is a page on the site) | Yes, once installed. The browser tabs and bars are hidden |
| Works offline | Only after the page has been cached | Yes, after the first visit |
| Shows the Maker | Only to signed-in editors on the owner page | Maker is a separate page you choose to run |
| Modules (folders of files) | Stored in each visitor's browser, not on the site | Stored on the device, in the Player |
| Publishes protected lessons | Through the owner page | Through the Maker |

## Where to do things
- **Organise your own files into modules:** in the Player. Press New module in the library, give it a title and description,
  then Add files. You can add videos, audio, PDFs, pictures and text together, arrange their order, and open each one from
  the module. Word and PowerPoint files can be kept as copies; save them as PDF to view them here.
- **Protect a lesson for learners:** in the Maker (one protected file at a time). Each protected lesson is a single file.
- **Protected modules** (one password for several files in order) are not built yet. They need a new file format,
  and that is a decision for the owner before work starts.
- **Full screen:** the Full screen button in the title bar, or install the app to open it without browser tabs.
