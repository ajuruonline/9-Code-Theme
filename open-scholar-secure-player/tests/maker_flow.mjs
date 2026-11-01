// Open Scholar Secure File Maker: makes the two files, with no player controls on the page.
//   (cd dist && python3 -m http.server 8765 --bind 127.0.0.1) &
//   SECURE_SCHOLAR_URL=http://127.0.0.1:8765/ node tests/maker_flow.mjs
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import {createRequire} from 'node:module';
const require = createRequire(import.meta.url);
const {chromium} = require('playwright');

const base = process.env.SECURE_SCHOLAR_URL || 'http://127.0.0.1:8765/';
const fixture = new URL('./fixtures/two-page-lesson.pdf', import.meta.url).pathname;
const out = fs.mkdtempSync(path.join(os.tmpdir(), 'ossp-maker-'));
const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
const checks = [];
try {
  const context = await browser.newContext({viewport: {width: 1280, height: 900}, acceptDownloads: true});
  const page = await context.newPage();
  await page.goto(base + 'maker.html', {waitUntil: 'networkidle'});
  assert.equal(await page.locator('#play-button').count(), 0, 'maker must not contain player controls');
  assert.equal(await page.locator('#viewer').count(), 0, 'maker must not contain the viewer');
  checks.push('maker page has the publisher tools and no player controls');

  await page.locator('.advanced-block > summary').click();
  await page.locator('#generate-pin').click();
  assert.equal((await page.locator('#pin').inputValue()).length, 12);
  await page.locator('#source').setInputFiles(fixture);
  await page.locator('#opens').fill('3');
  await page.locator('#secure-button').click();
  await page.locator('#delivery').waitFor({state: 'visible', timeout: 60000});
  const [lesson] = await Promise.all([page.waitForEvent('download'), page.locator('#download-file').click()]);
  const [password] = await Promise.all([page.waitForEvent('download'), page.locator('#download-pass').click()]);
  const lessonPath = path.join(out, 'two-page-lesson.scholar');
  const passPath = path.join(out, 'two-page-lesson.scholarpass');
  await lesson.saveAs(lessonPath);
  await password.saveAs(passPath);
  assert.equal(fs.readFileSync(lessonPath).subarray(0, 8).toString('latin1'), 'SCHOLR1\n', 'lesson file header');
  assert.equal(JSON.parse(fs.readFileSync(passPath, 'utf8')).format, 'SecureScholarPasswordFile', 'password file format');
  checks.push('maker makes a .scholar lesson file and a .scholarpass password file');
  fs.writeFileSync(path.join(out, 'pin.txt'), await page.locator('#pin').inputValue());
  console.log(JSON.stringify({status: 'passed', checks, files: out}, null, 2));
} finally {
  await browser.close();
}
