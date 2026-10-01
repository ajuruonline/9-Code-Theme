<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class ELHF_F_Widget_Global_Footer extends \Elementor\Widget_Base {
    public function get_name(){return 'n9f-global-footer';}
    public function get_title(){return '9 Footer — Global Copy';}
    public function get_icon(){return 'eicon-footer';}
    public function get_categories(){return ['nine-footer'];}
    public function get_style_depends(){return ELHF_F_Settings::is_enabled() ? ['n9f-frontend'] : [];}
    public function get_script_depends(){ if ( ! ELHF_F_Settings::is_enabled() ) return []; $s=ELHF_F_Settings::all(); return 'yes'===($s['footer_back_to_top']??'') ? ['n9f-frontend'] : []; }
    protected function register_controls(){ $this->start_controls_section('info',['label'=>'Global 9 Footer']);$this->add_control('notice',['type'=>\Elementor\Controls_Manager::RAW_HTML,'raw'=>'Renders the current WordPress → 9 Footer settings. Edit the normal footer in WordPress; Elementor is optional.','content_classes'=>'elementor-panel-alert elementor-panel-alert-info']);$this->end_controls_section(); }
    protected function render(){ if ( ! ELHF_F_Settings::is_enabled() ) return; ELHF_F_Renderer::instance()->render_footer(ELHF_F_Settings::all(),'elementor-'.$this->get_id());}
}
