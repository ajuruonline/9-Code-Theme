<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ELHF_H_Contact {
    private static $instance;
    public static function instance(){ return self::$instance ?: ( self::$instance = new self() ); }
    private function __construct(){
        add_action( 'admin_post_n9lh_contact', [ $this, 'submit' ] );
        add_action( 'admin_post_nopriv_n9lh_contact', [ $this, 'submit' ] );
    }
    public function submit(){
        $back = wp_get_referer() ?: home_url('/');
        $nonce = isset($_POST['n9lh_nonce']) ? sanitize_text_field( wp_unslash($_POST['n9lh_nonce']) ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'n9lh_contact' ) ) { wp_safe_redirect( add_query_arg('n9contact','invalid',$back) ); exit; }
        if ( ! empty($_POST['website']) ) { wp_safe_redirect( add_query_arg('n9contact','sent',$back) ); exit; }
        $author = absint($_POST['author_id'] ?? 0);
        $data = ELHF_H_Author::instance()->data($author);
        if ( ! $author || empty($data['email']) || ! is_email($data['email']) ) { wp_safe_redirect( add_query_arg('n9contact','unavailable',$back) ); exit; }
        $name = sanitize_text_field( wp_unslash($_POST['name'] ?? '') );
        $email = sanitize_email( wp_unslash($_POST['email'] ?? '') );
        $message = sanitize_textarea_field( wp_unslash($_POST['message'] ?? '') );
        if ( '' === $name || ! is_email($email) || strlen($message) < 5 ) { wp_safe_redirect( add_query_arg('n9contact','invalid',$back) ); exit; }
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field( wp_unslash($_SERVER['REMOTE_ADDR']) ) : 'unknown';
        $rate = 'n9lh_contact_' . md5($ip.'|'.$author);
        if ( get_transient($rate) ) { wp_safe_redirect( add_query_arg('n9contact','rate',$back) ); exit; }
        set_transient($rate,1,MINUTE_IN_SECONDS);
        $subject = sprintf( 'Website message for %s', $data['name'] );
        $body = "From: {$name}\nEmail: {$email}\nPage: {$back}\n\n{$message}";
        $headers = [ 'Reply-To: '.$name.' <'.$email.'>' ];
        $sent = wp_mail( $data['email'], $subject, $body, $headers );
        wp_safe_redirect( add_query_arg('n9contact',$sent?'sent':'failed',$back) ); exit;
    }
}
