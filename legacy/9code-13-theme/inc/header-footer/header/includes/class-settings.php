<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ELHF_H_Settings {
    private static $instance;
    const OPTION = 'n9lh8_settings'; // Preserve the v8 option namespace.
    public static function instance(){ return self::$instance ?: ( self::$instance = new self() ); }

    private function __construct(){
        $this->upgrade();
        add_action( 'admin_init', [ $this, 'register' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
        add_action( 'admin_post_elhh_header_reset', [ $this, 'reset' ] );
    }

    public static function presets(){
        return [
            'authorfest_core'=>'01 AuthorFest Core','nine_native'=>'02 9 Code Native','classroom_console'=>'03 Classroom Console','lecture_player'=>'04 Lecture Player','academic_journal'=>'05 Academic Journal','glass_lab'=>'06 Glass Lab','blackboard'=>'07 Blackboard','paper_notebook'=>'08 Paper Notebook','campus_portal'=>'09 Campus Portal','minimal_white'=>'10 Minimal White','dark_studio'=>'11 Dark Studio','bento_campus'=>'12 Bento Campus','ribbon_academy'=>'13 Ribbon Academy','pill_control'=>'14 Pill Control','square_grid'=>'15 Square Grid','rounded_cards'=>'16 Rounded Cards','editorial_line'=>'17 Editorial Line','neon_tech'=>'18 Neon Tech','soft_pastel'=>'19 Soft Pastel','high_contrast'=>'20 High Contrast','monochrome'=>'21 Monochrome','newspaper'=>'22 Newspaper','dashboard'=>'23 Dashboard','mobile_app'=>'24 Mobile App','command_bar'=>'25 Command Bar','heritage_university'=>'26 Heritage University','research_lab'=>'27 Research Lab','creator_studio'=>'28 Creator Studio','learning_path'=>'29 Learning Path','author_spotlight'=>'30 Author Spotlight'
        ];
    }

    public static function palettes(){
        return [
            'authorfest_core'=>['#0B1F3A','#D71942','#FFFFFF','#F3F6FA','#111827','#D8DEE8'],
            'nine_native'=>['#111827','#2563EB','#FFFFFF','#F3F4F6','#111827','#D1D5DB'],
            'classroom_console'=>['#1D4ED8','#F59E0B','#FFFEF7','#EFF6FF','#172554','#CBD5E1'],
            'lecture_player'=>['#0B1020','#E11D48','#0F172A','#151C2E','#F8FAFC','#334155'],
            'academic_journal'=>['#3F2D20','#9A6B37','#FFFDF8','#F5F0E8','#241A14','#D8CFC2'],
            'glass_lab'=>['#164E63','#06B6D4','#F8FDFF','#E6F8FB','#102A33','#B6DDE4'],
            'blackboard'=>['#15241A','#EAB308','#15241A','#203428','#F7FFF8','#405848'],
            'paper_notebook'=>['#234E70','#D45B5B','#FFFEF9','#F2F7FC','#1F2937','#CCD7E2'],
            'campus_portal'=>['#173B63','#F59E0B','#FFFFFF','#EEF4F9','#152536','#CBD7E1'],
            'minimal_white'=>['#111827','#6B7280','#FFFFFF','#F9FAFB','#111827','#E5E7EB'],
            'dark_studio'=>['#111318','#7C3AED','#111318','#1B1E25','#F7F7F8','#343945'],
            'bento_campus'=>['#4338CA','#F97316','#FFFFFF','#F4F3FF','#1F2937','#D8D7EE'],
            'ribbon_academy'=>['#7C2D12','#DC2626','#FFF7ED','#FFEDD5','#431407','#FED7AA'],
            'pill_control'=>['#0F766E','#F59E0B','#FFFFFF','#ECFDF5','#134E4A','#A7F3D0'],
            'square_grid'=>['#1F2937','#E62117','#FFFFFF','#F3F4F6','#111827','#9CA3AF'],
            'rounded_cards'=>['#4F46E5','#EC4899','#FFFFFF','#F5F3FF','#1F2937','#DDD6FE'],
            'editorial_line'=>['#18181B','#B91C1C','#FFFEFC','#F5F5F4','#18181B','#D6D3D1'],
            'neon_tech'=>['#070A12','#00E5FF','#070A12','#0D1421','#F8FAFC','#263246'],
            'soft_pastel'=>['#6750A4','#E8709A','#FFFBFF','#F5EFFA','#2B2238','#E4D8EA'],
            'high_contrast'=>['#000000','#FFD400','#FFFFFF','#F4F4F4','#000000','#000000'],
            'monochrome'=>['#111111','#666666','#FFFFFF','#F3F3F3','#111111','#CCCCCC'],
            'newspaper'=>['#171717','#9F1239','#FFFCF7','#F5EFE5','#171717','#D6CEC3'],
            'dashboard'=>['#1E40AF','#0EA5E9','#FFFFFF','#EFF6FF','#172554','#BFDBFE'],
            'mobile_app'=>['#312E81','#F43F5E','#FFFFFF','#F5F3FF','#1F2937','#DDD6FE'],
            'command_bar'=>['#111827','#22C55E','#111827','#172033','#F9FAFB','#334155'],
            'heritage_university'=>['#6F1D2E','#B68A2E','#FFF9EC','#F5EBD4','#331018','#D9C8A2'],
            'research_lab'=>['#0F3D4C','#14B8A6','#FFFFFF','#ECFEFF','#15333B','#B6E4E1'],
            'creator_studio'=>['#5B21B6','#F43F5E','#FFFFFF','#FAF5FF','#2E1065','#E9D5FF'],
            'learning_path'=>['#1E3A8A','#F59E0B','#FFFFFF','#EFF6FF','#172554','#BFDBFE'],
            'author_spotlight'=>['#7C2D92','#F59E0B','#FFFFFF','#FAF5FF','#3B1746','#E9D5F2'],
        ];
    }


    public static function default_menu_builder(){
        // v8.14: create the preferred learning-navigation slots automatically,
        // but never populate them until an administrator explicitly selects a source.
        // This keeps placement predictable while preventing unwanted content from
        // appearing merely because a post type or taxonomy exists.
        return [
            ['uid'=>'lecturers','enabled'=>'yes','kind'=>'none','label'=>'Lecturers','icon'=>'lecturer','icon_url'=>'','nav_menu_id'=>0,'post_type'=>'','post_type_manual'=>'','taxonomy'=>'','scope'=>'all','limit'=>30,'sort'=>'title','links'=>[]],
            ['uid'=>'courses','enabled'=>'yes','kind'=>'none','label'=>'Courses','icon'=>'course','icon_url'=>'','nav_menu_id'=>0,'post_type'=>'','post_type_manual'=>'','taxonomy'=>'','scope'=>'all','limit'=>30,'sort'=>'title','links'=>[]],
            ['uid'=>'flyers','enabled'=>'yes','kind'=>'none','label'=>'Flyers','icon'=>'flyer','icon_url'=>'','nav_menu_id'=>0,'post_type'=>'','post_type_manual'=>'','taxonomy'=>'','scope'=>'all','limit'=>30,'sort'=>'newest','links'=>[]],
            ['uid'=>'workshops','enabled'=>'yes','kind'=>'none','label'=>'Workshops','icon'=>'workshop','icon_url'=>'','nav_menu_id'=>0,'post_type'=>'','post_type_manual'=>'','taxonomy'=>'','scope'=>'all','limit'=>30,'sort'=>'newest','links'=>[]],
            ['uid'=>'real-lectures','enabled'=>'yes','kind'=>'none','label'=>'Real Lectures','icon'=>'lecture','icon_url'=>'','nav_menu_id'=>0,'post_type'=>'','post_type_manual'=>'','taxonomy'=>'','scope'=>'all','limit'=>30,'sort'=>'newest','links'=>[]],
            ['uid'=>'live-lectures','enabled'=>'yes','kind'=>'none','label'=>'Live Lectures','icon'=>'live','icon_url'=>'','nav_menu_id'=>0,'post_type'=>'','post_type_manual'=>'','taxonomy'=>'','scope'=>'all','limit'=>30,'sort'=>'newest','links'=>[]],
        ];
    }
    public static function default_header_actions(){
        return [
            ['uid'=>'profile','enabled'=>'yes','kind'=>'profile','label'=>'Profile','icon'=>'user','icon_url'=>'','url'=>'','target'=>'same','event_key'=>''],
            ['uid'=>'whatsapp','enabled'=>'yes','kind'=>'whatsapp','label'=>'WhatsApp','icon'=>'chat','icon_url'=>'','url'=>'','target'=>'new','event_key'=>''],
            ['uid'=>'email','enabled'=>'yes','kind'=>'email','label'=>'Email','icon'=>'mail','icon_url'=>'','url'=>'','target'=>'same','event_key'=>''],
            ['uid'=>'menu','enabled'=>'yes','kind'=>'menu','label'=>'Menu','icon'=>'menu','icon_url'=>'','url'=>'','target'=>'same','event_key'=>''],
        ];
    }
    public static function section_kind_options(){
        return [
            'none'=>'Not configured — show nothing',
            'wp_menu'=>'Selected WordPress menu',
            'post_type'=>'Selected post type',
            'taxonomy'=>'Selected taxonomy',
            'manual_links'=>'Manual links',
            'video'=>'Video popup launcher',
            'form'=>'Form popup launcher'
        ];
    }
    public static function action_kind_options(){
        return [
            'profile'=>'Lecturer profile link','menu'=>'Open drawer','whatsapp'=>'Author WhatsApp','email'=>'Author Email','video'=>'Open Video popup','form'=>'Open Form popup',
            'home'=>'Homepage','login'=>'Login / Account','top'=>'Scroll to top','link'=>'Custom link','event'=>'Custom function/event key'
        ];
    }

    public static function defaults(){
        return [
            'settings_version'=>'8.16.0','enabled'=>'','sticky_header'=>'','fixed_top'=>'','suppress_theme_header'=>'','layer_level'=>20,'preset'=>'authorfest_core',
            'desktop_mode'=>'dropdown','mobile_mode'=>'drawer_right','animation'=>'slide','duration'=>160,'panel_width'=>600,'drawer_width'=>350,

            // Admin-controlled frontend dark appearance. No visitor-facing toggle is rendered.
            'dark_mode'=>'yes','dark_mode_strength'=>55,

            // Identity and the three closed-header bands.
            'show_logo'=>'yes','show_site_name'=>'yes','custom_site_name'=>'','custom_site_description'=>'','show_site_description'=>'yes','brand_logo_id'=>0,'brand_logo_url'=>'','logo_width'=>34,
            'header_height'=>56,'header_padding_v'=>5,'header_padding_h'=>10,'header_spacing'=>7,'meta_row_height'=>28,'taxonomy_row_height'=>28,
            'site_name_size'=>13,'subtitle_size'=>8,'meta_text_size'=>9,'taxonomy_label_size'=>8,'taxonomy_term_size'=>8,
            'site_name_lines'=>'two','subtitle_lines'=>'one','meta_overflow_mode'=>'scroll','taxonomy_overflow_mode'=>'scroll','truncate_series_text'=>'','truncate_taxonomy_text'=>'',
            'show_site_address'=>'yes','site_address_override'=>'','site_address_link'=>'','show_series_chips'=>'yes','series_chip_count'=>2,
            'show_taxonomy_strip'=>'yes','show_taxonomy_label'=>'yes','header_taxonomy'=>'category','header_taxonomy_label'=>'Subject','header_taxonomy_limit'=>8,'taxonomy_strip_mode'=>'auto','taxonomy_manual_items'=>[],

            // Header action controls.
            'show_author_button'=>'yes','show_whatsapp_button'=>'yes','show_email_button'=>'yes','show_menu_button'=>'yes',
            'profile_icon'=>'user','whatsapp_icon'=>'chat','email_icon'=>'mail','menu_icon'=>'menu','close_icon'=>'close',
            'action_button_size'=>38,'action_icon_size'=>28,'closed_action_style'=>'template','closed_icon_fg'=>'#111111','closed_icon_bg'=>'#FFFFFF','closed_icon_border'=>'#D1D5DB','closed_icon_hover_fg'=>'#FFFFFF','closed_icon_hover_bg'=>'#111111','closed_icon_radius'=>8,'header_actions'=>self::default_header_actions(),

            // Drawer section controls.
            'home_author_id'=>0,'show_engine_note'=>'','default_open_section'=>'author','show_section_icons'=>'yes',
            'show_author_section'=>'yes','show_pathway_section'=>'yes','show_finder_section'=>'yes','show_series_section'=>'yes',
            'author_label'=>'Author','pathway_label'=>'Course Pathway','finder_label'=>'Find Module & Lesson','series_label'=>'Series',
            'author_section_icon'=>'user','pathway_section_icon'=>'course','finder_section_icon'=>'search','series_section_icon'=>'series',
            'panel_padding'=>7,'panel_section_gap'=>4,'drawer_section_font_size'=>10,'drawer_item_font_size'=>9,'menu_builder'=>self::default_menu_builder(),'menu_default_open_uid'=>'lecturers',

            // Author section controls.
            'show_author_photo'=>'yes','show_author_title'=>'yes','show_author_qualification'=>'yes','show_author_organisation'=>'yes',
            'show_author_profile_link'=>'yes','show_author_whatsapp_link'=>'yes','show_author_email_link'=>'yes','show_author_contact_form'=>'',
            'author_profile_link_label'=>'Full profile','author_whatsapp_link_label'=>'WhatsApp','author_email_link_label'=>'Email',
            'contact_name_label'=>'Name','contact_email_label'=>'Email','contact_message_label'=>'Message','contact_submit_label'=>'Send message',

            // Data mapping: defaults follow provider, but every source is editable.
            'course_post_type'=>'nls_course','module_post_type'=>'nls_module','lesson_post_type'=>'nls_lesson',
            'course_item_label'=>'Course','module_item_label'=>'Module','lesson_item_label'=>'Lesson',
            'author_relation_meta'=>'nls_author_id','module_course_meta'=>'nls_course_id','lesson_module_meta'=>'nls_module_id',
            'series_source'=>'taxonomy','series_taxonomy'=>'category','series_post_type'=>'post','series_drawer_limit'=>30,
            'pathway_course_limit'=>12,'pathway_module_limit'=>12,'pathway_lesson_limit'=>8,'show_pathway_open_links'=>'yes','pathway_open_prefix'=>'Open',

            // Finder controls.
            'finder_result_limit'=>24,'finder_min_chars'=>2,'finder_placeholder'=>'Type 2+ letters…','finder_default_type'=>'all','finder_default_sort'=>'path',
            'finder_show_type_filter'=>'yes','finder_show_course_filter'=>'yes','finder_show_sort'=>'yes','finder_search_icon'=>'search',

            // Optional popups.
            'show_video_popup'=>'','video_icon'=>'video','video_popup_title'=>'Video','video_source'=>'url','video_url'=>'','video_shortcode'=>'',
            'show_form_popup'=>'','form_icon'=>'form','form_popup_title'=>'Contact','form_mode'=>'builtin','form_shortcode'=>'','popup_width'=>520,'popup_max_height'=>80,

            // Palette.
            'custom_palette'=>'','custom_header_bg'=>'#FFFFFF','custom_header_fg'=>'#111827','custom_panel_bg'=>'#FFFFFF','custom_panel_fg'=>'#111827',
            'custom_primary'=>'#0B1F3A','custom_primary_fg'=>'#FFFFFF','custom_accent'=>'#D71942','custom_accent_fg'=>'#FFFFFF',
            'custom_surface'=>'#FFFFFF','custom_surface_fg'=>'#111827','custom_soft'=>'#F3F6FA','custom_soft_fg'=>'#111827',
            'custom_text'=>'#111827','custom_text_fg'=>'#FFFFFF','custom_border'=>'#D8DEE8',
        ];
    }

    private function upgrade(){
        $s=get_option(self::OPTION,[]); if(!is_array($s)||!$s)return;
        $from=(string)($s['settings_version']??'8.0.0');
        if(version_compare($from,'8.16.0','>='))return;
        $pre8101=version_compare($from,'8.10.1','<');$pre813=version_compare($from,'8.13.0','<');$pre814=version_compare($from,'8.14.0','<');$pre815=version_compare($from,'8.15.0','<');$pre816=version_compare($from,'8.16.0','<');
        $hadSticky=array_key_exists('sticky_header',$s);$oldFixed=$s['fixed_top']??'yes';
        $d=self::defaults();
        foreach($d as $k=>$v) if(!array_key_exists($k,$s)) $s[$k]=$v;
        // v8.7 called this fixed_top. Preserve that exact old choice before adding v8.8 defaults.
        if(!$hadSticky)$s['sticky_header']=('yes'===$oldFixed)?'yes':'';
        $s['fixed_top']=$s['sticky_header']; // compatibility alias only.
        if(empty($s['menu_builder'])||!is_array($s['menu_builder'])){
            $builder=self::default_menu_builder();
            $legacy=['author'=>'show_author_section','pathway'=>'show_pathway_section','finder'=>'show_finder_section','series'=>'show_series_section'];
            foreach($builder as &$row){$row['enabled']=('yes'===($s[$legacy[$row['uid']]]??'yes'))?'yes':'';} unset($row);
            $s['menu_builder']=$builder;
        }
        if(empty($s['header_actions'])||!is_array($s['header_actions'])){
            $actions=self::default_header_actions();
            $legacy=['profile'=>'show_author_button','whatsapp'=>'show_whatsapp_button','email'=>'show_email_button','menu'=>'show_menu_button'];
            foreach($actions as &$row){$row['enabled']=('yes'===($s[$legacy[$row['uid']]]??'yes'))?'yes':'';} unset($row);
            if('yes'===($s['show_video_popup']??''))$actions[]=['uid'=>'video','enabled'=>'yes','kind'=>'video','label'=>'Video','icon'=>$s['video_icon']??'video','icon_url'=>'','url'=>'','target'=>'same','event_key'=>''];
            if('yes'===($s['show_form_popup']??''))$actions[]=['uid'=>'form','enabled'=>'yes','kind'=>'form','label'=>'Contact','icon'=>$s['form_icon']??'form','icon_url'=>'','url'=>'','target'=>'same','event_key'=>''];
            $s['header_actions']=$actions;
        }
        if(empty($s['menu_default_open_uid']))$s['menu_default_open_uid']=$s['default_open_section']??'author';
        // v8.13 product defaults: backend-controlled dark-first header, standalone closed
        // icons, no duplicate author contact form, and optional WordPress-menu override
        // on every drawer section. Existing automatic sources remain the fallback.
        if($pre813){
            $s['dark_mode']='yes';
            $s['action_button_size']=max(38,min(48,absint($s['action_button_size']??38)));
            $s['action_icon_size']=max(28,min(32,absint($s['action_icon_size']??28)));
            $s['show_author_contact_form']='';
            foreach((array)($s['menu_builder']??[]) as &$row){
                if(!is_array($row))continue;
                if(!isset($row['nav_menu_id']))$row['nav_menu_id']=0;
                if('author'===($row['kind']??''))$row['icon']='none';
            } unset($row);
        }
        // v8.14 changes drawer navigation to explicit-source control. The six
        // preferred learning slots are placed automatically in the editor, but
        // nothing renders until the administrator chooses a WordPress menu,
        // post type, taxonomy or manual links. Module/Lesson automatic sections
        // are intentionally removed from the default navigation.
        if($pre814){
            $legacyKinds=['author','pathway','finder','series'];
            $rows=(array)($s['menu_builder']??[]);
            $legacyStock=true;
            if(count($rows)!==4)$legacyStock=false;
            if($legacyStock){
                $uids=array_map(function($r){return is_array($r)?($r['uid']??''):'';},$rows);
                $legacyStock=($uids===['author','pathway','finder','series']);
            }
            if($legacyStock){
                $s['menu_builder']=self::default_menu_builder();
                $s['menu_default_open_uid']='lecturers';
            }else{
                foreach($rows as &$row){
                    if(!is_array($row))continue;
                    if(in_array($row['kind']??'', $legacyKinds, true)){
                        $row['kind']='none';
                        $row['nav_menu_id']=0;
                    }
                } unset($row);
                $s['menu_builder']=$rows?:self::default_menu_builder();
                $open=$s['menu_default_open_uid']??'';
                if(in_array($open,['author','pathway','finder','series'],true))$s['menu_default_open_uid']='lecturers';
            }
        }
        // v8.10.1 made old upgrades theme-friendly. Do not overwrite choices already
        // made by administrators on 8.10.1+ when migrating to the dark-mode release.
        if($pre8101){
            $s['sticky_header']='';
            $s['fixed_top']='';
            $s['suppress_theme_header']='';
        }
        if($pre815){
            // v8.15 established the master switch.
            $s['enabled']='';
        }
        if($pre816){
            // v8.16 repairs master-state persistence. Force one clean OFF state
            // during upgrade so older saved/enabled values cannot survive silently.
            $s['enabled']='';
        }
        $s['layer_level']=isset($s['layer_level'])?max(1,min(9999,absint($s['layer_level']))):20;
        $s['settings_version']='8.16.0';
        if(empty($s['header_taxonomy_label']))$s['header_taxonomy_label']='Subject';
        update_option(self::OPTION,$s,false);
    }

    public static function activate(){ if(!get_option(self::OPTION))update_option(self::OPTION,self::defaults(),false); }
    private static function normalize_runtime_layout($settings){
        $d=self::defaults();
        $s=wp_parse_args((array)$settings,$d);
        $ranges=[
            'header_height'=>[44,84],'header_padding_v'=>[0,16],'header_padding_h'=>[4,30],'header_spacing'=>[2,22],
            'meta_row_height'=>[22,42],'taxonomy_row_height'=>[22,42],'site_name_size'=>[10,22],'subtitle_size'=>[7,16],
            'meta_text_size'=>[7,15],'taxonomy_label_size'=>[7,15],'taxonomy_term_size'=>[7,15],
            'action_button_size'=>[32,48],'action_icon_size'=>[18,32],'panel_padding'=>[4,20],'panel_section_gap'=>[2,16],
            'drawer_section_font_size'=>[9,18],'drawer_item_font_size'=>[8,16],'duration'=>[0,400],'panel_width'=>[320,860],
            'drawer_width'=>[280,460],'popup_width'=>[280,900],'popup_max_height'=>[40,95],'layer_level'=>[1,9999]
        ];
        foreach($ranges as $key=>$range){$s[$key]=max($range[0],min($range[1],absint($s[$key]??$d[$key])));}
        $available=max(24,$s['header_height']-(2*$s['header_padding_v']));
        $s['logo_width']=max(24,min(48,$available,absint($s['logo_width']??$d['logo_width'])));
        $s['closed_icon_radius']=max(0,min(24,absint($s['closed_icon_radius']??$d['closed_icon_radius'])));
        return $s;
    }
    public static function all(){ return self::normalize_runtime_layout((array)get_option(self::OPTION,[])); }
    public static function get($key){$s=self::all();return $s[$key]??null;}
    public static function is_enabled(){ return 'yes' === (string) self::get('enabled'); }

    public function register(){register_setting('n9lh_group',self::OPTION,['sanitize_callback'=>[$this,'sanitize']]);}
    public function sanitize($in){
        $d=self::defaults();$in=is_array($in)?$in:[];$o=[];
        $checks=[
            'enabled','sticky_header','suppress_theme_header','show_logo','show_site_name','show_site_description','show_site_address','show_series_chips','show_taxonomy_strip','show_taxonomy_label',
            'show_author_button','show_whatsapp_button','show_email_button','show_menu_button','show_engine_note','show_section_icons','show_author_section','show_pathway_section','show_finder_section','show_series_section',
            'show_author_photo','show_author_title','show_author_qualification','show_author_organisation','show_author_profile_link','show_author_whatsapp_link','show_author_email_link','show_author_contact_form',
            'show_pathway_open_links','finder_show_type_filter','finder_show_course_filter','finder_show_sort','show_video_popup','show_form_popup','custom_palette','truncate_series_text','truncate_taxonomy_text','dark_mode'
        ];
        foreach($checks as $k)$o[$k]=(isset($in[$k])&&'yes'===$in[$k])?'yes':'';
        // Do not permit a configuration with no way to open the drawer.
        if('yes'!==$o['show_menu_button']&&'yes'!==$o['show_author_button'])$o['show_menu_button']='yes';
        $o['fixed_top']=$o['sticky_header']; // old compatibility alias.
        $o['settings_version']='8.16.0';
        $preset=$in['preset']??$d['preset'];$o['preset']=array_key_exists($preset,self::presets())?$preset:$d['preset'];
        $desktop=$in['desktop_mode']??$d['desktop_mode'];$o['desktop_mode']=in_array($desktop,['dropdown','drawer_left','drawer_right'],true)?$desktop:$d['desktop_mode'];
        $mobile=$in['mobile_mode']??$d['mobile_mode'];$o['mobile_mode']=in_array($mobile,['dropdown','drawer_left','drawer_right'],true)?$mobile:$d['mobile_mode'];
        $anim=$in['animation']??$d['animation'];$o['animation']=in_array($anim,['slide','fade','scale','none'],true)?$anim:$d['animation'];
        $nameLines=$in['site_name_lines']??$d['site_name_lines'];$o['site_name_lines']=in_array($nameLines,['one','two','auto'],true)?$nameLines:$d['site_name_lines'];
        $subLines=$in['subtitle_lines']??$d['subtitle_lines'];$o['subtitle_lines']=in_array($subLines,['one','two','auto'],true)?$subLines:$d['subtitle_lines'];
        $metaOverflow=$in['meta_overflow_mode']??$d['meta_overflow_mode'];$o['meta_overflow_mode']=in_array($metaOverflow,['scroll','wrap'],true)?$metaOverflow:$d['meta_overflow_mode'];
        $taxOverflow=$in['taxonomy_overflow_mode']??$d['taxonomy_overflow_mode'];$o['taxonomy_overflow_mode']=in_array($taxOverflow,['scroll','wrap'],true)?$taxOverflow:$d['taxonomy_overflow_mode'];
        $stripMode=$in['taxonomy_strip_mode']??$d['taxonomy_strip_mode'];$o['taxonomy_strip_mode']=in_array($stripMode,['auto','manual','mixed'],true)?$stripMode:$d['taxonomy_strip_mode'];
        $actionStyle=$in['closed_action_style']??$d['closed_action_style'];$o['closed_action_style']=in_array($actionStyle,['template','black_on_white','white_on_black','custom'],true)?$actionStyle:$d['closed_action_style'];
        $open=$in['default_open_section']??$d['default_open_section'];$o['default_open_section']=in_array($open,['author','pathway','finder','series','none'],true)?$open:$d['default_open_section'];
        $ft=$in['finder_default_type']??$d['finder_default_type'];$o['finder_default_type']=in_array($ft,['all','module','lesson'],true)?$ft:$d['finder_default_type'];
        $fs=$in['finder_default_sort']??$d['finder_default_sort'];$o['finder_default_sort']=in_array($fs,['path','title','newest'],true)?$fs:$d['finder_default_sort'];

        foreach([
            'custom_site_name','custom_site_description','site_address_override','author_label','pathway_label','finder_label','series_label','header_taxonomy_label',
            'author_relation_meta','module_course_meta','lesson_module_meta','course_item_label','module_item_label','lesson_item_label','pathway_open_prefix',
            'author_profile_link_label','author_whatsapp_link_label','author_email_link_label','contact_name_label','contact_email_label','contact_message_label','contact_submit_label',
            'finder_placeholder','video_popup_title','form_popup_title','menu_default_open_uid'
        ] as $k)$o[$k]=sanitize_text_field($in[$k]??$d[$k]);
        foreach(['brand_logo_url','video_url','site_address_link'] as $k)$o[$k]=esc_url_raw($in[$k]??$d[$k]);
        foreach(['video_shortcode','form_shortcode'] as $k)$o[$k]=sanitize_textarea_field($in[$k]??$d[$k]);

        $o['brand_logo_id']=absint($in['brand_logo_id']??0);$o['logo_width']=max(24,min(48,absint($in['logo_width']??34)));
        foreach([
            'header_height'=>[44,84],'header_padding_v'=>[0,16],'header_padding_h'=>[4,30],'header_spacing'=>[2,22],'meta_row_height'=>[22,42],'taxonomy_row_height'=>[22,42],
            'site_name_size'=>[10,22],'subtitle_size'=>[7,16],'meta_text_size'=>[7,15],'taxonomy_label_size'=>[7,15],'taxonomy_term_size'=>[7,15],
            'action_button_size'=>[32,48],'action_icon_size'=>[18,32],'panel_padding'=>[4,20],'panel_section_gap'=>[2,16],'drawer_section_font_size'=>[9,18],'drawer_item_font_size'=>[8,16],
            'duration'=>[0,400],'panel_width'=>[320,860],'drawer_width'=>[280,460],'series_chip_count'=>[0,2],'header_taxonomy_limit'=>[1,20],
            'pathway_course_limit'=>[1,30],'pathway_module_limit'=>[1,40],'pathway_lesson_limit'=>[1,40],'series_drawer_limit'=>[1,100],'finder_result_limit'=>[6,50],'finder_min_chars'=>[1,5],
            'popup_width'=>[280,900],'popup_max_height'=>[40,95],'layer_level'=>[1,9999],'dark_mode_strength'=>[0,100]
        ] as $k=>$range)$o[$k]=max($range[0],min($range[1],absint($in[$k]??$d[$k])));
        $o['home_author_id']=absint($in['home_author_id']??0);

        $taxes=array_keys(self::taxonomy_options());
        $fallback_tax=self::default_available_taxonomy($d['header_taxonomy']);
        $fallback_series=self::default_available_taxonomy($d['series_taxonomy']);
        $tax=sanitize_key($in['header_taxonomy']??$fallback_tax);$o['header_taxonomy']=in_array($tax,$taxes,true)?$tax:$fallback_tax;
        $series_tax=sanitize_key($in['series_taxonomy']??$fallback_series);$o['series_taxonomy']=in_array($series_tax,$taxes,true)?$series_tax:$fallback_series;
        foreach(['course_post_type','module_post_type','lesson_post_type','series_post_type'] as $k){$v=sanitize_key($in[$k]??$d[$k]);$o[$k]=$v?:$d[$k];}
        $ss=$in['series_source']??'taxonomy';$o['series_source']=in_array($ss,['taxonomy','post_type'],true)?$ss:'taxonomy';

        $icons=array_keys(self::icon_options());
        foreach(['profile_icon','whatsapp_icon','email_icon','menu_icon','close_icon','author_section_icon','pathway_section_icon','finder_section_icon','series_section_icon','finder_search_icon','video_icon','form_icon'] as $k){$v=sanitize_key($in[$k]??$d[$k]);$o[$k]=in_array($v,$icons,true)?$v:$d[$k];}
        $vs=$in['video_source']??'url';$o['video_source']=in_array($vs,['url','shortcode'],true)?$vs:'url';
        $fm=$in['form_mode']??'builtin';$o['form_mode']=in_array($fm,['builtin','shortcode'],true)?$fm:'builtin';
        foreach(['custom_header_bg','custom_header_fg','custom_panel_bg','custom_panel_fg','custom_primary','custom_primary_fg','custom_accent','custom_accent_fg','custom_surface','custom_surface_fg','custom_soft','custom_soft_fg','custom_text','custom_text_fg','custom_border','closed_icon_fg','closed_icon_bg','closed_icon_border','closed_icon_hover_fg','closed_icon_hover_bg'] as $k)$o[$k]=sanitize_hex_color($in[$k]??$d[$k])?:$d[$k];
        $o['closed_icon_radius']=max(0,min(24,absint($in['closed_icon_radius']??$d['closed_icon_radius'])));
        $o['menu_builder']=$this->sanitize_menu_builder($in['menu_builder']??($d['menu_builder']??[]));
        $o['header_actions']=$this->sanitize_header_actions($in['header_actions']??($d['header_actions']??[]));
        $o['taxonomy_manual_items']=$this->sanitize_taxonomy_manual_items($in['taxonomy_manual_items']??[]);
        $enabledOpeners=array_filter($o['header_actions'],function($r){return 'yes'===($r['enabled']??'')&&'menu'===($r['kind']??'');});
        if(!$enabledOpeners)$o['header_actions'][]=['uid'=>'menu-safety','enabled'=>'yes','kind'=>'menu','label'=>'Menu','icon'=>'menu','icon_url'=>'','url'=>'','target'=>'same','event_key'=>''];
        return wp_parse_args($o,$d);
    }

    private function clean_uid($v,$prefix='item'){$v=sanitize_key((string)$v);return $v?:$prefix.'-'.wp_generate_password(6,false,false);}
    private function sanitize_menu_builder($rows){
        $out=[];$kinds=array_keys(self::section_kind_options());$icons=array_keys(self::icon_options());$taxes=array_keys(self::taxonomy_options());
        foreach(array_slice((array)$rows,0,16) as $row){if(!is_array($row))continue;$kind=sanitize_key($row['kind']??'none');if(!in_array($kind,$kinds,true))$kind='none';$pt=sanitize_key($row['post_type']??'');$manual=sanitize_key($row['post_type_manual']??'');if('__manual__'===$pt)$pt=$manual;$tax=sanitize_key($row['taxonomy']??'');if($tax&&!in_array($tax,$taxes,true))$tax='';$icon=sanitize_key($row['icon']??'series');if(!in_array($icon,$icons,true))$icon='series';$scopeRaw=$row['scope']??'author';$scope=in_array($scopeRaw,['author','all'],true)?$scopeRaw:'author';$sortRaw=$row['sort']??'newest';$sort=in_array($sortRaw,['newest','title','menu'],true)?$sortRaw:'newest';$links=[];foreach(array_slice((array)($row['links']??[]),0,12) as $ln){if(!is_array($ln))continue;$text=sanitize_text_field($ln['text']??'');$url=esc_url_raw($ln['url']??'');if(''===$text&&''===$url)continue;$links[]=['text'=>$text,'url'=>$url,'target'=>(function($v){return in_array($v,['same','new'],true)?$v:'same';})($ln['target']??'same')];}
            $out[]=['uid'=>$this->clean_uid($row['uid']??'','section'),'enabled'=>isset($row['enabled'])?'yes':'','kind'=>$kind,'label'=>sanitize_text_field($row['label']??''),'icon'=>$icon,'icon_url'=>esc_url_raw($row['icon_url']??''),'nav_menu_id'=>absint($row['nav_menu_id']??0),'post_type'=>$pt,'post_type_manual'=>$manual,'taxonomy'=>$tax,'scope'=>$scope,'limit'=>max(1,min(100,absint($row['limit']??20))),'sort'=>$sort,'links'=>$links];}
        return $out?:self::default_menu_builder();
    }
    private function sanitize_header_actions($rows){
        $out=[];$kinds=array_keys(self::action_kind_options());$icons=array_keys(self::icon_options());
        foreach(array_slice((array)$rows,0,12) as $row){if(!is_array($row))continue;$kind=sanitize_key($row['kind']??'link');if(!in_array($kind,$kinds,true))$kind='link';$icon=sanitize_key($row['icon']??'menu');if(!in_array($icon,$icons,true))$icon='menu';$out[]=['uid'=>$this->clean_uid($row['uid']??'','action'),'enabled'=>isset($row['enabled'])?'yes':'','kind'=>$kind,'label'=>sanitize_text_field($row['label']??''),'icon'=>$icon,'icon_url'=>esc_url_raw($row['icon_url']??''),'url'=>esc_url_raw($row['url']??''),'target'=>(function($v){return in_array($v,['same','new'],true)?$v:'same';})($row['target']??'same'),'event_key'=>sanitize_key($row['event_key']??'')];}
        return $out?:self::default_header_actions();
    }
    private function sanitize_taxonomy_manual_items($rows){
        $out=[];foreach(array_slice((array)$rows,0,20) as $row){if(!is_array($row))continue;$kind=in_array(($row['kind']??'manual'),['manual','term'],true)?$row['kind']:'manual';$termRef=sanitize_text_field($row['term_ref']??'');$text=sanitize_text_field($row['text']??'');$url=esc_url_raw($row['url']??'');if('manual'===$kind&&''===$text&&''===$url)continue;if('term'===$kind&&!preg_match('/^[a-z0-9_\-]+\|\d+$/',$termRef))continue;$out[]=['kind'=>$kind,'term_ref'=>$termRef,'text'=>$text,'url'=>$url,'target'=>(function($v){return in_array($v,['same','new'],true)?$v:'same';})($row['target']??'same')];}return $out;
    }

    public static function taxonomy_options(){
        $opts=[];
        if(function_exists('get_taxonomies')){
            foreach((array)get_taxonomies(['public'=>true],'objects') as $tax){
                if(!is_object($tax)||empty($tax->name))continue;
                $label=!empty($tax->labels->singular_name)?$tax->labels->singular_name:$tax->name;
                $opts[$tax->name]=$label.' ('.$tax->name.')';
            }
        }
        // Very defensive fallback for unusual admin/bootstrap contexts.
        if(!$opts){$opts=['category'=>'Category (category)','post_tag'=>'Tag (post_tag)'];}
        asort($opts,SORT_NATURAL|SORT_FLAG_CASE);
        return $opts;
    }
    public static function taxonomy_groups(){
        $author=[];$site=[];
        foreach(self::taxonomy_options() as $slug=>$label){
            if(0===strpos($slug,'nls_author_'))$author[$slug]=$label;else $site[$slug]=$label;
        }
        return ['provider taxonomies'=>$author,'Other available taxonomies'=>$site];
    }
    private static function default_available_taxonomy($preferred=''){
        $opts=self::taxonomy_options();
        if($preferred&&isset($opts[$preferred]))return $preferred;
        foreach(['category','post_tag','category'] as $candidate)if(isset($opts[$candidate]))return $candidate;
        $keys=array_keys($opts);return $keys?(string)$keys[0]:'';
    }
    public static function resolve_taxonomy($preferred=''){return self::default_available_taxonomy((string)$preferred);}
    public static function post_type_options(){
        $opts=['nls_course'=>'author Course (nls_course)','nls_module'=>'author Module (nls_module)','nls_lesson'=>'author Lesson (nls_lesson)'];
        if(function_exists('get_post_types'))foreach((array)get_post_types(['public'=>true],'objects') as $pt){if(!is_object($pt)||empty($pt->name)||'attachment'===$pt->name)continue;$label=!empty($pt->labels->singular_name)?$pt->labels->singular_name:$pt->name;$opts[$pt->name]=$label.' ('.$pt->name.')';}
        asort($opts,SORT_NATURAL|SORT_FLAG_CASE);return $opts;
    }
    public static function nav_menu_options(){
        $opts=[0=>'— Select WordPress menu —'];
        if(function_exists('wp_get_nav_menus')){
            foreach((array)wp_get_nav_menus() as $menu){
                if(!is_object($menu)||empty($menu->term_id))continue;
                $name=!empty($menu->name)?$menu->name:('Menu '.$menu->term_id);
                $opts[(int)$menu->term_id]=$name;
            }
        }
        return $opts;
    }
    private function builder_nav_menu_select($name,$selected){
        $this->raw_select($name,absint($selected),self::nav_menu_options());
    }

    public static function icon_options(){
        return ['none'=>'No icon','user'=>'Person outline','user_circle'=>'Person in circle','lecturer'=>'Lecturer/person','badge'=>'Profile badge','chat'=>'Chat bubble','phone'=>'Phone','mail'=>'Envelope','at'=>'@ symbol','menu'=>'Hamburger','grid'=>'Grid menu','dots'=>'Three dots','search'=>'Search','close'=>'Close/X','course'=>'Course/book','flyer'=>'Flyer/document','workshop'=>'Workshop/calendar','lecture'=>'Real lecture/play','live'=>'Live lecture/broadcast','series'=>'Series/list','video'=>'Video camera','play'=>'Play circle','form'=>'Form/document','edit'=>'Edit/pencil','clipboard'=>'Clipboard'];
    }

    private function slider($k,$label,$s,$min,$max,$unit='px'){$v=absint($s[$k]??$min);echo '<label class="n9lh-slider"><span>'.esc_html($label).' <output data-slider-out="'.esc_attr($k).'">'.esc_html($v.$unit).'</output></span><input type="range" min="'.absint($min).'" max="'.absint($max).'" step="1" name="'.esc_attr(self::OPTION).'['.esc_attr($k).']" value="'.esc_attr($v).'" data-slider="'.esc_attr($k).'" data-unit="'.esc_attr($unit).'"></label>';}
    private function dark_mode_slider($s){$v=max(0,min(100,absint($s['dark_mode_strength']??55)));echo '<label class="n9lh-slider n9lh-dark-slider"><span>Dark effect strength <output data-slider-out="dark_mode_strength" data-dark-strength-out>'.esc_html($v.'%').'</output></span><input type="range" min="0" max="100" step="1" name="'.esc_attr(self::OPTION).'[dark_mode_strength]" value="'.esc_attr($v).'" data-slider="dark_mode_strength" data-unit="%" data-dark-strength><small data-dark-strength-name>Balanced dark</small></label>';}
    public function menu(){}
    public function admin_assets($hook){if('toplevel_page_elearning-click-header-footer'===$hook)wp_enqueue_media();}
    private function check($k,$label,$s){echo '<label class="n9lh-switch"><input type="checkbox" name="'.esc_attr(self::OPTION).'['.esc_attr($k).']" value="yes" '.checked('yes',$s[$k]??'',false).'><span class="n9lh-switch-track" aria-hidden="true"></span><b>'.esc_html($label).'</b></label>';}
    private function field($k,$label,$s,$type='text',$min=null,$max=null){echo '<label><span>'.esc_html($label).'</span><input type="'.esc_attr($type).'" name="'.esc_attr(self::OPTION).'['.esc_attr($k).']" value="'.esc_attr($s[$k]??'').'"'.($min!==null?' min="'.esc_attr($min).'"':'').($max!==null?' max="'.esc_attr($max).'"':'').'></label>';}
    private function textarea($k,$label,$s){echo '<label><span>'.esc_html($label).'</span><textarea name="'.esc_attr(self::OPTION).'['.esc_attr($k).']" rows="3">'.esc_textarea($s[$k]??'').'</textarea></label>';}
    private function select($k,$label,$s,$opts){echo '<label><span>'.esc_html($label).'</span><select name="'.esc_attr(self::OPTION).'['.esc_attr($k).']">';foreach($opts as $v=>$lab)echo '<option value="'.esc_attr($v).'" '.selected($s[$k]??'',$v,false).'>'.esc_html($lab).'</option>';echo '</select></label>';}
    private function taxonomy_select($k,$label,$s){$groups=self::taxonomy_groups();$selected=$s[$k]??self::default_available_taxonomy();echo '<label><span>'.esc_html($label).'</span><select name="'.esc_attr(self::OPTION).'['.esc_attr($k).']">';foreach($groups as $group=>$opts){if(!$opts)continue;echo '<optgroup label="'.esc_attr($group).'">';foreach($opts as $v=>$lab)echo '<option value="'.esc_attr($v).'" '.selected($selected,$v,false).'>'.esc_html($lab).'</option>';echo '</optgroup>';}echo '</select><small>Select from taxonomies currently available on this site. No slug lookup required.</small></label>';}
    private function post_type_field($k,$label,$s){$list='n9lh-pt-'.sanitize_key($k);echo '<label><span>'.esc_html($label).'</span><input type="text" list="'.esc_attr($list).'" name="'.esc_attr(self::OPTION).'['.esc_attr($k).']" value="'.esc_attr($s[$k]??'').'" placeholder="post_type_slug"><datalist id="'.esc_attr($list).'">';foreach(self::post_type_options() as $v=>$lab)echo '<option value="'.esc_attr($v).'">'.esc_html($lab).'</option>';echo '</datalist><small>Type any custom post type slug or choose a suggestion.</small></label>';}

    public static function term_option_groups(){
        static $cache=null;if(null!==$cache)return $cache;$groups=[];
        foreach(self::taxonomy_options() as $tax=>$label){
            if(!taxonomy_exists($tax))continue;
            $terms=get_terms(['taxonomy'=>$tax,'hide_empty'=>false,'number'=>100,'orderby'=>'name','order'=>'ASC']);
            if(is_wp_error($terms)||!$terms)continue;
            foreach($terms as $term)$groups[$label][$tax.'|'.$term->term_id]=$term->name;
        }
        $cache=$groups;return $groups;
    }
    private function raw_select($name,$selected,$opts,$extra=''){
        echo '<select name="'.esc_attr($name).'" '.$extra.'>';foreach($opts as $v=>$lab)echo '<option value="'.esc_attr($v).'" '.selected($selected,$v,false).'>'.esc_html($lab).'</option>';echo '</select>';
    }
    private function builder_post_type_select($name,$selected,$manual=''){
        $opts=self::post_type_options();echo '<select name="'.esc_attr($name).'" data-builder-pt><option value="">— Select post type —</option>';foreach($opts as $v=>$lab)echo '<option value="'.esc_attr($v).'" '.selected($selected,$v,false).'>'.esc_html($lab).'</option>';echo '<option value="__manual__" '.selected(!isset($opts[$selected])&&$selected?'__manual__':'','__manual__',false).'>Manual / temporarily inactive slug…</option></select><input type="text" name="'.esc_attr(preg_replace('/\[post_type\]$/','[post_type_manual]',$name)).'" value="'.esc_attr($manual?:(!isset($opts[$selected])?$selected:'')).'" placeholder="custom_post_type" data-builder-pt-manual>';
    }
    private function builder_taxonomy_select($name,$selected){
        echo '<select name="'.esc_attr($name).'" data-builder-tax><option value="">— Select taxonomy —</option>';foreach(self::taxonomy_groups() as $group=>$opts){if(!$opts)continue;echo '<optgroup label="'.esc_attr($group).'">';foreach($opts as $v=>$lab)echo '<option value="'.esc_attr($v).'" '.selected($selected,$v,false).'>'.esc_html($lab).'</option>';echo '</optgroup>';}echo '</select>';
    }
    private function icon_select_raw($name,$selected){$this->raw_select($name,$selected,self::icon_options());}
    private function menu_builder_ui($s){
        $rows=(array)($s['menu_builder']??self::default_menu_builder());
        echo '<div class="n9lh-builder" data-menu-builder>';
        foreach($rows as $i=>$r)$this->menu_builder_row($i,$r,$s);
        echo '</div><button type="button" class="button button-primary" data-add-menu-section>+ Add dropdown section</button>';
    }
    private function menu_builder_row($i,$r,$s){$base=self::OPTION.'[menu_builder]['.$i.']';$uid=$r['uid']??('section-'.$i);$links=(array)($r['links']??[]);
        echo '<div class="n9lh-builder-row" data-builder-row><div class="n9lh-builder-head"><span class="drag">↕</span><strong data-row-title>'.esc_html($r['label']?:ucwords(str_replace('_',' ',$r['kind']??'section'))).'</strong><span class="builder-actions"><button type="button" class="button" data-move-up>↑</button><button type="button" class="button" data-move-down>↓</button><button type="button" class="button-link-delete" data-remove-row>Remove</button></span></div><div class="n9lh-builder-grid">';
        echo '<input type="hidden" name="'.esc_attr($base.'[uid]').'" value="'.esc_attr($uid).'">';
        echo '<label class="mini-check"><input type="checkbox" name="'.esc_attr($base.'[enabled]').'" value="yes" '.checked('yes',$r['enabled']??'',false).'> ON</label>';
        echo '<label><span>Content source</span>'; $this->raw_select($base.'[kind]',$r['kind']??'none',self::section_kind_options(),'data-section-kind'); echo '<small>Nothing is shown until you explicitly select a source.</small></label>';
        echo '<label><span>Display text</span><input type="text" name="'.esc_attr($base.'[label]').'" value="'.esc_attr($r['label']??'').'" data-row-label></label>';
        echo '<label class="n9lh-menu-allocation"><span>WordPress menu</span>'; $this->builder_nav_menu_select($base.'[nav_menu_id]',$r['nav_menu_id']??0); echo '<small>Used only when Content source is Selected WordPress menu.</small></label>';
        echo '<label><span>Built-in icon</span>'; $this->icon_select_raw($base.'[icon]',$r['icon']??'series'); echo '</label>';
        echo '<label><span>Custom icon image URL <small>(overrides built-in)</small></span><span class="inline-media"><input type="url" name="'.esc_attr($base.'[icon_url]').'" value="'.esc_attr($r['icon_url']??'').'" data-media-url><button type="button" class="button" data-pick-media>Choose</button></span></label>';
        echo '<label><span>Post type source</span>'; $this->builder_post_type_select($base.'[post_type]',$r['post_type']??'',$r['post_type_manual']??''); echo '</label>';
        echo '<label><span>Taxonomy source</span>'; $this->builder_taxonomy_select($base.'[taxonomy]',$r['taxonomy']??''); echo '</label>';
        echo '<label><span>Content scope</span>'; $this->raw_select($base.'[scope]',$r['scope']??'author',['author'=>'Current Author where possible','all'=>'All published items']); echo '</label>';
        echo '<label><span>Maximum items</span><input type="number" min="1" max="100" name="'.esc_attr($base.'[limit]').'" value="'.absint($r['limit']??20).'"></label>';
        echo '<label><span>Sort</span>'; $this->raw_select($base.'[sort]',$r['sort']??'newest',['newest'=>'Newest','title'=>'A–Z','menu'=>'Menu order']); echo '</label>';
        echo '<label class="default-open"><input type="radio" name="'.esc_attr(self::OPTION).'[menu_default_open_uid]" value="'.esc_attr($uid).'" '.checked($s['menu_default_open_uid']??'',$uid,false).'> Open this section by default</label>';
        echo '</div><div class="manual-links"><strong>Manual links for this section</strong><div data-link-list>';
        foreach($links as $j=>$ln)$this->manual_link_row($base,$j,$ln);
        echo '</div><button type="button" class="button" data-add-manual-link>+ Add manual link</button></div></div>';
    }
    private function manual_link_row($base,$j,$ln){$lb=$base.'[links]['.$j.']';echo '<div class="manual-link-row" data-link-row><input type="text" name="'.esc_attr($lb.'[text]').'" value="'.esc_attr($ln['text']??'').'" placeholder="Link text"><input type="url" name="'.esc_attr($lb.'[url]').'" value="'.esc_attr($ln['url']??'').'" placeholder="https://…">';$this->raw_select($lb.'[target]',$ln['target']??'same',['same'=>'Same tab','new'=>'New tab']);echo '<button type="button" class="button-link-delete" data-remove-link>×</button></div>';}
    private function header_actions_ui($s){$rows=(array)($s['header_actions']??self::default_header_actions());echo '<div class="n9lh-builder" data-action-builder>';foreach($rows as $i=>$r)$this->header_action_row($i,$r);echo '</div><button type="button" class="button button-primary" data-add-header-action>+ Add closed-header icon/action</button>';}
    private function header_action_row($i,$r){$base=self::OPTION.'[header_actions]['.$i.']';$uid=$r['uid']??('action-'.$i);echo '<div class="n9lh-builder-row" data-action-row><div class="n9lh-builder-head"><span class="drag">↕</span><strong data-action-title>'.esc_html($r['label']?:ucwords($r['kind']??'action')).'</strong><span class="builder-actions"><button type="button" class="button" data-move-up>↑</button><button type="button" class="button" data-move-down>↓</button><button type="button" class="button-link-delete" data-remove-row>Remove</button></span></div><div class="n9lh-builder-grid">';echo '<input type="hidden" name="'.esc_attr($base.'[uid]').'" value="'.esc_attr($uid).'">';echo '<label class="mini-check"><input type="checkbox" name="'.esc_attr($base.'[enabled]').'" value="yes" '.checked('yes',$r['enabled']??'',false).'> ON</label>';echo '<label><span>Function</span>';$this->raw_select($base.'[kind]',$r['kind']??'link',self::action_kind_options());echo '</label>';echo '<label><span>Accessible label</span><input type="text" name="'.esc_attr($base.'[label]').'" value="'.esc_attr($r['label']??'').'" data-action-label></label>';echo '<label><span>Built-in icon</span>';$this->icon_select_raw($base.'[icon]',$r['icon']??'menu');echo '</label>';echo '<label><span>Custom icon image URL <small>(overrides built-in)</small></span><span class="inline-media"><input type="url" name="'.esc_attr($base.'[icon_url]').'" value="'.esc_attr($r['icon_url']??'').'" data-media-url><button type="button" class="button" data-pick-media>Choose</button></span></label>';echo '<label><span>Custom link URL <small>(for Custom link)</small></span><input type="url" name="'.esc_attr($base.'[url]').'" value="'.esc_attr($r['url']??'').'"></label>';echo '<label><span>Link target</span>';$this->raw_select($base.'[target]',$r['target']??'same',['same'=>'Same tab','new'=>'New tab']);echo '</label>';echo '<label><span>Custom event/function key <small>(advanced safe hook)</small></span><input type="text" name="'.esc_attr($base.'[event_key]').'" value="'.esc_attr($r['event_key']??'').'" placeholder="my_action"></label>';echo '</div></div>';}
    private function taxonomy_manual_ui($s){$rows=(array)($s['taxonomy_manual_items']??[]);echo '<div class="n9lh-builder" data-tax-items-builder>';foreach($rows as $i=>$r)$this->taxonomy_manual_row($i,$r);echo '</div><template data-tax-item-template>'; $this->taxonomy_manual_row(999,['kind'=>'manual','term_ref'=>'','text'=>'','url'=>'','target'=>'same']); echo '</template><button type="button" class="button" data-add-tax-item>+ Add manual text/link or specific category/term</button>'; }
    private function taxonomy_manual_row($i,$r){$base=self::OPTION.'[taxonomy_manual_items]['.$i.']';echo '<div class="manual-tax-row" data-tax-item-row><span class="drag">↕</span><select name="'.esc_attr($base.'[kind]').'" data-tax-item-kind><option value="manual" '.selected($r['kind']??'manual','manual',false).'>Manual text / link</option><option value="term" '.selected($r['kind']??'','term',false).'>Specific category / taxonomy term</option></select><input type="text" name="'.esc_attr($base.'[text]').'" value="'.esc_attr($r['text']??'').'" placeholder="Manual text"><input type="url" name="'.esc_attr($base.'[url]').'" value="'.esc_attr($r['url']??'').'" placeholder="Optional URL"><select name="'.esc_attr($base.'[term_ref]').'"><option value="">— Choose specific term —</option>';foreach(self::term_option_groups() as $group=>$terms){echo '<optgroup label="'.esc_attr($group).'">';foreach($terms as $v=>$lab)echo '<option value="'.esc_attr($v).'" '.selected($r['term_ref']??'',$v,false).'>'.esc_html($lab).'</option>';echo '</optgroup>';}echo '</select>';$this->raw_select($base.'[target]',$r['target']??'same',['same'=>'Same tab','new'=>'New tab']);echo '<span class="builder-actions"><button type="button" class="button" data-move-up>↑</button><button type="button" class="button" data-move-down>↓</button><button type="button" class="button-link-delete" data-remove-row>Remove</button></span></div>';}

    public function page(){
        if(!current_user_can('manage_options'))return;
        $s=self::all();$logo='';if($s['brand_logo_id'])$logo=wp_get_attachment_image_url((int)$s['brand_logo_id'],'thumbnail');if(!$logo)$logo=$s['brand_logo_url']?:get_site_icon_url(96);
        ?>
        <div class="wrap n9lh-admin"><h1>Header Settings</h1>
        <form id="n9lh-settings-form" method="post" action="options.php"><?php settings_fields('n9lh_group'); ?>
        <div class="elhh-master-switch"><div><strong>Header</strong><span>OFF means no Theme header output. Turn it on only when the Theme should own the public header.</span></div><label class="elhh-switch"><input type="hidden" name="<?php echo esc_attr(self::OPTION);?>[enabled]" value=""><input type="checkbox" name="<?php echo esc_attr(self::OPTION);?>[enabled]" value="yes" <?php checked('yes',$s['enabled']??''); ?>><span aria-hidden="true"></span><b><?php echo 'yes'===($s['enabled']??'')?'ON':'OFF'; ?></b></label></div>
        <p><strong>Master state is authoritative.</strong> When Header is OFF, no Header renderer, fallback, compatibility asset, or plugin bridge may turn it back on.</p>
        <div class="notice notice-info inline"><p><strong>Builder rule:</strong> add, remove, rename and move sections with the ↑/↓ controls. Post-type and taxonomy selectors are regenerated from what is currently registered on the site every time you open this page.</p></div>

        <details open><summary>1. Placement, Compatibility & Template</summary><div class="grid"><div><?php $this->check('sticky_header','Sticky Header — keep it at the top while scrolling',$s);$this->check('suppress_theme_header','Hide normal theme header after successful mount',$s);?></div><?php $this->field('layer_level','Layer level (lower stays behind more site elements)',$s,'number',1,9999);$this->select('preset','Template',$s,self::presets());$this->select('desktop_mode','Desktop hamburger behavior',$s,['dropdown'=>'Dropdown','drawer_right'=>'Right drawer','drawer_left'=>'Left drawer']);$this->select('mobile_mode','Mobile hamburger behavior',$s,['drawer_right'=>'Right drawer','drawer_left'=>'Left drawer','dropdown'=>'Dropdown']);$this->select('animation','Open/close animation',$s,['slide'=>'Slide','fade'=>'Fade','scale'=>'Scale','none'=>'None']);$this->field('duration','Animation duration (ms)',$s,'number',0,400);?></div></details>

        <details open class="n9lh-dark-admin"><summary>1A. Dark Mode — Backend Control Only</summary><p class="n9lh-tip"><strong>No front-end switch is added.</strong> Turn this on here and the saved setting controls the header for visitors. It starts ON by default. Dark surfaces are derived from the active Theme palette, so navy stays navy, teal stays teal, purple stays plum, while contrast is corrected for readability.</p><div class="grid"><div><?php $this->check('dark_mode','Enable Dark Mode for the entire header',$s);?><div class="n9lh-dark-preview" data-dark-preview><span>Header</span><span>Drawer</span><span>Text & icons</span></div></div><div><?php $this->dark_mode_slider($s);?><p class="n9lh-dark-scale"><span>0 Soft shaded dark</span><span>50 Deep theme shade</span><span>100 Deepest theme shade</span></p></div></div></details>

        <details open><summary>2. Site Identity & Closed Header Rows</summary><div class="grid"><div class="n9lh-logo-box"><strong>Site logo / icon</strong><div class="logo-preview" data-logo-preview><?php if($logo):?><img src="<?php echo esc_url($logo);?>" alt=""><?php else:?><span>WordPress Site Icon</span><?php endif;?></div><input type="hidden" data-logo-id name="<?php echo esc_attr(self::OPTION);?>[brand_logo_id]" value="<?php echo absint($s['brand_logo_id']);?>"><label><span>Site icon URL <small>(optional alternative to Media Library)</small></span><input type="url" data-logo-url name="<?php echo esc_attr(self::OPTION);?>[brand_logo_url]" value="<?php echo esc_attr($s['brand_logo_url']);?>"></label><div class="button-row"><button type="button" class="button button-primary" data-logo-pick>Select / replace site icon</button><button type="button" class="button" data-logo-clear>Use WordPress Site Icon</button></div><?php $this->check('show_logo','Show logo/site icon',$s);?></div><?php $this->check('show_site_name','Show Site Name',$s);$this->field('custom_site_name','Header Site Name (blank = WordPress title)',$s);$this->check('show_site_description','Show subtitle',$s);$this->field('custom_site_description','Subtitle (blank = WordPress tagline)',$s);$this->slider('logo_width','Logo width',$s,24,48);$this->check('show_site_address','Show website-address box on Row 2',$s);$this->field('site_address_override','Website-address text (blank = live host)',$s);$this->field('site_address_link','Website-address link URL (blank = homepage)',$s,'url');$this->check('show_series_chips','Show Series as plain text on Row 2',$s);$this->field('series_chip_count','Series items on Row 2 (0–2)',$s,'number',0,2);?></div></details>

        <details open><summary>3. Last Line Below Site — automatic taxonomy + manual text/links/specific categories</summary><p class="n9lh-tip">Choose Automatic, Manual, or Mixed. Manual rows can be plain text, a manual link, or a specific category/taxonomy term selected from the site — no slug lookup.</p><div class="grid"><div><?php $this->check('show_taxonomy_strip','Show last taxonomy/category line',$s);$this->check('show_taxonomy_label','Show line label',$s);?></div><?php $this->field('header_taxonomy_label','Line display name',$s);$this->select('taxonomy_strip_mode','Line content mode',$s,['auto'=>'Automatic selected taxonomy','manual'=>'Manual items only','mixed'=>'Automatic + manual items']);$this->taxonomy_select('header_taxonomy','Automatic taxonomy source',$s);$this->field('header_taxonomy_limit','Maximum automatic terms',$s,'number',1,20);?></div><h3>Manual / specific items</h3><?php $this->taxonomy_manual_ui($s);?></details>

        <details open><summary>4. Closed Header Icon / Action Builder</summary><p class="n9lh-tip">Reorder every closed-header icon. Add custom links or safe built-in functions. A custom icon image URL overrides the selected built-in icon.</p><?php $this->header_actions_ui($s);?><h3>Standalone icon appearance</h3><div class="grid"><?php $this->select('closed_action_style','Icon colour style',$s,['template'=>'Follow dark/theme colours','black_on_white'=>'Black icons','white_on_black'=>'White icons','custom'=>'Custom icon colours']);$this->field('closed_icon_fg','Custom icon colour',$s,'color');$this->field('closed_icon_hover_fg','Hover icon colour',$s,'color');$this->select('close_icon','Drawer Close icon',$s,self::icon_options());$this->slider('action_button_size','Icon touch area size',$s,32,48);$this->slider('action_icon_size','Visible icon size',$s,18,32);?></div></details>

        <details open><summary>5. Dropdown Menu Builder — select exactly what appears</summary><p class="n9lh-tip">Six useful learning slots are placed for you: Lecturers, Courses, Flyers, Workshops, Real Lectures and Live Lectures. A slot does not appear on the live site until you explicitly select its content source. Labels, icons, order and source are all adjustable.</p><div><?php $this->check('show_section_icons','Show icons in section headings',$s);?></div><?php $this->menu_builder_ui($s);?></details>

        <details><summary>6. Legacy Author profile data controls — not a menu tab</summary><div class="grid"><div><?php $this->check('show_author_photo','Show Author photograph in drawer',$s);$this->check('show_author_title','Show professional title',$s);$this->check('show_author_qualification','Show qualification',$s);$this->check('show_author_organisation','Show organisation',$s);$this->check('show_author_profile_link','Show Full Profile link',$s);$this->check('show_author_whatsapp_link','Show Author WhatsApp link',$s);$this->check('show_author_email_link','Show Author Email link',$s);?></div><?php $this->field('home_author_id','Homepage Author/User ID (0 = automatic)',$s,'number',0,99999999);$this->field('author_profile_link_label','Profile link text',$s);$this->field('author_whatsapp_link_label','WhatsApp link text',$s);$this->field('author_email_link_label','Email link text',$s);?></div></details>

        <details><summary>7. Legacy hierarchy compatibility — not shown in menus automatically</summary><p class="n9lh-tip">provider defaults are prefilled. You can type any custom post type slug even if the provider plugin is temporarily inactive.</p><div class="grid"><?php $this->post_type_field('course_post_type','Course post type slug',$s);$this->post_type_field('module_post_type','Module post type slug',$s);$this->post_type_field('lesson_post_type','Lesson post type slug',$s);$this->field('course_item_label','Course item name',$s);$this->field('module_item_label','Module item name',$s);$this->field('lesson_item_label','Lesson item name',$s);$this->field('author_relation_meta','Author relation meta key',$s);$this->field('module_course_meta','Module → Course relation meta key',$s);$this->field('lesson_module_meta','Lesson → Module relation meta key',$s);?><div><?php $this->check('show_pathway_open_links','Show direct Open Course / Open Module links',$s);?></div><?php $this->field('pathway_open_prefix','Direct-link prefix text',$s);$this->field('pathway_course_limit','Course limit',$s,'number',1,30);$this->field('pathway_module_limit','Modules per course',$s,'number',1,40);$this->field('pathway_lesson_limit','Lessons per module',$s,'number',1,40);?></div></details>

        <details><summary>8. Legacy Module/Lesson finder compatibility — not shown in menus</summary><div class="grid"><?php $this->field('finder_placeholder','Search placeholder',$s);$this->field('finder_min_chars','Minimum typed characters',$s,'number',1,5);$this->field('finder_result_limit','Maximum Finder results',$s,'number',6,50);$this->select('finder_search_icon','Finder search icon',$s,self::icon_options());$this->select('finder_default_type','Default content type',$s,['all'=>'Modules + Lessons','module'=>'Modules only','lesson'=>'Lessons only']);$this->select('finder_default_sort','Default sort',$s,['path'=>'Pathway order','title'=>'A–Z','newest'=>'Newest']);?><div><?php $this->check('finder_show_type_filter','Show Module/Lesson type filter',$s);$this->check('finder_show_course_filter','Show Course filter',$s);$this->check('finder_show_sort','Show Sort control',$s);?></div></div></details>

        <details><summary>9. Legacy Series source compatibility — not shown in menus automatically</summary><div class="grid"><?php $this->select('series_source','Series source',$s,['taxonomy'=>'Taxonomy','post_type'=>'Post type']);$this->taxonomy_select('series_taxonomy','Series taxonomy',$s);$this->post_type_field('series_post_type','Series post type slug',$s);$this->field('series_drawer_limit','Maximum Series items in drawer',$s,'number',1,100);?></div></details>

        <details open><summary>10. Optional Video & Form Popups</summary><h3>Video popup</h3><div class="grid"><div><?php $this->check('show_video_popup','Show Video popup icon',$s);?></div><?php $this->select('video_icon','Video icon',$s,self::icon_options());$this->field('video_popup_title','Video popup title',$s);$this->select('video_source','Video source',$s,['url'=>'Video/oEmbed URL','shortcode'=>'Shortcode']);$this->field('video_url','Video/oEmbed URL',$s,'url');$this->textarea('video_shortcode','Video shortcode',$s);?></div><h3>Form popup</h3><div class="grid"><div><?php $this->check('show_form_popup','Show Form popup icon',$s);?></div><?php $this->select('form_icon','Form icon',$s,self::icon_options());$this->field('form_popup_title','Form popup title',$s);$this->select('form_mode','Form popup content',$s,['builtin'=>'Built-in Author contact form','shortcode'=>'Shortcode']);$this->textarea('form_shortcode','Form shortcode',$s);?></div><h3>Shared popup size</h3><div class="grid"><?php $this->slider('popup_width','Popup width',$s,280,900);$this->slider('popup_max_height','Popup maximum viewport height',$s,40,95,'%');?></div></details>

        <details open><summary>11. Closed Header Text & Overflow</summary><p class="n9lh-tip">These controls prevent long names, Series and taxonomy/categories from being cut off. Height sliders below are minimum heights; rows may grow when content needs more space.</p><div class="grid"><?php $this->select('site_name_lines','Site Name lines',$s,['auto'=>'Automatic — never hard cut','two'=>'Maximum 2 lines','one'=>'One line with ellipsis']);$this->select('subtitle_lines','Subtitle lines',$s,['auto'=>'Automatic — never hard cut','two'=>'Maximum 2 lines','one'=>'One line with ellipsis']);$this->select('meta_overflow_mode','Website + Series overflow',$s,['scroll'=>'Horizontal scroll — recommended','wrap'=>'Wrap onto extra line']);$this->select('taxonomy_overflow_mode','Taxonomy/category overflow',$s,['scroll'=>'Horizontal scroll — recommended','wrap'=>'Wrap onto extra line']);?><div><?php $this->check('truncate_series_text','Truncate long Series names instead of showing full text',$s);$this->check('truncate_taxonomy_text','Truncate long taxonomy/category names instead of showing full text',$s);?></div></div></details>

        <details open><summary>12. Header, Drawer & Typography Sliders</summary><div class="grid"><?php $this->slider('header_height','Main header minimum height',$s,44,84);$this->slider('header_padding_v','Vertical padding',$s,0,16);$this->slider('header_padding_h','Horizontal padding',$s,4,30);$this->slider('header_spacing','Header item spacing',$s,2,22);$this->slider('meta_row_height','Website + Series minimum row height',$s,22,42);$this->slider('taxonomy_row_height','Taxonomy minimum row height',$s,22,42);$this->slider('site_name_size','Site Name font size',$s,10,22);$this->slider('subtitle_size','Subtitle font size',$s,7,16);$this->slider('meta_text_size','Website/Series text size',$s,7,15);$this->slider('taxonomy_label_size','Taxonomy label size',$s,7,15);$this->slider('taxonomy_term_size','Taxonomy term size',$s,7,15);$this->slider('panel_padding','Drawer/popup panel padding',$s,4,20);$this->slider('panel_section_gap','Drawer section spacing',$s,2,16);$this->slider('drawer_section_font_size','Drawer section heading size',$s,9,18);$this->slider('drawer_item_font_size','Drawer item text size',$s,8,16);$this->field('panel_width','Desktop dropdown width',$s,'number',320,860);$this->field('drawer_width','Side drawer width',$s,'number',280,460);?></div></details>

        <details open><summary>13. Full Colour Control</summary><div class="grid"><div><strong>Override every plugin colour without changing your theme.</strong><?php $this->check('custom_palette','Override every plugin colour below',$s);?></div><?php foreach(['custom_header_bg'=>'Header background','custom_header_fg'=>'Header text and icons','custom_panel_bg'=>'Drawer and open panel background','custom_panel_fg'=>'Drawer and open panel text','custom_primary'=>'Primary buttons and open sections','custom_primary_fg'=>'Primary text','custom_accent'=>'Accent and highlights','custom_accent_fg'=>'Accent text','custom_surface'=>'Cards and inputs','custom_surface_fg'=>'Card and input text','custom_soft'=>'Secondary backgrounds','custom_soft_fg'=>'Secondary text','custom_text'=>'Strong content areas','custom_text_fg'=>'Strong content text','custom_border'=>'Borders and dividers'] as $k=>$lab)$this->field($k,$lab,$s,'color');?></div><p>Turn on the override checkbox to set each header surface and its text colour exactly to your site palette. Closed-icon colours remain independently available in section 4.</p></details>

        <?php submit_button('Save All Header Controls'); ?></form>
        <p><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=elhh_header_reset'),'elhh_header_reset'));?>" onclick="return confirm('Reset Header to safe defaults?')">Reset to safe defaults</a></p></div>
        <style>
        .n9lh-admin details{background:#fff;border:1px solid #dcdcde;border-radius:9px;margin:9px 0}.n9lh-admin summary{padding:11px 13px;font-weight:750;cursor:pointer;background:#f6f7f7}.n9lh-admin details>div,.n9lh-admin details>p,.n9lh-admin details>h3{padding-left:13px;padding-right:13px}.n9lh-admin details>div{padding-bottom:13px}.n9lh-admin .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}.n9lh-admin label{display:flex;flex-direction:column;gap:4px}.n9lh-admin input,.n9lh-admin select,.n9lh-admin textarea{width:100%;max-width:none}.logo-preview{width:110px;height:70px;display:grid;place-items:center;border:1px dashed #aaa;margin:5px 0;overflow:hidden;background:#f6f7f7}.logo-preview img{max-width:100%;max-height:100%;object-fit:contain}.button-row{display:flex;gap:6px;flex-wrap:wrap}.n9lh-slider output{font-weight:800;color:#2271b1}.n9lh-slider input[type=range]{width:100%}.n9lh-tip{margin-top:0;color:#50575e}.n9lh-admin h3{margin:12px 0 8px;font-size:14px}.n9lh-switch{display:grid!important;grid-template-columns:38px 1fr;align-items:center;gap:8px!important;margin:7px 0;cursor:pointer}.n9lh-switch input{position:absolute!important;opacity:0;width:1px!important;height:1px!important}.n9lh-switch-track{width:38px;height:22px;border-radius:999px;background:#8c8f94;position:relative;transition:.15s}.n9lh-switch-track:after{content:"";position:absolute;width:16px;height:16px;left:3px;top:3px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.25);transition:.15s}.n9lh-switch input:checked+.n9lh-switch-track{background:#2271b1}.n9lh-switch input:checked+.n9lh-switch-track:after{transform:translateX(16px)}.n9lh-switch input:focus-visible+.n9lh-switch-track{outline:2px solid #2271b1;outline-offset:2px}.n9lh-switch b{font-weight:650}.n9lh-dark-admin{border-color:#4b5563!important}.n9lh-dark-admin summary{background:#20242a!important;color:#fff}.n9lh-dark-preview{display:flex;gap:5px;flex-wrap:wrap;margin-top:10px;padding:7px;border-radius:7px;background:linear-gradient(90deg,#303a46,#11151a 62%,#030405);border:1px solid #4b5563}.n9lh-dark-preview span{padding:4px 7px;border-radius:5px;background:#161a20;color:#f8fafc;border:1px solid #59616d;font-size:11px;font-weight:700}.n9lh-dark-slider small{font-weight:750;color:#50575e}.n9lh-dark-scale{display:flex;justify-content:space-between;gap:8px;margin:4px 0 0;font-size:10px;color:#646970}.n9lh-dark-scale span{white-space:nowrap}
        @media(max-width:600px){.n9lh-admin .grid{grid-template-columns:1fr}.n9lh-admin summary{padding:9px 10px}.n9lh-admin details>div,.n9lh-admin details>p,.n9lh-admin details>h3{padding-left:10px;padding-right:10px}}
        </style>
        <script>(function(){var b=document.querySelector('[data-logo-pick]'),c=document.querySelector('[data-logo-clear]'),p=document.querySelector('[data-logo-preview]'),i=document.querySelector('[data-logo-id]'),u=document.querySelector('[data-logo-url]');if(b&&window.wp&&wp.media)b.addEventListener('click',function(){var f=wp.media({title:'Select Header Site Icon',button:{text:'Use this icon'},multiple:false,library:{type:'image'}});f.on('select',function(){var a=f.state().get('selection').first().toJSON();i.value=a.id||0;u.value=a.url||'';p.innerHTML='<img src="'+a.url+'" alt="">';});f.open();});if(c)c.addEventListener('click',function(){i.value='0';u.value='';p.innerHTML='<span>WordPress Site Icon</span>';});function darkName(v){v=parseInt(v,10)||0;if(v<20)return'Soft charcoal';if(v<40)return'Muted graphite';if(v<65)return'Balanced dark';if(v<85)return'Near black';return'OLED black';}document.querySelectorAll('[data-slider]').forEach(function(r){var o=document.querySelector('[data-slider-out="'+r.getAttribute('data-slider')+'"]');var unit=r.getAttribute('data-unit')||'';var update=function(){if(o)o.textContent=r.value+unit;if(r.hasAttribute('data-dark-strength')){var n=document.querySelector('[data-dark-strength-name]');if(n)n.textContent=darkName(r.value);}};r.addEventListener('input',update);update();});})();</script>
        <style>
        .n9lh-builder{display:grid;gap:8px;margin:8px 13px 10px}.n9lh-builder-row{border:1px solid #c3c4c7;border-radius:8px;background:#fff;overflow:hidden}.n9lh-builder-head{display:flex;align-items:center;gap:8px;padding:7px 8px;background:#f6f7f7;border-bottom:1px solid #e2e4e7}.n9lh-builder-head .drag,.manual-tax-row .drag{font-weight:900;color:#646970}.builder-actions{display:flex;align-items:center;gap:5px;margin-left:auto}.n9lh-builder-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;padding:8px}.mini-check,.default-open{display:flex!important;flex-direction:row!important;align-items:center!important;gap:6px!important}.mini-check input,.default-open input{width:auto!important}.manual-links{padding:0 8px 8px}.manual-link-row{display:grid;grid-template-columns:1fr 1.4fr 110px 28px;gap:5px;align-items:center;margin:5px 0}.manual-tax-row{display:grid;grid-template-columns:20px 160px 1fr 1.2fr 1.4fr 100px auto;gap:5px;align-items:center;border:1px solid #dcdcde;border-radius:7px;padding:6px;margin:6px 13px}.inline-media{display:flex;gap:5px}.inline-media input{min-width:0}.n9lh-admin small{font-weight:400;color:#646970}.n9lh-builder-row input[type=checkbox]{width:auto!important}.n9lh-admin .button-link-delete{white-space:nowrap}.n9lh-builder [data-builder-pt-manual]{margin-top:4px}
        @media(max-width:700px){.manual-link-row{grid-template-columns:1fr}.manual-tax-row{grid-template-columns:20px 1fr}.manual-tax-row>*:not(.drag){grid-column:2}.builder-actions{flex-wrap:wrap}.n9lh-builder-head{align-items:flex-start}.n9lh-builder{margin-left:10px;margin-right:10px}.n9lh-builder-grid{grid-template-columns:1fr}.manual-links{padding:0 7px 7px}}
        </style>
        <script>
        (function(){
          function qa(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s))}
          function uid(prefix){return prefix+'-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,6)}
          function replaceIndex(name,key,i){var re=new RegExp('\\['+key+'\\]\\[\\d+\\]');return name.replace(re,'['+key+']['+i+']')}
          function reindexLinks(row){qa('[data-link-row]',row).forEach(function(l,j){qa('[name]',l).forEach(function(el){el.name=el.name.replace(/\[links\]\[\d+\]/,'[links]['+j+']')})})}
          function reindexBuilder(box,key,rowSel){qa(rowSel,box).forEach(function(row,i){qa('[name]',row).forEach(function(el){el.name=replaceIndex(el.name,key,i)});reindexLinks(row)})}
          function move(btn,dir){var row=btn.closest('[data-builder-row],[data-action-row],[data-tax-item-row]');if(!row)return;var target=dir<0?row.previousElementSibling:row.nextElementSibling;if(!target)return;dir<0?row.parentNode.insertBefore(row,target):row.parentNode.insertBefore(target,row);syncAll()}
          function syncAll(){var m=document.querySelector('[data-menu-builder]'),a=document.querySelector('[data-action-builder]'),t=document.querySelector('[data-tax-items-builder]');if(m)reindexBuilder(m,'menu_builder','[data-builder-row]');if(a)reindexBuilder(a,'header_actions','[data-action-row]');if(t)reindexBuilder(t,'taxonomy_manual_items','[data-tax-item-row]')}
          document.addEventListener('click',function(e){
            var b=e.target.closest('[data-move-up]');if(b){e.preventDefault();move(b,-1);return}b=e.target.closest('[data-move-down]');if(b){e.preventDefault();move(b,1);return}
            b=e.target.closest('[data-remove-row]');if(b){e.preventDefault();var r=b.closest('[data-builder-row],[data-action-row],[data-tax-item-row]');if(r)r.remove();syncAll();return}
            b=e.target.closest('[data-remove-link]');if(b){e.preventDefault();var lr=b.closest('[data-link-row]'),br=b.closest('[data-builder-row]');if(lr)lr.remove();if(br)reindexLinks(br);return}
            b=e.target.closest('[data-add-manual-link]');if(b){e.preventDefault();var br=b.closest('[data-builder-row]'),list=br.querySelector('[data-link-list]'),base=br.querySelector('input[type=hidden][name*="[uid]"]').name.replace(/\[uid\]$/,'');var j=qa('[data-link-row]',list).length;list.insertAdjacentHTML('beforeend','<div class="manual-link-row" data-link-row><input type="text" name="'+base+'[links]['+j+'][text]" placeholder="Link text"><input type="url" name="'+base+'[links]['+j+'][url]" placeholder="https://…"><select name="'+base+'[links]['+j+'][target]"><option value="same">Same tab</option><option value="new">New tab</option></select><button type="button" class="button-link-delete" data-remove-link>×</button></div>');return}
            b=e.target.closest('[data-add-menu-section]');if(b){e.preventDefault();var box=document.querySelector('[data-menu-builder]'),src=box&&box.querySelector('[data-builder-row]:last-child');if(!src)return;var row=src.cloneNode(true);row.querySelectorAll('input,select').forEach(function(el){if(el.type==='checkbox')el.checked=true;else if(el.type==='radio')el.checked=false;else if(el.type!=='hidden')el.value=''});var hid=row.querySelector('input[type=hidden][name*="[uid]"]'),newUid=uid('section');if(hid)hid.value=newUid;var def=row.querySelector('input[type=radio]');if(def)def.value=newUid;var kind=row.querySelector('[data-section-kind]');if(kind)kind.value='none';var lab=row.querySelector('[data-row-label]');if(lab)lab.value='New Section';var title=row.querySelector('[data-row-title]');if(title)title.textContent='New Section';var links=row.querySelector('[data-link-list]');if(links)links.innerHTML='';box.appendChild(row);syncAll();return}
            b=e.target.closest('[data-add-header-action]');if(b){e.preventDefault();var box=document.querySelector('[data-action-builder]'),src=box&&box.querySelector('[data-action-row]:last-child');if(!src)return;var row=src.cloneNode(true);row.querySelectorAll('input,select').forEach(function(el){if(el.type==='checkbox')el.checked=true;else if(el.type!=='hidden')el.value=''});var hid=row.querySelector('input[type=hidden][name*="[uid]"]');if(hid)hid.value=uid('action');var selects=row.querySelectorAll('select');if(selects.length)selects[0].value='link';var lab=row.querySelector('[data-action-label]');if(lab)lab.value='New Action';var title=row.querySelector('[data-action-title]');if(title)title.textContent='New Action';box.appendChild(row);syncAll();return}
            b=e.target.closest('[data-add-tax-item]');if(b){e.preventDefault();var box=document.querySelector('[data-tax-items-builder]'),tpl=document.querySelector('[data-tax-item-template]');if(!box||!tpl)return;var frag=tpl.content.cloneNode(true),row=frag.querySelector('[data-tax-item-row]');row.querySelectorAll('input,select').forEach(function(el){if(el.tagName==='SELECT')el.selectedIndex=0;else el.value=''});box.appendChild(row);syncAll();return}
            b=e.target.closest('[data-pick-media]');if(b&&window.wp&&wp.media){e.preventDefault();var input=b.parentNode.querySelector('[data-media-url]');var f=wp.media({title:'Select custom icon image',button:{text:'Use icon'},multiple:false,library:{type:'image'}});f.on('select',function(){var a=f.state().get('selection').first().toJSON();if(input)input.value=a.url||''});f.open();return}
          });
          document.addEventListener('input',function(e){if(e.target.matches('[data-row-label]')){var r=e.target.closest('[data-builder-row]'),t=r&&r.querySelector('[data-row-title]');if(t)t.textContent=e.target.value||'Section'}if(e.target.matches('[data-action-label]')){var r=e.target.closest('[data-action-row]'),t=r&&r.querySelector('[data-action-title]');if(t)t.textContent=e.target.value||'Action'}});
          var form=document.querySelector('.n9lh-admin form');if(form)form.addEventListener('submit',syncAll);syncAll();
        })();
        </script>

        <?php
    }
    public function reset(){if(!current_user_can('manage_options'))wp_die('Forbidden');check_admin_referer('elhh_header_reset');update_option(self::OPTION,self::defaults(),false);wp_safe_redirect(admin_url('admin.php?page=elearning-click-header-footer&surface=header&reset=1'));exit;}
}
