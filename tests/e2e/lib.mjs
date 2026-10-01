// Shared helpers for the Nine Code end-to-end tests.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const here = path.dirname(fileURLToPath(import.meta.url));
export const ROOT = path.resolve(here, '../..');
export const DIR = process.env.WP_TEST_DIR || path.join(ROOT, '.wp-test');
export const PORT = process.env.WP_TEST_PORT || '8890';
export const BASE = process.env.BASE || `http://localhost:${PORT}`;
export const WPLOG = path.join(DIR, 'wp', 'debug.log');

// Noise that is not our code: wp.org is unreachable in the test sandbox.
const IGNORE_LOG = /wp_update_|wp_version_check|translations_api|update-core\.php/;

export function logSize() { try { return fs.statSync(WPLOG).size; } catch { return 0; } }
export function newLog(from) {
  try {
    return fs.readFileSync(WPLOG).subarray(from).toString().split('\n')
      .filter((l) => l && !IGNORE_LOG.test(l)).map((l) => l.replace(/^\[[^\]]+\] /, ''));
  } catch { return []; }
}

export function reporter(name) {
  let failed = 0; let passed = 0;
  return {
    ok(cond, msg) { if (cond) { passed++; console.log(`PASS ${msg}`); } else { failed++; console.log(`FAIL ${msg}`); } },
    done() {
      console.log(`\n${name}: ${passed} passed, ${failed} failed`);
      process.exit(failed ? 1 : 0);
    },
  };
}

export async function launch(viewport = { width: 1280, height: 900 }) {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  return { browser, context, page };
}

export async function login(page) {
  await page.goto(`${BASE}/wp-login.php`);
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'admin');
  await page.click('#wp-submit');
  await page.waitForLoadState('load');
}

/** Visit a URL and collect everything that went wrong while loading it. */
export async function visit(page, url, { expectStatus = 200 } = {}) {
  const problems = [];
  const onConsole = (m) => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) problems.push('console: ' + m.text().slice(0, 200)); };
  const onPageError = (e) => problems.push('pageerror: ' + String(e).slice(0, 200));
  const onResponse = (r) => { if (r.status() >= 400 && r.url().startsWith(BASE) && r.request().resourceType() !== 'document') problems.push(`http ${r.status()}: ${r.url().replace(BASE, '')}`); };
  page.on('console', onConsole); page.on('pageerror', onPageError); page.on('response', onResponse);
  const before = logSize();
  let status = 0;
  try {
    const r = await page.goto(url.startsWith('http') ? url : BASE + url, { waitUntil: 'load', timeout: 45000 });
    status = r ? r.status() : 0;
    await page.waitForTimeout(300);
  } catch (e) { problems.push('navigation: ' + String(e).slice(0, 150)); }
  page.off('console', onConsole); page.off('pageerror', onPageError); page.off('response', onResponse);
  const html = await page.content().catch(() => '');
  if (/Fatal error|critical error on this website|Parse error|<b>(Warning|Notice|Deprecated)<\/b>:/i.test(html)) problems.push('PHP error text in page');
  for (const l of newLog(before)) problems.push('php: ' + l.slice(0, 260));
  if (status !== expectStatus) problems.push(`status ${status}, expected ${expectStatus}`);
  return { status, problems, html };
}
