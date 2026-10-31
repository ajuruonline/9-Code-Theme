# Open Scholar Secure Player — User Guide (version 1.1.0)

Powered by OpenScholar.ng

## 1. What it is
Open Scholar Secure Player lets you open lessons, PDFs, videos, pictures and saved web pages in one simple place.
It can also protect a file so that only the people you choose can open it, for a set time and a set number of times.

- **Open a file**: no password needed. Pick the file and read or watch it. Your place is saved on your device.
- **Protect a file**: for teachers and publishers. You get two files: the lesson (`.scholar`) and its password (`.scholarpass`). Send both.
- **Open a protected file**: import the `.scholar` file, then its `.scholarpass` file, then press Open.

Nothing you open is uploaded anywhere. Files are read on your own device.

## 2. Where it runs
| Where | What you use | What you need |
|---|---|---|
| **Open Scholar website** | The Secure Scholar page inside the site (`/secure-scholar/`) | A web browser. The site owner adds it with the plugin. |
| **Phone** | The same Open Scholar shortcut you already use on your phone | Nothing new to install. Do not install a second app. |
| **Windows PC** | `OpenScholarSecurePlayer.exe`, a window of its own (not Chrome or Edge) | Windows 10 or 11 with WebView2 (normally already installed). |

## 3. Installing (site owners)
1. In WordPress go to **Plugins → Add New → Upload Plugin**.
2. Upload `Open_Scholar_Secure_Player_Plugin_v1.1.0.zip`, then press **Install Now** and **Activate**.
3. Open **Secure Scholar** in the dashboard. It shows the tutorial post and the shortcodes.
4. Add a menu or dashboard item named **Secure Scholar** that points to `/secure-scholar/`.
5. To place it on a page, add the shortcode `[secure_scholar size="medium"]` (sizes: `small`, `medium`, `big`).
6. The protected publishing page `/secure-scholar/owner/` needs sign-in and an account that can edit posts. Keep it out of public page caches.

## 4. Installing (Windows PC)
1. Download `OpenScholarSecurePlayer.exe`.
2. Check it: open PowerShell in the download folder and run `Get-FileHash OpenScholarSecurePlayer.exe -Algorithm SHA256`. Compare with the checksum in the `.sha256` file.
3. Double-click it. A window opens. No installation is needed; keep the file wherever you like.
4. Windows may show a warning because this build is **not code-signed yet** (see section 8). Choose "More info" → "Run anyway" only if the checksum matches.

## 5. Using it
### Open a file (everyone)
1. Press **Open a file**.
2. Pick a PDF, MP4 or WebM video, PNG or JPG picture, or a saved web page (.html).
3. Read with **Previous / Next**, use **Enlarge** for a bigger page, or press **Play** to watch.
4. Press **Close file** to go back to Home. Your place is kept on this device.
5. **My reading** lists files you opened recently. Open the file again to carry on.

### Open a protected file (learners)
1. Press **Open a protected file**.
2. Import the lesson file (`.scholar`), then the password file (`.scholarpass`).
3. If you were given a PIN, type it.
4. Press **Open file**. You will see how many openings you have left.

### Protect a file (teachers)
1. Press **Protect a file** and choose the file.
2. Answer the three questions: how long it should work, how many openings, and minutes each time.
3. Press **Protect file**, then send **both** downloaded files together. Keep the password file separate until the learner needs it.
Advanced settings (optional): file name, recipient name, start and expiry dates, PIN, device lock, no skipping in videos, offline only.

## 6. Limits you should know
- Protection makes casual copying harder. It cannot stop every camera or screen recording.
- Opening counts are kept on the device. A determined person can reset the browser and start again. A server-based check is planned for a later version.
- Maximum protected file size online: 100 MB. The Windows app allows larger files.
- Word and PowerPoint files: save them as PDF first (the Windows app can convert them if LibreOffice is installed).
- Video skipping can be blocked only for protected files.

## 7. Troubleshooting
| Problem | What to do |
|---|---|
| "This file type cannot be opened here yet" | Save the file as PDF, MP4, WebM, PNG, JPG or a web page (.html). |
| The file does not open after I import it | Check you imported both files: the `.scholar` file and its matching `.scholarpass` file. |
| "Secure Scholar requires HTTPS" | Open the site through its secure (https) address. |
| Video does not play on my phone | The phone may not support that video format. Try MP4 (H.264). |
| The Windows app says files are missing | Download the complete app again and check the checksum. |

## 8. Code signing and trust (plain explanation)
**Code signing** means the software's publisher attaches a digital certificate to the program. Windows checks that
certificate, and if the file was changed after signing, the signature breaks. A signed program shows the publisher's
name and gets fewer security warnings.

Our current Windows build is **not signed**. It was built automatically on GitHub and its checksum is published, so you
can check it has not been changed. Before a public release it needs a code-signing certificate, which has a yearly cost
and must be bought from a certificate authority.

## 9. What is working and what is not yet tested
Working and tested in the development environment: protecting and opening files in a browser, ordinary opening of PDF,
video, pictures and web pages at 320 to 1280 pixels wide, the WordPress plugin files, and the Windows app build.

Not yet tested: the Windows app on a real Windows computer, the WordPress plugin on a live site with the current theme,
phones (Android and iPhone), and the security review of protected files.

## 10. Getting help
Contact the Open Scholar team through OpenScholar.ng.
