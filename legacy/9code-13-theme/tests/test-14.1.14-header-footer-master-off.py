from pathlib import Path
root=Path(__file__).resolve().parents[1]
functions=(root/'functions.php').read_text()
header=(root/'inc/header-footer/header/includes/class-settings.php').read_text()
footer=(root/'inc/header-footer/footer/includes/class-n9f-settings.php').read_text()
bootstrap=(root/'inc/header-footer/bootstrap.php').read_text()
sitewide=(root/'inc/header-footer/footer/includes/class-n9f-sitewide.php').read_text()
renderer=(root/'inc/header-footer/footer/includes/class-n9f-renderer.php').read_text()
widget=(root/'inc/header-footer/footer/includes/widget-n9f-global-footer.php').read_text()
adminjs=(root/'inc/header-footer/assets/admin-unified.js').read_text()

assert "14.2.8" in functions, "Theme must identify the master-off persistence repair release"
assert "'settings_version'=>'8.16.0'" in header, "Header settings migration must advance to 8.16.0"
assert "'settings_version' => '1.7.0'" in footer, "Footer settings migration must advance to 1.7.0"
assert "$s['enabled']='';" in header and "$stored['enabled'] = '';" in footer, "Both migrations must force Theme chrome OFF once"
assert "(isset($in[$k])&&'yes'===$in[$k])?'yes':''" in header.replace(' ',''), "Header checkbox sanitization must only accept literal yes"
assert 'name="n9lh8_settings[enabled_off]"' not in header, "Do not use a fake option key for the master switch"
assert 'type="hidden" name="<?php echo esc_attr(self::OPTION);?>[enabled]" value=""' in header, "Header form must submit an explicit OFF value"
assert 'name="n9f_settings[enabled]" value=""' in footer, "Footer form must submit an explicit OFF value"
assert 'form="n9lh-settings-form"' not in header.split('elhh-master-switch',1)[1].split('</div>',2)[0], "Header master switch must live inside its form, not depend on form= indirection"
assert 'form="n9f-settings-form"' not in footer.split('elhh-master-switch',1)[1].split('</div>',2)[0], "Footer master switch must live inside its form, not depend on form= indirection"
assert 'ncu_theme_force_hf_master_off' in bootstrap, "Theme bootstrap must have a one-time master-off compatibility migration"
assert 'ELHF_F_Settings::is_enabled()' in sitewide, "Sitewide footer hooks must obey raw master state"
assert 'if ( ! ELHF_F_Settings::is_enabled() ) return;' in renderer, "Direct footer renderer calls must obey master OFF"
assert 'ELHF_F_Settings::is_enabled()' in widget, "Elementor global footer widget must obey master OFF"
assert "if ( $header_on )" in bootstrap and "if ( $header_on || $footer_on )" in bootstrap, "Header/Footer compatibility assets must be conditional"
assert "label.textContent=e.target.checked?'ON':'OFF'" in adminjs, "Switch label must update immediately when clicked"
print('PASS')
