// Isolated browser-computed CSS oracle; no WordPress installation or user data.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { chromium } from 'playwright';

const css = readFileSync(
  new URL('../../theme-upload/open-theme-green-edition/assets/css/open-scholar-topbar.css', import.meta.url),
  'utf8',
);
const html = `<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">
<style>${css}</style></head><body><div class="n9be-side-popup">
<div class="n9be-side-panel"><header><h2>Tools</h2><button type="button" id="close">Close</button></header>
<div class="n9be-side-content">
<div class="n9be-side-actions"><a href="#action">Action</a><button type="button">More</button></div>
<div class="n9be-tool-list"><a href="#tool">Tool</a></div>
<details class="n9be-toolbar-extra"><summary>Extra</summary></details>
<form class="n9be-search-form"><label for="q">Search</label><div class="n9be-search-fields">
<input id="q" type="search"><button type="button" id="borderless">Go</button></div>
<select aria-label="Category"><option>All</option></select></form>
<button type="button" class="n9be-clear-cache-button">Clear cache</button>
</div></div></div></body></html>`;

const bordered = [
  '.n9be-side-panel',
  '.n9be-side-panel header button',
  '.n9be-side-actions>a',
  '.n9be-tool-list>a',
  '.n9be-toolbar-extra',
  '.n9be-side-panel input[type=search]',
  '.n9be-side-panel select',
  '.n9be-clear-cache-button',
];
const browser = await chromium.launch({ headless: true });
try {
  for (const width of [320, 390, 1280]) {
    const page = await browser.newPage({ viewport: { width, height: 800 } });
    try {
      await page.setContent(html);
      for (const selector of bordered) {
        const computed = await page.locator(selector).first().evaluate((element) => {
          const s = getComputedStyle(element);
          return { radius: s.borderTopLeftRadius, borderStyle: s.borderTopStyle, borderWidth: s.borderTopWidth };
        });
        assert.equal(computed.radius, '0px', `${width}px ${selector} has rounded corners`);
        assert.notEqual(computed.borderStyle, 'none', `${width}px ${selector} lost its border`);
        assert.ok(parseFloat(computed.borderWidth) > 0, `${width}px ${selector} lost visible border width`);
      }
      assert.equal(await page.locator('#borderless').evaluate((element) => getComputedStyle(element).borderTopLeftRadius), '8px');
      await page.keyboard.press('Tab');
      const focused = await page.evaluate(() => {
        const el = document.activeElement;
        const s = getComputedStyle(el);
        return { isFocusVisible: el.matches(':focus-visible'), radius: s.borderTopLeftRadius, outlineWidth: s.outlineWidth, outlineStyle: s.outlineStyle };
      });
      assert.equal(focused.isFocusVisible, true, `${width}px keyboard focus not visible`);
      assert.equal(focused.radius, '0px', `${width}px focused outline rounded`);
      assert.ok(parseFloat(focused.outlineWidth) >= 2, `${width}px focus outline lost`);
      assert.notEqual(focused.outlineStyle, 'none');
      const panelFits = await page.locator('.n9be-side-panel').evaluate((el) => {
        const r = el.getBoundingClientRect();
        return r.left >= -1 && r.right <= innerWidth + 1;
      });
      assert.ok(panelFits, `${width}px side panel exceeds viewport`);
      console.log(`PASS: computed Open Scholar side-popup CSS at ${width}px (8 bordered selectors, focus, borderless action, panel fit)`);
    } finally {
      await page.close();
    }
  }
} finally {
  await browser.close();
}
