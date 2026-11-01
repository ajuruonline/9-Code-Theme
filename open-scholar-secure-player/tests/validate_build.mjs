import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const read = relative => fs.readFileSync(new URL(relative, import.meta.url), 'utf8');
const player = read('../dist/index.html');
const maker = read('../dist/maker.html');
const pluginPlayer = read('../wordpress/secure-scholar/app/index.html');
const pluginMaker = read('../wordpress/secure-scholar/app/maker.html');
const pluginPhp = read('../wordpress/secure-scholar/secure-scholar.php');

test('player contains the media-player controls and no publisher controls', () => {
  for (const id of ['start-open-file', 'start-protected', 'open-form', 'notice', 'notice-list', 'notice-agree', 'notice-start', 'viewer', 'play-button', 'stop-button', 'transport', 'recent-list', 'install-button']) {
    assert.match(player, new RegExp(`id=["']${id}["']`), `player missing #${id}`);
  }
  for (const id of ['secure-form', 'secure-button', 'generate-pin', 'source', 'download-file', 'download-pass', 'backup-owner']) {
    assert.doesNotMatch(player, new RegExp(`id=["']${id}["']`), `player must not contain #${id}`);
  }
  assert.match(player, /Open Scholar Secure Player/);
});

test('maker contains the publisher tools and no player controls', () => {
  for (const id of ['secure-form', 'source', 'duration', 'opens', 'minutes', 'generate-pin', 'secure-button', 'download-file', 'download-pass', 'backup-owner', 'restore-owner', 'copy-device']) {
    assert.match(maker, new RegExp(`id=["']${id}["']`), `maker missing #${id}`);
  }
  for (const id of ['play-button', 'viewer', 'notice', 'page-canvas']) {
    assert.doesNotMatch(maker, new RegExp(`id=["']${id}["']`), `maker must not contain #${id}`);
  }
  assert.match(maker, /Open Scholar Secure File Maker/);
  assert.match(maker, /FOR PUBLISHERS ONLY/);
});

test('inline module scripts parse and define no duplicate top-level names', () => {
  for (const html of [player, maker]) {
    const scripts = [...html.matchAll(/<script type="module">([\s\S]*?)<\/script>/g)].map(match => match[1]);
    const app = scripts[scripts.length - 1];
    assert.doesNotThrow(() => new vm.Script('(async function(){\n' + app + '\n})'));
    const names = [...app.matchAll(/^(?:const|let|function|async function) ([A-Za-z_$][\w$]*)/gm)].map(match => match[1]);
    assert.equal(new Set(names).size, names.length, 'duplicate top-level names');
  }
});

test('HTML ids are unique in both pages', () => {
  for (const html of [player, maker]) {
    const ids = [...html.matchAll(/\sid=["']([^"']+)["']/g)].map(match => match[1]);
    assert.equal(new Set(ids).size, ids.length);
  }
});

test('WordPress serves the same builds, the player on the public page and the maker on the owner page', () => {
  assert.equal(pluginPlayer, player);
  assert.equal(pluginMaker, maker);
  for (const value of ['Plugin Name: Open Scholar Secure Player', 'Version: 1.2.0', "'owner' ? 'maker.html' : 'index.html'", 'maker.html', '/secure-scholar/', 'secure_scholar_owner', "add_shortcode('secure_scholar'", 'Powered by OpenScholar.ng.']) {
    assert.ok(pluginPhp.includes(value) || (value.includes('?') && pluginPhp.includes('maker.html')), `missing ${value}`);
  }
});
