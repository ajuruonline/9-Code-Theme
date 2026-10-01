<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ELHF_F_Sitewide {
    private static $instance = null;
    public static function instance(){ return self::$instance ?: ( self::$instance = new self() ); }
    private function __construct(){
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 20 );
        add_action( 'wp_head', [ $this, 'suppress_theme_footer_css' ], 99 );
        add_action( 'wp_footer', [ $this, 'render' ], 2 );
    }
    private function combined_active(){ return defined('BAEHF_FILE') || class_exists('BAEHF_Plugin'); }
    public function enqueue(){
        if($this->combined_active()||(function_exists('ninecode_theme_surface_suppresses')&&ninecode_theme_surface_suppresses('footer')))return;
        if ( ! ELHF_F_Settings::is_enabled() ) return;
        $s=ELHF_Design::footer_settings(ELHF_F_Settings::all());
        wp_enqueue_style('n9f-frontend');

        // Dashicons are only required on the public site when the manager mark
        // is using the Dashicon fallback rather than an uploaded icon/image.
        $needs_manager = 'yes'===($s['footer_manager_enabled']??'') && ( 'native'===($s['footer_mode']??'native') || 'yes'===($s['footer_append_endcap_to_elementor']??'') );
        if($needs_manager && empty($s['footer_manager_image_override_url']) && empty($s['footer_manager_icon_url']) && !empty($s['footer_manager_icon_dashicon'])) wp_enqueue_style('dashicons');

        // The only native frontend JavaScript is Back to Top, so avoid loading
        // it when that control is disabled.
        if('yes'===($s['footer_back_to_top']??'') && ( 'native'===($s['footer_mode']??'native') || 'yes'===($s['footer_append_endcap_to_elementor']??'') )) wp_enqueue_script('n9f-frontend');
    }
    public function suppress_theme_footer_css(){
        if($this->combined_active()||(function_exists('ninecode_theme_surface_suppresses')&&ninecode_theme_surface_suppresses('footer')))return;
        if ( ! ELHF_F_Settings::is_enabled() ) return;
        $s=ELHF_Design::footer_settings(ELHF_F_Settings::all());
        if('yes'!==($s['suppress_theme_footer']??''))return;
        $selectors=array_filter(array_map('trim',explode(',',$s['theme_footer_selectors']??'')));
        $safe=[];
        foreach($selectors as $selector){
            // Conservative CSS-selector character allowlist: prevents a settings
            // value from breaking out of the style rule while supporting normal
            // theme IDs/classes, attributes and combinators.
            $selector=preg_replace('/[^a-zA-Z0-9_\-#\.\,\s>+~:\[\]\(\)="\'\*]/','',$selector);
            if($selector)$safe[]=$selector;
        }
        if(!$safe)return;
        echo '<style id="n9f-suppress-theme-footer">'.implode(',',$safe).'{display:none!important;}</style>';
    }
    private function render_elementor( $id ) { if(!$id||!did_action('elementor/loaded')||!class_exists('\\Elementor\\Plugin'))return false;try{$html=\Elementor\Plugin::$instance->frontend->get_builder_content_for_display($id,true);if(!$html)return false;echo '<div class="n9f-elementor-footer-override">'.$html.'</div>';return true;}catch(\Throwable $e){return false;} }
    public function render(){
        if($this->combined_active()||(function_exists('ninecode_theme_surface_suppresses')&&ninecode_theme_surface_suppresses('footer')))return;if ( ! ELHF_F_Settings::is_enabled() ) return;$s=ELHF_Design::footer_settings(ELHF_F_Settings::all());$renderer=ELHF_F_Renderer::instance();
        if('elementor'===($s['footer_mode']??'native')){
            $ok=$this->render_elementor(absint($s['elementor_footer_template_id']??0));
            if($ok){
                $show_endcap = 'yes' === ( $s['footer_append_endcap_to_elementor'] ?? '' );
                if($show_endcap){$color_class=$renderer->footer_color_class($s);$style=$renderer->footer_style_vars($s);echo '<div class="n9f-footer elhh-footer elhh-style-'.esc_attr(ELHF_Design::style_slug()).' n9f-footer--companion-only '.esc_attr($color_class).'" style="'.esc_attr($style).'"><div class="n9f-footer__inner">';$renderer->render_endcap($s);echo '</div></div>';}
                return;
            }
        }
        $renderer->render_footer($s,'native');
    }
}
