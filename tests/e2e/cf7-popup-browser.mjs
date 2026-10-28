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
    // DOMContentLoaded can precede deferred Alpha/CF7 listener installation.
    await page.waitForLoadState('load');
    const trigger = page.locator('[data-n9lh-popup-open="form"]:visible').first();
    await trigger.waitFor({ state: 'visible', timeout: 15000 });
    await trigger.click();
    const popup = page.locator('[data-n9lh-popup="form"].is-open[aria-hidden="false"]').first();
    await popup.waitFor({ state: 'visible', timeout: 15000 });
    const formCount = await page.locator('form.wpcf7-form').count();
    if (formCount !== 1) throw new Error('CF7_POPUP_DUPLICATE_FORM_INSTANCE: ' + formCount);
    const form = popup.locator('form.wpcf7-form').first();
    await form.waitFor({ state: 'visible', timeout: 15000 });
    if (await form.locator('input[name="_wpcf7"]').inputValue() !== String(formId)) {
      throw new Error('CF7_POPUP_FORM_ID_MISMATCH');
    }
    await page.waitForFunction(() => typeof window.wpcf7 !== 'undefined', null, { timeout: 15000 });
    const state = await form.evaluate(el => ({
      readyState: document.readyState,
      status: el.dataset.status || '',
      cf7SubmitType: typeof window.wpcf7?.submit,
      hasForm: !!el.closest('[data-n9lh-popup="form"]'),
      action: el.action,
      method: el.method
    }));
    const structural = await form.evaluate(el => ({
      wrapperClass: el.parentElement?.className || '',
      cf7Wrapper: !!el.closest('.wpcf7'),
      directChild: !!el.parentElement?.classList.contains('wpcf7'),
      noValidate: el.noValidate,
      valid: el.checkValidity(),
      hiddenFields: [...el.querySelectorAll('input[type=hidden]')].map(i => i.name),
      formsOnPage: document.querySelectorAll('form.wpcf7-form').length
    }));
    console.log('CF7_POPUP_DOM_STRUCTURE=' + JSON.stringify(structural));
    await form.evaluate(el => el.addEventListener('submit', event => {
      console.log('CF7_TEST_SUBMIT_EVENT=' + JSON.stringify({
        defaultPrevented: event.defaultPrevented,
        noValidate: el.noValidate,
        valid: el.checkValidity()
      }));
    }, { capture: true }));
    console.log('CF7_POPUP_INIT_STATE=' + JSON.stringify(state));
    await form.locator('[name="your-name"]').fill('Disposable Alpha Popup Tester');
    await form.locator('[name="your-message"]').fill('Local-only test; no mail delivery.');
    await form.evaluate(el => { el.noValidate = true; });
    return { form, popup, trigger };
  }
  page.on('console', msg => {
    if (msg.text().startsWith('CF7_TEST_') || msg.type() === 'error') {
      console.log('CF7_POPUP_BROWSER_CONSOLE=' + msg.text().slice(0, 250));
    }
  });
  page.on('requestfailed', req => {
    console.log('CF7_POPUP_REQUEST_FAILED=' + new URL(req.url()).pathname + ':' + (req.failure()?.errorText || 'unknown'));
  });
  page.on('request', req => {
    if (req.method() === 'POST' && req.url().includes('contact-form-7')) {
      console.log('CF7_POPUP_POST_OBSERVED=YES');
    }
  });
  page.on('pageerror', error => console.log('CF7_POPUP_PAGE_ERROR=' + String(error).slice(0, 180)));
  let { form } = await openFreshPopup();
  await form.locator('[name="your-email"]').fill('reject@example.test');
  const submitButton = form.locator('[type="submit"]').first();
  const buttonState = await submitButton.evaluate(el => ({
    disabled: el.disabled, type: el.type, linkedToForm: el.form === el.closest('form'),
    outer: el.outerHTML.slice(0, 200)
  }));
  console.log('CF7_POPUP_BUTTON_STATE=' + JSON.stringify(buttonState));
  const [badResult, clickResult] = await Promise.allSettled([
    page.waitForResponse(responseMatch, { timeout: 25000 }),
    submitButton.click({ timeout: 8000 })
  ]);
  if (clickResult.status === 'rejected') {
    throw new Error('CF7_POPUP_CLICK_FAILED: ' + String(clickResult.reason).slice(0, 500));
  }
  if (badResult.status === 'rejected') {
    throw new Error('CF7_POPUP_NO_HTTP_RESPONSE_AFTER_CLICK: ' +
      String(badResult.reason).slice(0, 250));
  }
  const bad = badResult.value;
  const badBody = await bad.json();
  if (bad.status() !== 200 || badBody.status !== 'validation_failed' ||
      !badBody.invalid_fields?.some(f => f.field === 'your-email') ||
      !(bad.request().headers()['content-type'] || '').includes('multipart/form-data')) {
    throw new Error('CF7_POPUP_INVALID_HTTP_REJECTION_FAILED: ' +
      JSON.stringify(badBody).slice(0, 350));
  }
  console.log('CF7_POPUP_SERVER_VALIDATION_HTTP_REJECTED=YES');
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
