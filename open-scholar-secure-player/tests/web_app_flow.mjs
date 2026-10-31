// Browser test for the web app: installable, offline, password generator, protected-view protections.
//   (cd dist && python3 -m http.server 8765 --bind 127.0.0.1) &
//   SECURE_SCHOLAR_URL=http://127.0.0.1:8765/ node tests/web_app_flow.mjs
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import {createRequire} from 'node:module';
const require = createRequire(import.meta.url);
const {chromium} = require('playwright');

const base = process.env.SECURE_SCHOLAR_URL || 'http://127.0.0.1:8765/';
const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'ossp-web-'));
const sample = new URL('./sample_lesson.html', import.meta.url).pathname;
const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
const checks = [];

try {
  const context = await browser.newContext({viewport: {width: 390, height: 844}, acceptDownloads: true});
  const page = await context.newPage();
  await page.goto(base, {waitUntil: 'networkidle'});

  // Installable web app
  const manifest = JSON.parse(await page.evaluate(async () => (await fetch('manifest.webmanifest')).text()));
  assert.equal(manifest.name, 'Open Scholar Secure Player');
  assert.equal(manifest.display, 'standalone');
  for (const icon of ['icon-192.png', 'icon-512.png', 'apple-touch-icon.png', 'favicon.ico']) {
    const status = await page.evaluate(async name => (await fetch(name)).status, icon);
    assert.equal(status, 200, `${icon} missing`);
  }
  checks.push('manifest, 192/512 px cap icons, apple touch icon and favicon are served');

  // Offline: the service worker caches the app, so it still opens with no network.
  await page.evaluate(() => navigator.serviceWorker.ready);
  await page.reload({waitUntil: 'networkidle'});
  await page.evaluate(() => navigator.serviceWorker.ready);
  await context.setOffline(true);
  await page.reload({waitUntil: 'load'});
  assert.equal(await page.locator('#home').isVisible(), true, 'home should load offline');
  await context.setOffline(false);
  checks.push('app opens with the network switched off (service worker cache)');

  // Password generator
  await page.locator('#start-secure').click();
  await page.locator('.advanced-block > summary').click();
  await page.locator('#generate-pin').click();
  const code = await page.locator('#pin').inputValue();
  assert.equal(code.length, 12, 'generated code must be 12 characters');
  assert.match(code, /^[A-HJ-NP-Za-km-z2-9]{12}$/, 'no look-alike characters');
  assert.equal(await page.locator('#copy-pin').isVisible(), true);
  await page.locator('#generate-pin').click();
  const second = await page.locator('#pin').inputValue();
  assert.notEqual(code, second, 'each code must be new');
  checks.push('password generator creates unique 12-character codes without look-alike characters');

  // Protected view: protect a file, reopen it, then check the veil and print block.
  await page.locator('#source').setInputFiles(sample);
  await page.locator('#opens').fill('3');
  await page.locator('#secure-button').click();
  await page.locator('#delivery').waitFor({state: 'visible', timeout: 30000});
  const secureDownload = await Promise.all([page.waitForEvent('download'), page.locator('#download-file').click()]).then(r => r[0]);
  const passDownload = await Promise.all([page.waitForEvent('download'), page.locator('#download-pass').click()]).then(r => r[0]);
  const securePath = path.join(temporary, 'lesson.scholar');
  const passPath = path.join(temporary, 'lesson.scholarpass');
  await secureDownload.saveAs(securePath);
  await passDownload.saveAs(passPath);
  await page.locator('#home-button').click();
  await page.locator('#start-open').click();
  await page.locator('#secure-file').setInputFiles(securePath);
  await page.locator('#pass-file').setInputFiles(passPath);
  await page.locator('#pin-row').waitFor({state: 'visible'});
  await page.locator('#open-pin').fill(second);
  await page.locator('#open-button').click();
  await page.locator('#viewer').waitFor({state: 'visible', timeout: 30000});

  await page.evaluate(() => window.dispatchEvent(new Event('blur')));
  await page.evaluate(() => Object.defineProperty(document, 'hidden', {value: true, configurable: true}));
  await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
  assert.equal(await page.evaluate(() => document.body.classList.contains('veiled')), true, 'veil on hidden tab');
  await page.evaluate(() => Object.defineProperty(document, 'hidden', {value: false, configurable: true}));
  await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
  await page.evaluate(() => window.dispatchEvent(new Event('focus')));
  assert.equal(await page.evaluate(() => document.body.classList.contains('veiled')), false, 'veil removed on return');
  checks.push('protected view is veiled when the tab is hidden or loses focus, and clears on return');

  await page.evaluate(() => window.dispatchEvent(new Event('beforeprint')));
  assert.equal(await page.evaluate(() => document.body.classList.contains('no-print')), true);
  await page.evaluate(() => window.dispatchEvent(new Event('afterprint')));
  checks.push('printing a protected file is blocked (no-print while printing)');

  await page.screenshot({path: path.join(temporary, 'protected-390.png')});
  console.log(JSON.stringify({status: 'passed', checks, screenshots: temporary}, null, 2));
} finally {
  await browser.close();
}
