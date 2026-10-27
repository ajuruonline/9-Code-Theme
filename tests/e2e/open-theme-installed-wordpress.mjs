// OS-VIS-OP-001: real WordPress activation acceptance, not a synthetic-only CSS fixture.
// Run only against the disposable .wp-test instance. No production writes.
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';
import { BASE, DIR } from './lib.mjs';
import path from 'node:path';

const wp = (args) => execFileSync('php', [
  path.join(DIR, 'wp-cli.phar'), '--allow-root',
  '--path=' + path.join(DIR, 'wp'), ...args,
], { encoding: 'utf8' }).trim();
assert.equal(wp(['option', 'get', 'stylesheet']), 'open-theme-green-edition',
  'RELEASE_HOLD: Open Theme must be the actual active WordPress theme, not 9 Code Alpha');

const browser = await chromium.launch({ headless: true });
try {
  for (const width of [320, 390, 1280]) {
    const page = await browser.newPage({ viewport: { width, height: 800 } });
    try {
      const response = await page.goto(BASE + '/', { waitUntil: 'load', timeout: 45000 });
      assert.equal(response?.status(), 200, width + 'px actual WordPress homepage HTTP 200');
      const href = await page.locator('link#ncu-theme-main-css').getAttribute('href');
      assert.match(href || '', /\/themes\/open-theme-green-edition\/assets\/css\/main\.css/,
        width + 'px real Open Theme presentation stylesheet must be enqueued');
      const card = page.locator('.ncu-card').first();
      assert.ok(await card.count(), width + 'px real WordPress-rendered post card must exist');
      const cardStyle = await card.evaluate(el => {
        const s = getComputedStyle(el);
        return { width: s.borderTopWidth, style: s.borderTopStyle, corners: [s.borderTopLeftRadius, s.borderTopRightRadius, s.borderBottomLeftRadius, s.borderBottomRightRadius] };
      });
      assert.notEqual(cardStyle.style, 'none', width + 'px card border must remain visible');
      assert.notEqual(cardStyle.width, '0px', width + 'px card border width must remain visible');
      assert.deepEqual(cardStyle.corners, ['0px', '0px', '0px', '0px'], width + 'px actual WordPress-rendered card must have four square corners');

      // The lean Open Theme deliberately delegates its public header to plugins;
      // its icon/quick-grid/widget CSS contracts are checked on elements injected
      // into the REAL WordPress document, with the REAL enqueued stylesheet.
      await page.evaluate(() => {
        const host = document.createElement('div');
        host.id = 'os-installed-css-contract-fixture';
        host.className = 'ncu-container';
        host.innerHTML = '<button class="ncu-icon-button" type="button" aria-label="QA icon">I</button>' +
          '<div class="ncu-quick-grid"><a href="#"><span>Q</span></a></div>' +
          '<main class="ncu-main"><div class="widget">W</div></main>';
        document.body.append(host);
      });
      for (const selector of ['.ncu-icon-button', '.ncu-quick-grid a span', '.ncu-main>.widget']) {
        const result = await page.locator('#os-installed-css-contract-fixture ' + selector)
          .evaluate(el => {
            const s = getComputedStyle(el);
            return { style: s.borderTopStyle, width: s.borderTopWidth,
              corners: [s.borderTopLeftRadius, s.borderTopRightRadius,
                s.borderBottomLeftRadius, s.borderBottomRightRadius] };
          });
        assert.notEqual(result.style, 'none', width + 'px ' + selector + ' stroke');
        assert.notEqual(result.width, '0px', width + 'px ' + selector + ' width');
        assert.deepEqual(result.corners, ['0px', '0px', '0px', '0px'],
          width + 'px ' + selector + ' four square corners');
      }
      await page.locator('#os-installed-css-contract-fixture .ncu-icon-button').focus();
      const focus = await page.locator('#os-installed-css-contract-fixture .ncu-icon-button')
        .evaluate(el => {
          const s = getComputedStyle(el);
          return { focused: document.activeElement === el, style: s.outlineStyle, width: s.outlineWidth };
        });
      assert.equal(focus.focused, true, width + 'px icon keyboard focus');
      assert.notEqual(focus.style, 'none', width + 'px visible focus outline');
      assert.notEqual(focus.width, '0px', width + 'px nonzero focus outline');
      await page.locator('#os-installed-css-contract-fixture').evaluate(el => el.remove());
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
      assert.ok(overflow <= 1, width + 'px real homepage no horizontal overflow (' + overflow + 'px)');
      console.log('PASS installed Open Theme actual WordPress + CSS contracts at ' + width + 'px');
    } finally { await page.close(); }
  }
} finally { await browser.close(); }
