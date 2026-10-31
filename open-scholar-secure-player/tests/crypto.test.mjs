import test from 'node:test';
import assert from 'node:assert/strict';
import {webcrypto} from 'node:crypto';
import {
  CHUNK, encryptFile, parsePackage, decryptBlob, decryptBlock,
  newPublisher, unlockPublisher, issuePass, verifyPass, contentKey,
  validatePolicy, seal, unseal, canonical, randomSecret
} from '../src/crypto.mjs';

if (!globalThis.crypto) globalThis.crypto = webcrypto;
const ownerPassword = 'Secure Scholar owner example 2026!';
let identity, privateKey, encrypted, parsed, passwordFile;

test('publisher, multi-chunk encryption and password-file round trip', async () => {
  identity = await newPublisher(ownerPassword);
  privateKey = await unlockPublisher(identity, ownerPassword);
  const original = crypto.getRandomValues(new Uint8Array(32768));
  const file = new File(Array.from({length: 70}, () => original), 'lecture.webm', {type: 'video/webm'});
  encrypted = await encryptFile(file, 'Secure Scholar Video');
  parsed = await parsePackage(encrypted.blob);
  const portableSecret = randomSecret();
  passwordFile = await issuePass(identity, privateKey, encrypted, encrypted.key, portableSecret, '', {
    recipient: 'Zebulon Abule', not_before: Date.now() - 60000,
    expires: Date.now() + 600000, max_opens: 2, session_seconds: 60,
    disable_seek: true, offline_only: false
  });
  const body = await verifyPass(passwordFile, identity.publicJwk, parsed);
  validatePolicy(body);
  const key = await contentKey(passwordFile);
  const decoded = await decryptBlob(parsed, key);
  assert.deepEqual(new Uint8Array(await decoded.arrayBuffer()), new Uint8Array(await file.arrayBuffer()));
  assert.equal(parsed.header.format, 'SecureScholar');
  assert.equal(body.format, 'SecureScholarPass');
  assert.equal(body.disable_seek, true);
});

test('optional PIN protects the password file', async () => {
  const portableSecret = randomSecret();
  const withPin = await issuePass(identity, privateKey, encrypted, encrypted.key, portableSecret, '4829', {
    recipient: 'PIN Viewer', not_before: Date.now() - 1000, expires: Date.now() + 60000,
    max_opens: 3, session_seconds: 60
  });
  await assert.rejects(() => contentKey(withPin, ''), /does not match/);
  const key = await contentKey(withPin, '4829');
  assert.ok(key);
  assert.equal(withPin.body.pin_required, true);
});

test('changed access rules and recipient invalidate signature', async () => {
  for (const [field, value] of [['expires', Date.now() + 9999999], ['max_opens', 999], ['recipient', 'Another viewer']]) {
    const changed = structuredClone(passwordFile);
    changed.body[field] = value;
    await assert.rejects(() => verifyPass(changed, identity.publicJwk, parsed), /invalid|trusted/);
  }
});

test('different publisher is rejected when the official publisher is pinned', async () => {
  const other = await newPublisher(ownerPassword);
  await assert.rejects(() => verifyPass(passwordFile, other.publicJwk, parsed), /invalid|trusted/);
});

test('password file is bound to one secure file', async () => {
  const other = await encryptFile(new File(['test'], 'other.webm'), 'Other');
  await assert.rejects(async () => verifyPass(passwordFile, identity.publicJwk, await parsePackage(other.blob)), /different secure file/);
});

test('ciphertext corruption is detected', async () => {
  const data = new Uint8Array(await encrypted.blob.arrayBuffer());
  data[parsed.start + CHUNK + 16 + 20] ^= 1;
  const altered = await parsePackage(new Blob([data]));
  const key = await contentKey(passwordFile);
  await assert.rejects(() => decryptBlock(altered, key, 1), /safety check/);
});

test('plaintext, truncation and unsupported files are rejected', async () => {
  await assert.rejects(() => parsePackage(new Blob(['A plaintext file'])), /Choose/);
  await assert.rejects(() => parsePackage(encrypted.blob.slice(0, -1)), /incomplete/);
  await assert.rejects(() => encryptFile(new File(['x'], 'slides.pptx'), 'Slides'), /PDF/);
});

test('HTML web lessons use the common secure format', async () => {
  const html = new File(['<!doctype html><h1>Offline lesson</h1>'], 'lesson.html', {type: 'text/html'});
  const secured = await encryptFile(html, 'Offline Lesson');
  const packageFile = await parsePackage(secured.blob);
  assert.equal(packageFile.header.kind, 'web');
  assert.equal(packageFile.header.mime, 'text/html');
});

test('expiry, start, device and rollback rules are enforced', () => {
  const body = passwordFile.body;
  assert.throws(() => validatePolicy(body, body.expires), /expired/);
  assert.throws(() => validatePolicy(body, body.not_before - 1), /not active/);
  assert.throws(() => validatePolicy({...body, device: 'ABC'}, Date.now(), 'DEF'), /another installation/);
  assert.throws(() => validatePolicy(body, Date.now(), null, Date.now() + 10000), /backwards/);
});

test('invalid policies and context-bound wrapping are rejected', async () => {
  for (const change of [{expires: NaN}, {max_opens: 0}, {session_seconds: 0}, {not_before: Infinity}]) {
    assert.throws(() => validatePolicy({...passwordFile.body, ...change}), /invalid/);
  }
  const box = await seal(new Uint8Array(32), ownerPassword, 'correct');
  await assert.rejects(() => unseal(box, ownerPassword, 'wrong'), /does not match/);
});

test('canonical serialization is independent of property order', () => {
  assert.equal(canonical({z: 1, a: {y: 2, x: 3}}), canonical({a: {x: 3, y: 2}, z: 1}));
});
