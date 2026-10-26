<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Minimal Google Drive OAuth + upload boundary. Credentials and refresh token
 * remain server-side in WordPress options; packages never pass through a
 * third-party Open Scholar server. */
class Nine_Backup_Google_Drive {
    const OPTION = 'nine_backup_google_drive';
    const FILES_OPTION = 'nine_backup_google_drive_files';

    public static function settings() { return wp_parse_args( get_option( self::OPTION, array() ), array( 'client_id'=>'', 'client_secret'=>'', 'folder_id'=>'', 'refresh_token'=>'', 'access_token'=>'', 'expires_at'=>0 ) ); }
    public static function configured() { $s = self::settings(); return '' !== $s['client_id'] && '' !== $s['client_secret']; }
    public static function connected() { $s = self::settings(); return self::configured() && '' !== $s['refresh_token']; }
    public static function redirect_uri() { return admin_url( 'admin-post.php?action=nine_backup_google_drive_callback' ); }

    public static function authorize_url() {
        if ( ! self::configured() ) { return ''; }
        $state = wp_generate_password( 32, false, false );
        set_transient( 'nine_backup_google_drive_state_' . get_current_user_id(), $state, 10 * MINUTE_IN_SECONDS );
        return add_query_arg( array( 'client_id'=>self::settings()['client_id'], 'redirect_uri'=>self::redirect_uri(), 'response_type'=>'code', 'scope'=>'https://www.googleapis.com/auth/drive.file', 'access_type'=>'offline', 'prompt'=>'consent', 'state'=>$state ), 'https://accounts.google.com/o/oauth2/v2/auth' );
    }

    public static function finish_authorization( $code, $state ) {
        $expected = (string) get_transient( 'nine_backup_google_drive_state_' . get_current_user_id() );
        delete_transient( 'nine_backup_google_drive_state_' . get_current_user_id() );
        if ( ! $expected || ! hash_equals( $expected, (string) $state ) ) { return new WP_Error( 'nine_drive_state', 'Google Drive connection could not be verified. Start the connection again.' ); }
        $s = self::settings();
        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', array( 'timeout'=>25, 'body'=>array( 'code'=>(string)$code, 'client_id'=>$s['client_id'], 'client_secret'=>$s['client_secret'], 'redirect_uri'=>self::redirect_uri(), 'grant_type'=>'authorization_code' ) ) );
        return self::save_token_response( $response, true );
    }

    private static function save_token_response( $response, $require_refresh = false ) {
        if ( is_wp_error( $response ) ) { return $response; }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) >= 300 || empty( $body['access_token'] ) ) { return new WP_Error( 'nine_drive_token', sanitize_text_field( $body['error_description'] ?? $body['error'] ?? 'Google rejected the Drive connection.' ) ); }
        $s = self::settings();
        if ( ! empty( $body['refresh_token'] ) ) { $s['refresh_token'] = $body['refresh_token']; }
        if ( $require_refresh && empty( $s['refresh_token'] ) ) { return new WP_Error( 'nine_drive_refresh', 'Google did not provide a refresh token. Disconnect this app in Google, then connect again and approve access.' ); }
        $s['access_token'] = $body['access_token']; $s['expires_at'] = time() + max( 60, absint( $body['expires_in'] ?? 3600 ) - 60 );
        update_option( self::OPTION, $s, false ); return true;
    }

    private static function access_token() {
        $s = self::settings();
        if ( ! self::connected() ) { return new WP_Error( 'nine_drive_disconnected', 'Google Drive is not connected.' ); }
        if ( ! empty( $s['access_token'] ) && (int) $s['expires_at'] > time() ) { return $s['access_token']; }
        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', array( 'timeout'=>25, 'body'=>array( 'refresh_token'=>$s['refresh_token'], 'client_id'=>$s['client_id'], 'client_secret'=>$s['client_secret'], 'grant_type'=>'refresh_token' ) ) );
        $saved = self::save_token_response( $response ); if ( is_wp_error( $saved ) ) { return $saved; }
        return self::settings()['access_token'];
    }

    public static function upload( $path ) {
        if ( ! is_file( $path ) || ! is_readable( $path ) ) { return new WP_Error( 'nine_drive_file', 'The finished backup file cannot be read for Google Drive upload.' ); }
        $token = self::access_token(); if ( is_wp_error( $token ) ) { return $token; }
        $s = self::settings();
        $metadata = array( 'name'=>basename( $path), 'mimeType'=>'application/zip' ); if ( ! empty( $s['folder_id'] ) ) { $metadata['parents'] = array( $s['folder_id'] ); }
        /* Prefer a resumable, file-streaming request. This avoids loading a
         * complete site archive into PHP memory on Bluehost/shared hosting. */
        if ( function_exists( 'curl_init' ) ) {
            $start = wp_remote_post( 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,name,webViewLink', array( 'timeout'=>30, 'headers'=>array( 'Authorization'=>'Bearer '.$token, 'Content-Type'=>'application/json; charset=UTF-8', 'X-Upload-Content-Type'=>'application/zip', 'X-Upload-Content-Length'=>(string)filesize($path) ), 'body'=>wp_json_encode($metadata) ) );
            $session = !is_wp_error($start) ? wp_remote_retrieve_header($start,'location') : '';
            if ( $session ) {
                $handle = @fopen($path,'rb'); $curl = $handle ? curl_init($session) : false;
                if ( $curl ) {
                    curl_setopt_array($curl,array(CURLOPT_UPLOAD=>true,CURLOPT_INFILE=>$handle,CURLOPT_INFILESIZE=>filesize($path),CURLOPT_HTTPHEADER=>array('Content-Type: application/zip','Content-Length: '.filesize($path)),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>300));
                    $raw=curl_exec($curl); $code=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE); $error=curl_error($curl); curl_close($curl); fclose($handle);
                    $data=json_decode((string)$raw,true);
                    if ( $code>=200 && $code<300 && !empty($data['id']) ) { return self::remember_file($path,$data); }
                    $message = $data['error']['message'] ?? ( $error ? $error : 'Google Drive resumable upload failed.' );
                    return new WP_Error('nine_drive_upload',sanitize_text_field($message));
                }
                if ($handle) { fclose($handle); }
            }
            if ( is_wp_error($start) ) { return $start; }
        }
        /* Small-package fallback for servers where cURL is unavailable. */
        $boundary = 'osch-' . wp_generate_password( 24, false, false );
        $contents = @file_get_contents( $path ); if ( false === $contents ) { return new WP_Error( 'nine_drive_read', 'Could not read backup package for Google Drive upload.' ); }
        $body = "--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n" . wp_json_encode( $metadata ) . "\r\n--{$boundary}\r\nContent-Type: application/zip\r\n\r\n" . $contents . "\r\n--{$boundary}--";
        $response = wp_remote_post( 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,webViewLink', array( 'timeout'=>120, 'headers'=>array( 'Authorization'=>'Bearer ' . $token, 'Content-Type'=>'multipart/related; boundary=' . $boundary ), 'body'=>$body ) );
        unset( $contents, $body );
        if ( is_wp_error( $response ) ) { return $response; }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) >= 300 || empty( $data['id'] ) ) { return new WP_Error( 'nine_drive_upload', sanitize_text_field( $data['error']['message'] ?? 'Google Drive rejected the backup upload.' ) ); }
        return self::remember_file($path,$data);
    }

    private static function remember_file( $path, array $data ) { $files = get_option( self::FILES_OPTION, array() ); $files[ basename( $path ) ] = array( 'id'=>$data['id'], 'name'=>$data['name'] ?? basename($path), 'url'=>$data['webViewLink'] ?? '', 'uploaded_at'=>time() ); update_option( self::FILES_OPTION, $files, false ); return array( 'status'=>'uploaded', 'id'=>$data['id'], 'url'=>$data['webViewLink'] ?? '' ); }

    public static function file( $basename ) { $files = get_option( self::FILES_OPTION, array() ); return isset( $files[$basename] ) ? $files[$basename] : array(); }
    public static function delete( $basename ) {
        $file = self::file( $basename ); if ( empty( $file['id'] ) ) { return true; }
        $token = self::access_token(); if ( is_wp_error($token) ) { return $token; }
        $response = wp_remote_request( 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $file['id'] ), array( 'method'=>'DELETE', 'timeout'=>30, 'headers'=>array( 'Authorization'=>'Bearer ' . $token ) ) );
        if ( is_wp_error($response) || ! in_array( wp_remote_retrieve_response_code($response), array(200,204,404), true ) ) { return is_wp_error($response) ? $response : new WP_Error( 'nine_drive_delete', 'Google Drive could not delete the remote copy.' ); }
        $files = get_option( self::FILES_OPTION, array() ); unset($files[$basename]); update_option(self::FILES_OPTION,$files,false); return true;
    }
    public static function disconnect() { $s=self::settings(); $s['refresh_token']=''; $s['access_token']=''; $s['expires_at']=0; update_option(self::OPTION,$s,false); }
}
