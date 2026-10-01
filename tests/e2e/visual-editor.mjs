// Data Manager Post Editor: the Post Content tab is a real visual (block) editor.
// Insert an image (upload) and a form through the Add block popup, edit text, save, verify in the
// database and on the public page. Also checks code mode round trip and that opening a post is not "dirty".
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { BASE, DIR, ROOT, launch, login, reporter } from './lib.mjs';
const t = reporter('visual editor');
const wp = (...a) => execFileSync('php', [path.join(DIR, 'wp-cli.phar'), '--allow-root', `--path=${path.join(DIR, 'wp')}`, ...a], { encoding: 'utf8' }).trim();
const postId = wp('post', 'list', '--name=sample-post-1', '--format=ids').split(' ')[0];
// Open the Add block popup and make sure it stays open (the editor re-renders while media settles).
async function openInserter(page, query, optionName) {
  for (let attempt = 0; attempt < 4; attempt++) {
    if (!(await page.locator('.block-editor-inserter__menu').count())) await page.click('#npm9-ve-host .nc-ve__add');
    try {
      await page.waitForSelector('.block-editor-inserter__menu', { timeout: 3000 });
      await page.locator('.block-editor-inserter__search input').first().fill(query);
      await page.locator('[role=option]').filter({ hasText: new RegExp('^' + optionName + '$') }).first().click({ timeout: 4000 });
      return;
    } catch (e) { console.log('  inserter attempt', attempt, String(e).split('\n').slice(0,12).join(' / ').slice(0,900), '; options:', JSON.stringify(await page.evaluate(() => [...document.querySelectorAll('[role=option]')].map((o) => o.textContent))), 'menu:', await page.locator('.block-editor-inserter__menu').count()); await page.keyboard.press('Escape'); await page.waitForTimeout(500); }
  }
  await page.screenshot({ path: process.env.FAIL_SHOT || '/tmp/ve-fail.png' });
  throw new Error(`could not insert "${optionName}" through the Add block popup`);
}
const reset = () => wp('post', 'update', postId, '--post_title=Sample post 1', '--post_content=<!-- wp:paragraph --><p>Body of sample post 1 with <strong>bold</strong> text.</p><!-- /wp:paragraph -->');

// Start from a clean slate: earlier aborted runs may have left forms behind.
const stale = wp('post', 'list', '--post_type=nine10_form_def', '--format=ids');
if (stale) wp('post', 'delete', ...stale.split(' '), '--force');
const formId = wp('post', 'create', '--post_type=nine10_form_def', '--post_title=Newsletter', '--post_status=publish', '--porcelain');
wp('post', 'meta', 'update', formId, '_nine10_form_active', '1');
reset();

for (const vp of [{ width: 1280, height: 900 }, { width: 390, height: 844 }]) {
  const tag = `${vp.width}px`;
  const { browser, page } = await launch(vp);
  const errs = []; page.on('pageerror', (e) => errs.push(String(e)));
  await login(page);
  await page.goto(`${BASE}/wp-admin/admin.php?page=nine-post-manager&post_id=${postId}`);
  await page.waitForSelector('.npm9-workspace-tab', { timeout: 30000 });
  await page.click('.npm9-workspace-tab[data-pane="content"]');
  await page.waitForSelector('#npm9-ve-host.is-ready .block-editor-block-list__layout', { timeout: 20000 });

  t.ok(true, `${tag}: visual editor mounted in Post Content tab`);
  t.ok(await page.locator('.npm9-code-field').isHidden(), `${tag}: raw textarea hidden while in Visual mode`);
  const btnColors = await page.evaluate(() => [...document.querySelectorAll('#npm9-ve-host .nc-ve__modes button')].map((b) => { const s = getComputedStyle(b); return [s.color, s.backgroundColor]; }));
  t.ok(btnColors.every(([fg, bg]) => fg !== bg), `${tag}: Visual/Code buttons are legible (text differs from background)`);
  t.ok(await page.locator('#npm9-dirty-indicator').isHidden(), `${tag}: opening a post does not mark it unsaved`);
  const visibleText = await page.locator('#npm9-ve-host .nc-ve__canvas').innerText();
  t.ok(visibleText.includes('Body of sample post 1'), `${tag}: existing content shown WYSIWYG`);

  // Type into the paragraph.
  const para = page.locator('#npm9-ve-host [data-type="core/paragraph"]').first();
  await para.click(); await page.keyboard.press('End'); await page.keyboard.type(` EDITED${vp.width}`);
  t.ok((await page.inputValue('#npm9-content')).includes(`EDITED${vp.width}`), `${tag}: typing updates post content`);
  t.ok(await page.locator('#npm9-dirty-indicator').isVisible(), `${tag}: editing marks the post unsaved`);

  // Add block popup -> Image, then upload a file.
  await openInserter(page, 'Image', 'Image');
  const fileInput = page.locator('#npm9-ve-host input[type="file"]').first();
  await fileInput.setInputFiles(path.join(ROOT, 'tests/e2e/fixtures/red.png'));
  await page.waitForSelector('#npm9-ve-host figure.wp-block-image img', { timeout: 20000 });
  await page.waitForFunction(() => /<!-- wp:image \{"id":\d+/.test(document.getElementById('npm9-content').value), null, { timeout: 20000 });
  t.ok(true, `${tag}: image uploaded and inserted through the Add block popup`);

  // Add block popup -> Form.
  await openInserter(page, 'Form', 'Form');
  await page.waitForSelector('#npm9-ve-host .nc-form-block');
  await page.locator('#npm9-ve-host .nc-form-block select').selectOption(String(formId));
  await page.waitForFunction((id) => document.getElementById('npm9-content').value.includes(`"formId":${id}`), formId, { timeout: 10000 });
  t.ok(true, `${tag}: Form block inserted and bound to a form`);

  // Code mode round trip.
  await page.click('#npm9-ve-host .nc-ve__modes >> text=Code');
  t.ok(await page.locator('#npm9-content').isVisible(), `${tag}: Code mode reveals the raw markup`);
  const code = await page.inputValue('#npm9-content');
  t.ok(code.includes('<!-- wp:paragraph') && code.includes('<!-- wp:image') && code.includes('<!-- wp:nine-code-data/form'), `${tag}: content is valid block markup (paragraph + image + form)`);
  await page.fill('#npm9-content', code + '\n<!-- wp:paragraph --><p>FROMCODE</p><!-- /wp:paragraph -->');
  await page.click('#npm9-ve-host .nc-ve__modes >> text=Visual');
  await page.waitForTimeout(600);
  await page.waitForSelector('#npm9-ve-host .nc-ve__canvas');
  t.ok((await page.locator('#npm9-ve-host .nc-ve__canvas').innerText()).includes('FROMCODE'), `${tag}: code edits appear in Visual mode`);

  // Save with the Post Editor's own Update button.
  const saveStart = Date.now();
  await page.click('#npm9-save');
  let saved = '';
  for (let i = 0; i < 120 && !saved.includes('FROMCODE'); i++) { await page.waitForTimeout(500); saved = wp('post', 'get', postId, '--field=post_content'); }
  console.log('  save landed after', Date.now() - saveStart, 'ms');
  t.ok(await page.locator('#npm9-dirty-indicator').isHidden(), `${tag}: Unsaved badge clears after saving`);
  t.ok(saved.includes(`EDITED${vp.width}`) && saved.includes('wp:image') && saved.includes('FROMCODE') && saved.includes(`"formId":${formId}`), `${tag}: Update Post saves text, image, form and code edits to the database`);

  if (!(saved.includes(`EDITED${vp.width}`) && saved.includes('wp:image') && saved.includes('FROMCODE') && saved.includes(`"formId":${formId}`))) console.log('  saved content was:', saved.slice(0, 700));
  const front = await (await page.request.get(`${BASE}/sample-post-1/`)).text();
  t.ok(front.includes('wp-block-image') && front.includes('<img') && front.includes('FROMCODE'), `${tag}: public page shows the image and text`);
  t.ok(/nine10-front-form|<form/.test(front), `${tag}: public page renders the form`);
  t.ok(errs.length === 0, `${tag}: no uncaught JS errors${errs.length ? ' :: ' + errs.slice(0, 2).join(' | ') : ''}`);
  await browser.close();
  reset();
}
wp('post', 'delete', formId, '--force');
t.done();
