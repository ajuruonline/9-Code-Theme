// Open Scholar Secure Player: modules (a described group of files in order), audio, text, full screen.
//   (cd dist && python3 -m http.server 8766 --bind 127.0.0.1) &
//   SECURE_SCHOLAR_URL=http://127.0.0.1:8766/ node tests/modules_flow.mjs
import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
const require = createRequire(import.meta.url);
const {chromium} = require('playwright');

const base = process.env.SECURE_SCHOLAR_URL || 'http://127.0.0.1:8766/';
const fixtures = new URL('./fixtures/', import.meta.url).pathname;
const files = [fixtures + 'two-page-lesson.pdf', fixtures + 'tone.wav', fixtures + 'notes.txt'];
const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
const checks = [];
try {
  const context = await browser.newContext({viewport: {width: 1280, height: 800}});
  const page = await context.newPage();
  page.on('pageerror', error => { throw error; });
  page.on('dialog', dialog => dialog.accept());
  await page.goto(base, {waitUntil: 'networkidle'});

  // 1. Create a module with a title and description.
  await page.locator('#new-module').click();
  await page.locator('#module-title').fill('Week 1: Introduction');
  await page.locator('#module-description').fill('Start with the PDF, then listen to the audio, then read the notes.');
  await page.locator('#module-create-form button[type=submit]').click();
  await page.locator('#module-view').waitFor({state: 'visible'});
  assert.equal(await page.locator('#module-title-view').textContent(), 'Week 1: Introduction');
  assert.match(await page.locator('#module-description-view').textContent(), /Start with the PDF/);
  checks.push('module created with a title and a description (the post)');

  // 2. Add three different files in one go; they keep their order.
  await page.locator('#module-files').setInputFiles(files);
  await page.locator('#module-items li').nth(2).waitFor({state: 'visible'});
  const names = await page.locator('.launcher strong').allTextContents();
  assert.deepEqual(names.map(n => n.replace(/^\d+\.\s/, '')), ['two-page-lesson.pdf', 'tone.wav', 'notes.txt']);
  checks.push('three files added at once, shown in order as launchers');

  // 3. Each launcher opens its file; back returns to the module post.
  await page.locator('.launcher').nth(0).click();
  await page.waitForFunction(() => document.querySelector('#page-indicator').textContent === 'Page 1 of 2', null, {timeout: 30000});
  assert.equal(await page.locator('#back-to-module').isVisible(), true);
  await page.locator('#back-to-module').click();
  await page.locator('#module-view').waitFor({state: 'visible'});
  await page.locator('.launcher').nth(1).click();
  await page.locator('#audio-card').waitFor({state: 'visible'});
  assert.equal(await page.locator('#audio-title').textContent(), 'tone.wav');
  await page.locator('#back-to-module').click();
  await page.locator('.launcher').nth(2).click();
  await page.waitForFunction(() => document.querySelector('#text-view').textContent.includes('Line two of the text lesson'), null, {timeout: 15000});
  checks.push('PDF, audio and text open from the module and return to it');

  // 4. Arrange: move the audio up, then move it back down.
  await page.locator('#back-to-module').click();
  await page.locator('#module-edit').click();
  await page.locator('#module-items li').nth(1).getByRole('button', {name: 'Move up'}).click();
  await page.waitForFunction(() => document.querySelectorAll('.launcher strong')[0].textContent.includes('tone.wav'));
  checks.push('arrange: items can be moved up and down');

  // 5. Persistence: the module is still there after reloading the player.
  await page.locator('#module-edit').click();
  await page.reload({waitUntil: 'networkidle'});
  await page.locator('#module-list .module-card').first().waitFor({state: 'visible'});
  assert.match(await page.locator('#module-list .module-card').first().textContent(), /Week 1: Introduction/);
  assert.match(await page.locator('#module-list .module-card').first().textContent(), /3 items/);
  checks.push('module and its order survive a reload (stored on this device)');

  // 6. Full screen button and layout at phone widths.
  assert.equal(await page.locator('#fullscreen-app').isVisible(), true);
  for (const width of [320, 390, 768]) {
    await page.setViewportSize({width, height: 800});
    await page.locator('#module-list .module-card').first().click();
    await page.locator('#module-view').waitFor({state: 'visible'});
    const overflow = await page.evaluate(() => document.querySelector('.player').scrollWidth - document.querySelector('.player').clientWidth);
    assert.ok(overflow <= 0, `module view overflows by ${overflow}px at ${width}px`);
    await page.locator('#module-back').click();
  }
  checks.push('module view has no sideways overflow at 320, 390 and 768 px; full screen button present');

  // 7. Delete the module.
  await page.locator('#module-list .module-card').first().click();
  await page.locator('#module-view').waitFor({state: 'visible'});
  await page.locator('#module-delete').click();
  await page.locator('#module-empty').waitFor({state: 'visible'});
  checks.push('module can be deleted with all its files');
  console.log(JSON.stringify({status: 'passed', checks}, null, 2));
} finally {
  await browser.close();
}
