from pathlib import Path
import re
css=(Path(__file__).resolve().parents[1]/'assets/css/block-edition-quick-actions.css').read_text()
vals=[int(x) for x in re.findall(r'z-index:(\d+)',css)]
assert vals, 'launcher z-index values missing'
assert max(vals) < 1000000, 'Theme launcher/modal layer is too dominant for provider plugin dialogs'
assert '#n9be-admin-launcher{position:fixed;z-index:99990' in css, 'front-end launcher should sit below the WordPress admin-bar layer'
print('PASS: Theme launcher yields z-index authority to provider modals')
