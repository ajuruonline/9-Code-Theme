# Open Scholar — User Guide (version 1.2)

Powered by OpenScholar.ng

There are two web apps. Learners only ever need the first one.

| App | Who uses it | What it does |
|---|---|---|
| **Open Scholar Secure Player** | Learners | Plays lessons, PDFs and videos. Opens protected lessons when you send both files. Works offline once installed. |
| **Open Scholar Secure File Maker** | Publishers only | Makes the protected lesson file (.scholar) and its password file (.scholarpass). Learners never see it. |

The Player and the Maker are separate pages. The website shows the Player to everyone. The Maker is kept behind sign-in
on the owner page of the site.

## 1. For learners: the Player
### Install it (recommended)
1. Open the Open Scholar Secure Player link in Chrome, Edge or Safari.
2. Press **Install the player** (in the **i** panel), or on iPhone tap Share, then **Add to Home Screen**.
3. It now opens with its own window and the cap icon, and it keeps working without the internet.

### Open a normal file
1. Press **Open a file** (or **Open file** in the menu bar).
2. Pick a PDF, MP4 or WebM video, PNG or JPG picture, or a saved web page (.html).
3. Read with **Prev / Next**, or press **Play** to watch. Your place is saved on this device.

### Open a protected lesson
1. Press **Open a protected file**.
2. Choose the lesson file (.scholar), then the password file (.scholarpass).
3. If you were given an access code, type it.
4. Press **Continue**. The player shows **Before you view**: the list of what you can and cannot do with this lesson.
5. Tick **I have read these limits**, then press **Start viewing**. Only this press uses one of your openings.

The limits are deliberate. If the screen blurs or goes blank when you leave the window, that is the protection working,
not a crash. Click back into the player to continue.

## 2. For publishers: the Maker
1. Open the Maker from the owner page of the site, signed in with an account that can edit posts.
2. Choose the lesson, set how long it works, how many openings, and minutes for each viewing.
3. Optional: open **Advanced settings** to generate an access code, lock the lesson to one device, stop video skipping,
   or make it offline-only.
4. Press **Make the two files**, then send both files together. Send any access code separately.
5. Keep the **publisher backup** safe (publisher settings). Without it you cannot make new password files after clearing
   this browser.

## 3. Website (WordPress plugin)
- `/secure-scholar/` shows the Player to visitors.
- `/secure-scholar/owner/` shows the Maker to signed-in publishers only.
- Add the shortcode `[secure_scholar size="medium"]` (sizes: small, medium, big) to place the player on a page.

## 4. What is not possible, by design
- Copying, saving, downloading or printing a protected lesson.
- Right-click, drag and text selection on protected lessons.
- Skipping forward in a video if the publisher turned that off.
- Opening after the end date or after the openings are used up.

No software can stop every camera or screen recording. Protection makes copying much harder, not impossible.

## 5. Troubleshooting
| Problem | What to do |
|---|---|
| "This file type cannot be opened here yet" | Save it as PDF, MP4, WebM, PNG, JPG or a web page (.html). |
| The protected file does not open | Check you chose both files: the .scholar lesson and its matching .scholarpass password file. Enter the access code if one was sent. |
| The screen goes blank when I switch windows | This is the protection working. Click back into the player. |
| The player does not open offline | Open it once while online, so it can save itself for offline use. |
| "Protected lessons are unavailable on this browser" | Use a current Chrome, Edge or Safari, and do not use a private window. |
