// Post editor acceptance (the invariant that drove the 15.0.x white-screen fixes):
// Post Content and metadata must always be reachable and editable, Save/Update visible,
// and no Nine Code layer may cover the viewport. Gutenberg + Classic, phone + desktop.
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { BASE, DIR, launch, login, reporter } from './lib.mjs';
const t = reporter('editor');

const wp = (...args) => execFileSync('php', [path.join(DIR, 'wp-cli.phar'), '--allow-root', `--path=${path.join(DIR, 'wp')}`, ...args], { encoding: 'utf8' }).trim();
const postId = wp('post', 'list', '--name=sample-post-1', '--format=ids').split(' ')[0];

const resetFixture = () => {
  wp('post', 'update', postId, '--post_title=Sample post 1', '--post_content=<!-- wp:paragraph --><p>Body of sample post 1 with <strong>bold</strong> text.</p><!-- /wp:paragraph -->');
  wp('post', 'meta', 'update', postId, 'custom_field', 'hello meta');
};

// Any visible fixed-position element from Nine Code that covers (nearly) the whole viewport.
const coverCheck = () => {
  const out = [];
  for (const e of document.querySelectorAll('body *')) {
    const s = getComputedStyle(e);
    if (s.position !== 'fixed' || s.display === 'none' || s.visibility === 'hidden' || +s.opacity === 0) continue;
    if (!/ncu|n9|nine|ultron|9code/i.test(`${e.id} ${e.className}`)) continue;
    const r = e.getBoundingClientRect();
    if (r.width >= innerWidth * 0.9 && r.height >= innerHeight * 0.9) out.push(`${e.id}.${String(e.className).slice(0, 50)}`);
  }
  return out;
};

for (const vp of [{ width: 390, height: 844 }, { width: 1280, height: 900 }]) {
  resetFixture();
  const { browser, page } = await launch(vp);
  const errs = []; page.on('pageerror', (e) => errs.push(String(e)));
  await login(page);

  // ---------- Gutenberg ----------
  let tag = `${vp.width}px Gutenberg`;
  await page.goto(`${BASE}/wp-admin/post.php?post=${postId}&action=edit`);
  await page.waitForSelector('iframe[name="editor-canvas"]', { timeout: 40000 });
  await page.waitForTimeout(2000);
  await page.keyboard.press('Escape');
  t.ok((await page.evaluate(coverCheck)).length === 0, `${tag}: no Nine Code viewport-sized layer`);
  t.ok(await page.locator('.editor-post-publish-button, .editor-post-publish-panel__toggle').first().isVisible(), `${tag}: Update/Publish button visible`);
  const topbar = await page.evaluate(() => {
    const header = document.querySelector('.editor-header,.edit-post-header,.interface-interface-skeleton__header');
    const toggle = document.querySelector('.ncu-editor-tools-toggle');
    const visible = (node) => {
      const s = getComputedStyle(node);
      return s.display !== 'none' && s.visibility !== 'hidden' && +s.opacity !== 0 && node.getClientRects().length > 0;
    };
    const allowed = (node) => node.matches('.ncu-editor-tools-toggle,.editor-post-save-draft,.editor-post-publish-button,.editor-post-publish-panel__toggle') ||
      !!node.closest('.ncu-editor-tools-toggle,.editor-post-save-draft,.editor-post-publish-button,.editor-post-publish-panel__toggle');
    const label = (node) => String(node.getAttribute('aria-label') || node.getAttribute('title') || node.textContent || '').replace(/\s+/g, ' ').trim();
    const extra = header ? [...header.querySelectorAll('button,a,[role="button"]')].filter(visible).filter((node) => !allowed(node)).map(label).filter(Boolean) : ['header missing'];
    return { hamburgerVisible: !!(toggle && visible(toggle)), extra };
  });
  t.ok(topbar.hamburgerVisible, `${tag}: one hamburger is visible for editor actions`);
  t.ok(topbar.extra.length === 0, `${tag}: only Save/Publish and the hamburger remain in the top bar${topbar.extra.length ? ' :: ' + topbar.extra.join(' | ') : ''}`);
  const frame = page.frameLocator('iframe[name="editor-canvas"]');
  const title = frame.locator('h1.wp-block-post-title, .editor-post-title__input').first();
  await title.click(); await page.keyboard.press('End'); await page.keyboard.type(` GB${vp.width}`);
  t.ok((await title.innerText()).trim().endsWith(`GB${vp.width}`), `${tag}: title editable`);
  const para = frame.locator('p.wp-block-paragraph, [data-type="core/paragraph"]').first();
  await para.click(); await page.keyboard.press('End'); await page.keyboard.type(` BODY${vp.width}`);
  t.ok((await para.innerText()).trim().endsWith(`BODY${vp.width}`), `${tag}: post content editable`);
  t.ok(await page.locator('.edit-post-meta-boxes-area').count() > 0, `${tag}: meta boxes area present`);
  await page.locator('.editor-post-publish-button').first().click();
  await page.waitForSelector('.editor-post-publish-button.is-busy', { state: 'detached', timeout: 15000 }).catch(() => {});
  await page.waitForTimeout(1500);
  t.ok(wp('post', 'get', postId, '--field=post_title').endsWith(`GB${vp.width}`), `${tag}: Update saves the title to the database`);
  t.ok(wp('post', 'get', postId, '--field=post_content').includes(`BODY${vp.width}`), `${tag}: Update saves the content to the database`);

  // ---------- Classic editor (+ Nine Code Data recovery box) ----------
  tag = `${vp.width}px Classic`;
  await page.goto(`${BASE}/wp-admin/post.php?post=${postId}&action=edit&nc_classic=1`);
  await page.waitForSelector('#title', { timeout: 20000 });
  t.ok((await page.evaluate(coverCheck)).length === 0, `${tag}: no Nine Code viewport-sized layer`);
  t.ok(await page.locator('#publish').isVisible(), `${tag}: Update button visible`);
  await page.fill('#title', `Classic ${vp.width}`);
  await page.click('#content-html').catch(() => {});
  await page.fill('#content', `<p>classic body ${vp.width}</p>`);
  t.ok((await page.inputValue('#content')).includes(`classic body ${vp.width}`), `${tag}: content textarea editable`);
  const recovery = page.locator('#npm9-native-recovery-content');
  t.ok(await recovery.count() > 0, `${tag}: "9 Data - Post Content & Metadata" recovery box present`);
  if (await recovery.count()) {
    await recovery.scrollIntoViewIfNeeded();
    t.ok(await recovery.isVisible() && await recovery.isEditable(), `${tag}: recovery content field visible and editable`);
    const metaRow = page.locator('.npm9-native-recovery-meta-row[data-meta-key="custom_field"] textarea');
    t.ok(await metaRow.count() > 0, `${tag}: recovery box lists post metadata (custom_field)`);
    if (await metaRow.count()) {
      await metaRow.fill(`meta-${vp.width}`);
      await page.click('#npm9-native-recovery-save');
      await page.waitForFunction(() => /saved/i.test(document.getElementById('npm9-native-recovery-status')?.textContent || ''), null, { timeout: 15000 }).catch(() => {});
      t.ok(wp('post', 'meta', 'get', postId, 'custom_field') === `meta-${vp.width}`, `${tag}: recovery box saves metadata to the database`);
    }
  }
  await page.click('#publish');
  await page.waitForLoadState('load');
  t.ok(wp('post', 'get', postId, '--field=post_title') === `Classic ${vp.width}`, `${tag}: Update saves the title`);

  t.ok(errs.length === 0, `${vp.width}px: no uncaught JS errors${errs.length ? ' :: ' + errs.slice(0, 2).join(' | ') : ''}`);
  await browser.close();
}
// leave the fixture as we found it
resetFixture();
t.done();
