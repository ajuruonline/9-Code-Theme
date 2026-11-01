// Open Scholar Secure Player: ordinary files, protected lessons with the limits screen, protections, offline, layout.
//   (cd dist && python3 -m http.server 8765 --bind 127.0.0.1) &
//   SECURE_SCHOLAR_URL=http://127.0.0.1:8765/ node tests/player_flow.mjs
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import {createRequire} from 'node:module';
const require = createRequire(import.meta.url);
const {chromium} = require('playwright');

const base = process.env.SECURE_SCHOLAR_URL || 'http://127.0.0.1:8765/';
const fixture = new URL('./fixtures/two-page-lesson.pdf', import.meta.url).pathname;
const picture = new URL('../assets/original-cap.png', import.meta.url).pathname;
const work = fs.mkdtempSync(path.join(os.tmpdir(), 'ossp-player-'));
const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
const checks = [];

try {
  const context = await browser.newContext({viewport: {width: 1280, height: 800}, acceptDownloads: true});
  const maker = await context.newPage();
  const player = await context.newPage();

  // 1. The player has no publisher controls.
  await player.goto(base, {waitUntil: 'networkidle'});
  assert.equal(await player.locator('#secure-form').count(), 0, 'player must not contain the protect form');
  assert.equal(await player.locator('#generate-pin').count(), 0, 'player must not contain the password generator');
  checks.push('player page has no protect form or password generator');

  // 2. Ordinary PDF: opens at once, pages work, nothing is used up.
  await player.locator('#plain-file').setInputFiles(fixture);
  await player.locator('#viewer').waitFor({state: 'visible', timeout: 30000});
  await player.waitForFunction(() => document.querySelector('#page-indicator').textContent === 'Page 1 of 2', null, {timeout: 30000});
  assert.match(await player.locator('#status-text').textContent(), /nothing is used up/);
  await player.locator('#next').click();
  await player.waitForFunction(() => document.querySelector('#page-indicator').textContent === 'Page 2 of 2', null, {timeout: 15000});
  checks.push('ordinary PDF opens with no password and pages turn');

  await player.locator('#menu-close').click();
  await player.locator('#welcome').waitFor({state: 'visible'});

  // 3. Protected lesson made by the maker.
  await maker.goto(base + 'maker.html', {waitUntil: 'networkidle'});
  await maker.locator('.advanced-block > summary').click();
  await maker.locator('#generate-pin').click();
  const code = await maker.locator('#pin').inputValue();
  await maker.locator('#source').setInputFiles(fixture);
  await maker.locator('#opens').fill('3');
  await maker.locator('#secure-button').click();
  await maker.locator('#delivery').waitFor({state: 'visible', timeout: 60000});
  const [lesson] = await Promise.all([maker.waitForEvent('download'), maker.locator('#download-file').click()]);
  const [password] = await Promise.all([maker.waitForEvent('download'), maker.locator('#download-pass').click()]);
  const lessonPath = path.join(work, 'lesson.scholar');
  const passPath = path.join(work, 'lesson.scholarpass');
  await lesson.saveAs(lessonPath);
  await password.saveAs(passPath);

  await player.locator('#menu-protected').click();
  await player.locator('#secure-file').setInputFiles(lessonPath);
  await player.locator('#pass-file').setInputFiles(passPath);
  await player.locator('#pin-row').waitFor({state: 'visible'});
  await player.locator('#open-pin').fill(code);
  await player.locator('#open-button').click();

  // 4. The limits are listed before any viewing, and nothing is used up yet.
  await player.locator('#notice').waitFor({state: 'visible', timeout: 30000});
  const limits = await player.locator('#notice-list li').allTextContents();
  assert.ok(limits.length >= 8, `expected the list of limits, got ${limits.length}`);
  assert.ok(limits.some(text => /cannot copy, save, download or print/.test(text)));
  assert.ok(limits.some(text => /deliberate/.test(text) && /not a crash/.test(text)));
  assert.equal(await player.locator('#notice-start').isDisabled(), true, 'start locked until the reader agrees');
  await player.locator('#notice-agree').check();
  assert.equal(await player.locator('#notice-start').isDisabled(), false);
  await player.screenshot({path: path.join(work, 'notice.png')});
  checks.push(`protected lesson lists ${limits.length} limits and needs agreement before viewing`);

  // 5. Cancelling uses no opening; starting uses one.
  await player.locator('#notice-cancel').click();
  await player.locator('#welcome').waitFor({state: 'visible'});
  await player.locator('#menu-protected').click();
  await player.locator('#secure-file').setInputFiles(lessonPath);
  await player.locator('#pass-file').setInputFiles(passPath);
  await player.locator('#open-pin').fill(code);
  await player.locator('#open-button').click();
  await player.locator('#notice').waitFor({state: 'visible', timeout: 60000});
  await player.locator('#notice-agree').check();
  await player.locator('#notice-start').click();
  await player.locator('#viewer').waitFor({state: 'visible', timeout: 60000});
  await player.waitForFunction(() => /2 openings left after this one/.test(document.getElementById('status-text').textContent), null, {timeout: 60000});
  checks.push('cancel uses no opening; start uses one (3 to 2 left)');

  // 6. Protections on the protected lesson.
  await player.evaluate(() => window.dispatchEvent(new Event('blur')));
  await player.evaluate(() => Object.defineProperty(document, 'hidden', {value: true, configurable: true}));
  await player.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
  assert.equal(await player.evaluate(() => document.body.classList.contains('veiled')), true, 'veil when hidden');
  await player.evaluate(() => Object.defineProperty(document, 'hidden', {value: false, configurable: true}));
  await player.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
  await player.evaluate(() => window.dispatchEvent(new Event('focus')));
  assert.equal(await player.evaluate(() => document.body.classList.contains('veiled')), false, 'veil clears');
  await player.evaluate(() => window.dispatchEvent(new Event('beforeprint')));
  assert.equal(await player.evaluate(() => document.body.classList.contains('no-print')), true, 'print blocked');
  await player.evaluate(() => window.dispatchEvent(new Event('afterprint')));
  checks.push('protected view: veil on leaving the window, print blocked');

  // 7. Installable and offline.
  const manifest = JSON.parse(await player.evaluate(async () => (await fetch('manifest.webmanifest')).text()));
  assert.equal(manifest.name, 'Open Scholar Secure Player');
  for (const icon of ['icon-192.png', 'icon-512.png', 'apple-touch-icon.png', 'favicon.ico']) {
    assert.equal(await player.evaluate(async name => (await fetch(name)).status, icon), 200, icon);
  }
  await player.evaluate(() => navigator.serviceWorker.ready);
  await player.reload({waitUntil: 'networkidle'});
  await player.evaluate(() => navigator.serviceWorker.ready);
  await context.setOffline(true);
  await player.reload({waitUntil: 'load'});
  assert.equal(await player.locator('#welcome').isVisible(), true, 'player opens offline');
  await context.setOffline(false);
  checks.push('installable manifest and cap icons; opens with the network off');

  // 8. Layout at phone and PC widths: no horizontal scrolling.
  for (const width of [320, 360, 390, 430, 768, 1280]) {
    const page = await context.newPage();
    await page.setViewportSize({width, height: 800});
    await page.goto(base, {waitUntil: 'networkidle'});
    const overflow = await page.evaluate(() => document.querySelector('.player').scrollWidth - document.querySelector('.player').clientWidth);
    assert.ok(overflow <= 0, `horizontal overflow ${overflow}px at ${width}px`);
    await page.screenshot({path: path.join(work, `player-${width}.png`)});
    await page.close();
  }
  checks.push('player layout at 320, 360, 390, 430, 768 and 1280 px: no horizontal overflow');
  console.log(JSON.stringify({status: 'passed', checks, screenshots: work}, null, 2));
} finally {
  await browser.close();
}
