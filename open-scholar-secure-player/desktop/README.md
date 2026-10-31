# Secure Scholar Windows edition

This is the offline desktop edition of Secure Scholar v1. It opens in its own application window using the Microsoft WebView2 runtime included with current Windows 10/11 installations. It does not open Chrome, Edge or another normal browser window.

The Windows and WordPress editions use the same `.scholar` and `.scholarpass` files. A file prepared online opens on Windows, and a file prepared on Windows opens online when it is within the online size limit.

Desktop additions:

- own Secure Scholar application window;
- larger local file allowance;
- direct Word/PowerPoint preparation through LibreOffice;
- best-effort Windows capture exclusion;
- no internet requirement after installation.

To create `SecureScholar.exe`, run `Build_Secure_Scholar_EXE.ps1` on Windows. The build output must be tested on a clean Windows 10/11 computer and code-signed before public distribution.

Capture exclusion deters common Windows capture tools but cannot stop an external camera or every privileged/system-level recorder.
