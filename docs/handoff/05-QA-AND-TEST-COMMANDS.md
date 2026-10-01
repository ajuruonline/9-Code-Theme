# QA and Test Commands

Run from the unpacked handoff source.

## Component regression tests
```bash
cd source/core/nine-code-ultra-core
for f in tests/*.py; do python3 "$f"; done
for f in tests/*.php; do php "$f"; done

cd ../../theme/9code-13-theme
for f in tests/*.py; do python3 "$f"; done
for f in tests/*.php; do php "$f"; done

cd ../../data/nine10-data-edition
for f in tests/*.py; do python3 "$f"; done
```

## Syntax gates
```bash
find source -name '*.php' -print0 | xargs -0 -n1 php -l
find source -name '*.js' -print0 | xargs -0 -n1 node --check
```

## Critical browser test
Run `source/core/nine-code-ultra-core/tests/test-v15-focus-runtime.py`. It uses Python Playwright + Chromium and checks Classic + Gutenberg at 390x844.

## Manual live-site acceptance
Always perform a final test on the actual WordPress site because host/admin CSS, cache/minification, third-party plugins and browser state can differ from the isolated harness.
