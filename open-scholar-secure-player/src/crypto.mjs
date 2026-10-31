/* Secure Scholar v1 file and access format. Native Web Crypto; no remote cryptography. */
export const CHUNK = 1048576;
export const ONLINE_MAX_FILE = 100 * 1024 * 1024;
export const DESKTOP_MAX_FILE = 2 * 1024 * 1024 * 1024;
export const ITERATIONS = 600000;
const te = new TextEncoder();
const td = new TextDecoder('utf-8', {fatal: true});
const MAGIC = te.encode('SCHOLR1\n');

export function bytes(text) { return te.encode(text); }
export function canonical(value) {
  if (value === null || typeof value !== 'object') return JSON.stringify(value);
  if (Array.isArray(value)) return '[' + value.map(canonical).join(',') + ']';
  return '{' + Object.keys(value).sort().map(key => JSON.stringify(key) + ':' + canonical(value[key])).join(',') + '}';
}
export function b64(value) {
  const data = new Uint8Array(value);
  let text = '';
  for (let index = 0; index < data.length; index += 32768) text += String.fromCharCode(...data.subarray(index, index + 32768));
  return btoa(text);
}
export function unb64(value) { return Uint8Array.from(atob(value), character => character.charCodeAt(0)); }
export function randomSecret() { return b64(crypto.getRandomValues(new Uint8Array(32))).replaceAll('+', '-').replaceAll('/', '_').replaceAll('=', ''); }

function concat(...parts) {
  const output = new Uint8Array(parts.reduce((size, part) => size + part.length, 0));
  let offset = 0;
  for (const part of parts) { output.set(part, offset); offset += part.length; }
  return output;
}
function indexBytes(index) {
  const output = new Uint8Array(4);
  new DataView(output.buffer).setUint32(0, index);
  return output;
}
export async function hash(value) {
  return Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', value)), item => item.toString(16).padStart(2, '0')).join('');
}
export async function publisherFingerprint(jwk) {
  return hash(bytes(canonical({kty: jwk.kty, crv: jwk.crv, x: jwk.x, y: jwk.y})));
}
async function passwordKey(password, salt) {
  if (typeof password !== 'string' || password.length > 2048) throw Error('The security code is invalid.');
  const input = await crypto.subtle.importKey('raw', bytes(password), 'PBKDF2', false, ['deriveKey']);
  return crypto.subtle.deriveKey({name: 'PBKDF2', salt, iterations: ITERATIONS, hash: 'SHA-256'}, input, {name: 'AES-GCM', length: 256}, false, ['encrypt', 'decrypt']);
}
export async function seal(secret, password, context) {
  if (password.length < 12) throw Error('The security code is too short.');
  const salt = crypto.getRandomValues(new Uint8Array(16));
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const key = await passwordKey(password, salt);
  const data = await crypto.subtle.encrypt({name: 'AES-GCM', iv, additionalData: bytes('SSKEY1:' + context)}, key, secret);
  return {kdf: 'PBKDF2-SHA256', iterations: ITERATIONS, salt: b64(salt), iv: b64(iv), data: b64(data)};
}
export async function unseal(box, password, context) {
  try {
    if (box.kdf !== 'PBKDF2-SHA256' || box.iterations !== ITERATIONS) throw Error();
    const salt = unb64(box.salt);
    const iv = unb64(box.iv);
    if (salt.length !== 16 || iv.length !== 12) throw Error();
    const key = await passwordKey(password, salt);
    return new Uint8Array(await crypto.subtle.decrypt({name: 'AES-GCM', iv, additionalData: bytes('SSKEY1:' + context)}, key, unb64(box.data)));
  } catch {
    throw Error('The password file, PIN or secure file does not match.');
  }
}
export async function newPublisher(password) {
  const keys = await crypto.subtle.generateKey({name: 'ECDSA', namedCurve: 'P-256'}, true, ['sign', 'verify']);
  const publicJwk = await crypto.subtle.exportKey('jwk', keys.publicKey);
  const fingerprint = await publisherFingerprint(publicJwk);
  const raw = await crypto.subtle.exportKey('pkcs8', keys.privateKey);
  const privateBox = await seal(raw, password, 'publisher:' + fingerprint);
  return {id: 'publisher', publicJwk, fingerprint, privateBox};
}
export async function unlockPublisher(record, password) {
  const raw = await unseal(record.privateBox, password, 'publisher:' + record.fingerprint);
  try { return await crypto.subtle.importKey('pkcs8', raw, {name: 'ECDSA', namedCurve: 'P-256'}, false, ['sign']); }
  finally { raw.fill(0); }
}

function contentType(file) {
  const extension = file.name.split('.').pop().toLowerCase();
  const allowed = {
    mp4: ['video', 'video/mp4'], webm: ['video', 'video/webm'],
    pdf: ['pdf', 'application/pdf'], png: ['image', 'image/png'],
    jpg: ['image', 'image/jpeg'], jpeg: ['image', 'image/jpeg'],
    html: ['web', 'text/html'], htm: ['web', 'text/html']
  };
  return allowed[extension];
}
export async function encryptFile(file, title, onProgress = () => {}, maximum = ONLINE_MAX_FILE) {
  if (file.size <= 0 || file.size > maximum) throw Error('Choose a file that fits this edition. The online limit is 100 MB.');
  const type = contentType(file);
  if (!type) throw Error('Choose a video, PDF, image or self-contained HTML file. Save Word or PowerPoint as PDF online.');
  const keyRaw = crypto.getRandomValues(new Uint8Array(32));
  const key = await crypto.subtle.importKey('raw', keyRaw, 'AES-GCM', false, ['encrypt']);
  const nonce = crypto.getRandomValues(new Uint8Array(8));
  const header = {
    format: 'SecureScholar', version: 1, id: crypto.randomUUID(),
    title: title.trim().slice(0, 200), kind: type[0], mime: type[1],
    size: file.size, chunk: CHUNK, nonce: b64(nonce)
  };
  if (!header.title) throw Error('Enter a name for this file.');
  const raw = bytes(canonical(header));
  const aad = new Uint8Array(await crypto.subtle.digest('SHA-256', raw));
  const parts = [MAGIC, indexBytes(raw.length), raw];
  for (let offset = 0, index = 0; offset < file.size; offset += CHUNK, index++) {
    const block = new Uint8Array(await file.slice(offset, offset + CHUNK).arrayBuffer());
    const idx = indexBytes(index);
    parts.push(await crypto.subtle.encrypt({name: 'AES-GCM', iv: concat(nonce, idx), additionalData: concat(aad, idx)}, key, block));
    block.fill(0);
    onProgress(Math.min(100, Math.round((offset + CHUNK) / file.size * 100)));
  }
  return {blob: new Blob(parts, {type: 'application/octet-stream'}), key: keyRaw, header, header_hash: await hash(raw)};
}
export async function parsePackage(file) {
  try {
    const first = new Uint8Array(await file.slice(0, 12).arrayBuffer());
    if (first.length !== 12 || !MAGIC.every((value, index) => first[index] === value)) throw Error('Choose a Secure Scholar file.');
    const length = new DataView(first.buffer).getUint32(8);
    if (length < 2 || length > 65536) throw Error();
    const raw = new Uint8Array(await file.slice(12, 12 + length).arrayBuffer());
    const header = JSON.parse(td.decode(raw));
    const types = {video: ['video/mp4', 'video/webm'], pdf: ['application/pdf'], image: ['image/png', 'image/jpeg'], web: ['text/html']};
    if (header.format !== 'SecureScholar' || header.version !== 1 || header.chunk !== CHUNK || !Number.isInteger(header.size) || header.size <= 0 || header.size > DESKTOP_MAX_FILE || !types[header.kind]?.includes(header.mime) || typeof header.id !== 'string' || typeof header.title !== 'string' || unb64(header.nonce).length !== 8) throw Error();
    if (file.size !== 12 + length + header.size + Math.ceil(header.size / CHUNK) * 16) throw Error('The secure file is incomplete or damaged.');
    return {file, header, header_hash: await hash(raw), aad: new Uint8Array(await crypto.subtle.digest('SHA-256', raw)), start: 12 + length};
  } catch (error) {
    if (error.message?.startsWith('Choose') || error.message?.startsWith('The secure')) throw error;
    throw Error('This is not a valid Secure Scholar file.');
  }
}
export function validatePolicy(body, now = Date.now(), device = null, high = 0) {
  if (body.format !== 'SecureScholarPass' || body.version !== 1 || !Number.isFinite(body.not_before) || !Number.isFinite(body.expires) || body.not_before >= body.expires || !Number.isInteger(body.max_opens) || body.max_opens < 1 || body.max_opens > 100000 || !Number.isInteger(body.session_seconds) || body.session_seconds < 60 || body.session_seconds > 86400) throw Error('The password settings are invalid.');
  if (now < high - 5000) throw Error('The device date or time moved backwards. Correct it and try again.');
  if (now < body.not_before) throw Error('This password is not active yet.');
  if (now >= body.expires) throw Error('This password has expired.');
  if (body.device && body.device !== device) throw Error('This password belongs to another installation.');
}
export async function issuePass(publisher, privateKey, content, key, portableSecret, pin, policy) {
  const body = {
    format: 'SecureScholarPass', version: 1, id: crypto.randomUUID(),
    content_id: content.header.id, header_hash: content.header_hash,
    recipient: (policy.recipient || 'Secure Scholar Viewer').trim().slice(0, 200),
    not_before: policy.not_before, expires: policy.expires,
    max_opens: policy.max_opens, session_seconds: policy.session_seconds,
    device: policy.device || null, disable_seek: !!policy.disable_seek,
    offline_only: !!policy.offline_only, pin_required: !!pin
  };
  validatePolicy({...body, device: null}, body.not_before, null, 0);
  body.keybox = await seal(key, portableSecret + ':' + pin, 'pass:' + body.id);
  const signature = await crypto.subtle.sign({name: 'ECDSA', hash: 'SHA-256'}, privateKey, bytes('Secure Scholar Pass v1\n' + canonical(body)));
  return {format: 'SecureScholarPasswordFile', version: 1, body, signature: b64(signature), publicJwk: publisher.publicJwk, portableSecret};
}
export async function verifyPass(document, pinnedPublic, content) {
  try {
    if (document.format !== 'SecureScholarPasswordFile' || document.version !== 1 || typeof document.portableSecret !== 'string') throw Error();
    const publicJwk = pinnedPublic || document.publicJwk;
    const key = await crypto.subtle.importKey('jwk', publicJwk, {name: 'ECDSA', namedCurve: 'P-256'}, false, ['verify']);
    const valid = await crypto.subtle.verify({name: 'ECDSA', hash: 'SHA-256'}, key, unb64(document.signature), bytes('Secure Scholar Pass v1\n' + canonical(document.body)));
    if (!valid) throw Error();
    if (document.body.content_id !== content.header.id || document.body.header_hash !== content.header_hash) throw Error('This password belongs to a different secure file.');
    return document.body;
  } catch (error) {
    if (error.message?.startsWith('This password')) throw error;
    throw Error('The password file is invalid or its publisher is not trusted.');
  }
}
export async function contentKey(document, pin = '') {
  const raw = await unseal(document.body.keybox, document.portableSecret + ':' + pin, 'pass:' + document.body.id);
  try { return await crypto.subtle.importKey('raw', raw, 'AES-GCM', false, ['decrypt']); }
  finally { raw.fill(0); }
}
export async function decryptBlock(content, key, index) {
  const offset = index * CHUNK;
  const size = Math.min(CHUNK, content.header.size - offset);
  if (size <= 0) throw Error('Invalid secure file block.');
  const idx = indexBytes(index);
  const start = content.start + index * (CHUNK + 16);
  const raw = await content.file.slice(start, start + size + 16).arrayBuffer();
  try { return new Uint8Array(await crypto.subtle.decrypt({name: 'AES-GCM', iv: concat(unb64(content.header.nonce), idx), additionalData: concat(content.aad, idx)}, key, raw)); }
  catch { throw Error('The secure file failed its safety check.'); }
}
export async function decryptBlob(content, key, onProgress = () => {}) {
  const blocks = [];
  for (let index = 0; index < Math.ceil(content.header.size / CHUNK); index++) {
    blocks.push(await decryptBlock(content, key, index));
    onProgress(Math.min(100, Math.round((index + 1) * CHUNK / content.header.size * 100)));
  }
  return new Blob(blocks, {type: content.header.mime});
}
