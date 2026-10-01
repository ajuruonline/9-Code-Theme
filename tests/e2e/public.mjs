// Public site: templates render, header/nav/footer/title present, no errors, accessible basics.
import { BASE, launch, visit, reporter } from './lib.mjs';
const t = reporter('public');
const { browser, page } = await launch({ width: 390, height: 844 });

for (const [url, expectStatus] of [['/', 200], ['/sample-post-1/', 200], ['/sample-page/', 200], ['/category/news/', 200], ['/?s=sample', 200], ['/does-not-exist/', 404], ['/feed/', 200]]) {
  const r = await visit(page, url, { expectStatus });
  t.ok(r.problems.length === 0, `${url} loads cleanly (${expectStatus})${r.problems.length ? ' :: ' + r.problems.join(' | ') : ''}`);
}

await visit(page, '/sample-post-1/');
t.ok(await page.locator('header.ncu-site-header').count() === 1, 'single post has exactly one site header');
t.ok(await page.locator('footer.ncu-site-footer').count() === 1, 'single post has exactly one site footer');
t.ok(await page.locator('h1.ncu-entry-title').innerText() === 'Sample post 1', 'single post shows its title as the h1');
t.ok(await page.locator('h1').count() === 1, 'exactly one h1 on a single post');
t.ok(await page.locator('a.ncu-skip-link').count() === 1, 'skip link present');
t.ok(!!(await page.getAttribute('html', 'lang')), 'html has lang attribute');
t.ok(!!(await page.getAttribute('meta[name=viewport]', 'content')), 'viewport meta present');

// Mobile nav toggle works and is keyboard-closable.
const toggle = page.locator('.ncu-nav-toggle');
t.ok(await toggle.isVisible(), 'mobile menu toggle visible at 390px');
t.ok((await page.locator('#ncu-primary-nav').isVisible()) === false, 'menu closed by default');
await toggle.click();
t.ok(await page.locator('#ncu-primary-nav').isVisible(), 'menu opens');
t.ok((await toggle.getAttribute('aria-expanded')) === 'true', 'aria-expanded reflects open state');
await page.keyboard.press('Escape');
t.ok((await page.locator('#ncu-primary-nav').isVisible()) === false, 'Escape closes the menu');

// No horizontal overflow on phone.
const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
t.ok(overflow <= 1, `no horizontal overflow at 390px (${overflow}px)`);

await browser.close();
t.done();
