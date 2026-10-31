import {ONLINE_MAX_FILE, DESKTOP_MAX_FILE, b64, unb64, canonical, randomSecret, seal, unseal, newPublisher, unlockPublisher, encryptFile, parsePackage, issuePass, verifyPass, contentKey, decryptBlob} from './crypto.mjs';
import {openStore, readRecord, putRecord, createLedger, useLedger} from './ledger.mjs';
import {plainKind, resumeKey, UNSUPPORTED_MESSAGE} from './plain.mjs';

const $ = id => document.getElementById(id);
const integration = window.SECURE_SCHOLAR_INTEGRATION || {};
let db;
let selectedSource = null;
let delivery = null;
let active = null;
let pdf = null;
let renderTask = null;
let objectURL = null;
let currentPage = 1;
let pageCount = 0;
let pdfWorkerURL = null;
let watermarkPosition = 0;
let activeKey = null;
let lastSaved = 0;

function showMessage(text, error = false) {
  $('message').textContent = text;
  $('message').classList.toggle('error', error);
  $('message').hidden = false;
  clearTimeout(showMessage.timer);
  showMessage.timer = setTimeout(() => { $('message').hidden = true; }, error ? 9000 : 5000);
}
function setBusy(button, busy, text = 'Please wait…') {
  if (busy) { button.dataset.original = button.innerHTML; button.textContent = text; button.disabled = true; }
  else { button.innerHTML = button.dataset.original || button.innerHTML; button.disabled = false; }
}
function progress(value, text) {
  $('progress-box').hidden = false;
  $('progress').value = value;
  $('progress-text').textContent = text;
}
function saveFile(blob, name) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = name;
  document.body.append(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 60000);
}
function safeName(name) { return name.replace(/\.[^.]+$/, '').replace(/[^a-zA-Z0-9_-]+/g, '_').slice(0, 70) || 'Secure_Scholar'; }
function localDate(date) { return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16); }
function screen(name) {
  for (const id of ['home', 'secure-screen', 'open-screen', 'reading-screen']) $(id).hidden = id !== name;
  window.scrollTo({top: 0, behavior: 'smooth'});
}
function closePanels() {
  $('settings-panel').hidden = true;
  $('help-panel').hidden = true;
  $('panel-shade').hidden = true;
}
function openPanel(id) {
  closePanels();
  $(id).hidden = false;
  $('panel-shade').hidden = false;
}
async function ownerState() {
  let secret = await readRecord(db, 'owner-secret');
  let publisher = await readRecord(db, 'publisher');
  if (!secret) { secret = {id: 'owner-secret', value: randomSecret()}; await putRecord(db, secret); }
  if (!publisher) { publisher = await newPublisher(secret.value); await putRecord(db, publisher); }
  return {secret: secret.value, publisher};
}
function fileTitle(file) { return file.name.replace(/\.[^.]+$/, '').replaceAll('_', ' ').trim().slice(0, 200) || 'Secure Scholar File'; }

$('source').addEventListener('change', () => {
  selectedSource = $('source').files[0] || null;
  if (selectedSource) {
    $('source-label').textContent = selectedSource.name;
    if (!$('title').value) $('title').value = fileTitle(selectedSource);
  }
});
$('duration').addEventListener('change', () => {
  $('expires').value = localDate(new Date(Date.now() + Number($('duration').value) * 86400000));
});

$('secure-form').addEventListener('submit', async event => {
  event.preventDefault();
  const button = $('secure-button');
  setBusy(button, true, 'Securing your file…');
  try {
    const file = selectedSource || $('source').files[0];
    if (!file) throw Error('Choose the file you want to secure.');
    const title = $('title').value.trim() || fileTitle(file);
    const start = $('starts').value ? new Date($('starts').value).getTime() : Date.now() - 60000;
    const expiry = $('expires').value ? new Date($('expires').value).getTime() : Date.now() + Number($('duration').value) * 86400000;
    const opens = Number($('opens').value);
    const minutes = Number($('minutes').value);
    if (!Number.isInteger(opens) || opens < 1) throw Error('Choose at least one opening.');
    if (!Number.isInteger(minutes) || minutes < 1) throw Error('Choose at least one minute.');
    progress(2, 'Preparing security…');
    const owner = await ownerState();
    const privateKey = await unlockPublisher(owner.publisher, owner.secret);
    const desktop = !!window.pywebview;
    const encrypted = await encryptFile(file, title, value => progress(Math.max(5, Math.round(value * .78)), 'Securing your file…'), desktop ? DESKTOP_MAX_FILE : (integration.onlineLimit || ONLINE_MAX_FILE));
    const portableSecret = randomSecret();
    const pin = $('pin').value;
    const pass = await issuePass(owner.publisher, privateKey, encrypted, encrypted.key, portableSecret, pin, {
      recipient: $('recipient').value,
      not_before: start,
      expires: expiry,
      max_opens: opens,
      session_seconds: minutes * 60,
      device: $('device').value.trim().toUpperCase() || null,
      disable_seek: $('disable-seek').checked,
      offline_only: $('offline-only').checked
    });
    const keybox = await seal(encrypted.key, owner.secret, 'content:' + encrypted.header.id);
    encrypted.key.fill(0);
    await putRecord(db, {id: 'content:' + encrypted.header.id, header: encrypted.header, header_hash: encrypted.header_hash, blob: encrypted.blob, keybox, created: Date.now()});
    const base = safeName(title);
    const secureName = base + '.scholar';
    const passName = base + '.scholarpass';
    const passBlob = new Blob([canonical(pass)], {type: 'application/json'});
    delivery = {secureName, passName, secureBlob: encrypted.blob, passBlob};
    progress(100, 'Ready.');
    $('delivery').hidden = false;
    $('delivery').scrollIntoView({behavior: 'smooth', block: 'start'});
    showMessage('Your secure file and password are ready.');
  } catch (error) {
    showMessage(error.message || 'The file could not be secured.', true);
  } finally {
    setBusy(button, false);
  }
});

$('download-file').addEventListener('click', () => delivery && saveFile(delivery.secureBlob, delivery.secureName));
$('download-pass').addEventListener('click', () => delivery && saveFile(delivery.passBlob, delivery.passName));
$('share-files').addEventListener('click', async () => {
  if (!delivery) return;
  const files = [new File([delivery.secureBlob], delivery.secureName), new File([delivery.passBlob], delivery.passName)];
  try {
    if (navigator.canShare?.({files})) await navigator.share({title: 'Secure Scholar files', text: 'Open both files only in Secure Scholar.', files});
    else { saveFile(delivery.secureBlob, delivery.secureName); setTimeout(() => saveFile(delivery.passBlob, delivery.passName), 700); showMessage('Both files are downloading. Send them together.'); }
  } catch (error) { if (error.name !== 'AbortError') showMessage('Sharing is unavailable. Download both files instead.', true); }
});

$('pass-file').addEventListener('change', async () => {
  try {
    const document = JSON.parse(await $('pass-file').files[0].text());
    $('pin-row').hidden = !document.body?.pin_required;
    $('open-pin').required = !!document.body?.pin_required;
  } catch { $('pin-row').hidden = true; }
});

$('open-form').addEventListener('submit', async event => {
  event.preventDefault();
  const button = $('open-button');
  setBusy(button, true, 'Opening safely…');
  try {
    const secureFile = $('secure-file').files[0];
    const passwordFile = $('pass-file').files[0];
    if (!secureFile || !passwordFile) throw Error('Import the secure file and its password file.');
    const content = await parsePackage(secureFile);
    const document = JSON.parse(await passwordFile.text());
    const pinned = integration.pinnedPublic || null;
    const body = await verifyPass(document, pinned, content);
    await useLedger(db, body, false);
    const key = await contentKey(document, $('open-pin').value);
    progress(5, 'Opening safely…');
    let blob;
    try { blob = await decryptBlob(content, key, value => progress(value, 'Opening safely…')); }
    finally { /* CryptoKey is non-extractable and becomes unreachable after this scope. */ }
    const usage = await useLedger(db, body, true);
    await display(content, body, blob, usage.remaining);
  } catch (error) {
    showMessage(error.message || 'The file could not be opened.', true);
  } finally {
    setBusy(button, false);
  }
});

async function preparePdfWorker() {
  if (pdfWorkerURL) return;
  const binary = atob(window.OS_PDF_WORKER);
  const bytes = Uint8Array.from(binary, character => character.charCodeAt(0));
  pdfWorkerURL = URL.createObjectURL(new Blob([bytes], {type: 'text/javascript'}));
  window.OSPDF.GlobalWorkerOptions.workerSrc = pdfWorkerURL;
}
function watermarkText() { return active?.protected ? `${active.body.recipient} | ${active.body.id.slice(0, 8)} | Secure Scholar` : ''; }
function burnWatermark(context, width, height) {
  if (!active?.protected) return;
  context.save();
  context.fillStyle = 'rgba(0,83,45,.23)';
  context.font = `${Math.max(12, Math.round(width / 55))}px system-ui`;
  for (let y = 45; y < height; y += Math.max(100, Math.round(height / 4))) context.fillText(watermarkText(), 15, y, width - 30);
  context.restore();
}
async function display(content, body, blob, remaining, plain = false) {
  await lock('', false);
  active = {body, protected: !plain, plain, deadline: plain ? Infinity : performance.now() + body.session_seconds * 1000, remaining};
  $('unlock-panel').hidden = true;
  $('viewer').hidden = false;
  $('view-title').textContent = content.header.title;
  $('view-kind').textContent = plain ? 'OPENED FILE' : 'PROTECTED FILE';
  $('view-status').textContent = plain ? 'Opened on this device' : `For ${body.recipient}`;
  $('credits').textContent = plain ? 'Nothing is used up. Your place is saved on this device.' : `${remaining} opening credit${remaining === 1 ? '' : 's'} remaining`;
  $('watermark').textContent = watermarkText();
  $('video-wrap').hidden = true;
  $('pages-wrap').hidden = true;
  $('web-wrap').hidden = true;
  $('page-controls').hidden = true;
  $('web-controls').hidden = true;
  if (content.header.kind === 'video') {
    objectURL = URL.createObjectURL(blob);
    $('video').src = objectURL;
    $('video-wrap').hidden = false;
    $('seek').disabled = !!body.disable_seek;
    if (plain) {
      const place = savedPlace();
      if (place?.time) $('video').addEventListener('loadedmetadata', () => { $('video').currentTime = place.time; }, {once: true});
    }
  } else if (content.header.kind === 'pdf') {
    await preparePdfWorker();
    pdf = await window.OSPDF.getDocument({data: new Uint8Array(await blob.arrayBuffer())}).promise;
    pageCount = pdf.numPages;
    $('page-select').replaceChildren();
    for (let page = 1; page <= pageCount; page++) $('page-select').add(new Option(String(page), String(page)));
    $('pages-wrap').hidden = false;
    $('page-controls').hidden = false;
    await showPage(plain ? savedPlace()?.page || 1 : 1);
  } else if (content.header.kind === 'image') {
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
  } else {
    const original = await blob.text();
    const policy = `<meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src data: blob:; media-src data: blob:; style-src 'unsafe-inline'; font-src data:; connect-src 'none'; frame-src 'none'; form-action 'none'">${plain ? '' : '<style>html,body{user-select:none!important;-webkit-user-select:none!important}a,button,input,select,textarea{pointer-events:none!important}</style>'}`;
    const protectedHtml = /<head[^>]*>/i.test(original) ? original.replace(/<head[^>]*>/i, match => match + policy) : policy + original;
    $('web-frame').srcdoc = protectedHtml.replace(/<base[^>]*>/gi, '');
    $('web-wrap').hidden = false;
    $('web-controls').hidden = false;
  }
  updateClock();
  $('viewer').scrollIntoView({behavior: 'smooth', block: 'start'});
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
  if (active.plain && activeKey) savePlace({page: requested});
  $('previous').disabled = requested === 1;
  $('next').disabled = requested === pageCount;
}
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
    detail.textContent = `${entry.kind.toUpperCase()} · ${new Date(entry.at).toLocaleDateString()} · Open the file again to continue`;
    item.append(name, detail);
    items.append(item);
  }
  $('recent-empty').hidden = list.length > 0;
  $('clear-recent').hidden = list.length === 0;
}
async function openPlain(file) {
  const kind = plainKind(file.name);
  if (!kind) throw Error(UNSUPPORTED_MESSAGE);
  activeKey = resumeKey(file);
  screen('open-screen');
  await display({header: {kind, title: fileTitle(file)}}, {recipient: '', id: '', session_seconds: 0}, file, null, true);
  rememberRecent(file, kind);
}
async function lock(reason = 'The secure file is closed.', notify = true) {
  const wasPlain = !!active?.plain;
  active = null;
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
  $('viewer').hidden = true;
  $('unlock-panel').hidden = false;
  if (notify && reason) showMessage(reason);
  if (wasPlain && notify) screen('home');
}
function updateClock() {
  if (!active) return;
  if (active.plain) { $('session-clock').textContent = ''; return; }
  const seconds = Math.max(0, Math.min((active.deadline - performance.now()) / 1000, (active.body.expires - Date.now()) / 1000));
  $('session-clock').textContent = `${Math.ceil(seconds / 60)} minute(s) remaining`;
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
  if (!active) return;
  watermarkPosition = (watermarkPosition + 1) % 3;
  $('watermark').classList.toggle('position-two', watermarkPosition === 1);
  $('watermark').classList.toggle('position-three', watermarkPosition === 2);
}, 12000);

const video = $('video');
let lastAllowedTime = 0;
$('play-button').addEventListener('click', async () => { try { if (video.paused) await video.play(); else video.pause(); } catch { showMessage('This video format is not supported on this device.', true); } });
video.addEventListener('play', () => { $('play-button').textContent = 'Pause'; });
video.addEventListener('pause', () => { $('play-button').textContent = 'Play'; });
video.addEventListener('ended', () => { if (active?.protected) lock('Playback finished.'); });
video.addEventListener('timeupdate', () => {
  if (!active) return;
  if (active.plain && activeKey && video.currentTime - lastSaved >= 5) { lastSaved = video.currentTime; savePlace({time: Math.floor(video.currentTime)}); }
  if (!video.seeking) lastAllowedTime = video.currentTime;
  $('time').textContent = `${Math.floor(video.currentTime / 60)}:${String(Math.floor(video.currentTime % 60)).padStart(2, '0')}`;
  $('seek').value = String(Number.isFinite(video.duration) ? Math.round(video.currentTime / video.duration * 1000) : 0);
});
video.addEventListener('seeking', () => { if (active?.body.disable_seek && Math.abs(video.currentTime - lastAllowedTime) > 1.5) video.currentTime = lastAllowedTime; });
$('seek').addEventListener('input', () => { if (active && !active.body.disable_seek && Number.isFinite(video.duration)) video.currentTime = Number($('seek').value) / 1000 * video.duration; });
$('mute-button').addEventListener('click', () => { video.muted = !video.muted; $('mute-button').textContent = video.muted ? 'Unmute' : 'Mute'; });
$('fullscreen-button').addEventListener('click', async () => { try { if (document.fullscreenElement) await document.exitFullscreen(); else await $('video-wrap').requestFullscreen(); } catch { showMessage('Full screen is unavailable.', true); } });
$('previous').addEventListener('click', () => showPage(currentPage - 1).catch(error => showMessage(error.message, true)));
$('next').addEventListener('click', () => showPage(currentPage + 1).catch(error => showMessage(error.message, true)));
$('page-select').addEventListener('change', () => showPage(Number($('page-select').value)).catch(error => showMessage(error.message, true)));
$('zoom').addEventListener('click', () => { $('pages-wrap').classList.toggle('enlarged'); $('zoom').textContent = $('pages-wrap').classList.contains('enlarged') ? 'Fit page' : 'Enlarge'; });
$('web-up').addEventListener('click', () => $('web-frame').contentWindow?.scrollBy({top: -400, behavior: 'smooth'}));
$('web-down').addEventListener('click', () => $('web-frame').contentWindow?.scrollBy({top: 400, behavior: 'smooth'}));
$('lock-button').addEventListener('click', () => lock());

for (const element of document.querySelectorAll('[data-home]')) element.addEventListener('click', () => screen('home'));
$('home-button').addEventListener('click', () => screen('home'));
$('start-secure').addEventListener('click', () => screen('secure-screen'));
$('start-open').addEventListener('click', () => screen('open-screen'));
$('plain-file').addEventListener('change', async () => {
  const file = $('plain-file').files[0];
  $('plain-file').value = '';
  if (!file) return;
  try { await openPlain(file); }
  catch (error) { showMessage(error.message || 'This file could not be opened.', true); }
});
$('start-reading').addEventListener('click', () => { renderRecent(); screen('reading-screen'); });
$('clear-recent').addEventListener('click', () => {
  for (const key of Object.keys(localStorage)) if (key.startsWith('ossp-place:') || key === 'ossp-recent') localStorage.removeItem(key);
  renderRecent();
  showMessage('Your reading list and saved places were forgotten on this device.');
});
$('settings-button').addEventListener('click', () => openPanel('settings-panel'));
$('help-button').addEventListener('click', () => openPanel('help-panel'));
$('close-settings').addEventListener('click', closePanels);
$('close-help').addEventListener('click', closePanels);
$('panel-shade').addEventListener('click', closePanels);
$('copy-device').addEventListener('click', async () => {
  const state = await useLedger(db);
  try { await navigator.clipboard.writeText(state.device); showMessage('Installation code copied.'); }
  catch { showMessage('Installation code: ' + state.device); }
});

$('backup-owner').addEventListener('click', async () => {
  const password = prompt('Create a backup password of at least 12 characters.');
  if (!password) return;
  try {
    const owner = await ownerState();
    const raw = new TextEncoder().encode(canonical(owner));
    const box = await seal(raw, password, 'owner-backup');
    raw.fill(0);
    saveFile(new Blob([canonical({format: 'SecureScholarOwnerBackup', version: 1, box})], {type: 'application/json'}), 'Secure_Scholar_Owner_Backup.json');
  } catch (error) { showMessage(error.message, true); }
});
$('restore-owner').addEventListener('change', async () => {
  const password = prompt('Enter the backup password.');
  if (!password) return;
  try {
    const document = JSON.parse(await $('restore-owner').files[0].text());
    if (document.format !== 'SecureScholarOwnerBackup' || document.version !== 1) throw Error('Choose a Secure Scholar owner backup.');
    const raw = await unseal(document.box, password, 'owner-backup');
    const owner = JSON.parse(new TextDecoder().decode(raw));
    raw.fill(0);
    await putRecord(db, {id: 'owner-secret', value: owner.secret});
    await putRecord(db, owner.publisher);
    showMessage('Owner backup restored.');
  } catch (error) { showMessage(error.message, true); }
});

document.addEventListener('contextmenu', event => { if (active?.protected) event.preventDefault(); });
document.addEventListener('dragstart', event => { if (active?.protected) event.preventDefault(); });
document.addEventListener('copy', event => { if (active?.protected) event.preventDefault(); });
document.addEventListener('cut', event => { if (active?.protected) event.preventDefault(); });
document.addEventListener('keydown', event => {
  if (!active?.protected) return;
  if ((event.ctrlKey || event.metaKey) && ['c', 's', 'p', 'u'].includes(event.key.toLowerCase())) { event.preventDefault(); showMessage('This action is unavailable in secure view.'); }
  if (event.key === 'F12') event.preventDefault();
});
document.addEventListener('visibilitychange', () => { if (document.hidden && active) video.pause(); updateVeil(); });
window.addEventListener('blur', updateVeil);
window.addEventListener('focus', updateVeil);
function updateVeil() {
  const hidden = document.hidden || !document.hasFocus();
  document.body.classList.toggle('veiled', !!(active?.protected && hidden));
}
window.addEventListener('beforeprint', () => { if (active?.protected) document.body.classList.add('no-print'); });
window.addEventListener('afterprint', () => document.body.classList.remove('no-print'));
document.addEventListener('keyup', async event => {
  if (event.key !== 'PrintScreen' || !active?.protected) return;
  document.body.classList.add('veiled');
  try { await navigator.clipboard.writeText(''); } catch {}
  setTimeout(updateVeil, 2500);
  showMessage('Screenshots are blocked for protected files.', true);
});

async function prepareDesktopOffice() {
  try {
    const result = await window.pywebview.api.convert_office();
    if (!result || !result.data) return;
    selectedSource = new File([unb64(result.data)], result.name, {type: 'application/pdf'});
    $('source-label').textContent = result.original + ' → PDF';
    $('title').value = fileTitle(selectedSource);
    showMessage('Document prepared. Press Secure file.');
  } catch (error) { showMessage(String(error), true); }
}
$('desktop-office').addEventListener('click', prepareDesktopOffice);

async function boot() {
  try {
    if (!window.isSecureContext || !crypto.subtle) throw Error('Secure Scholar requires HTTPS or its Windows application.');
    db = await openStore();
    await createLedger(db);
    const state = await useLedger(db);
    $('copy-device').textContent = state.device;
    $('starts').value = localDate(new Date(Date.now() - 60000));
    $('expires').value = localDate(new Date(Date.now() + 7 * 86400000));
    if (integration.readerOnly) $('start-secure').hidden = true;
    if (integration.ownerOnly) $('start-open').hidden = true;
    if (window.pywebview) $('desktop-office').hidden = false;
    if (integration.readerOnly) screen('open-screen');
  } catch (error) {
    showMessage(error.message || 'Secure Scholar could not start.', true);
    document.querySelectorAll('form button').forEach(button => { button.disabled = true; });
  }
}
const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
function generateCode(length = 12) {
  // Rejection sampling keeps every character equally likely.
  const limit = 256 - (256 % CODE_ALPHABET.length);
  let code = '';
  while (code.length < length) {
    const bytes = crypto.getRandomValues(new Uint8Array(length));
    for (const byte of bytes) {
      if (byte < limit && code.length < length) code += CODE_ALPHABET[byte % CODE_ALPHABET.length];
    }
  }
  return code;
}
$('generate-pin').addEventListener('click', () => {
  $('pin').value = generateCode(12);
  $('pin').type = 'text';
  $('copy-pin').hidden = false;
  showMessage('A new code was created. Copy it and send it separately.');
});
$('copy-pin').addEventListener('click', async () => {
  try { await navigator.clipboard.writeText($('pin').value); showMessage('Code copied.'); }
  catch { showMessage('Select the code and copy it by hand.', true); }
});

let installPrompt = null;
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
boot();
