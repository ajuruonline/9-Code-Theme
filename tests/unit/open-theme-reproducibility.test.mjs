// OS-VIS-OP-001: a versioned installer must be reproducible from identical source bytes.
// Changes only a disposable checkout file timestamp; restores it in finally.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { execFileSync } from 'node:child_process';

const root = path.resolve(import.meta.dirname, '../..');
const style = path.join(root, 'theme-upload/open-theme-green-edition/style.css');
const source = fs.readFileSync(style, 'utf8');
const version = source.match(/^Version:\s*([0-9]+(?:\.[0-9]+)+)\s*$/m)?.[1];
assert.ok(version, 'missing source version');
const zip = path.join(root, 'dist', `open-theme-${version}.zip`);
assert.ok(fs.existsSync(zip), 'build package before reproducibility test');
const hash = () => crypto.createHash('sha256').update(fs.readFileSync(zip)).digest('hex');
const build = () => execFileSync('bash', ['bin/build-open-theme.sh'], { cwd: root, stdio: 'pipe' });
const before = hash();
const old = fs.statSync(style);
let altered;
try {
  const epoch = new Date('2001-01-01T00:00:00Z');
  fs.utimesSync(style, epoch, epoch);
  build();
  altered = hash();
} finally {
  fs.utimesSync(style, old.atime, old.mtime);
}
assert.equal(altered, before, 'RELEASE_HOLD: identical Open Theme source bytes produced different ZIP SHA-256 after timestamp change');
build();
assert.equal(hash(), before, 'RELEASE_HOLD: restored source metadata produced different ZIP SHA-256');
console.log(`PASS reproducible Open Theme ZIP: SHA256 ${before}`);
