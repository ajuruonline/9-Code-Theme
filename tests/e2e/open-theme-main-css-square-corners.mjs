// OS-VIS-OP-001: computed-style regression for theme-owned Open Theme pages.
// Isolated fixture using the actual packaged CSS; not a WordPress staging certification.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { chromium } from 'playwright';

const active = readFileSync('theme/nine-code/assets/css/main.css', 'utf8');
const mirror = readFileSync('theme-upload/open-theme-green-edition/assets/css/main.css', 'utf8');
assert.equal(mirror, active, 'Installable theme CSS must match the theme-upload mirror');

const selectors = ['.ncu-icon-button', '.ncu-quick-grid a span', '.ncu-card', '.ncu-main>.widget'];
const browser = await chromium.launch({ headless: true });
try {
  for (const width of [320, 390, 1280]) {
    const page = await browser.newPage({ viewport: { width, height: 800 } });
    try {
      await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${active}</style></head><body>
        <div class="ncu-container">
          <button class="ncu-icon-button" type="button" aria-label="Menu">M</button>
          <div class="ncu-quick-grid"><a href="#"><span>Q</span></a></div>
          <div class="ncu-card">Card</div>
          <main class="ncu-main"><div class="widget">Widget</div></main>
          <div class="ncu-featured"><img alt="decorative" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20'/%3E"></div>
        </div></body></html>`);
      for (const selector of selectors) {
        const result = await page.locator(selector).first().evaluate(el => {
          const css = getComputedStyle(el);
          return { borderStyle: css.borderTopStyle, borderWidth: css.borderTopWidth,
            radius: css.borderTopLeftRadius };
        });
        assert.notEqual(result.borderStyle, 'none', `${width}px ${selector} should retain its visible stroke`);
        assert.notEqual(result.borderWidth, '0px', `${width}px ${selector} should retain its visible stroke width`);
        assert.equal(result.radius, '0px', `${width}px ${selector} must have square corners`);
      }
      const unborderedRadius = await page.locator('.ncu-featured img').evaluate(el => getComputedStyle(el).borderTopLeftRadius);
      assert.notEqual(unborderedRadius, '0px', 'Borderless imagery may remain rounded');
      await page.keyboard.press('Tab');
      const focused = await page.locator('.ncu-icon-button').evaluate(el => {
        const s = getComputedStyle(el);
        return { active: document.activeElement === el, outlineStyle: s.outlineStyle, outlineWidth: s.outlineWidth };
      });
      assert.equal(focused.active, true, `${width}px keyboard must focus the icon button`);
      assert.notEqual(focused.outlineStyle, 'none', `${width}px focus outline must remain visible`);
      assert.notEqual(focused.outlineWidth, '0px', `${width}px focus outline must have nonzero width`);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
      assert.equal(overflow, false, `${width}px isolated fixture must not horizontally overflow`);
      console.log(`PASS Open Theme main.css computed corners, focus, and fit at ${width}px`);
    } finally {
      await page.close();
    }
  }
} finally {
  await browser.close();
}
