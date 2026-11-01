// Open Scholar Secure File Maker: for publishers only. It makes the lesson file (.scholar) and the password file (.scholarpass).
// Learners never need this page; they use the Open Scholar Secure Player.

let db = null;
let selectedSource = null;
let delivery = null;

function progress(value, text) {
  $('progress-box').hidden = false;
  $('progress').value = value;
  $('progress-text').textContent = text;
}

// The maker keeps its own publisher key in this browser, so it can make new password files later.
async function ownerState() {
  let secret = await readRecord(db, 'owner-secret');
  let publisher = await readRecord(db, 'publisher');
  if (!secret) { secret = {id: 'owner-secret', value: randomSecret()}; await putRecord(db, secret); }
  if (!publisher) { publisher = await newPublisher(secret.value); await putRecord(db, publisher); }
  return {secret: secret.value, publisher};
}

const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
function generateCode(length = 12) {
  // Rejection sampling keeps every character equally likely.
  const limit = 256 - (256 % CODE_ALPHABET.length);
  let code = '';
  while (code.length < length) {
    for (const byte of crypto.getRandomValues(new Uint8Array(length))) {
      if (byte < limit && code.length < length) code += CODE_ALPHABET[byte % CODE_ALPHABET.length];
    }
  }
  return code;
}

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
$('generate-pin').addEventListener('click', () => {
  $('pin').value = generateCode(12);
  $('pin').type = 'text';
  $('copy-pin').hidden = false;
  showMessage('A new code was created. Copy it and send it separately from the files.');
});
$('copy-pin').addEventListener('click', async () => {
  try { await navigator.clipboard.writeText($('pin').value); showMessage('Code copied.'); }
  catch { showMessage('Select the code and copy it by hand.', true); }
});

$('secure-form').addEventListener('submit', async event => {
  event.preventDefault();
  const button = $('secure-button');
  setBusy(button, true, 'Making the files…');
  try {
    const file = selectedSource || $('source').files[0];
    if (!file) throw Error('Choose the lesson you want to protect.');
    const title = $('title').value.trim() || fileTitle(file);
    const start = $('starts').value ? new Date($('starts').value).getTime() : Date.now() - 60000;
    const expiry = $('expires').value ? new Date($('expires').value).getTime() : Date.now() + Number($('duration').value) * 86400000;
    const opens = Number($('opens').value);
    const minutes = Number($('minutes').value);
    if (!Number.isInteger(opens) || opens < 1) throw Error('Choose at least one opening.');
    if (!Number.isInteger(minutes) || minutes < 1) throw Error('Choose at least one minute.');
    progress(2, 'Preparing the security…');
    const owner = await ownerState();
    const privateKey = await unlockPublisher(owner.publisher, owner.secret);
    const encrypted = await encryptFile(file, title, value => progress(Math.max(5, Math.round(value * .78)), 'Encrypting the lesson…'), ONLINE_MAX_FILE);
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
    const passBlob = new Blob([canonical(pass)], {type: 'application/json'});
    delivery = {secureName: base + '.scholar', passName: base + '.scholarpass', secureBlob: encrypted.blob, passBlob};
    progress(100, 'Ready.');
    $('delivery').hidden = false;
    $('delivery').scrollIntoView({behavior: 'smooth', block: 'start'});
    showMessage('Both files are ready. Send them together.');
  } catch (error) {
    showMessage(error.message || 'The lesson could not be protected.', true);
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
    if (navigator.canShare?.({files})) await navigator.share({title: 'Open Scholar lesson files', text: 'Open both files in the Open Scholar Secure Player.', files});
    else { saveFile(delivery.secureBlob, delivery.secureName); setTimeout(() => saveFile(delivery.passBlob, delivery.passName), 700); showMessage('Both files are downloading. Send them together.'); }
  } catch (error) { if (error.name !== 'AbortError') showMessage('Sharing is unavailable. Download both files instead.', true); }
});

$('copy-device').addEventListener('click', async () => {
  const state = await useLedger(db);
  try { await navigator.clipboard.writeText(state.device); showMessage('Device code copied.'); }
  catch { showMessage('Device code: ' + state.device); }
});
$('backup-owner').addEventListener('click', async () => {
  const password = prompt('Create a backup password of at least 12 characters.');
  if (!password) return;
  if (password.length < 12) { showMessage('The backup password needs at least 12 characters.', true); return; }
  try {
    const owner = await ownerState();
    const raw = new TextEncoder().encode(canonical(owner));
    const box = await seal(raw, password, 'owner-backup');
    raw.fill(0);
    saveFile(new Blob([canonical({format: 'OpenScholarMakerBackup', version: 1, box})], {type: 'application/json'}), 'Open_Scholar_Maker_Backup.json');
  } catch (error) { showMessage(error.message, true); }
});
$('restore-owner').addEventListener('change', async () => {
  const password = prompt('Enter the backup password.');
  if (!password) return;
  try {
    const document = JSON.parse(await $('restore-owner').files[0].text());
    if (document.format !== 'OpenScholarMakerBackup' || document.version !== 1) throw Error('Choose an Open Scholar maker backup.');
    const raw = await unseal(document.box, password, 'owner-backup');
    const owner = JSON.parse(new TextDecoder().decode(raw));
    raw.fill(0);
    await putRecord(db, {id: 'owner-secret', value: owner.secret});
    await putRecord(db, owner.publisher);
    showMessage('Publisher backup restored.');
  } catch (error) { showMessage(error.message, true); }
});

async function boot() {
  try {
    if (!window.isSecureContext || !crypto.subtle) throw Error('The maker needs a secure (https) address or localhost.');
    db = await openStore();
    await createLedger(db);
    const state = await useLedger(db);
    $('copy-device').textContent = state.device;
    $('starts').value = localDate(new Date(Date.now() - 60000));
    $('expires').value = localDate(new Date(Date.now() + 7 * 86400000));
  } catch (error) {
    showMessage(error.message || 'The maker could not start.', true);
    document.querySelectorAll('form button').forEach(button => { button.disabled = true; });
  }
}
boot();
