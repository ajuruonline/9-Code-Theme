// OS-VIS-OP-001 / OS-GOV-016: fail-closed Open Theme installer identity + integrity.
// This checks a separate draft artifact, never the existing 9 Code Alpha package.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { execFileSync } from 'node:child_process';

const root = path.resolve(import.meta.dirname, '../..');
const sourceStyle = fs.readFileSync(path.join(root, 'theme-upload/open-theme-green-edition/style.css'), 'utf8');
assert.match(sourceStyle, /^Theme Name:\s*Open Theme\s*$/m);
const version = sourceStyle.match(/^Version:\s*([0-9]+(?:\.[0-9]+)+)\s*$/m)?.[1];
assert.ok(version, 'Theme version must be present in style.css');
const name = `open-theme-${version}.zip`;
const archive = path.join(root, 'dist', name);
assert.ok(fs.existsSync(archive), `RELEASE_HOLD: missing separate Open Theme installer: ${name}`);
const entries = execFileSync('unzip', ['-Z', '-1', archive], { encoding: 'utf8' }).trim().split(/\r?\n/);
assert.equal(entries.length, new Set(entries).size, 'duplicate ZIP entries');
assert.ok(entries.length > 10, 'theme archive unexpectedly empty');
assert.ok(entries.every(s => s.startsWith('open-theme-green-edition/') && !s.includes('..') && !s.startsWith('/')), 'wrong or unsafe ZIP root');
for (const required of [
  'style.css', 'functions.php', 'index.php', 'theme.json',
  'assets/css/main.css', 'inc/setup.php'
]) {
  assert.ok(entries.includes('open-theme-green-edition/' + required), `missing ${required}`);
}
assert.ok(entries.every(s => !/(^|\/)(\.git|\.github|node_modules|\.env(?:\.|$)|\.DS_Store)(\/|$)/i.test(s)), 'private/build files leaked into installer');
const packedStyle = execFileSync('unzip', ['-p', archive, 'open-theme-green-edition/style.css'], { encoding: 'utf8' });
assert.equal(packedStyle, sourceStyle, 'package style header differs from approved source');
assert.match(packedStyle, /^Theme Name:\s*Open Theme\s*$/m);
assert.doesNotMatch(packedStyle, /^Theme Name:\s*9 Code Alpha\s*$/m);
const manifest = fs.readFileSync(path.join(root, 'dist', 'SHA256SUMS-open-theme.txt'), 'utf8').trim();
const digest = crypto.createHash('sha256').update(fs.readFileSync(archive)).digest('hex');
assert.equal(manifest, `${digest}  ${name}`, 'SHA-256 manifest does not match actual ZIP bytes');
execFileSync('unzip', ['-tqq', archive]);
console.log(`PASS separate Open Theme ZIP: ${name}; SHA256 ${digest}; ${entries.length} entries`);
