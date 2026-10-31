// Browser test for ordinary (unprotected) opening. Serve dist/ first, then:
//   SECURE_SCHOLAR_URL=http://127.0.0.1:8765/ node tests/plain_flow.mjs
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import {createRequire} from 'node:module';
const require = createRequire(import.meta.url);
const {chromium} = require('playwright');

const base = process.env.SECURE_SCHOLAR_URL || 'http://127.0.0.1:8765/';
const fixtures = new URL('./fixtures/', import.meta.url).pathname;
const pdfPath = path.join(fixtures, 'two-page-lesson.pdf');
const pngPath = new URL('../assets/original-cap.png', import.meta.url).pathname;
const htmlPath = new URL('./sample_lesson.html', import.meta.url).pathname;
const shots = fs.mkdtempSync(path.join(os.tmpdir(), 'ossp-plain-'));
const browser = await chromium.launch({headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage']});
const results = [];

async function noHorizontalScroll(page, width) {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  assert.ok(overflow <= 0, `horizontal scroll of ${overflow}px at ${width}px`);
}

try {
  for (const width of [320, 360, 390, 430, 768, 1280]) {
    const page = await browser.newPage({viewport: {width, height: 800}});
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(base, {waitUntil: 'networkidle'});
    await page.waitForFunction(() => document.querySelector('#plain-file') !== null);
    assert.equal(await page.locator('#home').isVisible(), true, 'home visible');
    await noHorizontalScroll(page, width);
    await page.screenshot({path: path.join(shots, `home-${width}.png`), fullPage: true});
    results.push(`home ${width}px: no horizontal scroll`);
    if (width === 390) {
      // Ordinary PDF: opens straight away, no password, page navigation works.
      await page.locator('#plain-file').setInputFiles(pdfPath);
      await page.locator('#viewer').waitFor({state: 'visible', timeout: 30000});
      assert.equal(await page.locator('#view-kind').textContent(), 'OPENED FILE');
      await page.locator('#page-controls').waitFor({state: 'visible', timeout: 30000});
      assert.equal(await page.locator('#next').isDisabled(), false);
      await page.locator('#next').click();
      await page.waitForFunction(() => document.querySelector('#page-select').value === '2', null, {timeout: 15000});
      await page.screenshot({path: path.join(shots, `pdf-390.png`), fullPage: true});
      await noHorizontalScroll(page, width);
      // Close and reopen: the saved place should return us to page 2.
      await page.locator('#lock-button').click();
      await page.locator('#home').waitFor({state: 'visible'});
      await page.locator('#plain-file').setInputFiles(pdfPath);
      await page.waitForFunction(() => document.querySelector('#page-select').value === '2', null, {timeout: 30000});
      results.push('plain PDF: opens without password, next page, closes to home, resumes page 2');
      // My reading lists it.
      await page.locator('#lock-button').click();
      await page.locator('#start-reading').click();
      await page.locator('#recent-list li').first().waitFor({state: 'visible'});
      assert.match(await page.locator('#recent-list li strong').first().textContent(), /two-page-lesson/);
      results.push('My reading lists the recently opened file');
      await page.locator('#clear-recent').click();
      assert.equal(await page.locator('#recent-empty').isVisible(), true);
      results.push('Forget this list clears the list');
    }
    if (width === 390) {
      // Unsupported type: friendly message, nothing opens.
      const bad = path.join(shots, 'notes.docx');
      fs.writeFileSync(bad, 'not really a document');
      await page.locator('#home-button').click();
      await page.locator('#plain-file').setInputFiles(bad);
      await page.locator('#message').waitFor({state: 'visible'});
      assert.match(await page.locator('#message').textContent(), /cannot be opened here yet/);
      assert.equal(await page.locator('#viewer').isVisible(), false);
      results.push('unsupported file: friendly message, viewer stays closed');
      // Picture and saved web lesson open with the same viewer.
      await page.locator('#home-button').click();
      await page.locator('#plain-file').setInputFiles(pngPath);
      await page.locator('#pages-wrap').waitFor({state: 'visible', timeout: 15000});
      results.push('picture opens in the same viewer');
      await page.locator('#lock-button').click();
      await page.locator('#home').waitFor({state: 'visible'});
      await page.locator('#plain-file').setInputFiles(htmlPath);
      await page.locator('#web-wrap').waitFor({state: 'visible', timeout: 15000});
      results.push('saved web lesson opens in the same viewer');
    }
    assert.deepEqual(errors, [], `page errors at ${width}px`);
    await page.close();
  }
  console.log(JSON.stringify({status: 'passed', checks: results, screenshots: shots}, null, 2));
} finally {
  await browser.close();
}
