import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import {createRequire} from 'node:module';
const {chromium} = createRequire(import.meta.url)('playwright');

const base = process.env.SECURE_SCHOLAR_URL || 'http://127.0.0.1:8765/';
const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'secure-scholar-ui-'));
const browser = await chromium.launch({headless: true, executablePath: process.env.CHROMIUM_PATH || undefined, args: ['--no-sandbox', '--disable-dev-shm-usage']});
try {
  const page = await browser.newPage({viewport: {width: 390, height: 844}, acceptDownloads: true});
  await page.goto(base, {waitUntil: 'networkidle'});
  assert.equal(await page.locator('#start-secure strong').textContent(), 'Protect a file');
  assert.equal(await page.locator('#start-open strong').textContent(), 'Open a protected file');
  await page.locator('#start-secure').click();
  await page.locator('#source').setInputFiles(new URL('./sample_lesson.html', import.meta.url).pathname);
  await page.locator('#duration').selectOption('7');
  await page.locator('#opens').fill('3');
  await page.locator('#minutes').fill('60');
  await page.locator('#secure-button').click();
  await page.locator('#delivery').waitFor({state: 'visible', timeout: 30000});
  const secureDownload = await Promise.all([page.waitForEvent('download'), page.locator('#download-file').click()]).then(result => result[0]);
  const passDownload = await Promise.all([page.waitForEvent('download'), page.locator('#download-pass').click()]).then(result => result[0]);
  const securePath = path.join(temporary, 'lesson.scholar');
  const passPath = path.join(temporary, 'lesson.scholarpass');
  await secureDownload.saveAs(securePath);
  await passDownload.saveAs(passPath);
  await page.locator('#home-button').click();
  await page.locator('#start-open').click();
  await page.locator('#secure-file').setInputFiles(securePath);
  await page.locator('#pass-file').setInputFiles(passPath);
  await page.locator('#open-button').click();
  await page.locator('#viewer').waitFor({state: 'visible', timeout: 30000});
  assert.match(await page.locator('#view-title').textContent(), /sample lesson/i);
  assert.match(await page.locator('#credits').textContent(), /2 opening credits remaining/i);
  assert.equal(await page.locator('#web-wrap').isVisible(), true);
  await page.screenshot({path: path.join(temporary, 'mobile-secure-scholar.png'), fullPage: true});
  console.log(JSON.stringify({status: 'passed', screenshot: path.join(temporary, 'mobile-secure-scholar.png')}));
} finally {
  await browser.close();
}
