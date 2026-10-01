<?php
$root = $_SERVER['DOCUMENT_ROOT'];
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = realpath( $root . $path );
if ( $file && is_file( $file ) ) {
    if ( substr( $file, -4 ) === '.php' ) { chdir( dirname( $file ) ); $_SERVER['SCRIPT_NAME'] = $path; $_SERVER['SCRIPT_FILENAME'] = $file; $_SERVER['PHP_SELF'] = $path; require $file; return true; }
    return false;
}
if ( is_dir( $root . $path ) && is_file( $root . rtrim( $path, '/' ) . '/index.php' ) ) { chdir( $root . rtrim( $path, '/' ) ); $_SERVER['SCRIPT_NAME'] = rtrim( $path, '/' ) . '/index.php'; require $root . rtrim( $path, '/' ) . '/index.php'; return true; }
$_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['SCRIPT_FILENAME'] = $root . '/index.php'; $_SERVER['PHP_SELF'] = '/index.php';
chdir( $root ); require $root . '/index.php'; return true;
