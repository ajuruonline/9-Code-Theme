<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class ELHF_H_Renderer {
    private static $instance; private $rendered=false;
    public static function instance(){ return self::$instance ?: ( self::$instance = new self() ); }
    private function __construct(){
        add_action('wp_enqueue_scripts',[ $this,'assets' ],5); add_action('wp_body_open',[ $this,'render' ],1); add_action('wp_footer',[ $this,'footer_fallback' ],1); add_filter('body_class',[ $this,'body_class' ]);
    }
    private function s(){return ELHF_Design::header_settings(ELHF_H_Settings::all());}
    public function body_class($classes){if(function_exists('ninecode_theme_surface_suppresses')&&ninecode_theme_surface_suppresses('header'))return $classes;if(ELHF_H_Settings::is_enabled()){$classes[]='n9lh-active';if('yes'===ELHF_H_Settings::get('sticky_header'))$classes[]='n9lh-sticky';if('yes'===ELHF_H_Settings::get('suppress_theme_header'))$classes[]='n9lh-suppress-theme';}return $classes;}
    public function assets(){if(is_admin()||(function_exists('ninecode_theme_surface_suppresses')&&ninecode_theme_surface_suppresses('header'))||! ELHF_H_Settings::is_enabled())return;$css=ELHF_H_DIR.'assets/frontend.css';$js=ELHF_H_DIR.'assets/frontend.js';wp_enqueue_style('n9lh',ELHF_H_URL.'assets/frontend.css',[],file_exists($css)?filemtime($css):ELHF_H_VERSION);wp_enqueue_script('n9lh',ELHF_H_URL.'assets/frontend.js',[],file_exists($js)?filemtime($js):ELHF_H_VERSION,true);wp_localize_script('n9lh','N9LH_FIND',['url'=>admin_url('admin-ajax.php')]);}

    private function luminance($hex){$hex=ltrim((string)$hex,'#');if(strlen($hex)!==6)return 1;$v=[];foreach([0,2,4] as $i){$x=hexdec(substr($hex,$i,2))/255;$v[]=$x<=.03928?$x/12.92:pow(($x+.055)/1.055,2.4);}return .2126*$v[0]+.7152*$v[1]+.0722*$v[2];}
    private function contrast_fg($hex){$l=$this->luminance($hex);$dark=.0056053916;$dr=($l+.05)/($dark+.05);$wr=1.05/($l+.05);return $dr>=$wr?'#111111':'#FFFFFF';}
    private function rgb($hex){$hex=ltrim((string)$hex,'#');if(3===strlen($hex))$hex=$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];if(6!==strlen($hex))return [0,0,0];return [hexdec(substr($hex,0,2)),hexdec(substr($hex,2,2)),hexdec(substr($hex,4,2))];}
    private function mix_hex($from,$to,$amount){$amount=max(0,min(1,(float)$amount));$a=$this->rgb($from);$b=$this->rgb($to);$r=[];for($i=0;$i<3;$i++)$r[$i]=(int)round($a[$i]+(($b[$i]-$a[$i])*$amount));return sprintf('#%02X%02X%02X',$r[0],$r[1],$r[2]);}
    private function contrast_ratio($a,$b){$la=$this->luminance($a);$lb=$this->luminance($b);$hi=max($la,$lb);$lo=min($la,$lb);return ($hi+.05)/($lo+.05);}
    private function visible_on_dark($color,$bg,$minimum=2.6){if($this->contrast_ratio($color,$bg)>=$minimum)return $color;for($i=1;$i<=7;$i++){$candidate=$this->mix_hex($color,'#FFFFFF',$i*.1);if($this->contrast_ratio($candidate,$bg)>=$minimum)return $candidate;}return '#E5E7EB';}
    private function apply_dark_mode($colors,$strength){
        $t=max(0,min(100,absint($strength)))/100;
        // Build the dark system FROM the active brand palette instead of replacing it
        // with generic charcoal. Primary supplies the hue; accent adds a small bias.
        $anchor=$this->mix_hex($colors['p'],$colors['a'],.12);
        $colors['barbg']=$this->mix_hex($anchor,'#06080B',.54+(.28*$t));
        $colors['panelbg']=$this->mix_hex($anchor,'#080B10',.60+(.25*$t));
        $colors['surface']=$this->mix_hex($anchor,'#11151B',.67+(.20*$t));
        $colors['soft']=$this->mix_hex($anchor,'#171C24',.72+(.17*$t));
        $colors['text']=$this->mix_hex($anchor,'#1C222B',.75+(.15*$t));
        $colors['border']=$this->mix_hex($anchor,'#667085',.62+(.15*$t));
        $light=$this->mix_hex('#E7EDF4','#FFFFFF',.35+(.45*$t));
        $softLight=$this->mix_hex('#CBD5E1','#F7FAFC',.28+(.40*$t));
        $colors['barfg']=$light;$colors['panelfg']=$light;$colors['surfacefg']=$light;$colors['softfg']=$softLight;$colors['textfg']=$light;
        // Accents remain recognisably Theme colours but are contrast-corrected.
        $colors['p']=$this->mix_hex($colors['p'],$colors['surface'],.08+(.10*$t));
        $colors['a']=$this->mix_hex($colors['a'],$colors['barbg'],.02+(.07*$t));
        $colors['p']=$this->visible_on_dark($colors['p'],$colors['surface'],2.2);
        $colors['a']=$this->visible_on_dark($colors['a'],$colors['barbg'],2.8);
        $colors['pfg']=$this->contrast_fg($colors['p']);$colors['afg']=$this->contrast_fg($colors['a']);
        return $colors;
    }
    private function palette($s){
        $all=ELHF_H_Settings::palettes();$p=$all[$s['preset']]??$all['authorfest_core'];
        $colors=['p'=>$p[0],'a'=>$p[1],'surface'=>$p[2],'soft'=>$p[3],'text'=>$p[4],'border'=>$p[5],'barbg'=>$p[2],'panelbg'=>$p[2]];
        $colors['pfg']=$this->contrast_fg($colors['p']);$colors['afg']=$this->contrast_fg($colors['a']);$colors['surfacefg']=$this->contrast_fg($colors['surface']);$colors['softfg']=$this->contrast_fg($colors['soft']);$colors['textfg']=$this->contrast_fg($colors['text']);$colors['barfg']=$colors['surfacefg'];$colors['panelfg']=$colors['surfacefg'];
        if('yes'===($s['custom_palette']??'')){
            $map=['barbg'=>'custom_header_bg','barfg'=>'custom_header_fg','panelbg'=>'custom_panel_bg','panelfg'=>'custom_panel_fg','p'=>'custom_primary','pfg'=>'custom_primary_fg','a'=>'custom_accent','afg'=>'custom_accent_fg','surface'=>'custom_surface','surfacefg'=>'custom_surface_fg','soft'=>'custom_soft','softfg'=>'custom_soft_fg','text'=>'custom_text','textfg'=>'custom_text_fg','border'=>'custom_border'];
            foreach($map as $role=>$key){$v=sanitize_hex_color($s[$key]??'');if($v)$colors[$role]=$v;}
        }
        if('yes'===($s['dark_mode']??''))$colors=$this->apply_dark_mode($colors,$s['dark_mode_strength']??55);
        return $colors;
    }
    private function identity($s){$name=trim((string)$s['custom_site_name']);if(''===$name)$name=get_bloginfo('name');if('yes'!==($s['show_site_name']??'yes'))$name='';$desc=trim((string)$s['custom_site_description']);if(''===$desc)$desc=get_bloginfo('description');if('yes'!==$s['show_site_description'])$desc='';$logo='';if('yes'===($s['show_logo']??'yes')){if($s['brand_logo_id'])$logo=wp_get_attachment_image_url((int)$s['brand_logo_id'],'full');if(!$logo)$logo=$s['brand_logo_url'];if(!$logo)$logo=get_site_icon_url(128);}return ['name'=>$name,'desc'=>$desc,'logo'=>$logo];}

    private function icon($n){
        $m=[
            'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4.5 20c.8-4.4 3.2-6.5 7.5-6.5s6.7 2.1 7.5 6.5"/>',
            'user_circle'=>'<circle cx="12" cy="12" r="9"/><circle cx="12" cy="9" r="3"/><path d="M6.5 18c1.2-3 3-4.3 5.5-4.3s4.3 1.3 5.5 4.3"/>',
            'badge'=>'<path d="M12 3l7 3v5c0 4.7-2.6 7.8-7 10-4.4-2.2-7-5.3-7-10V6z"/><circle cx="12" cy="9" r="2.5"/><path d="M8.5 16c.8-2 2-3 3.5-3s2.7 1 3.5 3"/>',
            'menu'=>'<path d="M4 7h16M4 12h16M4 17h16"/>','grid'=>'<rect x="4" y="4" width="6" height="6"/><rect x="14" y="4" width="6" height="6"/><rect x="4" y="14" width="6" height="6"/><rect x="14" y="14" width="6" height="6"/>','dots'=>'<circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>',
            'close'=>'<path d="m6 6 12 12M18 6 6 18"/>','chat'=>'<path d="M4 5.5h16v11H9l-5 3z"/><path d="M8 10h8M8 13h5"/>','phone'=>'<path d="M7 3h3l1.5 4-2 1.8a15 15 0 0 0 5.7 5.7l1.8-2 4 1.5v3c0 2-1.6 3-3.5 3C10.6 20 4 13.4 4 6.5 4 4.6 5 3 7 3z"/>',
            'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>','at'=>'<circle cx="12" cy="12" r="8"/><path d="M16 12v2c0 1.2.8 2 2 2 1.5 0 2.5-1.6 2.5-4a8.5 8.5 0 1 0-3.4 6.8"/><circle cx="12" cy="12" r="3"/>',
            'search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5"/>','chev'=>'<path d="m9 6 6 6-6 6"/>',
            'course'=>'<path d="M5 4h14v10H5zM8 8h8M8 11h5"/>','lecturer'=>'<circle cx="9" cy="8" r="3"/><path d="M3.5 19c.7-3.5 2.5-5.2 5.5-5.2s4.8 1.7 5.5 5.2M15 6h6M18 3v6"/>','flyer'=>'<path d="M6 3h9l3 3v15H6zM15 3v4h4M9 11h6M9 14h6M9 17h4"/>','workshop'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18M8 14h3M13 14h3M8 18h3"/>','lecture'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3z"/>','live'=>'<circle cx="12" cy="12" r="2"/><path d="M7.8 7.8a6 6 0 0 0 0 8.4M16.2 7.8a6 6 0 0 1 0 8.4M4.8 4.8a10 10 0 0 0 0 14.4M19.2 4.8a10 10 0 0 1 0 14.4"/>','module'=>'<path d="M4 5h6v6H4zM14 5h6v6h-6zM4 15h6v5H4zM14 15h6v5h-6z"/>','lesson'=>'<circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4z"/>','series'=>'<path d="M4 6h16M4 12h12M4 18h8"/>',
            'video'=>'<rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3z"/>','play'=>'<circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4z"/>',
            'form'=>'<path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 11h6M9 15h6"/>','edit'=>'<path d="M4 20h4l11-11-4-4L4 16zM13 7l4 4"/>','clipboard'=>'<rect x="5" y="5" width="14" height="16" rx="2"/><path d="M9 5V3h6v2M9 10h6M9 14h6"/>'
        ];if('none'===$n)return '';$d=$m[$n]??$m['chev'];return '<svg viewBox="0 0 24 24" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">'.$d.'</g></svg>';
    }
    private function icon_markup($icon,$url=''){if($url)return '<img class="n9lh-custom-icon" src="'.esc_url($url).'" alt="">';return $this->icon($icon);}
    private function section($label,$uid,$icon,$iconUrl,$cb,$open=false,$showIcon=true){$class=sanitize_html_class($uid);echo '<details class="n9lh-section n9lh-section-'.esc_attr($class).'" data-section-id="'.esc_attr($uid).'" name="n9lh-main"'.($open?' open':'').'><summary><span class="n9lh-section-title">'.($showIcon&&(($icon&&'none'!==$icon)||$iconUrl)?'<span class="n9lh-section-icon">'.$this->icon_markup($icon,$iconUrl).'</span>':'').'<span>'.esc_html($label).'</span></span><span class="chev">'.$this->icon('chev').'</span></summary><div class="n9lh-section-body">';call_user_func($cb);echo '</div></details>'; }
    private function pt_label($pt,$fallback){$obj=post_type_exists($pt)?get_post_type_object($pt):null;return $obj&&!empty($obj->labels->singular_name)?$obj->labels->singular_name:$fallback;}

    private function render_contact_form($d,$author,$s){
        if(!$d||empty($d['email'])){echo '<p class="n9lh-empty">No contact email is available for this Author.</p>';return;}
        echo '<form class="n9lh-contact-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="n9lh_contact"><input type="hidden" name="author_id" value="'.absint($author).'">'.wp_nonce_field('n9lh_contact','n9lh_nonce',true,false).'<div class="n9lh-contact-grid"><label><span>'.esc_html($s['contact_name_label']).'</span><input name="name" required autocomplete="name"></label><label><span>'.esc_html($s['contact_email_label']).'</span><input type="email" name="email" required autocomplete="email"></label></div><label><span>'.esc_html($s['contact_message_label']).'</span><textarea name="message" rows="3" required></textarea></label><input class="n9lh-honeypot" type="text" name="website" tabindex="-1" autocomplete="off"><button type="submit">'.esc_html($s['contact_submit_label']).'</button></form>';
    }
    private function render_author($d,$author,$s){
        if(!$d){echo '<p class="n9lh-empty">No Author could be resolved.</p>';return;}
        echo '<div class="n9lh-author-card">';if('yes'===$s['show_author_photo']&&!empty($d['image']))echo '<img src="'.esc_url($d['image']).'" alt=""><div>';else echo '<div>';
        echo '<strong>'.esc_html($d['name']).'</strong>';if('yes'===$s['show_author_title']&&$d['title'])echo '<span>'.esc_html($d['title']).'</span>';if('yes'===$s['show_author_qualification']&&$d['qualification'])echo '<small>'.esc_html($d['qualification']).'</small>';if('yes'===$s['show_author_organisation']&&!empty($d['organisation']))echo '<small>'.esc_html($d['organisation']).'</small>';echo '</div></div>';
        $links=[];if('yes'===$s['show_author_profile_link']&&!empty($d['profile']))$links[]='<a href="'.esc_url($d['profile']).'">'.esc_html($s['author_profile_link_label']).'</a>';if('yes'===$s['show_author_whatsapp_link']&&!empty($d['whatsapp']))$links[]='<a href="'.esc_url($d['whatsapp']).'" target="_blank" rel="noopener">'.esc_html($s['author_whatsapp_link_label']).'</a>';if('yes'===$s['show_author_email_link']&&!empty($d['email']))$links[]='<a href="mailto:'.esc_attr($d['email']).'">'.esc_html($s['author_email_link_label']).'</a>';if($links)echo '<div class="n9lh-author-links">'.implode('',$links).'</div>';
    }
    private function render_pathway($author,$s){
        $b=ELHF_H_Author::instance();
        $courseName=trim((string)$s['course_item_label'])?:$this->pt_label($s['course_post_type'],'Course');
        $moduleName=trim((string)$s['module_item_label'])?:$this->pt_label($s['module_post_type'],'Module');
        if(!$author){echo '<p class="n9lh-empty">No Author selected.</p>';return;}
        $courses=$b->courses($author,(int)$s['pathway_course_limit']);
        if(!$courses){echo '<p class="n9lh-empty">No '.esc_html($courseName).' is allocated to this Author yet.</p>';return;}
        foreach($courses as $cid){
            echo '<details class="path-course" name="n9lh-course"><summary><span class="sem">'.$this->icon('course').'</span><span>'.esc_html(get_the_title($cid)).'</span><span class="chev">'.$this->icon('chev').'</span></summary><div class="path-body">';
            if('yes'===$s['show_pathway_open_links'])echo '<a class="path-open" href="'.esc_url(get_permalink($cid)).'">'.esc_html(trim($s['pathway_open_prefix'].' '.$courseName)).'</a>';
            foreach($b->course_modules($cid,(int)$s['pathway_module_limit']) as $mid){
                echo '<details class="path-module" name="n9lh-module-'.absint($cid).'"><summary><span class="sem">'.$this->icon('module').'</span><span>'.esc_html(get_the_title($mid)).'</span><span class="chev">'.$this->icon('chev').'</span></summary><div class="path-body">';
                if('yes'===$s['show_pathway_open_links'])echo '<a class="path-open" href="'.esc_url(get_permalink($mid)).'">'.esc_html(trim($s['pathway_open_prefix'].' '.$moduleName)).'</a>';
                foreach($b->module_lessons($mid,(int)$s['pathway_lesson_limit']) as $lid)echo '<a class="lesson-link" href="'.esc_url(get_permalink($lid)).'"><span>'.$this->icon('lesson').'</span><span>'.esc_html(get_the_title($lid)).'</span></a>';
                echo '</div></details>';
            }
            echo '</div></details>';
        }
    }
    private function render_finder($author,$s){
        $b=ELHF_H_Author::instance();$moduleName=trim((string)$s['module_item_label'])?:$this->pt_label($s['module_post_type'],'Module');$lessonName=trim((string)$s['lesson_item_label'])?:$this->pt_label($s['lesson_post_type'],'Lesson');$courseName=trim((string)$s['course_item_label'])?:$this->pt_label($s['course_post_type'],'Course');$courses=$author?$b->courses($author,(int)$s['pathway_course_limit']):[];
        echo '<div class="n9lh-finder" data-n9lh-finder data-author="'.absint($author).'" data-module-label="'.esc_attr($moduleName).'" data-lesson-label="'.esc_attr($lessonName).'" data-course-label="'.esc_attr($courseName).'" data-min-chars="'.absint($s['finder_min_chars']).'"><div class="find-search"><input type="search" data-find-q placeholder="'.esc_attr($s['finder_placeholder']).'"><button type="button" data-find-go aria-label="Search">'.$this->icon($s['finder_search_icon']).'</button></div><div class="find-controls">';
        if('yes'===$s['finder_show_type_filter'])echo '<select data-find-type><option value="all" '.selected($s['finder_default_type'],'all',false).'>'.esc_html($moduleName).' + '.esc_html($lessonName).'</option><option value="module" '.selected($s['finder_default_type'],'module',false).'>'.esc_html($moduleName).' only</option><option value="lesson" '.selected($s['finder_default_type'],'lesson',false).'>'.esc_html($lessonName).' only</option></select>';
        if('yes'===$s['finder_show_course_filter']){echo '<select data-find-course><option value="0">All '.esc_html($courseName).'s</option>';foreach($courses as $cid)echo '<option value="'.absint($cid).'">'.esc_html(get_the_title($cid)).'</option>';echo '</select>';}
        if('yes'===$s['finder_show_sort'])echo '<select data-find-sort><option value="path" '.selected($s['finder_default_sort'],'path',false).'>Pathway order</option><option value="title" '.selected($s['finder_default_sort'],'title',false).'>A–Z</option><option value="newest" '.selected($s['finder_default_sort'],'newest',false).'>Newest</option></select>';
        echo '</div><div class="find-status" data-find-status>'.esc_html($s['finder_placeholder']).'</div><div class="find-results" data-find-results></div></div>';
    }
    private function site_address($s){$override=trim((string)($s['site_address_override']??''));if($override)return $override;$host=wp_parse_url(home_url('/'),PHP_URL_HOST);if(!$host)$host=preg_replace('#^https?://#i','',untrailingslashit(home_url('/')));return (string)$host;}
    private function manual_taxonomy_items($s){
        $out=[];foreach((array)($s['taxonomy_manual_items']??[]) as $row){if(!is_array($row))continue;if('term'===($row['kind']??'')){list($tax,$tid)=array_pad(explode('|',(string)($row['term_ref']??''),2),2,'');$term=$tid&&taxonomy_exists($tax)?get_term((int)$tid,$tax):null;if($term&&!is_wp_error($term)){$url=get_term_link($term,$tax);if(!is_wp_error($url))$out[]=['name'=>$term->name,'url'=>$url,'target'=>'same'];}}else{$text=trim((string)($row['text']??''));$url=trim((string)($row['url']??''));if($text||$url)$out[]=['name'=>$text?:$url,'url'=>$url,'target'=>$row['target']??'same'];}}return $out;
    }
    private function render_taxonomy_strip($author,$s){
        if('yes'!==($s['show_taxonomy_strip']??'yes'))return;$b=ELHF_H_Author::instance();$mode=$s['taxonomy_strip_mode']??'auto';$items=[];$tax=ELHF_H_Settings::resolve_taxonomy($s['header_taxonomy']??'');
        if(in_array($mode,['auto','mixed'],true)&&$tax){foreach($b->context_terms($author,$tax,(int)($s['header_taxonomy_limit']??8),true) as $t)$items[]=['name'=>$t->name,'url'=>$b->term_url($tax,$t),'target'=>'same'];}
        if(in_array($mode,['manual','mixed'],true))$items=array_merge($items,$this->manual_taxonomy_items($s));
        $label=trim((string)($s['header_taxonomy_label']??'Subject'));if(''===$label&&$tax)$label=$b->taxonomy_label($tax);
        echo '<div class="n9lh-tax-strip">';if('yes'===($s['show_taxonomy_label']??'yes')&&$label!=='')echo '<span class="n9lh-tax-label">'.esc_html($label).'</span>';echo '<div class="n9lh-tax-items">';if($items){foreach($items as $item){if(!empty($item['url']))echo '<a href="'.esc_url($item['url']).'"'.('new'===($item['target']??'')?' target="_blank" rel="noopener"':'').'>'.esc_html($item['name']).'</a>';else echo '<span class="n9lh-tax-manual">'.esc_html($item['name']).'</span>';}}else echo '<span class="n9lh-tax-empty">No items</span>';echo '</div></div>';
    }
    private function series_items($author,$s,$limit=0){
        $b=ELHF_H_Author::instance();if('post_type'===($s['series_source']??'taxonomy')){$ids=$b->author_posts($author,$s['series_post_type'],$limit?:30);$out=[];foreach($ids as $id)$out[]=['name'=>get_the_title($id),'url'=>get_permalink($id),'id'=>$id];return $out;}
        $seriesTax=ELHF_H_Settings::resolve_taxonomy($s['series_taxonomy']??'');$terms=$b->context_terms($author,$seriesTax,$limit,true);$out=[];foreach($terms as $t)$out[]=['name'=>$t->name,'url'=>$b->term_url($seriesTax,$t),'id'=>$t->term_id];return $out;
    }
    private function render_series($author,$s){$items=$this->series_items($author,$s,(int)$s['series_drawer_limit']);if(!$items){echo '<p class="n9lh-empty">No Series are allocated to this Author in the selected source.</p>';return;}echo '<div class="n9lh-series-list">';foreach($items as $item)echo '<a href="'.esc_url($item['url']).'"><span>'.$this->icon('series').'</span><strong>'.esc_html($item['name']).'</strong></a>';echo '</div>';}
    private function sort_post_ids($ids,$sort){$ids=array_values(array_unique(array_map('intval',(array)$ids)));usort($ids,function($a,$b)use($sort){if('title'===$sort)return strcasecmp(get_the_title($a),get_the_title($b));if('menu'===$sort){$ma=(int)get_post_field('menu_order',$a);$mb=(int)get_post_field('menu_order',$b);if($ma!==$mb)return $ma<=>$mb;}return strcmp((string)get_post_field('post_date',$b),(string)get_post_field('post_date',$a));});return $ids;}
    private function render_custom_post_type($author,$row){$pt=(string)($row['post_type']??'');if(!$pt||!post_type_exists($pt)){echo '<p class="n9lh-empty">This post type is not currently available.</p>';return;}$limit=max(1,(int)($row['limit']??20));if('author'===($row['scope']??'author'))$ids=ELHF_H_Author::instance()->author_posts($author,$pt,$limit);else{$orderby='date';$order='DESC';if('title'===($row['sort']??'')){$orderby='title';$order='ASC';}elseif('menu'===($row['sort']??'')){$orderby=['menu_order'=>'ASC','title'=>'ASC'];}$ids=get_posts(['post_type'=>$pt,'post_status'=>'publish','posts_per_page'=>$limit,'fields'=>'ids','no_found_rows'=>true,'orderby'=>$orderby,'order'=>$order]);}$ids=array_slice($this->sort_post_ids($ids,$row['sort']??'newest'),0,$limit);if(!$ids){echo '<p class="n9lh-empty">No published items found.</p>';return;}echo '<div class="n9lh-custom-list">';foreach($ids as $id)echo '<a href="'.esc_url(get_permalink($id)).'"><span>'.$this->icon('chev').'</span><strong>'.esc_html(get_the_title($id)).'</strong></a>';echo '</div>';}
    private function render_custom_taxonomy($author,$row){$tax=(string)($row['taxonomy']??'');if(!$tax||!taxonomy_exists($tax)){echo '<p class="n9lh-empty">This taxonomy is not currently available.</p>';return;}$limit=max(1,(int)($row['limit']??20));$b=ELHF_H_Author::instance();if('author'===($row['scope']??'author'))$terms=$b->context_terms($author,$tax,$limit,'title'!==($row['sort']??''));else{$args=['taxonomy'=>$tax,'hide_empty'=>false,'number'=>$limit];if('title'===($row['sort']??'')){$args['orderby']='name';$args['order']='ASC';}else{$args['orderby']='term_id';$args['order']='DESC';}$terms=get_terms($args);if(is_wp_error($terms))$terms=[];}if(!$terms){echo '<p class="n9lh-empty">No terms found.</p>';return;}echo '<div class="n9lh-custom-list">';foreach($terms as $t)echo '<a href="'.esc_url($b->term_url($tax,$t)).'"><span>'.$this->icon('chev').'</span><strong>'.esc_html($t->name).'</strong></a>';echo '</div>';}
    private function render_manual_links($row){$links=(array)($row['links']??[]);if(!$links){echo '<p class="n9lh-empty">No manual links have been added.</p>';return;}echo '<div class="n9lh-custom-list">';foreach($links as $ln){$text=trim((string)($ln['text']??''));$url=trim((string)($ln['url']??''));if(!$text&&!$url)continue;if($url)echo '<a href="'.esc_url($url).'"'.('new'===($ln['target']??'')?' target="_blank" rel="noopener"':'').'><span>'.$this->icon('chev').'</span><strong>'.esc_html($text?:$url).'</strong></a>';else echo '<span class="n9lh-manual-text">'.esc_html($text).'</span>';}echo '</div>';}
    private function render_assigned_wp_menu($row){
        $menuId=absint($row['nav_menu_id']??0);
        if(!$menuId||!function_exists('wp_nav_menu'))return false;
        $menu=wp_get_nav_menu_object($menuId);
        if(!$menu||is_wp_error($menu))return false;
        $html=wp_nav_menu(['menu'=>$menuId,'container'=>false,'fallback_cb'=>false,'echo'=>false,'depth'=>4,'items_wrap'=>'<ul class="n9lh-wp-menu">%3$s</ul>']);
        if(!is_string($html)||''===trim($html)){echo '<p class="n9lh-empty">This WordPress menu has no items yet.</p>';return true;}
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core wp_nav_menu output.
        return true;
    }
    private function section_is_configured($row){
        if('yes'!==($row['enabled']??''))return false;
        $kind=$row['kind']??'none';
        if('wp_menu'===$kind)return absint($row['nav_menu_id']??0)>0;
        if('post_type'===$kind)return !empty($row['post_type']);
        if('taxonomy'===$kind)return !empty($row['taxonomy']);
        if('manual_links'===$kind)return !empty($row['links']);
        if(in_array($kind,['video','form'],true))return true;
        return false;
    }
    private function first_section_uid($s,$kind){foreach((array)($s['menu_builder']??[]) as $row)if($this->section_is_configured($row)&&$kind===($row['kind']??''))return (string)($row['uid']??'');return '';}
    private function render_menu_builder($author,$d,$s){
        $rows=(array)($s['menu_builder']??[]);$showIcons='yes'===($s['show_section_icons']??'yes');$default=(string)($s['menu_default_open_uid']??'');$hasDefault=false;
        foreach($rows as $r)if($this->section_is_configured($r)&&($r['uid']??'')===$default)$hasDefault=true;
        if(!$hasDefault){foreach($rows as $r)if($this->section_is_configured($r)){$default=(string)($r['uid']??'');break;}}
        foreach($rows as $row){
            if(!$this->section_is_configured($row))continue;
            $kind=$row['kind']??'none';$uid=(string)($row['uid']??sanitize_key($kind));$label=trim((string)($row['label']??''));if(''===$label)$label=self::fallback_section_label($kind);$icon=$row['icon']??'series';$iconUrl=$row['icon_url']??'';
            $cb=function()use($kind,$author,$s,$row){
                if('wp_menu'===$kind){if(!$this->render_assigned_wp_menu($row))echo '<p class="n9lh-empty">The selected WordPress menu is unavailable.</p>';}
                elseif('post_type'===$kind)$this->render_custom_post_type($author,$row);
                elseif('taxonomy'===$kind)$this->render_custom_taxonomy($author,$row);
                elseif('manual_links'===$kind)$this->render_manual_links($row);
                elseif('video'===$kind)echo '<button type="button" class="n9lh-section-launch" data-n9lh-popup-open="video">'.esc_html($s['video_popup_title']??'Video').'</button>';
                elseif('form'===$kind)echo '<button type="button" class="n9lh-section-launch" data-n9lh-popup-open="form">'.esc_html($s['form_popup_title']??'Contact').'</button>';
            };
            $this->section($label,$uid,$icon,$iconUrl,$cb,$uid===$default,$showIcons);
        }
    }
    private static function fallback_section_label($kind){$map=['wp_menu'=>'Menu','post_type'=>'Posts','taxonomy'=>'Taxonomy','manual_links'=>'Links','video'=>'Video','form'=>'Contact'];return $map[$kind]??'Section';}
    private function action_style($s,$p){if('yes'===($s['dark_mode']??''))return [$p['soft'],$p['softfg'],$p['border'],$p['surface'],$p['surfacefg']];$style=$s['closed_action_style']??'template';if('black_on_white'===$style)return ['#FFFFFF','#111111','#111111','#111111','#FFFFFF'];if('white_on_black'===$style)return ['#111111','#FFFFFF','#111111','#FFFFFF','#111111'];if('custom'===$style)return [$s['closed_icon_bg'],$s['closed_icon_fg'],$s['closed_icon_border'],$s['closed_icon_hover_bg'],$s['closed_icon_hover_fg']];return [$p['surface'],$p['surfacefg'],$p['border'],$p['text'],$p['textfg']];}
    private function render_header_actions($s,$d){$authorUid=$this->first_section_uid($s,'author');foreach((array)($s['header_actions']??[]) as $row){if('yes'!==($row['enabled']??''))continue;$kind=$row['kind']??'link';$label=trim((string)($row['label']??''))?:ucwords($kind);$ico=$this->icon_markup($row['icon']??'menu',$row['icon_url']??'');$target='new'===($row['target']??'')?' target="_blank" rel="noopener"':'';if('profile'===$kind&&!empty($d['profile'])){echo '<a class="n9lh-action author" href="'.esc_url($d['profile']).'" aria-label="'.esc_attr($label).'">'.$ico.'</a>';}elseif('menu'===$kind){echo '<button class="n9lh-menu" type="button" data-n9lh-toggle aria-expanded="false" aria-controls="n9lh-panel" aria-label="'.esc_attr($label).'">'.$ico.'</button>';}elseif('whatsapp'===$kind&&!empty($d['whatsapp']))echo '<a class="n9lh-action" href="'.esc_url($d['whatsapp']).'" target="_blank" rel="noopener" aria-label="'.esc_attr($label).'">'.$ico.'</a>';elseif('email'===$kind&&!empty($d['email']))echo '<a class="n9lh-action" href="mailto:'.esc_attr($d['email']).'" aria-label="'.esc_attr($label).'">'.$ico.'</a>';elseif('video'===$kind&&'yes'===($s['show_video_popup']??''))echo '<button class="n9lh-action" type="button" data-n9lh-popup-open="video" aria-label="'.esc_attr($label).'">'.$ico.'</button>';elseif('form'===$kind&&'yes'===($s['show_form_popup']??''))echo '<button class="n9lh-action" type="button" data-n9lh-popup-open="form" aria-label="'.esc_attr($label).'">'.$ico.'</button>';elseif('home'===$kind)echo '<a class="n9lh-action" href="'.esc_url(home_url('/')).'" aria-label="'.esc_attr($label).'">'.$ico.'</a>';elseif('login'===$kind){$url=is_user_logged_in()?admin_url('profile.php'):wp_login_url();echo '<a class="n9lh-action" href="'.esc_url($url).'" aria-label="'.esc_attr($label).'">'.$ico.'</a>';}elseif('top'===$kind)echo '<button class="n9lh-action" type="button" data-n9lh-top aria-label="'.esc_attr($label).'">'.$ico.'</button>';elseif('event'===$kind)echo '<button class="n9lh-action" type="button" data-n9lh-event="'.esc_attr($row['event_key']??'').'" aria-label="'.esc_attr($label).'">'.$ico.'</button>';elseif('link'===$kind&&!empty($row['url']))echo '<a class="n9lh-action" href="'.esc_url($row['url']).'"'.$target.' aria-label="'.esc_attr($label).'">'.$ico.'</a>';}}

    private function render_popup($kind,$s,$d,$author){
        if('video'===$kind){if('yes'!==($s['show_video_popup']??''))return;$content='';if('shortcode'===$s['video_source']&&!empty($s['video_shortcode']))$content=do_shortcode($s['video_shortcode']);elseif(!empty($s['video_url'])){$content=wp_oembed_get($s['video_url']);if(!$content)$content='<a class="n9lh-popup-fallback" href="'.esc_url($s['video_url']).'" target="_blank" rel="noopener">Open video</a>';}echo '<div class="n9lh-popup" data-n9lh-popup="video" aria-hidden="true"><button class="n9lh-popup-shade" type="button" data-n9lh-popup-close aria-label="Close video"></button><section class="n9lh-popup-box" role="dialog" aria-modal="true" aria-label="'.esc_attr($s['video_popup_title']).'"><header><strong>'.esc_html($s['video_popup_title']).'</strong><button type="button" data-n9lh-popup-close aria-label="Close">'.$this->icon($s['close_icon']).'</button></header><div class="n9lh-popup-content">'.($content?:'<p class="n9lh-empty">No video has been configured.</p>').'</div></section></div>';return;}
        if('form'===$kind){if('yes'!==($s['show_form_popup']??''))return;echo '<div class="n9lh-popup" data-n9lh-popup="form" aria-hidden="true"><button class="n9lh-popup-shade" type="button" data-n9lh-popup-close aria-label="Close form"></button><section class="n9lh-popup-box" role="dialog" aria-modal="true" aria-label="'.esc_attr($s['form_popup_title']).'"><header><strong>'.esc_html($s['form_popup_title']).'</strong><button type="button" data-n9lh-popup-close aria-label="Close">'.$this->icon($s['close_icon']).'</button></header><div class="n9lh-popup-content">';if('shortcode'===$s['form_mode']&&!empty($s['form_shortcode']))echo do_shortcode($s['form_shortcode']);else $this->render_contact_form($d,$author,$s);echo '</div></section></div>';}
    }

    public function render(){if($this->rendered||is_admin()||(function_exists('ninecode_theme_surface_suppresses')&&ninecode_theme_surface_suppresses('header'))||! ELHF_H_Settings::is_enabled())return;$this->rendered=true;$this->output(false);}
    public function footer_fallback(){if($this->rendered||is_admin()||(function_exists('ninecode_theme_surface_suppresses')&&ninecode_theme_surface_suppresses('header'))||! ELHF_H_Settings::is_enabled())return;$this->rendered=true;$this->output(true);}
    private function output($fallback){
        $s=$this->s();$b=ELHF_H_Author::instance();$author=$b->id();$d=$b->data($author);$id=$this->identity($s);$p=$this->palette($s);$act=$this->action_style($s,$p);$series=$this->series_items($author,$s,max(2,(int)$s['series_chip_count']));$site_address=$this->site_address($s);
        $style='--p:'.$p['p'].';--pfg:'.$p['pfg'].';--a:'.$p['a'].';--afg:'.$p['afg'].';--surface:'.$p['surface'].';--surfacefg:'.$p['surfacefg'].';--soft:'.$p['soft'].';--softfg:'.$p['softfg'].';--text:'.$p['text'].';--textfg:'.$p['textfg'].';--border:'.$p['border'].';--dur:'.absint($s['duration']).'ms;--drop:'.absint($s['panel_width']).'px;--drawer:'.absint($s['drawer_width']).'px;--logo:'.absint($s['logo_width']).'px;--mainh:'.absint($s['header_height']).'px;--padv:'.absint($s['header_padding_v']).'px;--padh:'.absint($s['header_padding_h']).'px;--gap:'.absint($s['header_spacing']).'px;--metah:'.absint($s['meta_row_height']).'px;--taxh:'.absint($s['taxonomy_row_height']).'px;--namesz:'.absint($s['site_name_size']).'px;--subsz:'.absint($s['subtitle_size']).'px;--metasz:'.absint($s['meta_text_size']).'px;--taxlabelsz:'.absint($s['taxonomy_label_size']).'px;--taxtermsz:'.absint($s['taxonomy_term_size']).'px;--action:'.absint($s['action_button_size']).'px;--actionicon:'.absint($s['action_icon_size']).'px;--panelpad:'.absint($s['panel_padding']).'px;--sectiongap:'.absint($s['panel_section_gap']).'px;--sectionsz:'.absint($s['drawer_section_font_size']).'px;--itemsz:'.absint($s['drawer_item_font_size']).'px;--popupw:'.absint($s['popup_width']).'px;--popupmax:'.absint($s['popup_max_height']).'vh;--actionbg:'.$act[0].';--actionfg:'.$act[1].';--actionborder:'.$act[2].';--actionhoverbg:'.$act[3].';--actionhoverfg:'.$act[4].';--actionradius:'.absint($s['closed_icon_radius']).'px;';
        $style.='--barbg:'.$p['barbg'].';--barfg:'.$p['barfg'].';--panelbg:'.$p['panelbg'].';--panelfg:'.$p['panelfg'].';--layer:'.absint($s['layer_level']).';';
        ?>
        <div class="n9lh-shell elhh-shell elhh-style-<?php echo esc_attr(ELHF_Design::style_slug());?> n9lh-preset-<?php echo esc_attr($s['preset']);?><?php echo 'yes'===($s['dark_mode']??'')?' is-dark-mode':'';?><?php echo $fallback?' is-footer-fallback':'';?>" data-n9lh data-dark-strength="<?php echo absint($s['dark_mode_strength']??55);?>" data-mode="<?php echo esc_attr($s['desktop_mode']);?>" data-mobile-mode="<?php echo esc_attr($s['mobile_mode']);?>" data-animation="<?php echo esc_attr($s['animation']);?>" data-default-section="<?php echo esc_attr($s['menu_default_open_uid']);?>" data-site-lines="<?php echo esc_attr($s['site_name_lines']);?>" data-subtitle-lines="<?php echo esc_attr($s['subtitle_lines']);?>" data-meta-overflow="<?php echo esc_attr($s['meta_overflow_mode']);?>" data-tax-overflow="<?php echo esc_attr($s['taxonomy_overflow_mode']);?>" data-truncate-series="<?php echo 'yes'===$s['truncate_series_text']?'1':'0';?>" data-truncate-taxonomy="<?php echo 'yes'===$s['truncate_taxonomy_text']?'1':'0';?>" style="<?php echo esc_attr($style);?>">
          <div class="n9lh-bar">
            <?php if('yes'===$s['show_logo']):?><a class="n9lh-home" href="<?php echo esc_url(home_url('/'));?>" aria-label="Home"><?php if($id['logo']):?><img src="<?php echo esc_url($id['logo']);?>" alt=""><?php else:?><span class="n9lh-logo-fallback">9</span><?php endif;?></a><?php endif;?>
            <?php if($id['name']||$id['desc']):?><div class="n9lh-identity"><?php if($id['name']):?><strong><?php echo esc_html($id['name']);?></strong><?php endif;?><?php if($id['desc']):?><small><?php echo esc_html($id['desc']);?></small><?php endif;?></div><?php endif;?>
            <div class="n9lh-actions"><?php $this->render_header_actions($s,$d);?></div>
          </div>
          <?php if('yes'===$s['show_site_address']||'yes'===$s['show_series_chips']):?><div class="n9lh-meta-row"><?php if('yes'===$s['show_site_address']):?><a class="n9lh-site-address" href="<?php echo esc_url($s['site_address_link']?:home_url('/'));?>"><?php echo esc_html($site_address);?></a><?php endif;?><?php if('yes'===$s['show_series_chips']):?><div class="n9lh-chips"><?php foreach(array_slice($series,0,2) as $item):?><a href="<?php echo esc_url($item['url']);?>"><?php echo esc_html($item['name']);?></a><?php endforeach;?></div><?php endif;?></div><?php endif;?>
          <?php $this->render_taxonomy_strip($author,$s); ?>
          <button class="n9lh-overlay" type="button" data-n9lh-close tabindex="-1" aria-label="Close menu"></button>
          <aside id="n9lh-panel" class="n9lh-panel" aria-hidden="true"><div class="n9lh-panel-head"><button class="n9lh-close" type="button" data-n9lh-close aria-label="Close"><?php echo $this->icon($s['close_icon']);?></button></div>
          <nav class="n9lh-nav" aria-label="Site navigation"><?php $this->render_menu_builder($author,$d,$s);?></nav></aside>
          <?php $this->render_popup('video',$s,$d,$author);$this->render_popup('form',$s,$d,$author);?>
        </div>
        <?php
    }
}
