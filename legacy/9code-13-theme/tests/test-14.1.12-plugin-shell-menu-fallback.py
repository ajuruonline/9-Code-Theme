from pathlib import Path
s=(Path(__file__).parents[1]/'assets/js/block-edition-quick-actions.js').read_text()
errors=[]
if "function themeMenu()" not in s: errors.append('Theme menu handler missing')
if "drawer(true)" not in s[s.find('function themeMenu()'):s.find('function frontSave')]: errors.append('Theme hamburger has no fallback when Theme header is suppressed')
print('\n'.join(errors) if errors else 'PASS plugin-shell Theme-menu fallback')
raise SystemExit(1 if errors else 0)
