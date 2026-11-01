// Creator shares a module as a file; a learner in a fresh browser opens it and uses it. Also checks the phone layout.
//   SECURE_SCHOLAR_URL=http://127.0.0.1:8766/ node tests/share_flow.mjs
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import {createRequire} from 'node:module';
const require = createRequire(import.meta.url);
const {chromium} = require('playwright');
const base = process.env.SECURE_SCHOLAR_URL || 'http://127.0.0.1:8766/';
const fx = new URL('./fixtures/', import.meta.url).pathname;
const work = fs.mkdtempSync(path.join(os.tmpdir(), 'ossp-share-'));
const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
const checks = [];
try {
  // Creator (PC)
  const creator = await (await browser.newContext({viewport: {width: 1280, height: 800}, acceptDownloads: true})).newPage();
  creator.on('pageerror', error => { throw error; });
  await creator.goto(base, {waitUntil: 'networkidle'});
  await creator.locator('#new-module').click();
  await creator.locator('#module-title').fill('Week 1: Introduction');
  await creator.locator('#module-description').fill('Start with the PDF, then listen to the audio, then read the notes.');
  await creator.locator('#module-create-form button[type=submit]').click();
  await creator.locator('#module-view').waitFor({state: 'visible'});
  await creator.locator('#module-files').setInputFiles([fx + 'two-page-lesson.pdf', fx + 'tone.wav', fx + 'notes.txt']);
  await creator.locator('#module-items li').nth(2).waitFor({state: 'visible'});
  const [download] = await Promise.all([creator.waitForEvent('download'), creator.locator('#module-export').click()]);
  const shared = path.join(work, 'week1.osmodule');
  await download.saveAs(shared);
  const bundle = JSON.parse(fs.readFileSync(shared, 'utf8'));
  assert.equal(bundle.format, 'OpenScholarModule');
  assert.equal(bundle.protected, false);
  assert.equal(bundle.items.length, 3);
  checks.push('creator shares the module as one .osmodule file (3 items, marked not protected)');

  // Learner (phone, fresh browser profile) opens the file with Open a file
  const learner = await (await browser.newContext({viewport: {width: 390, height: 844}, deviceScaleFactor: 2})).newPage();
  learner.on('pageerror', error => { throw error; });
  await learner.goto(base, {waitUntil: 'networkidle'});
  await learner.locator('#plain-file').setInputFiles(shared);
  await learner.locator('#module-view').waitFor({state: 'visible', timeout: 30000});
  assert.equal(await learner.locator('#module-title-view').textContent(), 'Week 1: Introduction');
  assert.equal(await learner.locator('.launcher').count(), 3);
  checks.push('learner opens the shared file on a phone; the module appears with its 3 items');

  // Learner opens the PDF from the module; the library is hidden while viewing, so the lesson has the room.
  await learner.locator('.launcher').nth(0).click();
  await learner.locator('#page-indicator').filter({hasText: 'Page 1 of 2'}).waitFor({timeout: 30000});
  assert.equal(await learner.locator('.library').isVisible(), false, 'library should be hidden while viewing on a phone');
  checks.push('lesson opens from the module; the library steps aside on the phone');

  // Back to the module, then the welcome screen again with the library visible.
  await learner.locator('#back-to-module').click();
  await learner.locator('#module-view').waitFor({state: 'visible'});
  await learner.locator('#module-back').click();
  await learner.locator('#welcome').waitFor({state: 'visible'});
  assert.equal(await learner.locator('.library').isVisible(), true);
  checks.push('learner can return to the library and the module list');

  for (const width of [320, 390]) {
    await learner.setViewportSize({width, height: 800});
    const overflow = await learner.evaluate(() => document.querySelector('.player').scrollWidth - document.querySelector('.player').clientWidth);
    assert.ok(overflow <= 0, `overflow ${overflow}px at ${width}px`);
  }
  checks.push('no sideways overflow at 320 and 390 px');
  console.log(JSON.stringify({status: 'passed', checks, files: work}, null, 2));
} finally {
  await browser.close();
}
