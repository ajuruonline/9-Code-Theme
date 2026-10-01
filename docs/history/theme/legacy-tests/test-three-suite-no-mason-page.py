from pathlib import Path
import sys
root=Path(__file__).resolve().parents[1]
errors=[]
for rel in ['inc/surface-contract.php','inc/enqueue.php','inc/block-edition/bootstrap.php']:
    s=(root/rel).read_text(errors='ignore')
    for bad in ['NINE_PAGE_EDITION','MASON_PAGE_EDITOR_VERSION','ninecode_theme_mason_ready','9page-9.10','9page-9.77','mason-page-editor']:
        if bad in s: errors.append(f'{rel}: active discontinued reference {bad}')
if errors:
    print('\n'.join(errors)); sys.exit(1)
print('PASS three-suite active code has no Mason/9Page special cases')
