import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const html = fs.readFileSync(new URL('../dist/index.html', import.meta.url), 'utf8');
const pluginHtml = fs.readFileSync(new URL('../wordpress/secure-scholar/app/index.html', import.meta.url), 'utf8');
const pluginPhp = fs.readFileSync(new URL('../wordpress/secure-scholar/secure-scholar.php', import.meta.url), 'utf8');

test('built application contains all required simple-flow controls', () => {
  const required = ['start-secure','start-open','source','duration','opens','minutes','secure-button','download-file','download-pass','share-files','secure-file','pass-file','open-button','credits','settings-panel'];
  for (const id of required) assert.match(html, new RegExp(`id=["']${id}["']`), `missing #${id}`);
  assert.match(html, /Secure Scholar Plugin v1/);
  assert.doesNotMatch(html, /GiliSoft|GEM format/i);
});

test('inline classic scripts parse', () => {
  const scripts = [...html.matchAll(/<script(?: [^>]*)?>([\s\S]*?)<\/script>/g)].map(match => match[1]);
  assert.ok(scripts.length >= 3);
  for (const script of scripts) {
    if (!script.trim() || /\bimport\b|\bexport\b/.test(script)) continue;
    new vm.Script(script);
  }
});

test('HTML ids are unique', () => {
  const ids = [...html.matchAll(/\sid=["']([^"']+)["']/g)].map(match => match[1]);
  assert.equal(new Set(ids).size, ids.length);
});

test('WordPress and desktop use the exact same application build', () => {
  assert.equal(pluginHtml, html);
});

test('WordPress package contains Open Scholar standard entry points', () => {
  for (const value of ['Plugin Name: Secure Scholar Plugin', 'Version: 1.0.0', '/secure-scholar/', 'secure_scholar_owner', "add_shortcode('secure_scholar'", 'secure_scholar_tutorial_post', 'Powered by OpenScholar.ng.']) {
    assert.ok(pluginPhp.includes(value), `missing ${value}`);
  }
});
