// Open Scholar Secure Player: the viewer only. It opens ordinary files and protected lesson files.
// It never creates protected files; that is done by the separate Open Scholar Secure File Maker.

const integration = window.SECURE_SCHOLAR_INTEGRATION || {};
let db = null;
let active = null;           // the file on screen: {body, protected, plain, deadline, remaining}
let pending = null;          // protected lesson waiting for the reader to accept its limits
let pdf = null;
let renderTask = null;
let objectURL = null;
let currentPage = 1;
let pageCount = 0;
let pdfWorkerURL = null;
let watermarkPosition = 0;
let activeKey = null;        // where to remember the place in an ordinary file
let lastSaved = 0;
let lastAllowedTime = 0;
let installPrompt = null;

const SECTIONS = ['welcome', 'protected-form', 'notice', 'viewer'];
function showSection(name) {
  for (const section of SECTIONS) $(section).hidden = section !== name;
  $('transport').hidden = name !== 'viewer';
}
function setStatus(text) { $('status-text').textContent = text; }
function setTitle(text) { $('title-now').textContent = text; }

function openPanel(id) {
  closePanels();
  $(id).hidden = false;
  $('panel-shade').hidden = false;
}
function closePanels() {
  $('about-panel').hidden = true;
  $('help-panel').hidden = true;
  $('panel-shade').hidden = true;
}

// ---- library and resume (stored only in this browser) ----
function savePlace(data) {
  if (!activeKey) return;
  try { localStorage.setItem(activeKey, JSON.stringify({...data, at: Date.now()})); } catch {}
}
function savedPlace() {
  try { return JSON.parse(localStorage.getItem(activeKey) || 'null'); } catch { return null; }
}
function readRecent() {
  try { return JSON.parse(localStorage.getItem('ossp-recent') || '[]'); } catch { return []; }
}
function rememberRecent(file, kind) {
  const key = resumeKey(file);
  const list = readRecent().filter(item => item.key !== key);
  list.unshift({key, name: file.name, kind, at: Date.now()});
  try { localStorage.setItem('ossp-recent', JSON.stringify(list.slice(0, 20))); } catch {}
}
function renderRecent() {
  const list = readRecent();
  const items = $('recent-list');
  items.replaceChildren();
  for (const entry of list) {
    const item = document.createElement('li');
    const name = document.createElement('strong');
    name.textContent = entry.name;
    const detail = document.createElement('span');
    detail.className = 'hint';
    detail.textContent = `${entry.kind.toUpperCase()} · ${new Date(entry.at).toLocaleDateString()}`;
    item.append(name, detail);
    items.append(item);
  }
  $('recent-empty').hidden = list.length > 0;
  $('clear-recent').hidden = list.length === 0;
}

// ---- ordinary files: no password, no limits ----
async function openPlain(file) {
  const kind = plainKind(file.name);
  if (!kind) throw Error(UNSUPPORTED_MESSAGE);
  activeKey = resumeKey(file);
  await display({header: {kind, title: fileTitle(file)}}, {recipient: '', id: '', session_seconds: 0}, file, null, true);
  rememberRecent(file, kind);
  renderRecent();
}

// ---- protected lesson: check, show the limits, then count one opening only when the reader starts ----
function limitsFor(body) {
  const minutes = Math.round(body.session_seconds / 60);
  const items = [
    'You can view this lesson here. You cannot copy, save, download or print it.',
    'Copy, save, print and screenshot shortcuts are blocked. Right-click, drag and text selection are turned off.',
    'The view is hidden when you leave this window, so keep the player in front while you learn.',
    `Your name and code appear faintly on everything you see (watermark for ${body.recipient || 'this learner'}).`,
    `The lesson works until ${formatDate(body.expires)}, for ${body.max_opens} opening${body.max_opens === 1 ? '' : 's'} in total.`,
    `Each opening lasts ${minutes} minute${minutes === 1 ? '' : 's'}. When the time ends, the lesson closes.`
  ];
  if (body.disable_seek) items.push('The publisher has turned off skipping forward in videos.');
  if (body.device) items.push('This lesson opens only on the device it was prepared for.');
  if (body.offline_only) items.push('This lesson is for offline viewing only.');
  items.push('Nothing on your computer is changed or damaged. These limits are deliberate, to protect the publisher’s content. They are not a crash.');
  items.push('No software can stop every camera or screen recording. Protection makes copying much harder, not impossible.');
  return items;
}
function showNotice(content, body) {
  $('notice-title').textContent = `Before you view “${content.header.title}”`;
  const list = $('notice-list');
  list.replaceChildren(...limitsFor(body).map(text => {
    const item = document.createElement('li');
    item.textContent = text;
    return item;
  }));
  $('notice-agree').checked = false;
  $('notice-start').disabled = true;
  showSection('notice');
  setStatus('Read the limits before you start viewing');
  setTitle(content.header.title);
}

$('open-form').addEventListener('submit', async event => {
  event.preventDefault();
  const button = $('open-button');
  setBusy(button, true, 'Checking the files…');
  try {
    const secureFile = $('secure-file').files[0];
    const passwordFile = $('pass-file').files[0];
    if (!secureFile || !passwordFile) throw Error('Choose the lesson file and its password file.');
    const content = await parsePackage(secureFile);
    const pass = JSON.parse(await passwordFile.text());
    const body = await verifyPass(pass, integration.pinnedPublic || null, content);
    await useLedger(db, body, false);
    const key = await contentKey(pass, $('open-pin').value);
    pending = {content, body, key};
    showNotice(content, body);
  } catch (error) {
    showMessage(error.message || 'The file could not be opened.', true);
  } finally {
    setBusy(button, false);
  }
});

$('notice-agree').addEventListener('change', () => { $('notice-start').disabled = !$('notice-agree').checked; });
$('notice-cancel').addEventListener('click', () => { pending = null; showSection('welcome'); setStatus('Ready'); setTitle('Ready'); });
$('notice-start').addEventListener('click', async () => {
  if (!pending || !$('notice-agree').checked) return;
  const {content, body, key} = pending;
  const button = $('notice-start');
  setBusy(button, true, 'Opening…');
  try {
    setStatus('Opening safely…');
    const blob = await decryptBlob(content, key, value => setStatus(`Opening safely… ${Math.round(value)}%`));
    const usage = await useLedger(db, body, true);   // the opening is counted only now
    pending = null;
    activeKey = null;
    await display(content, body, blob, usage.remaining, false);
  } catch (error) {
    pending = null;
    showSection('welcome');
    setStatus('Ready');
    showMessage(error.message || 'The lesson could not be opened.', true);
  } finally {
    setBusy(button, false);
    button.disabled = !$('notice-agree').checked;
  }
});

// Show the access-code box only when the password file asks for one.
$('pass-file').addEventListener('change', async () => {
  try {
    const pass = JSON.parse(await $('pass-file').files[0].text());
    const needed = !!pass.body?.pin_required;
    $('pin-row').hidden = !needed;
    $('open-pin').required = needed;
  } catch { $('pin-row').hidden = true; $('open-pin').required = false; }
});

// ---- playing ----
async function preparePdfWorker() {
  if (pdfWorkerURL) return;
  const binary = atob(window.OS_PDF_WORKER);
  const bytes = Uint8Array.from(binary, character => character.charCodeAt(0));
  pdfWorkerURL = URL.createObjectURL(new Blob([bytes], {type: 'text/javascript'}));
  window.OSPDF.GlobalWorkerOptions.workerSrc = pdfWorkerURL;
}
function watermarkText() {
  return active?.protected ? `${active.body.recipient} | ${active.body.id.slice(0, 8)} | Open Scholar` : '';
}
function burnWatermark(context, width, height) {
  if (!active?.protected) return;
  context.save();
  context.fillStyle = 'rgba(0,83,45,.23)';
  context.font = `${Math.max(12, Math.round(width / 55))}px system-ui`;
  for (let y = 45; y < height; y += Math.max(100, Math.round(height / 4))) context.fillText(watermarkText(), 15, y, width - 30);
  context.restore();
}
function showControls(kind) {
  const video = kind === 'video';
  const pages = kind === 'pdf' || kind === 'image';
  const web = kind === 'web';
  for (const id of ['play-button', 'seek', 'time', 'mute-button', 'volume', 'fullscreen-button']) $(id).hidden = !video;
  for (const id of ['previous', 'next', 'page-indicator', 'zoom']) $(id).hidden = !pages;
  $('page-select').hidden = kind !== 'pdf';
  for (const id of ['web-up', 'web-down']) $(id).hidden = !web;
  $('stop-button').hidden = false;
  $('fullscreen-button').hidden = kind === 'image' || kind === 'web';
}

async function display(content, body, blob, remaining, plain) {
  await lock('', false);
  active = {body, protected: !plain, plain, deadline: plain ? Infinity : performance.now() + body.session_seconds * 1000, remaining};
  showSection('viewer');
  setTitle(content.header.title);
  $('watermark').textContent = watermarkText();
  for (const id of ['video-wrap', 'pages-wrap', 'web-wrap']) $(id).hidden = true;
  const kind = content.header.kind;
  showControls(kind);
  if (kind === 'video') {
    objectURL = URL.createObjectURL(blob);
    $('video').src = objectURL;
    $('video-wrap').hidden = false;
    $('seek').disabled = !!body.disable_seek;
    if (plain) {
      const place = savedPlace();
      if (place?.time) $('video').addEventListener('loadedmetadata', () => { $('video').currentTime = place.time; }, {once: true});
    }
  } else if (kind === 'pdf') {
    await preparePdfWorker();
    pdf = await window.OSPDF.getDocument({data: new Uint8Array(await blob.arrayBuffer())}).promise;
    pageCount = pdf.numPages;
    $('page-select').replaceChildren();
    for (let page = 1; page <= pageCount; page++) $('page-select').add(new Option(`Page ${page}`, String(page)));
    $('pages-wrap').hidden = false;
    await showPage(plain ? savedPlace()?.page || 1 : 1);
  } else if (kind === 'image') {
    const image = await createImageBitmap(blob);
    const canvas = $('page-canvas');
    const scale = Math.min(1, 1800 / Math.max(image.width, image.height));
    canvas.width = Math.round(image.width * scale);
    canvas.height = Math.round(image.height * scale);
    const context = canvas.getContext('2d');
    context.drawImage(image, 0, 0, canvas.width, canvas.height);
    image.close();
    burnWatermark(context, canvas.width, canvas.height);
    $('pages-wrap').hidden = false;
    $('page-indicator').textContent = 'Picture';
  } else {
    const original = await blob.text();
    const lockdown = plain ? '' : '<style>html,body{user-select:none!important;-webkit-user-select:none!important}a,button,input,select,textarea{pointer-events:none!important}</style>';
    const policy = `<meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src data: blob:; media-src data: blob:; style-src 'unsafe-inline'; font-src data:; connect-src 'none'; frame-src 'none'; form-action 'none'">${lockdown}`;
    const protectedHtml = /<head[^>]*>/i.test(original) ? original.replace(/<head[^>]*>/i, match => match + policy) : policy + original;
    $('web-frame').srcdoc = protectedHtml.replace(/<base[^>]*>/gi, '');
    $('web-wrap').hidden = false;
  }
  updateClock();
  if (active.protected) setStatus(`Protected · ${remaining} opening${remaining === 1 ? '' : 's'} left after this one`);
  else setStatus('Opened on this device · nothing is used up');
}

async function showPage(number) {
  if (!pdf || !active) return;
  currentPage = Math.min(pageCount, Math.max(1, number));
  if (renderTask) { renderTask.cancel(); try { await renderTask.promise; } catch {} }
  const requested = currentPage;
  const page = await pdf.getPage(requested);
  const original = page.getViewport({scale: 1});
  const viewport = page.getViewport({scale: Math.min(2, 1800 / Math.max(original.width, original.height))});
  const canvas = $('page-canvas');
  canvas.width = Math.round(viewport.width);
  canvas.height = Math.round(viewport.height);
  renderTask = page.render({canvasContext: canvas.getContext('2d'), viewport});
  try { await renderTask.promise; } catch (error) { if (error.name === 'RenderingCancelledException') return; throw error; }
  if (!active || requested !== currentPage) return;
  burnWatermark(canvas.getContext('2d'), canvas.width, canvas.height);
  $('page-select').value = String(requested);
  $('page-indicator').textContent = `Page ${requested} of ${pageCount}`;
  $('previous').disabled = requested === 1;
  $('next').disabled = requested === pageCount;
  if (active.plain && activeKey) savePlace({page: requested});
}

async function lock(reason = '', notify = true) {
  const wasOpen = !!active;
  active = null;
  pending = null;
  $('video').pause();
  $('video').removeAttribute('src');
  $('video').load();
  if (objectURL) { URL.revokeObjectURL(objectURL); objectURL = null; }
  if (renderTask) { renderTask.cancel(); renderTask = null; }
  if (pdf) { await pdf.destroy().catch(() => {}); pdf = null; }
  $('web-frame').srcdoc = '';
  const canvas = $('page-canvas');
  canvas.width = 1;
  canvas.height = 1;
  showSection('welcome');
  setTitle('Ready');
  setStatus('Ready');
  renderRecent();
  if (notify && reason) showMessage(reason);
  if (wasOpen && notify) setStatus('Closed');
}

function updateClock() {
  if (!active || active.plain) return;
  const seconds = Math.max(0, Math.min((active.deadline - performance.now()) / 1000, (active.body.expires - Date.now()) / 1000));
  setStatus(`Protected · ${active.remaining} opening${active.remaining === 1 ? '' : 's'} left · ${Math.ceil(seconds / 60)} min in this viewing`);
}

setInterval(async () => {
  if (!active || active.plain) return;
  try {
    if (performance.now() >= active.deadline) throw Error('Viewing time finished.');
    await useLedger(db, active.body, false);
    updateClock();
  } catch (error) { await lock(error.message); }
}, 2000);
setInterval(() => {
  if (!active?.protected) return;
  watermarkPosition = (watermarkPosition + 1) % 3;
  $('watermark').classList.toggle('position-two', watermarkPosition === 1);
  $('watermark').classList.toggle('position-three', watermarkPosition === 2);
}, 12000);

const video = $('video');
video.addEventListener('play', () => { $('play-button').textContent = 'Pause'; $('play-button').setAttribute('aria-label', 'Pause'); });
video.addEventListener('pause', () => { $('play-button').textContent = 'Play'; $('play-button').setAttribute('aria-label', 'Play'); });
video.addEventListener('ended', () => { if (active?.protected) lock('Playback finished.'); });
video.addEventListener('timeupdate', () => {
  if (!active) return;
  if (active.plain && activeKey && video.currentTime - lastSaved >= 5) { lastSaved = video.currentTime; savePlace({time: Math.floor(video.currentTime)}); }
  if (!video.seeking) lastAllowedTime = video.currentTime;
  $('time').textContent = formatTime(video.currentTime);
  $('seek').value = String(Number.isFinite(video.duration) ? Math.round(video.currentTime / video.duration * 1000) : 0);
});
video.addEventListener('seeking', () => {
  if (active?.body?.disable_seek && Math.abs(video.currentTime - lastAllowedTime) > 1.5) video.currentTime = lastAllowedTime;
});
$('play-button').addEventListener('click', async () => {
  try { if (video.paused) await video.play(); else video.pause(); }
  catch { showMessage('This video format is not supported on this device.', true); }
});
$('stop-button').addEventListener('click', async () => {
  if (!$('video-wrap').hidden) { video.pause(); video.currentTime = 0; }
  if (pdf) await showPage(1);
});
$('seek').addEventListener('input', () => {
  if (active && !active.body?.disable_seek && Number.isFinite(video.duration)) video.currentTime = Number($('seek').value) / 1000 * video.duration;
});
$('mute-button').addEventListener('click', () => { video.muted = !video.muted; $('mute-button').textContent = video.muted ? 'Unmute' : 'Mute'; });
$('volume').addEventListener('input', () => { video.volume = Number($('volume').value) / 100; });
$('fullscreen-button').addEventListener('click', async () => {
  try { if (document.fullscreenElement) await document.exitFullscreen(); else await $('video-wrap').requestFullscreen(); }
  catch { showMessage('Full screen is not available here.', true); }
});
$('previous').addEventListener('click', () => showPage(currentPage - 1).catch(error => showMessage(error.message, true)));
$('next').addEventListener('click', () => showPage(currentPage + 1).catch(error => showMessage(error.message, true)));
$('page-select').addEventListener('change', () => showPage(Number($('page-select').value)).catch(error => showMessage(error.message, true)));
$('zoom').addEventListener('click', () => { $('pages-wrap').classList.toggle('enlarged'); $('zoom').textContent = $('pages-wrap').classList.contains('enlarged') ? 'Fit page' : 'Enlarge'; });
$('web-up').addEventListener('click', () => $('web-frame').contentWindow?.scrollBy({top: -400, behavior: 'smooth'}));
$('web-down').addEventListener('click', () => $('web-frame').contentWindow?.scrollBy({top: 400, behavior: 'smooth'}));

// ---- menus and library ----
$('plain-file').addEventListener('change', async () => {
  const file = $('plain-file').files[0];
  $('plain-file').value = '';
  if (!file) return;
  try { await openPlain(file); }
  catch (error) { showMessage(error.message || 'This file could not be opened.', true); }
});
$('start-open-file').addEventListener('click', () => $('plain-file').click());
$('menu-open').addEventListener('click', () => $('plain-file').click());
$('start-protected').addEventListener('click', () => { showSection('protected-form'); setStatus('Choose both files'); });
$('menu-protected').addEventListener('click', () => { showSection('protected-form'); setStatus('Choose both files'); });
$('protected-cancel').addEventListener('click', () => { showSection('welcome'); setStatus('Ready'); });
$('menu-library').addEventListener('click', () => { renderRecent(); $('recent-list').scrollIntoView({behavior: 'smooth', block: 'nearest'}); });
$('menu-close').addEventListener('click', () => lock());
$('menu-help').addEventListener('click', () => openPanel('help-panel'));
$('help-button').addEventListener('click', () => openPanel('help-panel'));
$('about-button').addEventListener('click', () => openPanel('about-panel'));
$('close-about').addEventListener('click', closePanels);
$('close-help').addEventListener('click', closePanels);
$('panel-shade').addEventListener('click', closePanels);
$('clear-recent').addEventListener('click', () => {
  for (const key of Object.keys(localStorage)) if (key.startsWith('ossp-place:') || key === 'ossp-recent') localStorage.removeItem(key);
  renderRecent();
  showMessage('Your library and saved places were forgotten on this device.');
});

// ---- protections for protected lessons only ----
document.addEventListener('contextmenu', event => { if (active?.protected) event.preventDefault(); });
document.addEventListener('dragstart', event => { if (active?.protected) event.preventDefault(); });
document.addEventListener('copy', event => { if (active?.protected) event.preventDefault(); });
document.addEventListener('cut', event => { if (active?.protected) event.preventDefault(); });
document.addEventListener('keydown', event => {
  if (!active?.protected) return;
  if ((event.ctrlKey || event.metaKey) && ['c', 's', 'p', 'u', 'a'].includes(event.key.toLowerCase())) {
    event.preventDefault();
    showMessage('This action is blocked for protected lessons.', true);
  }
  if (event.key === 'F12') event.preventDefault();
});
document.addEventListener('keyup', async event => {
  if (event.key !== 'PrintScreen' || !active?.protected) return;
  document.body.classList.add('veiled');
  try { await navigator.clipboard.writeText(''); } catch {}
  setTimeout(updateVeil, 2500);
  showMessage('Screenshots are blocked for protected lessons.', true);
});
function updateVeil() {
  const hidden = document.hidden || !document.hasFocus();
  document.body.classList.toggle('veiled', !!(active?.protected && hidden));
}
document.addEventListener('visibilitychange', () => { if (document.hidden && active) video.pause(); updateVeil(); });
window.addEventListener('blur', updateVeil);
window.addEventListener('focus', updateVeil);
window.addEventListener('beforeprint', () => { if (active?.protected) document.body.classList.add('no-print'); });
window.addEventListener('afterprint', () => document.body.classList.remove('no-print'));

// ---- install as an app ----
window.addEventListener('beforeinstallprompt', event => {
  event.preventDefault();
  installPrompt = event;
  $('install-button').hidden = false;
});
window.addEventListener('appinstalled', () => { $('install-button').hidden = true; showMessage('Open Scholar Secure Player is installed.'); });
$('install-button').addEventListener('click', async () => {
  if (!installPrompt) return;
  installPrompt.prompt();
  await installPrompt.userChoice;
  installPrompt = null;
  $('install-button').hidden = true;
});
if (/iPhone|iPad/.test(navigator.userAgent) && !window.navigator.standalone) $('install-ios').hidden = false;

if ('serviceWorker' in navigator && location.protocol.startsWith('http')) {
  navigator.serviceWorker.register('./sw.js').catch(() => {});
}

async function boot() {
  try {
    db = await openStore();
    await createLedger(db);
    await useLedger(db);
  } catch (error) {
    // Ordinary files still open without the protected-file store.
    showMessage('Protected lessons are unavailable on this browser. Ordinary files still open.', true);
  }
  renderRecent();
  setStatus('Ready');
}
boot();
