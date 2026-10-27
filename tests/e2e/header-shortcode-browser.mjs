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
    // Synthetic plugin-style delegated JS: no inline executable shortcode markup.
    await page.addScriptTag({ content: `
      document.addEventListener('submit', function (event) {
        if (!event.target.matches('form[data-n9eh-form="integration"]')) return;
        event.preventDefault();
        const data = new FormData(event.target);
        window.__n9eh_form_submitted = {
          email: data.get('email'), topic: data.get('topic'),
          message: data.get('message'), nonce: data.get('nonce')
        };
      });
    ` });
    const formEvidence = await page.evaluate(() => {
      const form = document.querySelector('[data-n9lh-popup="form"] form[data-n9eh-form="integration"]');
      if (!form) return { found: false };
      const email = form.querySelector('input[name="email"]');
      const topic = form.querySelector('select[name="topic"]');
      const message = form.querySelector('textarea[name="message"]');
      const nonce = form.querySelector('input[name="nonce"]');
      const preserved = Boolean(
        email && email.type === 'email' && email.required &&
        email.dataset.n9ehField === 'email' && topic &&
        topic.options.length === 2 && message && nonce &&
        nonce.value === 'fixture-nonce' && form.querySelector('button[type="submit"]') &&
        form.querySelector('label[for="n9eh-email"]') &&
        form.method.toLowerCase() === 'post'
      );
      if (preserved) {
        email.value = 'academic@example.org';
        topic.value = 'teaching';
        message.value = 'Collaborative research';
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
      }
      return {
        found: true, preserved,
        inlineBlocked: !window.__n9eh_form_script_probe &&
          !window.__n9eh_form_event_probe && !email?.hasAttribute('oninput'),
        submission: window.__n9eh_form_submitted || null
      };
    });
    const formSubmitted = formEvidence.submission?.email === 'academic@example.org' &&
      formEvidence.submission?.topic === 'teaching' &&
      formEvidence.submission?.message === 'Collaborative research' &&
      formEvidence.submission?.nonce === 'fixture-nonce';
    console.log('FORM_FIELDS_PRESERVED=' + (formEvidence.preserved ? 'YES' : 'NO'));
    console.log('FORM_INLINE_SCRIPT_BLOCKED=' + (formEvidence.inlineBlocked ? 'YES' : 'NO'));
    console.log('FORM_EXTERNAL_JS_SUBMIT_PASS=' + (formSubmitted ? 'YES' : 'NO'));
    if (!formEvidence.preserved || !formEvidence.inlineBlocked || !formSubmitted) {
      throw new Error('External-JS form contract regressed or inline script survived sanitization');
    }
    console.log('BROWSER_SHORTCODE_NO_EXECUTION_PASS');
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error.stack || String(error));
  process.exitCode = 1;
});
