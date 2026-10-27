/**
 * Browser execution oracle for the real Alpha header shortcode renderer.
 * Runs against disposable WP-CLI output, never against production.
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

(async () => {
  const html = execFileSync('php', [
    '.wp-test/wp-cli.phar', '--allow-root', '--path=.wp-test/wp',
    'eval-file', 'tests/e2e/header-shortcode-browser.php'
  ], { encoding: 'utf8', timeout: 30000 });
  if (!html.includes('data-n9lh-popup="video"')) {
    throw new Error('Popup fixture was not rendered');
  }
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    await page.setContent(html, { waitUntil: 'load' });
    await page.waitForTimeout(350);
    const observed = await page.evaluate(() => ({
      script: Boolean(window.__n9eh_script_probe),
      event: Boolean(window.__n9eh_event_probe)
    }));
    console.log('BROWSER_SCRIPT_EXECUTED=' + (observed.script ? 'YES' : 'NO'));
    console.log('BROWSER_EVENT_EXECUTED=' + (observed.event ? 'YES' : 'NO'));
    if (observed.script || observed.event) {
      throw new Error('Unsafe shortcode JavaScript executed in browser');
    }
    console.log('BROWSER_SHORTCODE_NO_EXECUTION_PASS');
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error.stack || String(error));
  process.exitCode = 1;
});
