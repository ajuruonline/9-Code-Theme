// Read-only source-level regression for OS-STD-001: visible strokes require square corners.
// This is NOT a browser/computed-style, WordPress, or WCAG certification.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const css = readFileSync(
  new URL('../../theme-upload/open-theme-green-edition/assets/css/open-scholar-topbar.css', import.meta.url),
  'utf8',
);
const rules = [...css.matchAll(/([^{}]+)\{([^{}]*)\}/g)].map((match) => ({
  selector: match[1].trim(),
  declarations: match[2],
}));
const declarationsFor = (selector) => rules
  .filter((rule) => rule.selector === selector)
  .map((rule) => rule.declarations);
const zeroRadius = (declarations) =>
  /(?:^|;)\s*border-radius\s*:\s*0(?:px)?\s*(?:!important)?\s*(?:;|$)/.test(declarations);

const borderedSelectors = [
  ['side panel', '.n9be-side-panel'],
  ['close button', '.n9be-side-panel header button'],
  ['action/tool links', '.n9be-side-actions>a,.n9be-side-actions>button,.n9be-tool-list>a,.n9be-tool-list>button'],
  ['toolbar extra', '.n9be-toolbar-extra'],
  ['search input and select', '.n9be-side-panel input[type=search],.n9be-side-panel select'],
  ['cache button', '.n9be-clear-cache-button'],
];

for (const [label, selector] of borderedSelectors) {
  test(`bordered ${label} has a zero radius`, () => {
    const matches = declarationsFor(selector);
    assert.ok(matches.length > 0, `missing selector: ${selector}`);
    assert.ok(zeroRadius(matches[0]), `non-square bordered selector: ${selector}`);
  });
}

test('mobile side panel cannot reintroduce rounded corners', () => {
  const overrides = declarationsFor('.n9be-side-panel').filter((body) => /border-radius\s*:/.test(body));
  assert.equal(overrides.length, 2, 'expected base and <=782px radius declarations');
  for (const declarations of overrides) assert.ok(zeroRadius(declarations));
});

test('keyboard focus outline remains visibly present', () => {
  assert.ok(declarationsFor('.n9be-side-popup :focus-visible')
    .some((body) => /outline\s*:\s*2px solid #16844b!important/.test(body)));
});

test('focus outline does not produce rounded outlined elements', () => {
  assert.ok(declarationsFor('.n9be-side-popup :focus-visible')
    .some((body) => /border-radius\s*:\s*0!important/.test(body)));
});

test('borderless search action may retain its rounded treatment', () => {
  assert.ok(declarationsFor('.n9be-search-fields button')
    .some((body) => /border:0;border-radius:8px/.test(body)));
});

test('mobile panel width remains bounded', () => {
  assert.ok(declarationsFor('.n9be-side-panel')
    .some((body) => /width:min\(78vw,350px\)/.test(body)));
});
