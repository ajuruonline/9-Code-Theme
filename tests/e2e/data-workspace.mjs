// Data Workspace end-to-end acceptance (definition of done), phone and desktop:
// discover apps → open a data type → edit an authorized field → protected fields locked →
// export for AI → stage an edited import → preview → selectively apply → publish only when
// explicitly allowed → undo from History. Also checks there are no JS errors or horizontal scroll.
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { BASE, DIR, launch, login, reporter } from './lib.mjs';
const t = reporter('data workspace');
const wp = (...args) => execFileSync('php', [path.join(DIR, 'wp-cli.phar'), '--allow-root', `--path=${path.join(DIR, 'wp')}`, ...args], { encoding: 'utf8' }).trim();
const URL = `${BASE}/wp-admin/admin.php?page=ninecode-data-workspace`;

for (const vp of [{ width: 390, height: 844 }, { width: 1280, height: 900 }]) {
  const tag = `${vp.width}px`;
  const id = wp('post', 'create', '--post_type=post', `--post_title=E2E ${tag}`, '--post_status=draft', '--porcelain');
  wp('post', 'meta', 'update', id, '_edit_lock', '1:1');
  const { browser, context, page } = await launch(vp);
  const errs = [];
  page.on('pageerror', (e) => errs.push(String(e)));
  page.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource|ERR_CERT/.test(m.text())) errs.push(m.text()); });
  await login(page);

  // Discover.
  await page.goto(URL);
  await page.waitForSelector('.ncd-entity-card');
  const providers = await page.$$eval('.ncd-provider h2', (h) => h.map((x) => x.textContent));
  t.ok(providers.length >= 2, `${tag}: providers discovered (${providers.join(', ')})`);
  await page.fill('.ncd-search', 'posts');
  t.ok(await page.locator('a.ncd-entity-card[href="#/wordpress/type-post"]').isVisible(), `${tag}: data type search`);
  t.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${tag}: no horizontal scroll on discovery`);

  // List + search.
  await page.click('a.ncd-entity-card[href="#/wordpress/type-post"]');
  await page.waitForSelector('.ncd-table');
  await page.fill('.ncd-tools .ncd-search', `E2E ${tag}`);
  await page.waitForFunction((n) => [...document.querySelectorAll('.ncd-col-title a')].some((a) => a.textContent === n), `E2E ${tag}`);
  t.ok(true, `${tag}: records search`);

  // Edit an authorized field; protected field is locked.
  await page.click(`.ncd-col-title a:text-is("E2E ${tag}")`);
  await page.waitForSelector('.ncd-form');
  const title = page.locator('[data-field="post_title"] input');
  await title.fill(`E2E ${tag} edited`);
  t.ok(await page.locator('.ncd-dirty').innerText() !== 'No changes', `${tag}: unsaved change tracked`);
  const locked = page.locator('[data-field="meta:_edit_lock"]');
  t.ok(await locked.count() === 1 && await locked.evaluate((el) => el.classList.contains('is-locked') && !el.querySelector('input,textarea,select')), `${tag}: protected field shown read-only`);
  // Status change without the explicit toggle is refused.
  await page.locator('details:has([data-field="post_status"])').evaluate((d) => { d.open = true; });
  await page.selectOption('[data-field="post_status"] select', 'publish');
  await page.click('.ncd-savebar .button-primary');
  await page.waitForSelector('.ncd-notice--error');
  t.ok(wp('post', 'get', id, '--field=post_status') === 'draft', `${tag}: publish refused without explicit permission`);
  t.ok(wp('post', 'get', id, '--field=post_title') === `E2E ${tag}`, `${tag}: nothing saved when one field is refused`);
  // With the toggle: saves and publishes.
  await page.check('.ncd-savebar .ncd-toggle input');
  await page.click('.ncd-savebar .button-primary');
  await page.waitForSelector('.ncd-toast');
  await page.waitForTimeout(500);
  t.ok(wp('post', 'get', id, '--field=post_status') === 'publish' && wp('post', 'get', id, '--field=post_title') === `E2E ${tag} edited`, `${tag}: saved and published with explicit permission`);

  // Export for AI (download).
  await page.goto(`${URL}#/wordpress/type-post`);
  await page.waitForSelector('.ncd-table');
  await page.fill('.ncd-tools .ncd-search', `E2E ${tag}`);
  await page.waitForFunction(() => document.querySelectorAll('.ncd-table tbody tr').length >= 1);
  await page.locator('tbody input[type=checkbox]').first().check();
  await page.click('.ncd-bulkbar button:has-text("Export selected")');
  const [dl] = await Promise.all([page.waitForEvent('download'), page.click('.ncd-modal .button-primary')]);
  const file = await dl.path();
  const pkg = JSON.parse(fs.readFileSync(file, 'utf8'));
  t.ok(pkg.format === 'ninecode-data-package' && pkg.records.length === 1 && pkg.instructions.length > 3, `${tag}: AI package downloaded with schema + instructions`);
  t.ok(!JSON.stringify(pkg.records[0].fields).includes('_edit_lock'), `${tag}: protected keys not offered for editing`);

  // Import: AI edits title + excerpt and unpublishes; apply only the excerpt.
  pkg.records[0].fields.post_title = `AI ${tag}`;
  pkg.records[0].fields.post_excerpt = `AI excerpt ${tag}`;
  pkg.records[0].fields.post_status = 'draft';
  const edited = path.join(path.dirname(file), `edited-${vp.width}.json`);
  fs.writeFileSync(edited, JSON.stringify(pkg));
  await page.goto(`${URL}#/wordpress/type-post/import`);
  await page.setInputFiles('.ncd-card input[type=file]', edited);
  await page.click('.ncd-card .button-primary');
  await page.waitForSelector('.ncd-plan');
  const stats = await page.locator('.ncd-stats').innerText();
  t.ok(/1\s*status changes/.test(stats.replace(/\n/g, ' ')), `${tag}: preview shows the unpublish as a status change`);
  t.ok(await page.locator('.ncd-notice--warning:has-text("Status changes are listed but will NOT be applied")').count() === 1, `${tag}: preview warns status will not apply`);
  t.ok(wp('post', 'get', id, '--field=post_title') === `E2E ${tag} edited`, `${tag}: preview wrote nothing`);
  await page.locator('.ncd-diff tr:has-text("Title") input[type=checkbox]').uncheck();
  page.once('dialog', (d) => d.accept());
  await page.click('.ncd-savebar .button-primary:has-text("Apply selected")');
  await page.click('.ncd-modal .button-primary');
  await page.waitForSelector('h2:has-text("Import finished")');
  t.ok(wp('post', 'get', id, '--field=post_excerpt') === `AI excerpt ${tag}` && wp('post', 'get', id, '--field=post_title') === `E2E ${tag} edited`, `${tag}: only the ticked field applied`);
  t.ok(wp('post', 'get', id, '--field=post_status') === 'publish', `${tag}: import did not unpublish`);

  // Undo from History.
  await page.goto(`${URL}#/history`);
  await page.waitForSelector('.ncd-table');
  await page.locator('tbody tr').first().locator('button:has-text("Undo")').click();
  await page.click('.ncd-modal .button-primary');
  await page.waitForSelector('.ncd-toast');
  await page.waitForTimeout(500);
  t.ok(wp('post', 'get', id, '--field=post_excerpt') === '', `${tag}: undo restored the excerpt`);

  // Mobile layout: cards, sticky save bar reachable, tap targets.
  if (vp.width < 783) {
    await page.goto(`${URL}#/wordpress/type-post/${id}`);
    await page.waitForSelector('.ncd-form');
    const cardLayout = await page.evaluate(() => getComputedStyle(document.querySelector('.ncd-field')).display);
    t.ok(cardLayout !== 'grid', `${tag}: single-column field layout on phones`);
    const save = await page.locator('.ncd-savebar .button-primary').boundingBox();
    t.ok(save && save.y + save.height <= vp.height + 1 && save.height >= 36, `${tag}: save bar visible with a touch-size button`);
  }
  t.ok(errs.length === 0, `${tag}: no JS errors${errs.length ? ' :: ' + errs.join(' | ') : ''}`);
  await context.close();
  await browser.close();
  wp('post', 'delete', id, '--force');
}
t.done();
