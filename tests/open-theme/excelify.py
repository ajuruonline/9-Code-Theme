"""Rewrite an inline-string .xlsx the way Excel saves it: shared strings, rich-text runs, absolute targets."""
import re, sys, zipfile
from xml.sax.saxutils import escape
src, dst = sys.argv[1], sys.argv[2]
zin = zipfile.ZipFile(src)
shared, index = [], {}
def sid(text):
    if text not in index:
        index[text] = len(shared); shared.append(text)
    return index[text]
out = {}
for name in zin.namelist():
    data = zin.read(name).decode('utf-8')
    if name.startswith('xl/worksheets/'):
        def repl(m):
            ref, raw = m.group(1), m.group(2)
            text = re.sub(r'&(lt|gt|amp|quot|apos);', lambda e: {'lt':'<','gt':'>','amp':'&','quot':'"','apos':"'"}[e.group(1)], raw)
            text = re.sub(r'&#(\d+);', lambda e: chr(int(e.group(1))), text)
            return '<c r="%s" t="s"><v>%d</v></c>' % (ref, sid(text))
        data = re.sub(r'<c r="([A-Z]+\d+)" t="inlineStr"><is><t xml:space="preserve">(.*?)</t></is></c>', repl, data, flags=re.S)
    if name == 'xl/_rels/workbook.xml.rels':
        data = data.replace('Target="worksheets/', 'Target="/xl/worksheets/').replace('</Relationships>', '<Relationship Id="rIdS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>')
    if name == '[Content_Types].xml':
        data = data.replace('</Types>', '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>')
    out[name] = data
items = []
for i, text in enumerate(shared):
    if i == 1 and len(text) > 3:  # one string stored as formatted runs, as Excel does after bolding part of a cell
        items.append('<si><r><rPr><b/></rPr><t xml:space="preserve">%s</t></r><r><t xml:space="preserve">%s</t></r></si>' % (escape(text[:2]), escape(text[2:])))
    else:
        items.append('<si><t xml:space="preserve">%s</t></si>' % escape(text))
out['xl/sharedStrings.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="%d" uniqueCount="%d">%s</sst>' % (len(shared), len(shared), ''.join(items))
with zipfile.ZipFile(dst, 'w', zipfile.ZIP_DEFLATED) as zout:
    for name, data in out.items():
        zout.writestr(name, data)
print('shared strings:', len(shared))
