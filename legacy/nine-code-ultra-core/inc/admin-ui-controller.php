<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$GLOBALS['ncu13_ui_menu_inventory'] = array();

function ncu13_ui_defaults() {
    return array('menu_filter'=>1,'dashboard_filter'=>1,'ai_editor_modern'=>1,'runtime_blue_lock'=>1,'auto_show_9code'=>1,'menu_configured'=>0,'visible_slugs'=>array());
}
function ncu13_ui_settings() {
    $saved = get_option( 'ncu13_ui_settings', array() );
    return wp_parse_args( is_array($saved)?$saved:array(), ncu13_ui_defaults() );
}
function ncu13_ui_safe_mode() {
    return ( defined('NCU13_UI_DISABLE') && NCU13_UI_DISABLE ) || ( current_user_can('manage_options') && isset($_GET['ncu_ui_safe']) && '1' === sanitize_text_field(wp_unslash($_GET['ncu_ui_safe'])) );
}
function ncu13_ui_is_ai_editor() {
    if ( ! isset( $_GET['page'] ) ) { return false; }
    $page = sanitize_key( wp_unslash( $_GET['page'] ) );
    return in_array( $page, array( 'nine-code-ultra', 'nine-code-ultra-data-engine', 'ninecode-acf-ai' ), true );
}
function ncu13_ui_menu_label( $item ) { return isset($item[0]) ? trim(wp_strip_all_tags((string)$item[0])) : ''; }
function ncu13_ui_menu_slug( $item ) { return isset($item[2]) ? (string)$item[2] : ''; }
function ncu13_ui_is_9code_menu( $label, $slug ) {
    $hay = strtolower(trim($label.' '.$slug));
    return (bool) preg_match('/(?:^|[\s_\-\/])(9\s*code|9code|nine\s*code|9\s*elements|99\b|n9\b|ncu\b|nine\b)/i',$hay)
        || 0 === stripos(trim($label),'9') || 0 === stripos(trim($label),'Nine') || 0 === stripos(trim($slug),'ncu') || 0 === stripos(trim($slug),'nine-');
}
function ncu13_ui_is_default_visible( $label, $slug ) {
    $l=strtolower($label); $s=strtolower($slug);
    if ( in_array($slug,array('index.php','upload.php','users.php','edit.php?post_type=page'),true) ) return true;
    if ( false!==strpos($l,'bluehost') || false!==strpos($s,'bluehost') ) return true;
    if ( false!==strpos($l,'jetpack') || false!==strpos($s,'jetpack') ) return true;
    if ( false!==strpos($l,'elementor') || false!==strpos($s,'elementor') ) return true;
    if ( false!==strpos($l,'acf') || false!==strpos($l,'custom fields') || false!==strpos($s,'acf') ) return true;
    return ncu13_ui_is_9code_menu($label,$slug);
}
function ncu13_ui_capture_menu_inventory() {
    if ( ! current_user_can('manage_options') || ncu13_ui_safe_mode() ) return;
    global $menu; if ( ! is_array($menu) ) return;
    $rows=array(); foreach($menu as $item){ $slug=ncu13_ui_menu_slug($item); if(!$slug)continue; $label=ncu13_ui_menu_label($item); $rows[$slug]=array('slug'=>$slug,'label'=>$label,'nine'=>ncu13_ui_is_9code_menu($label,$slug)); }
    $GLOBALS['ncu13_ui_menu_inventory']=$rows;
}
add_action('admin_menu','ncu13_ui_capture_menu_inventory',PHP_INT_MAX-20);

function ncu13_ui_apply_menu_policy() {
    if ( ncu13_ui_safe_mode() ) return;
    $cfg=ncu13_ui_settings(); if(empty($cfg['menu_filter'])) return;
    global $menu; if(!is_array($menu))return;
    $selected=array_fill_keys(array_map('strval',(array)$cfg['visible_slugs']),true);
    $configured=!empty($cfg['menu_configured']);
    foreach($menu as $priority=>$item){
        $slug=ncu13_ui_menu_slug($item); if(!$slug)continue; $label=ncu13_ui_menu_label($item);
        $keep = $configured ? isset($selected[$slug]) : ncu13_ui_is_default_visible($label,$slug);
        if(!empty($cfg['auto_show_9code']) && ncu13_ui_is_9code_menu($label,$slug)) $keep=true;
        if(!$keep) unset($menu[$priority]);
    }
}
add_action('admin_menu','ncu13_ui_apply_menu_policy',PHP_INT_MAX);
add_action('admin_head','ncu13_ui_apply_menu_policy',0);

add_filter('custom_menu_order','__return_true',9999);
add_filter('menu_order','ncu13_ui_menu_order',9999);
function ncu13_ui_menu_order($order){
    if(ncu13_ui_safe_mode() || !is_array($order)) return $order;
    global $menu; $labels=array(); if(is_array($menu))foreach($menu as $i){$labels[ncu13_ui_menu_slug($i)]=ncu13_ui_menu_label($i);} $rank=function($slug)use($labels){$l=strtolower($labels[$slug]??'');$s=strtolower($slug);if('index.php'===$slug)return 0;if(ncu13_ui_is_9code_menu($l,$s))return 10;if('edit.php?post_type=page'===$slug)return 20;if('upload.php'===$slug)return 30;if('users.php'===$slug)return 40;if(false!==strpos($l,'elementor')||false!==strpos($s,'elementor'))return 50;if(false!==strpos($l,'acf')||false!==strpos($l,'custom fields')||false!==strpos($s,'acf'))return 60;if(false!==strpos($l,'bluehost')||false!==strpos($s,'bluehost'))return 70;if(false!==strpos($l,'jetpack')||false!==strpos($s,'jetpack'))return 80;return 100;};
    $indexed=array();foreach($order as $i=>$slug)$indexed[]=array($slug,$rank($slug),$i);usort($indexed,function($a,$b){return $a[1]===$b[1]?$a[2]<=>$b[2]:$a[1]<=>$b[1];});return array_column($indexed,0);
}

function ncu13_ui_sanitize_settings($input){$d=ncu13_ui_defaults();$input=is_array($input)?$input:array();foreach(array('menu_filter','dashboard_filter','ai_editor_modern','runtime_blue_lock','auto_show_9code')as$k)$d[$k]=empty($input[$k])?0:1;$d['menu_configured']=1;$d['visible_slugs']=array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)($input['visible_slugs']??array())))));return $d;}
register_activation_hook(NCU_CORE_FILE,'ncu13_ui_activation');
function ncu13_ui_activation(){if(false===get_option('ncu13_ui_settings',false))add_option('ncu13_ui_settings',ncu13_ui_defaults(),'',false);}
function ncu13_ui_migrate_legacy_settings(){ if(false!==get_option('ncu13_ui_settings',false))return; $legacy=get_option('ncu12_ui_controller_settings',false); if(is_array($legacy))update_option('ncu13_ui_settings',ncu13_ui_sanitize_settings($legacy),false); }
add_action('admin_init','ncu13_ui_migrate_legacy_settings',1);

add_action('admin_post_ncu13_ui_save','ncu13_ui_save');
function ncu13_ui_save(){if(!current_user_can('manage_options'))wp_die('Permission denied.');check_admin_referer('ncu13_ui_save');$raw=isset($_POST['ncu13_ui'])?wp_unslash($_POST['ncu13_ui']):array();update_option('ncu13_ui_settings',ncu13_ui_sanitize_settings($raw),false);wp_safe_redirect(admin_url('admin.php?page=nine-code-ultra-menu-ui&updated=1'));exit;}
add_action('admin_post_ncu13_ui_reset','ncu13_ui_reset');
function ncu13_ui_reset(){if(!current_user_can('manage_options'))wp_die('Permission denied.');check_admin_referer('ncu13_ui_reset');update_option('ncu13_ui_settings',ncu13_ui_defaults(),false);wp_safe_redirect(admin_url('admin.php?page=nine-code-ultra-menu-ui&reset=1'));exit;}
function ncu13_ui_render_settings_page(){if(!current_user_can('manage_options'))return;$cfg=ncu13_ui_settings();$items=$GLOBALS['ncu13_ui_menu_inventory'];if(empty($items)){global$menu;if(is_array($menu))foreach($menu as$i){$slug=ncu13_ui_menu_slug($i);if($slug)$items[$slug]=array('slug'=>$slug,'label'=>ncu13_ui_menu_label($i),'nine'=>ncu13_ui_is_9code_menu(ncu13_ui_menu_label($i),$slug));}}$selected=array_fill_keys((array)$cfg['visible_slugs'],true);?>
<div class="wrap ncu13-ui-settings"><h1>Menu & UI</h1><p class="ncu13-ui-lead">9Core 15 owns one predictable WordPress admin shell. Defaults show Dashboard, 9-family tools, Pages, Media, Users, plus Elementor, ACF, Bluehost and Jetpack only when they exist.</p><?php if(isset($_GET['updated']))echo'<div class="notice notice-success is-dismissible"><p>Menu & UI settings saved.</p></div>';?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="ncu13_ui_save"><?php wp_nonce_field('ncu13_ui_save');?><section class="ncu13-ui-panel"><h2>UI controls</h2><div class="ncu13-ui-toggle-grid"><?php foreach(array('menu_filter'=>'Control left menu','dashboard_filter'=>'9Core-only dashboard','ai_editor_modern'=>'Modern AI Data Manager','runtime_blue_lock'=>'Runtime zero-blue lock','auto_show_9code'=>'Auto-show 9-family plugins')as$k=>$lab):?><label class="ncu13-ui-toggle"><input type="checkbox" name="ncu13_ui[<?php echo esc_attr($k);?>]" value="1" <?php checked(!empty($cfg[$k]));?>><?php echo esc_html($lab);?></label><?php endforeach;?></div></section><section class="ncu13-ui-panel"><h2>Left menu visibility</h2><p>9-family plugins remain visible automatically while Auto-show is enabled. Everything else is explicit.</p><div class="ncu13-ui-menu-grid"><?php foreach($items as$item):$checked=!empty($cfg['menu_configured'])?isset($selected[$item['slug']]):ncu13_ui_is_default_visible($item['label'],$item['slug']);?><label class="ncu13-ui-menu-item <?php echo $item['nine']?'is-nine':'';?>"><input type="checkbox" name="ncu13_ui[visible_slugs][]" value="<?php echo esc_attr($item['slug']);?>" <?php checked($checked);?>><span class="ncu13-ui-menu-name"><?php echo esc_html($item['label']?:$item['slug']);?></span><code><?php echo esc_html($item['slug']);?></code><?php if($item['nine']):?><small>9-family · automatic</small><?php endif;?></label><?php endforeach;?></div></section><p><button class="button button-primary button-hero">Save menu & UI</button></p></form><form class="ncu13-ui-reset-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" onsubmit="return confirm('Restore the 9Core 15 default menu policy?');"><input type="hidden" name="action" value="ncu13_ui_reset"><?php wp_nonce_field('ncu13_ui_reset');?><button class="button">Restore defaults</button></form><p class="ncu13-ui-recovery"><strong>Recovery:</strong> append <code>&ncu_ui_safe=1</code> to an administrator URL to bypass menu filtering for that request.</p></div><?php }

add_action('wp_dashboard_setup','ncu13_ui_filter_dashboard',PHP_INT_MAX);
function ncu13_ui_filter_dashboard(){if(ncu13_ui_safe_mode()||empty(ncu13_ui_settings()['dashboard_filter']))return;remove_action('welcome_panel','wp_welcome_panel');global$wp_meta_boxes;if(is_array($wp_meta_boxes)){$wp_meta_boxes['dashboard']=array();}wp_add_dashboard_widget('ncu13_data_manager',ncu_core_external_data_edition_active()?'9.10 Data Edition':'Legacy Data Fallback','ncu13_ui_dashboard_widget');wp_add_dashboard_widget('ncu13_responses','Responses','ncu13_ui_responses_widget');}
function ncu13_ui_dashboard_widget(){if(ncu_core_external_data_edition_active()){echo'<p><strong>9 Data owns the Edition 9.10 data workspace.</strong></p><p>Post Editor, Category Manager, Post Creator, Form Manager and Data Backup are owned by 9 Data Manager to preserve one-owner-per-responsibility.</p><p><a class="button button-primary button-hero" href="'.esc_url(admin_url('admin.php?page=nine10-data-edition')).'">Open 9 Data Manager</a></p>';return;}echo'<p><strong>Legacy compatibility fallback.</strong></p><p>Install 9.10 Data Edition for the current data-management workflow.</p><p><a class="button button-primary button-hero" href="'.esc_url(admin_url('admin.php?page=nine-code-ultra')).'">Open fallback</a></p>'; }
function ncu13_ui_responses_widget(){echo'<p><strong>Open responses collected by installed 9-family forms and workflow plugins.</strong></p><p>Responses stays a hub; each owning plugin keeps its own authoritative records.</p><p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=nine-code-ultra-responses')).'">Open Responses</a></p>'; }

add_action('admin_enqueue_scripts','ncu13_ui_enqueue_assets',PHP_INT_MAX);
function ncu13_ui_enqueue_assets(){if(ncu13_ui_safe_mode())return;$cfg=ncu13_ui_settings();wp_enqueue_style('ncu13-admin-ui',NCU_CORE_URL.'assets/css/admin-ui-13.css',array(),NCU_CORE_VERSION);wp_enqueue_script('ncu13-admin-ui',NCU_CORE_URL.'assets/js/admin-ui-13.js',array(),NCU_CORE_VERSION,true);if(function_exists('wp_script_add_data'))wp_script_add_data('ncu13-admin-ui','strategy','defer');wp_localize_script('ncu13-admin-ui','NCU13UI',array('runtimeBlueLock'=>!empty($cfg['runtime_blue_lock']),'aiEditorModern'=>!empty($cfg['ai_editor_modern']),'isAiEditor'=>ncu13_ui_is_ai_editor()));}
add_filter('admin_body_class','ncu13_ui_admin_body_class');
function ncu13_ui_admin_body_class($classes){if(ncu13_ui_safe_mode())return$classes;$classes.=' ncu13-ui-controller';if(ncu13_ui_is_ai_editor()&&!empty(ncu13_ui_settings()['ai_editor_modern']))$classes.=' ncu13-ai-editor-modern';return$classes;}

add_action('admin_footer','ncu13_ui_final_neutral_contract',PHP_INT_MAX);
function ncu13_ui_final_neutral_contract(){if(ncu13_ui_safe_mode())return;?><style id="ncu13-final-neutral-contract">
body #adminmenuback,body #adminmenuwrap,body #adminmenu,body #adminmenu .wp-submenu{background:#171717!important;border-color:transparent!important;box-shadow:none!important}
body #adminmenu a,body #adminmenu div.wp-menu-image:before{color:#e7e7e7!important}
body #adminmenu>li>a.menu-top:hover,body #adminmenu>li>a.menu-top:focus,body #adminmenu>li>a.menu-top:focus-visible,body #adminmenu .wp-submenu a:hover,body #adminmenu .wp-submenu a:focus{background:#303030!important;color:#fff!important;border-color:transparent!important;box-shadow:none!important}
body #adminmenu>li.current>a.menu-top,body #adminmenu>li.wp-has-current-submenu>a.wp-has-current-submenu,body #adminmenu .wp-submenu li.current a{background:#f4f4f4!important;color:#080808!important;border-color:transparent!important;box-shadow:inset 3px 0 0 #080808!important}
body #adminmenu>li.current>a.menu-top div.wp-menu-image:before,body #adminmenu>li.wp-has-current-submenu>a.wp-has-current-submenu div.wp-menu-image:before{color:#080808!important}
body #adminmenu .wp-menu-arrow,body #adminmenu .wp-menu-arrow div,body #adminmenu:before,body #adminmenuwrap:before,body #adminmenuback:before{background:transparent!important;border-color:transparent!important;box-shadow:none!important}
body.ncu13-ui-controller .wp-core-ui .button-primary,body.ncu13-ui-controller .components-button.is-primary,body.ncu13-ui-controller .components-button.is-pressed{background:#111!important;border-color:#111!important;color:#fff!important;box-shadow:none!important;text-shadow:none!important}
body.ncu13-ui-controller .wp-core-ui .button-primary:hover,body.ncu13-ui-controller .wp-core-ui .button-primary:focus,body.ncu13-ui-controller .components-button.is-primary:hover,body.ncu13-ui-controller .components-button.is-primary:focus{background:#303030!important;border-color:#303030!important;color:#fff!important}
@media(max-width:782px){body.wp-responsive-open #adminmenuback,body.wp-responsive-open #adminmenuwrap,body.wp-responsive-open #adminmenu{width:min(280px,88vw)!important;max-width:88vw!important}body.wp-responsive-open #adminmenuwrap,body.wp-responsive-open #adminmenuback{position:fixed!important;left:0!important;top:46px!important;bottom:0!important;height:auto!important;transform:none!important}body.wp-responsive-open #adminmenuwrap{overflow-x:hidden!important;overflow-y:auto!important;z-index:100000!important}body.ncu13-ui-controller #wpcontent,body.ncu13-ui-controller #wpfooter{margin-left:0!important;transform:none!important}body.ncu13-ui-controller #adminmenu>li>a.menu-top{display:grid!important;grid-template-columns:42px minmax(0,1fr)!important;align-items:center!important;min-height:48px!important;height:auto!important;padding:0!important}body.ncu13-ui-controller #adminmenu div.wp-menu-image{position:static!important;float:none!important;display:grid!important;place-items:center!important;width:42px!important;min-width:42px!important;height:48px!important;margin:0!important;padding:0!important}body.ncu13-ui-controller #adminmenu div.wp-menu-name{min-width:0!important;margin:0!important;padding:9px 12px 9px 3px!important;line-height:1.28!important;white-space:normal!important;overflow-wrap:anywhere!important}body.ncu13-ui-controller #adminmenu .wp-has-current-submenu>.wp-submenu,body.ncu13-ui-controller #adminmenu .wp-menu-open>.wp-submenu{position:static!important;width:100%!important;min-width:0!important;padding:4px 6px 6px 42px!important;box-sizing:border-box!important}body.ncu13-ui-controller #adminmenu .wp-submenu>li>a{display:block!important;width:100%!important;min-height:40px!important;height:auto!important;padding:10px 8px!important;line-height:1.25!important;white-space:normal!important;overflow-wrap:anywhere!important}}
</style><?php }


/* The 12.1.x companion controller is retired. Remove known hooks if it is still active. */
add_action( 'plugins_loaded', 'ncu13_ui_quarantine_legacy_companion', PHP_INT_MAX );
function ncu13_ui_quarantine_legacy_companion() {
    $actions = array(
        array( 'admin_menu', 'ncu12_ui_capture_menu_inventory', PHP_INT_MAX - 20 ),
        array( 'admin_menu', 'ncu12_ui_apply_menu_policy', PHP_INT_MAX ),
        array( 'admin_head', 'ncu12_ui_apply_menu_policy', 0 ),
        array( 'admin_menu', 'ncu12_ui_register_settings_page', 999 ),
        array( 'admin_menu', 'ncu12_ui_fallback_settings_page', 1000 ),
        array( 'admin_init', 'ncu12_ui_register_setting', 10 ),
        array( 'admin_init', 'ncu12_ui_handle_reset', 10 ),
        array( 'wp_dashboard_setup', 'ncu12_ui_filter_dashboard', PHP_INT_MAX ),
        array( 'admin_head-index.php', 'ncu12_ui_filter_dashboard', PHP_INT_MAX ),
        array( 'admin_enqueue_scripts', 'ncu12_ui_enqueue_assets', PHP_INT_MAX ),
        array( 'admin_footer', 'ncu12_ui_final_menu_contract', PHP_INT_MAX ),
    );
    foreach ( $actions as $spec ) {
        if ( function_exists( $spec[1] ) ) { remove_action( $spec[0], $spec[1], $spec[2] ); }
    }
    if ( function_exists( 'ncu12_ui_menu_order_enabled' ) ) { remove_filter( 'custom_menu_order', 'ncu12_ui_menu_order_enabled', 10 ); }
    if ( function_exists( 'ncu12_ui_menu_order' ) ) { remove_filter( 'menu_order', 'ncu12_ui_menu_order', PHP_INT_MAX ); }
    if ( function_exists( 'ncu12_ui_admin_body_class' ) ) { remove_filter( 'admin_body_class', 'ncu12_ui_admin_body_class', 10 ); }
}
