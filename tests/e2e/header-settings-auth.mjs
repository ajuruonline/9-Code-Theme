/**
 * Disposable WordPress authorization/CSRF acceptance for Alpha header settings.
 * Tests the real options.php and admin-post.php HTTP routes, not only PHP source.
 * No production endpoints or user accounts are touched.
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { isDeepStrictEqual } from 'node:util';
import { BASE, DIR, launch } from './lib.mjs';

const wpRoot = path.join(DIR, 'wp');
const cli = path.join(DIR, 'wp-cli.phar');
const fixture = path.join(wpRoot, 'wp-content/mu-plugins/n9eh-header-auth-qa.php');
const optionName = 'n9lh8_settings';
const marker = 'N9EH_AUTHORIZED_HEADER_WRITE_QA';
let checks = 0;
let original = null;
const createdUsers = [];
const browsers = [];
function wp(...args) {
  const p = spawnSync('php', [cli, '--allow-root', `--path=${wpRoot}`, ...args], {
    encoding: 'utf8', timeout: 30000, maxBuffer: 10 * 1024 * 1024,
  });
  if (p.error || p.status !== 0) {
    throw new Error(`WP-CLI ${args.slice(0, 3).join(' ')} failed: ${p.error || p.stderr}`);
  }
  return p.stdout.trim();
}
function currentOption() { return JSON.parse(wp('option', 'get', optionName, '--format=json')); }
function check(value, label) {
  if (!value) throw new Error(`FAIL ${label}`);
  checks++;
  console.log(`PASS ${label}`);
}
async function session(user, password) {
  const s = await launch();
  browsers.push(s.browser);
  await s.page.goto(`${BASE}/wp-login.php`);
  await s.page.fill('#user_login', user);
  await s.page.fill('#user_pass', password);
  await s.page.click('#wp-submit');
  await s.page.waitForLoadState('load');
  check(s.page.url().includes('/wp-admin/'), `${user} login succeeded`);
  return s.context;
}
async function nonces(context) {
  const response = await context.request.get(`${BASE}/wp-admin/admin-ajax.php?action=n9eh_header_auth_nonce`);
  check(response.status() === 200, 'fixture nonce endpoint requires authenticated session');
  const body = await response.json();
  check(body.success && body.data?.settings && body.data?.reset, 'session-bound nonces returned');
  return body.data;
}
async function save(context, nonce, value) {
  const data = new URLSearchParams({
    option_page: 'n9lh_group',
    action: 'update',
    _wpnonce: nonce,
    [`${optionName}[custom_site_name]`]: value,
  });
  return context.request.post(`${BASE}/wp-admin/options.php`, {
    data: data.toString(), headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    maxRedirects: 0,
  });
}
async function reset(context, nonce) {
  return context.request.get(`${BASE}/wp-admin/admin-post.php?action=elhh_header_reset&_wpnonce=${encodeURIComponent(nonce)}`, { maxRedirects: 0 });
}
function unchanged(before, label) {
  check(isDeepStrictEqual(currentOption(), before), label);
}
try {
  original = currentOption();
  // Fixture is installed ONLY in the throw-away .wp-test site and removed below.
  fs.writeFileSync(fixture, `<?php
if (!defined('ABSPATH')) exit;
add_action('wp_ajax_n9eh_header_auth_nonce', function () {
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Forbidden'], 403);
    wp_send_json_success([
        'settings' => wp_create_nonce('n9lh_group-options'),
        'reset' => wp_create_nonce('elhh_header_reset'),
    ]);
});
`);
  for (const role of ['editor', 'subscriber']) {
    const user = `n9eh_header_${role}_qa`;
    const id = wp('user', 'create', user, `${user}@example.invalid`,
      `--role=${role}`, '--user_pass=N9eh-Test-Only-2026', '--porcelain');
    check(/^\d+$/.test(id), `${role} fixture account created`);
    createdUsers.push(id);
  }
  const guest = await launch();
  browsers.push(guest.browser);
  const guestResult = await save(guest.context, 'invalid', 'GUEST_UNAUTHORIZED');
  check(guestResult.status() !== 302, 'guest settings write rejected');
  unchanged(original, 'guest did not modify header option');

  for (const role of ['editor', 'subscriber']) {
    const context = await session(`n9eh_header_${role}_qa`, 'N9eh-Test-Only-2026');
    const ownNonce = await nonces(context);
    const response = await save(context, ownNonce.settings, `${role.toUpperCase()}_UNAUTHORIZED`);
    check(response.status() !== 302, `${role} valid-own-nonce settings write rejected`);
    unchanged(original, `${role} settings option unchanged`);
    const resetResponse = await reset(context, ownNonce.reset);
    check(resetResponse.status() !== 302, `${role} valid-own-nonce reset rejected`);
    unchanged(original, `${role} reset did not modify option`);
  }

  const admin = await session('admin', 'admin');
  const adminNonce = await nonces(admin);
  const invalidSave = await save(admin, 'invalid', 'ADMIN_BAD_NONCE');
  check(invalidSave.status() !== 302, 'admin missing/invalid settings nonce rejected');
  unchanged(original, 'invalid admin settings nonce left option unchanged');
  const allowedSave = await save(admin, adminNonce.settings, marker);
  check(allowedSave.status() === 302, 'admin valid settings nonce accepted');
  check(currentOption().custom_site_name === marker, 'admin valid save persisted exact marker');
  const beforeReset = currentOption();
  const invalidReset = await reset(admin, 'invalid');
  check(invalidReset.status() !== 302, 'admin invalid reset nonce rejected');
  unchanged(beforeReset, 'invalid admin reset did not modify option');
  const allowedReset = await reset(admin, adminNonce.reset);
  check(allowedReset.status() === 302, 'admin valid reset nonce accepted');
  check(currentOption().custom_site_name !== marker, 'admin valid reset removed marker');
  console.log(`HEADER_SETTINGS_AUTH_GATE_PASS=${checks}`);
} finally {
  // Restore the complete original option even if any assertion fails.
  if (original !== null) {
    wp('option', 'update', optionName, JSON.stringify(original), '--format=json');
    check(isDeepStrictEqual(currentOption(), original), 'original header option restored exactly');
  }
  for (const browser of browsers) await browser.close();
  if (fs.existsSync(fixture)) fs.unlinkSync(fixture);
  check(!fs.existsSync(fixture), 'temporary nonce fixture removed');
  for (const id of createdUsers) wp('user', 'delete', id, '--yes');
  console.log('HEADER_SETTINGS_AUTH_CLEANUP_COMPLETE');
}
