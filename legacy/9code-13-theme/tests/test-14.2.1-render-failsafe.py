from pathlib import Path
root=Path(__file__).resolve().parents[1]
r=(root/'inc/renderers.php').read_text()
p=(root/'inc/post-display-settings.php').read_text()
checks={
 'content output catches Throwable': ("catch ( \\Throwable $e )" in r or "catch ( Throwable $e )" in r) and "ncu_output_post_content" in r,
 'full width filter has failsafe': "ncu_builder_full_width" in r and "9Code Theme render filter" in r,
 'feature visibility filter has failsafe': "ncu_should_show_post_feature" in p and "9Code Theme feature filter" in p,
}
bad=[k for k,v in checks.items() if not v]
if bad:
 print('FAIL: '+', '.join(bad)); raise SystemExit(1)
print('PASS Theme render failsafe contract')
