# Secure Scholar Plugin v1

**Extreme simplicity in front. Advanced protection behind the interface.**

Secure Scholar is an Open Scholar product for protecting and opening learning content. The online WordPress edition and offline Windows edition use the same `.scholar` content file and `.scholarpass` password/access file.

## Basic workflow

To secure a file: choose the file, choose how long it should work, choose the opening credits and viewing time, press **Secure file**, then download or share the secure file and password file.

To open a file: import the `.scholar` file, import its `.scholarpass` password file, press **Open file**, then view the content and remaining opening credits.

No cryptographic terms, licence terminology or format grammar appears in the basic workflow. Optional PIN, exact dates, installation binding, seeking restrictions, offline-only access, backup and technical information remain under **Advanced Settings**.

## Editions

| Edition | Purpose | Main difference |
| --- | --- | --- |
| WordPress | Existing Open Scholar site and phone shortcut | 100 MB online limit; Word/PowerPoint should be saved as PDF |
| Windows | Own Secure Scholar WebView2 window; offline use | larger allowance, direct Office preparation with LibreOffice, Windows capture-exclusion request |

The Windows edition does not open Chrome or Edge as a normal browser. It uses the Windows WebView2 component inside the Secure Scholar application window.

## Supported content

- MP4 and WebM video
- PDF documents and slides
- PNG and JPEG images
- self-contained HTML lessons or saved web pages
- Word, PowerPoint, OpenDocument, RTF and text through desktop LibreOffice-to-PDF preparation

Multi-file websites and live third-party embeds require a future signed course-bundle format. YouTube and other platform players must retain controls required by their platform rules; Secure Scholar does not obscure required third-party controls.

## Deterrence layers

- AES-256-GCM encrypted content with authenticated chunks
- PBKDF2-SHA256 key protection
- ECDSA-signed password/access rules
- expiry, opening credits and session duration
- optional PIN and installation binding
- anti-copy, anti-print, anti-drag and blocked viewer shortcuts
- no normal decrypted-file download or share control
- recipient watermarking
- protected local usage history and clock rollback checks
- interaction curtain for static self-contained web lessons
- Windows capture-exclusion request where supported

These controls are designed to make casual copying and sharing difficult. An external camera or sufficiently privileged/modified system can still capture visible content.

## Installation

Upload `Secure_Scholar_Plugin_v1.0.0.zip`, activate it, then add **Secure Scholar** to the existing Open Scholar dashboard or menu. Users keep the existing Open Scholar phone shortcut.

Build the Windows executable on Windows using `desktop/Build_Secure_Scholar_EXE.ps1` or the included GitHub Actions workflow. Test and code-sign the executable before public distribution.

Powered by OpenScholar.ng.
