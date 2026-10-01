// Logged-in crawl: every wp-admin menu link plus key editor screens must load with zero PHP/JS errors.
import { BASE, launch, login, visit, reporter } from './lib.mjs';
const t = reporter('admin crawl');
const { browser, page } = await launch();
await login(page);

await page.goto(`${BASE}/wp-admin/`);
const links = await page.$$eval('#adminmenu a', (as) => [...new Set(as.map((a) => a.href))]);
const extra = ['/wp-admin/post.php?post=4&action=edit', '/wp-admin/post-new.php', '/wp-admin/post.php?post=2&action=edit',
  '/wp-admin/edit-tags.php?taxonomy=category', '/wp-admin/profile.php', '/wp-admin/themes.php', '/wp-admin/plugins.php',
  '/wp-admin/admin.php?page=nine-code-ultra', '/wp-admin/admin.php?page=nine-code-ultra-header-footer',
  '/wp-admin/admin.php?page=nine-post-manager', '/wp-admin/admin.php?page=nine-category-manager'].map((u) => BASE + u);

const seen = new Set();
for (const url of [...links, ...extra]) {
  if (!url.startsWith(BASE) || seen.has(url) || /action=logout|wp-login/.test(url)) continue;
  seen.add(url);
  const r = await visit(page, url);
  t.ok(r.problems.length === 0, `${url.replace(BASE, '')}${r.problems.length ? ' :: ' + r.problems.join(' | ') : ''}`);
}
await browser.close();
t.done();
