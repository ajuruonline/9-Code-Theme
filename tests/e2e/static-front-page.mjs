// Disposable WordPress acceptance: static front-page mode must retain one meaningful H1.
// This is deliberately separate from posts-mode public.mjs and restores options afterwards.
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { DIR, launch, visit, reporter } from './lib.mjs';

const t = reporter('static-front-page');
const wpPath = path.join(DIR, 'wp');
const cliPath = path.join(DIR, 'wp-cli.phar');
function wp(...args) {
  const result = spawnSync('php', [cliPath, '--allow-root', `--path=${wpPath}`, ...args], {
    encoding: 'utf8', timeout: 30000,
  });
  if (result.error || result.status !== 0) {
    throw new Error(`WP-CLI ${args.slice(0, 3).join(' ')} failed: ${result.error || result.stderr}`);
  }
  return result.stdout.trim();
}

const originalMode = wp('option', 'get', 'show_on_front');
const originalFront = wp('option', 'get', 'page_on_front');
let fixtureId = null;
try {
  fixtureId = wp('post', 'create', '--post_type=page', '--post_status=publish',
    '--post_title=Static Homepage QA', '--post_name=static-homepage-qa',
    '--post_content=<p>Static homepage fixture content.</p>', '--porcelain');
  if (!/^\d+$/.test(fixtureId)) throw new Error('Could not create a numeric fixture post ID');
  wp('option', 'update', 'show_on_front', 'page');
  wp('option', 'update', 'page_on_front', fixtureId);

  for (const width of [320, 390, 1280]) {
    const { browser, page } = await launch({ width, height: 844 });
    try {
      const result = await visit(page, '/');
      t.ok(result.problems.length === 0, `static front page loads cleanly at ${width}px: ${result.problems.join(' | ')}`);
      const headings = await page.locator('h1').allInnerTexts();
      t.ok(headings.length === 1 && headings[0].trim() === 'Static Homepage QA',
        `static front page has exactly one correct H1 at ${width}px: ${JSON.stringify(headings)}`);
      t.ok(await page.locator('main#primary').count() === 1, `main landmark exists at ${width}px`);
      t.ok((await page.locator('main#primary').innerText()).includes('Static homepage fixture content.'),
        `static page content is rendered at ${width}px`);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      t.ok(overflow <= 1, `no horizontal overflow at ${width}px (${overflow}px)`);
    } finally {
      await browser.close();
    }
  }
} finally {
  // Do not leave this fixture enabled for the subsequent admin/editor CI suites.
  wp('option', 'update', 'show_on_front', originalMode);
  wp('option', 'update', 'page_on_front', originalFront);
  if (fixtureId) wp('post', 'delete', fixtureId, '--force');
}
t.done();
