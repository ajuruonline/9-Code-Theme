/**
 * Genuine disposable WordPress + Contact Form 7 browser/HTTP acceptance.
 * Real Chromium submits the plugin's own form JavaScript over localhost.
 * This is NOT an Alpha popup integration or production certification.
 */
import { chromium } from 'playwright';

const base = process.env.CF7_BROWSER_BASE_URL || '';
const formId = Number(process.env.CF7_BROWSER_FORM_ID);
const pageId = Number(process.env.CF7_BROWSER_PAGE_ID);
const url = new URL(base || 'http://invalid.example/');
if (!['localhost', '127.0.0.1'].includes(url.hostname) ||
    url.protocol !== 'http:' ||
    !Number.isSafeInteger(formId) || formId <= 0 ||
    !Number.isSafeInteger(pageId) || pageId <= 0) {
  throw new Error('CF7_BROWSER_REFUSED: local URL and disposable form/page IDs required');
}
const browser = await chromium.launch({ headless: true });
try {
  const page = await browser.newPage({ viewport: { width: 320, height: 720 } });
  const target = new URL('/?page_id=' + pageId, base);
  const response = await page.goto(target.toString(), { waitUntil: 'domcontentloaded' });
  if (!response || response.status() !== 200) {
    throw new Error('Disposable CF7 page did not return HTTP 200');
  }
  const form = page.locator('form.wpcf7-form').first();
  await form.waitFor({ state: 'visible', timeout: 15000 });
  const actualFormId = await form.locator('input[name="_wpcf7"]').inputValue();
  if (actualFormId !== String(formId)) {
    throw new Error('Rendered form ID does not match the disposable form');
  }
  // Confirm real plugin JavaScript initializes rather than synthesizing submit.
  await page.waitForFunction(() => typeof window.wpcf7 !== 'undefined', { timeout: 15000 });
  await form.locator('[name="your-name"]').fill('Disposable Browser Tester');
  await form.locator('[name="your-message"]').fill('Local test only; do not deliver mail.');
  const feedback = r => r.request().method() === 'POST' &&
    r.url().includes('/contact-form-7/v1/contact-forms/') &&
    r.url().includes('/feedback');
  // Bypass browser-native email validity only to exercise CF7's own server validator.
  await form.evaluate(el => { el.noValidate = true; });
  await form.locator('[name="your-email"]').fill('invalid-email');
  const [badResponse] = await Promise.all([
    page.waitForResponse(feedback, { timeout: 25000 }),
    form.locator('[type="submit"]').click()
  ]);
  const badBody = await badResponse.json();
  const badFields = Array.isArray(badBody.invalid_fields) ? badBody.invalid_fields : [];
  if (badResponse.status() !== 200 || badBody.status !== 'validation_failed' ||
      !badFields.some(f => f.field === 'your-email')) {
    throw new Error('Real HTTP invalid-email validation did not reject the address: ' +
      JSON.stringify({ status: badResponse.status(), body: badBody }).slice(0, 400));
  }
  const badContentType = badResponse.request().headers()['content-type'] || '';
  if (!badContentType.includes('multipart/form-data')) {
    throw new Error('CF7_BROWSER_INVALID_HTTP_NOT_MULTIPART: ' + badContentType);
  }
  console.log('CF7_BROWSER_INVALID_EMAIL_HTTP_REJECTED=YES');
  // A network response arrives before CF7 finishes its DOM/status transition.
  // A second click while the form is still submitting can be ignored entirely.
  await page.waitForFunction(() => {
    const active = document.querySelector('form.wpcf7-form');
    return active && active.dataset.status === 'invalid';
  }, null, { timeout: 12000 });
  await form.locator('[type="submit"]').waitFor({ state: 'visible' });
  console.log('CF7_BROWSER_INVALID_UI_SETTLED=YES');
  // Use a fresh page for the positive case: CF7 retains per-form validation
  // state after a rejected submission, which can block a rapid second submit.
  await page.goto(target.toString(), { waitUntil: 'domcontentloaded' });
  await form.waitFor({ state: 'visible', timeout: 15000 });
  await page.waitForFunction(() => typeof window.wpcf7 !== 'undefined');
  await form.locator('[name="your-name"]').fill('Disposable Browser Tester');
  await form.locator('[name="your-message"]').fill('Local test only; do not deliver mail.');
  await form.locator('[name="your-email"]').fill('disposable@example.test');
  await form.evaluate(el => { el.noValidate = true; });
  console.log('CF7_BROWSER_VALID_FRESH_PAGE=YES');
  const [goodResponse] = await Promise.all([
    page.waitForResponse(feedback, { timeout: 25000 }),
    form.locator('[type="submit"]').click()
  ]);
  const goodBody = await goodResponse.json();
  const goodContentType = goodResponse.request().headers()['content-type'] || '';
  if (!goodContentType.includes('multipart/form-data')) {
    throw new Error('CF7_BROWSER_VALID_HTTP_NOT_MULTIPART: ' + goodContentType);
  }
  if (goodResponse.status() !== 200 || goodBody.status !== 'mail_sent') {
    throw new Error('Real HTTP valid submission failed: ' +
      JSON.stringify({ status: goodResponse.status(), body: goodBody }).slice(0, 400));
  }
  console.log('CF7_BROWSER_VALID_HTTP_ACCEPTED=YES');
  console.log('CF7_BROWSER_HTTP_AJAX_PASS: real Chromium/localhost multipart; outbound mail guarded.');
} finally {
  await browser.close();
}
