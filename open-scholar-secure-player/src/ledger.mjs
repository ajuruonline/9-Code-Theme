/* Local, integrity-checked Secure Scholar opening history. */
import {b64, bytes, canonical, unb64, validatePolicy} from './crypto.mjs';
const storeName = 'records';

export async function openStore(name = 'SecureScholarV1') {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(name, 1);
    request.onupgradeneeded = () => request.result.createObjectStore(storeName, {keyPath: 'id'});
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(Error('Allow secure local storage to use Secure Scholar.'));
  });
}
export function readRecord(db, id) {
  return new Promise((resolve, reject) => {
    const request = db.transaction(storeName, 'readonly').objectStore(storeName).get(id);
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}
export function putRecord(db, value) {
  return new Promise((resolve, reject) => {
    const transaction = db.transaction(storeName, 'readwrite');
    transaction.objectStore(storeName).put(value);
    transaction.oncomplete = () => resolve();
    transaction.onerror = () => reject(transaction.error);
    transaction.onabort = () => reject(transaction.error || Error('Unable to save secure local data.'));
  });
}
export function allRecords(db) {
  return new Promise((resolve, reject) => {
    const request = db.transaction(storeName, 'readonly').objectStore(storeName).getAll();
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}
function casRecord(db, previous, next) {
  return new Promise((resolve, reject) => {
    const transaction = db.transaction(storeName, 'readwrite');
    const store = transaction.objectStore(storeName);
    let accepted = false;
    const request = store.get('usage');
    request.onsuccess = () => {
      if ((request.result?.mac || null) === (previous?.mac || null)) {
        store.put(next);
        accepted = true;
      }
    };
    transaction.oncomplete = () => resolve(accepted);
    transaction.onerror = () => reject(transaction.error);
    transaction.onabort = () => reject(transaction.error || Error('Unable to update opening credits.'));
  });
}
export async function createLedger(db) {
  const guard = await readRecord(db, 'guard');
  const usage = await readRecord(db, 'usage');
  if (!!guard !== !!usage) throw Error('The opening-credit history is incomplete.');
  if (guard) return;
  const key = await crypto.subtle.generateKey({name: 'HMAC', hash: 'SHA-256'}, false, ['sign', 'verify']);
  const state = {device: crypto.randomUUID().replaceAll('-', '').toUpperCase(), clock: Date.now(), counts: {}, revision: 0};
  const mac = b64(await crypto.subtle.sign('HMAC', key, bytes(canonical(state))));
  await new Promise((resolve, reject) => {
    const transaction = db.transaction(storeName, 'readwrite');
    const store = transaction.objectStore(storeName);
    const request = store.get('guard');
    request.onsuccess = () => {
      if (!request.result) {
        store.put({id: 'guard', key});
        store.put({id: 'usage', state, mac});
      }
    };
    transaction.oncomplete = resolve;
    transaction.onerror = () => reject(transaction.error);
  });
}
export async function useLedger(db, body = null, consume = false) {
  for (let attempt = 0; attempt < 15; attempt++) {
    const guard = await readRecord(db, 'guard');
    const previous = await readRecord(db, 'usage');
    if (!guard || !previous) throw Error('The opening-credit history is missing.');
    const valid = await crypto.subtle.verify('HMAC', guard.key, unb64(previous.mac), bytes(canonical(previous.state)));
    if (!valid) throw Error('The opening-credit history failed its safety check.');
    const state = structuredClone(previous.state);
    const now = Date.now();
    if (now < state.clock - 5000) throw Error('The device date or time moved backwards.');
    if (body) validatePolicy(body, now, state.device, state.clock);
    const used = body ? (state.counts[body.id] || 0) : 0;
    if (consume && used >= body.max_opens) throw Error('No opening credits remain.');
    if (consume) state.counts[body.id] = used + 1;
    state.clock = Math.max(state.clock, now);
    state.revision++;
    const mac = b64(await crypto.subtle.sign('HMAC', guard.key, bytes(canonical(state))));
    if (await casRecord(db, previous, {id: 'usage', state, mac})) {
      return {device: state.device, remaining: body ? body.max_opens - (consume ? used + 1 : used) : null};
    }
  }
  throw Error('Another Secure Scholar window is busy. Close it and try again.');
}
