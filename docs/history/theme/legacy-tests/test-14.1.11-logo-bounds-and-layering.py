from pathlib import Path
root = Path(__file__).resolve().parents[1]
css = (root/'inc/header-footer/header/assets/frontend.css').read_text()
settings = (root/'inc/header-footer/header/includes/class-settings.php').read_text()
main = (root/'assets/css/main.css').read_text()
checks = {
    'header logo uses safe responsive clamp': '--n9lh-logo-safe' in css and 'width:var(--n9lh-logo-safe)' in css,
    'fallback 9 has explicit bounded typography': '.n9lh-logo-fallback' in css and 'font-size:clamp(' in css and 'line-height:1!important' in css,
    'logo container clips overflow': '.n9lh-home' in css and 'overflow:hidden!important' in css,
    'mobile logo has compact cap': '--n9lh-logo-safe-mobile' in css,
    'runtime settings normalize legacy logo values': 'normalize_runtime_layout' in settings,
    'runtime logo max is compact': "min(48" in settings or 'min( 48' in settings,
    'header layer uses configured layer instead of int max': 'z-index:calc(80000 + var(--layer))' in css,
    'legacy side tabs are normalized below launcher': 'z-index:79980!important' in css,
    'theme fallback mark also clips and uses line-height': '.ncu-brand__fallback' in main and 'overflow:hidden' in main and 'line-height:1' in main,
}
failed=[k for k,v in checks.items() if not v]
if failed:
    raise SystemExit('FAIL: ' + '; '.join(failed))
print('PASS: logo bounds and layering contract')
