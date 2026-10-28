/**
 * Real Contact Form 7 inside the ACTUAL Alpha header popup on disposable WP.
 * Browser JavaScript and multipart HTTP; no production or external mail.
 */
import { chromium } from 'playwright';

const base = process.env.CF7_BROWSER_BASE_URL || '';
const formId = Number(process.env.CF7_BROWSER_FORM_ID);
const pageId = Number(process.env.CF7_BROWSER_PAGE_ID);
const target = new URL(base || 'http://invalid.example/');
if (target.protocol !== 'http:' ||
    !['localhost', '127.0.0.1'].includes(target.hostname) ||
    !Number.isSafeInteger(formId) || formId <= 0 ||
    !Number.isSafeInteger(pageId) || pageId <= 0) {
  throw new Error('CF7_POPUP_REFUSED: local URL and disposable IDs required');
}
const browser = await chromium.launch({ headless: true });
try {
  const page = await browser.newPage({ viewport: { width: 320, height: 720 } });
  const url = new URL('/?page_id=' + pageId, base).toString();
  const responseMatch = r => r.request().method() === 'POST' &&
    r.url().includes('/contact-form-7/v1/contact-forms/') &&
    r.url().includes('/feedback');
  async function openFreshPopup() {
    const response = await page.goto(url, { waitUntil: 'domcontentloaded' });
    if (!response || response.status() !== 200) {
      throw new Error('CF7_POPUP_PAGE_HTTP_NOT_200');
    }
    const trigger = page.locator('[data-n9lh-popup-open="form"]:visible').first();
    await trigger.waitFor({ state: 'visible', timeout: 15000 });
    await trigger.click();
    const popup = page.locator('[data-n9lh-popup="form"].is-open[aria-hidden="false"]').first();
    await popup.waitFor({ state: 'visible', timeout: 15000 });
    const form = popup.locator('form.wpcf7-form').first();
    await form.waitFor({ state: 'visible', timeout: 15000 });
    if (await form.locator('input[name="_wpcf7"]').inputValue() !== String(formId)) {
      throw new Error('CF7_POPUP_FORM_ID_MISMATCH');
    }
    await page.waitForFunction(() => typeof window.wpcf7 !== 'undefined', null, { timeout: 15000 });
    await form.locator('[name="your-name"]').fill('Disposable Alpha Popup Tester');
    await form.locator('[name="your-message"]').fill('Local-only test; no mail delivery.');
    await form.evaluate(el => { el.noValidate = true; });
    return { form, popup, trigger };
  }
  let { form } = await openFreshPopup();
  await form.locator('[name="your-email"]').fill('invalid-email');
  const [bad] = await Promise.all([
    page.waitForResponse(responseMatch, { timeout: 25000 }),
    form.locator('[type="submit"]').click()
  ]);
  const badBody = await bad.json();
  if (bad.status() !== 200 || badBody.status !== 'validation_failed' ||
      !badBody.invalid_fields?.some(f => f.field === 'your-email') ||
      !(bad.request().headers()['content-type'] || '').includes('multipart/form-data')) {
    throw new Error('CF7_POPUP_INVALID_HTTP_REJECTION_FAILED: ' +
      JSON.stringify(badBody).slice(0, 350));
  }
  console.log('CF7_POPUP_INVALID_EMAIL_HTTP_REJECTED=YES');
  // Fresh form instance avoids CF7's retained invalid-submission state.
  ({ form } = await openFreshPopup());
  await form.locator('[name="your-email"]').fill('disposable@example.test');
  const [good] = await Promise.all([
    page.waitForResponse(responseMatch, { timeout: 25000 }),
    form.locator('[type="submit"]').click()
  ]);
  const goodBody = await good.json();
  if (good.status() !== 200 || goodBody.status !== 'mail_sent' ||
      !(good.request().headers()['content-type'] || '').includes('multipart/form-data')) {
    throw new Error('CF7_POPUP_VALID_HTTP_ACCEPTANCE_FAILED: ' +
      JSON.stringify(goodBody).slice(0, 350));
  }
  const overflows = await page.evaluate(() =>
    document.documentElement.scrollWidth > window.innerWidth + 1);
  if (overflows) throw new Error('CF7_POPUP_320PX_HORIZONTAL_OVERFLOW');
  console.log('CF7_POPUP_VALID_HTTP_ACCEPTED=YES');
  console.log('CF7_POPUP_320PX_REFLOW=YES');
  console.log('CF7_POPUP_BROWSER_HTTP_PASS: real Alpha popup; mail suppressed in disposable staging.');
} finally {
  await browser.close();
}
