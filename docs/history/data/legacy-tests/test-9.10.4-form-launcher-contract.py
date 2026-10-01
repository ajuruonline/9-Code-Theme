from pathlib import Path
root=Path(__file__).resolve().parents[1]
main=(root/'nine55-ultron-data.php').read_text()
form=(root/'includes/class-nine10-form.php').read_text()
post=(root/'modules/post/includes/class-nine-post-manager.php').read_text()
postjs=(root/'modules/post/assets/frontend.js').read_text()
cat=(root/'modules/category/includes/class-ninecm-core.php').read_text()
catjs=(root/'modules/category/assets/frontend.js').read_text()
ninecf=(root/'modules/post/includes/class-nine-post-manager-ninecf.php').read_text()
app=(root/'modules/post/assets/app.js').read_text()
checks={
 'version 9.10.14': 'Version: 9.10.14' in main,
 'theme launcher availability contract': 'nine10_data_theme_launcher_available' in main,
 'Post Editor fallback suppressed with Theme': 'nine10_data_theme_launcher_available' in post and 'npm9-front-toggle' in post,
 'Category fallback suppressed with Theme': 'nine10_data_theme_launcher_available' in cat and 'ninecm-fab' in cat,
 'Post Editor lazy load with Theme': 'if ( $theme_launcher )' in post and 'return;' in post,
 'Category Manager lazy load with Theme': 'if ( $theme_launcher )' in cat and 'return;' in cat,
 'category response screen': 'Category Responses' in form and 'render_category_responses_admin' in form,
 'category CSV download': 'handle_download_category_responses' in form and 'Download Category CSV' in form,
 'response source tracking': '_nine10_source_post_id' in form,
 'response category tracking': '_nine10_response_category_ids' in form,
 'post/page/CPT form selector': 'register_form_attachment_metaboxes' in form and '_nine10_attached_form_id' in form,
 'form placement selector': '_nine10_attached_form_position' in form,
 'Post Editor semantic form dropdown': 'enrich_9cf_form_field' in form and 'npm9_9cf_field_contract' in ninecf,
 'named enum labels': 'enum_labels' in form and 'enumLabels' in app,
}
failed=[k for k,v in checks.items() if not v]
if failed: raise SystemExit('FAIL: '+', '.join(failed))
print('PASS 9 Data Manager 9.10.14 form/lazy-launcher contract')
