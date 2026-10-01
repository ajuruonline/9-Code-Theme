from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
CSS=(ROOT/'assets/css/editor-workspace.css').read_text(errors='ignore')
JS=(ROOT/'assets/js/editor-workspace.js').read_text(errors='ignore')

def config(block):
    return f"""<script>window.NCUEditorWorkspace={{enabled:true,isBlockEditor:{str(block).lower()},toolsDrawer:true,focusPanels:false,hidePluginPanels:false,breakpoint:1180,title:'Editor Tools',shortcuts:[{{label:'9Code Theme',url:'#theme'}},{{label:'WordPress Settings',url:'#settings'}}]}};</script>"""

def assert_editable(page, selector, value):
    loc=page.locator(selector); assert loc.is_visible(), selector
    loc.fill(value); assert loc.input_value()==value

def classic(browser):
    page=browser.new_page(viewport={'width':390,'height':844})
    page.set_content(f'''<!doctype html><style>{CSS}</style><body class="ncu-editor-workspace ncu-editor-tools-enabled ncu-editor-focus-panels ncu-editor-hide-plugin-panels ncu-editor-panel-fullscreen-open"><div id="post-body-content" class="ncu-editor-panel-fullscreen"><textarea id="content">Editable</textarea></div><div id="meta" class="postbox ncu-editor-panel-hidden"><div class="inside"><input id="field" value="Meta"></div></div><div class="ncu-editor-focus-backdrop"></div>{config(False)}<script>{JS}</script></body>''')
    page.wait_for_timeout(220)
    assert_editable(page,'#content','Changed')
    assert_editable(page,'#field','Changed Meta')
    assert page.locator('.ncu-editor-focus-backdrop').count()==0
    assert page.locator('.ncu-editor-tools-toggle--floating').is_visible()
    page.locator('.ncu-editor-tools-toggle--floating').click(); page.wait_for_timeout(40)
    assert page.locator('.ncu-editor-tools-shortcuts').get_by_text('9Code Theme').is_visible()
    assert page.locator('.ncu-editor-tools-shortcuts').get_by_text('WordPress Settings').is_visible()
    page.locator('.ncu-editor-tools-close').click(); page.close()

def gutenberg(browser):
    page=browser.new_page(viewport={'width':390,'height':844})
    page.set_content(f'''<!doctype html><style>{CSS}</style><body class="ncu-editor-workspace ncu-editor-tools-enabled"><header class="editor-header"><div class="editor-header__settings"><button class="components-button editor-post-publish-button">Update</button><button class="components-button rank-math-toolbar" aria-label="Rank Math">SEO</button></div></header><main class="interface-interface-skeleton__content"><input id="gb-title" value="Title"><textarea id="gb-content">Body</textarea><div class="postbox" id="acf"><input id="acf-field" value="Meta"></div></main>{config(True)}<script>{JS}</script></body>''')
    page.wait_for_timeout(250)
    assert_editable(page,'#gb-title','New Title')
    assert_editable(page,'#gb-content','New Body')
    assert_editable(page,'#acf-field','New Meta')
    assert page.locator('.editor-post-publish-button').is_visible()
    assert page.locator('.ncu-editor-tools-toggle').is_visible()
    # Nonessential plugin toolbar action is moved into the drawer only on compact Gutenberg.
    assert not page.locator('.rank-math-toolbar').is_visible()
    page.locator('.ncu-editor-tools-toggle').click(); page.wait_for_timeout(40)
    assert page.locator('.ncu-editor-tools-list').get_by_text('Rank Math').is_visible()
    assert page.locator('.ncu-editor-tools-shortcuts').get_by_text('WordPress Settings').is_visible()
    page.close()

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
    try:
        classic(browser); gutenberg(browser)
    finally:
        browser.close()
print('PASS browser runtime: Classic + Gutenberg native content/meta editing; settings in hamburger; no focus overlay')
