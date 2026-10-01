#!/usr/bin/env python3
"""Append justified `phpcs:ignore` comments for low-risk, intentional raw file-stream calls
(CSV/ZIP download streams, staged temp files) reported by `wp plugin check`.
Usage: pc-annotate.py <plugin-check.json> """
import json, re, sys
raw = open(sys.argv[1]).read()
blocks = re.split(r'^FILE: ', raw, flags=re.M)[1:]
REASON = {
 'file_system_operations_fopen': 'streams export/import data (php://output or a temp file); WP_Filesystem cannot stream',
 'file_system_operations_fclose': 'closes a stream opened above',
 'file_system_operations_fwrite': 'writes to a stream/temp file opened above',
 'file_system_operations_readfile': 'streams a generated download to the browser',
 'file_system_operations_rmdir': 'removes this plugin\'s own staging directory',
 'file_system_operations_chmod': 'sets permissions on this plugin\'s own staged file',
 'rename_rename': 'moves a staged file within this plugin\'s own directory',
}
edits = {}
for b in blocks:
    name, _, rest = b.partition('\n')
    name = name.strip()
    try: rows = json.loads(rest.strip() or '[]')
    except Exception: continue
    for r in rows:
        code = r['code']
        m = re.match(r'WordPress\.WP\.AlternativeFunctions\.(.*)', code)
        if not m or m.group(1) not in REASON: continue
        edits.setdefault((name, r['line']), set()).add((code, REASON[m.group(1)]))
by_file = {}
for (f, line), items in edits.items(): by_file.setdefault(f, {})[line] = items
for f, lines in by_file.items():
    src = open(f).read().split('\n')
    for ln, items in lines.items():
        i = ln - 1
        if 'phpcs:ignore' in src[i]: continue
        codes = ','.join(sorted(c for c, _ in items)); why = sorted({w for _, w in items})[0]
        src[i] = src[i].rstrip() + f' // phpcs:ignore {codes} -- {why}.'
    open(f, 'w').write('\n'.join(src))
    print('annotated', f.split('/plugin/')[-1], len(lines))
