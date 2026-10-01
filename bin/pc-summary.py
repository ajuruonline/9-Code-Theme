#!/usr/bin/env python3
"""Summarise `wp plugin check --format=json` output (FILE: path / [json] blocks)."""
import json, re, sys, collections
raw = open(sys.argv[1]).read()
blocks = re.split(r'^FILE: ', raw, flags=re.M)[1:]
rows = []
for b in blocks:
    name, _, rest = b.partition('\n')
    try:
        for r in json.loads(rest.strip() or '[]'):
            r['file'] = name.strip(); rows.append(r)
    except Exception:
        pass
print(len(rows), 'findings in', len(blocks), 'files')
c = collections.Counter((r['type'], r['code']) for r in rows)
for (t, k), v in c.most_common(40): print(f'{v:5} {t:7} {k}')
if len(sys.argv) > 2:
    for r in rows:
        if sys.argv[2] in r['code']:
            print(f"{r['file'].split('/plugin/')[-1].split('/theme/')[-1]}:{r['line']}  {r['message'][:140]}")
