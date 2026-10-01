from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
for rel in ['inc/surface-contract.php','inc/enqueue.php','inc/block-edition/bootstrap.php']:
    s=(ROOT/rel).read_text()
    for bad in ['NINE_PAGE_EDITION','MASON_PAGE_EDITOR_VERSION','ninecode_theme_mason_ready','9page-9.10','9page-9.77','mason-page-editor']:
        assert bad not in s, f'{rel} still contains active discontinued reference {bad}'
print('Three-suite discontinued Page/Mason guard: PASS')
