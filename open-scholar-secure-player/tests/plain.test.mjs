import test from 'node:test';
import assert from 'node:assert/strict';
import {plainKind, resumeKey, UNSUPPORTED_MESSAGE} from '../src/plain.mjs';

test('ordinary files are recognised case-insensitively', () => {
  assert.equal(plainKind('Lecture 1.PDF'), 'pdf');
  assert.equal(plainKind('clip.mp4'), 'video');
  assert.equal(plainKind('clip.WebM'), 'video');
  assert.equal(plainKind('slide.png'), 'image');
  assert.equal(plainKind('photo.JPEG'), 'image');
  assert.equal(plainKind('lesson.htm'), 'web');
});

test('unsupported or extensionless files are rejected with a friendly message', () => {
  assert.equal(plainKind('notes.docx'), null);
  assert.equal(plainKind('README'), null);
  assert.equal(plainKind(''), null);
  assert.match(UNSUPPORTED_MESSAGE, /PDF/);
});

test('resume key changes when the file changes', () => {
  const a = {name: 'a.pdf', size: 10, lastModified: 1};
  const b = {name: 'a.pdf', size: 11, lastModified: 1};
  assert.notEqual(resumeKey(a), resumeKey(b));
  assert.equal(resumeKey(a), resumeKey({...a}));
});
