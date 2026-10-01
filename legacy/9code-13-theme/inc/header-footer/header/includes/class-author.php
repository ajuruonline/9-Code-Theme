<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class ELHF_H_Author {
    private static $instance;
    public static function instance(){ return self::$instance ?: ( self::$instance = new self() ); }
    private function __construct(){}
    public function id(){
        $forced=(int)apply_filters('n9lh_forced_author_id',0); if($forced&&get_userdata($forced))return $forced;
        if(is_author()){ $id=(int)get_queried_object_id(); if($id&&get_userdata($id))return $id; }
        if(is_singular()){ $post_id=(int)get_queried_object_id(); $id=(int)get_post_field('post_author',$post_id); if($id&&get_userdata($id))return $id; }
        $home=(int)ELHF_H_Settings::get('home_author_id'); if($home&&get_userdata($home))return $home;
        $users=get_users(['number'=>1,'orderby'=>'ID','order'=>'ASC','fields'=>'ID']); return $users?absint($users[0]):0;
    }
    private function meta($id,$keys){ foreach((array)$keys as $k){$v=trim((string)get_user_meta($id,$k,true));if($v!=='')return $v;} return ''; }
    public function data($id){
        $u=$id?get_userdata($id):false; if(!$u)return [];
        $name=$u->display_name?:$u->user_login;
        $image=get_avatar_url($id,['size'=>180]); $profile=get_author_posts_url($id);
        $email=$this->meta($id,['public_email','contact_email']); if(!$email)$email=$u->user_email;
        $whatsapp=$this->meta($id,['whatsapp_url','whatsapp','phone_whatsapp']);
        if($whatsapp && 0!==strpos($whatsapp,'http')){$digits=preg_replace('/\D+/','',$whatsapp);$whatsapp=$digits?'https://wa.me/'.$digits:'';}
        return ['id'=>$id,'name'=>$name,'image'=>$image,'profile'=>$profile,'email'=>$email,'whatsapp'=>$whatsapp,
            'title'=>$this->meta($id,['professional_title','academic_title','job_title']),
            'qualification'=>$this->meta($id,['qualification','credentials']),
            'organisation'=>$this->meta($id,['organisation','organization','institution'])];
    }
    public function author_posts($author,$type,$limit=20){
        if(!$type||!post_type_exists($type))return [];$args=['post_type'=>$type,'post_status'=>'publish','posts_per_page'=>max(1,(int)$limit),'fields'=>'ids','no_found_rows'=>true,'orderby'=>['menu_order'=>'ASC','date'=>'DESC']];
        if($author)$args['author']=(int)$author;return array_map('intval',(array)get_posts($args));
    }
    public function courses($author,$limit=20){return $this->author_posts($author,(string)ELHF_H_Settings::get('course_post_type'),$limit);}
    public function modules($author,$limit=220){return $this->author_posts($author,(string)ELHF_H_Settings::get('module_post_type'),$limit);}
    public function lessons($author,$limit=320){return $this->author_posts($author,(string)ELHF_H_Settings::get('lesson_post_type'),$limit);}
    public function course_modules($course,$limit=40){return $this->children_or_meta((string)ELHF_H_Settings::get('module_post_type'),$course,(string)ELHF_H_Settings::get('module_course_meta'),$limit);}
    public function module_lessons($module,$limit=40){return $this->children_or_meta((string)ELHF_H_Settings::get('lesson_post_type'),$module,(string)ELHF_H_Settings::get('lesson_module_meta'),$limit);}
    private function children_or_meta($pt,$parent,$meta,$limit){if(!$pt||!post_type_exists($pt))return [];$args=['post_type'=>$pt,'post_status'=>'publish','posts_per_page'=>max(1,(int)$limit),'fields'=>'ids','no_found_rows'=>true,'orderby'=>['menu_order'=>'ASC','date'=>'ASC']];if($meta){$args['meta_key']=$meta;$args['meta_value']=(int)$parent;}else{$args['post_parent']=(int)$parent;}return array_map('intval',(array)get_posts($args));}
    public function module_course($id){$meta=(string)ELHF_H_Settings::get('module_course_meta');return $meta?absint(get_post_meta($id,$meta,true)):absint(get_post_field('post_parent',$id));}
    public function lesson_module($id){$meta=(string)ELHF_H_Settings::get('lesson_module_meta');return $meta?absint(get_post_meta($id,$meta,true)):absint(get_post_field('post_parent',$id));}
    public function context_terms($author,$tax,$limit=8,$hide_empty=true){
        if(!$tax||!taxonomy_exists($tax))return [];$post_id=is_singular()?absint(get_queried_object_id()):0;
        if($post_id&&is_object_in_taxonomy(get_post_type($post_id),$tax)){$terms=wp_get_post_terms($post_id,$tax,['orderby'=>'name','order'=>'ASC']);if(!is_wp_error($terms)&&$terms)return array_slice($terms,0,max(1,(int)$limit));}
        $terms=get_terms(['taxonomy'=>$tax,'hide_empty'=>(bool)$hide_empty,'number'=>max(1,(int)$limit),'orderby'=>'name','order'=>'ASC']);return is_wp_error($terms)?[]:$terms;
    }
    public function taxonomy_label($tax){$o=taxonomy_exists($tax)?get_taxonomy($tax):null;return $o&&!empty($o->labels->singular_name)?(string)$o->labels->singular_name:ucwords(str_replace(['_','-'],' ',(string)$tax));}
    public function term_url($tax,$term){$url=get_term_link($term,$tax);return is_wp_error($url)?home_url('/'):$url;}
}
