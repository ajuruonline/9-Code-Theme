<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** ZIP compatibility layer: ZipArchive when available, pure-PHP creation fallback, PclZip extraction fallback. */
class Nine_Backup_Zip {
    public static function create_entries( $destination, array $entries ) {
        if ( self::can_ziparchive_strings() ) {
            $zip = new ZipArchive();
            if ( true !== $zip->open( $destination, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
                return new WP_Error( 'nine_zip_open', 'Could not create ZIP archive.' );
            }
            foreach ( $entries as $name => $content ) {
                $name = self::safe_entry_name( $name );
                if ( '' === $name ) { continue; }
                $zip->addFromString( $name, $content );
            }
            $zip->close();
            return file_exists( $destination ) ? true : new WP_Error( 'nine_zip_missing', 'ZIP archive was not written.' );
        }

        // Pure PHP, uncompressed (store) ZIP fallback. Compatible with standard ZIP readers.
        $data = '';
        $central = '';
        $offset = 0;
        $count = 0;
        foreach ( $entries as $name => $content ) {
            $name = self::safe_entry_name( $name );
            if ( '' === $name ) { continue; }
            $content = (string) $content;
            $name_bytes = $name;
            $crc = crc32( $content );
            if ( $crc < 0 ) { $crc += 4294967296; }
            $size = strlen( $content );
            $time = self::dos_time();
            $date = self::dos_date();
            $local = pack( 'VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $time, $date, $crc, $size, $size, strlen( $name_bytes ), 0 ) . $name_bytes . $content;
            $data .= $local;
            $central .= pack( 'VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $time, $date, $crc, $size, $size, strlen( $name_bytes ), 0, 0, 0, 0, 0, $offset ) . $name_bytes;
            $offset += strlen( $local );
            $count++;
        }
        $end = pack( 'VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen( $central ), strlen( $data ), 0 );
        $ok = @file_put_contents( $destination, $data . $central . $end );
        return false !== $ok ? true : new WP_Error( 'nine_zip_write', 'Could not write ZIP archive.' );
    }

    public static function create_from_directory( $source, $destination, $exclude_callback = null ) {
        $source = realpath( $source );
        if ( ! $source || ! is_dir( $source ) ) { return new WP_Error( 'nine_zip_source', 'ZIP source directory does not exist.' ); }

        if ( self::can_ziparchive_files() ) {
            $zip = new ZipArchive();
            if ( true !== $zip->open( $destination, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) { return new WP_Error( 'nine_zip_open', 'Could not create ZIP archive.' ); }
            $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ) );
            foreach ( $iterator as $file ) {
                if ( $file->isDir() || $file->isLink() ) { continue; }
                $path = $file->getRealPath();
                $name = ltrim( str_replace( '\\', '/', substr( $path, strlen( $source ) ) ), '/' );
                if ( is_callable( $exclude_callback ) && call_user_func( $exclude_callback, $path, $name ) ) { continue; }
                $zip->addFile( $path, $name );
            }
            $zip->close();
            return file_exists( $destination ) ? true : new WP_Error( 'nine_zip_missing', 'ZIP archive was not written.' );
        }

        $files = array();
        $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ) );
        foreach ( $iterator as $file ) {
            if ( $file->isDir() || $file->isLink() ) { continue; }
            $path = $file->getRealPath();
            $name = ltrim( str_replace( '\\', '/', substr( $path, strlen( $source ) ) ), '/' );
            if ( is_callable( $exclude_callback ) && call_user_func( $exclude_callback, $path, $name ) ) { continue; }
            $files[] = array( 'path'=>$path, 'name'=>$name );
        }
        return self::create_file_entries_streaming( $destination, $files );
    }


    public static function create_named_files( array $files, $destination ) {
        $list = array();
        foreach ( $files as $entry ) {
            if ( empty( $entry['path'] ) || ! is_file( $entry['path'] ) ) { continue; }
            $name = self::safe_entry_name( $entry['name'] ?? basename( $entry['path'] ) );
            if ( ! $name ) { continue; }
            $list[] = array( 'path'=>$entry['path'], 'name'=>$name );
        }
        if ( self::can_ziparchive_files() ) {
            $zip = new ZipArchive();
            if ( true !== $zip->open( $destination, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) { return new WP_Error( 'nine_zip_open', 'Could not create ZIP archive.' ); }
            foreach ( $list as $entry ) { $zip->addFile( $entry['path'], $entry['name'] ); }
            $zip->close();
            return file_exists( $destination ) ? true : new WP_Error( 'nine_zip_missing', 'ZIP archive was not written.' );
        }
        return self::create_file_entries_streaming( $destination, $list );
    }

    public static function create_from_directory_prefixed( $source, $destination, $prefix ) {
        $source = realpath( $source );
        if ( ! $source || ! is_dir( $source ) ) { return new WP_Error( 'nine_zip_source', 'ZIP source directory does not exist.' ); }
        $prefix = trim( str_replace( '\\', '/', (string) $prefix ), '/' );
        if ( ! $prefix || ! self::is_safe_name( $prefix ) ) { return new WP_Error( 'nine_zip_prefix', 'ZIP prefix is invalid.' ); }
        $files = array();
        $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ) );
        foreach ( $iterator as $file ) {
            if ( $file->isDir() || $file->isLink() ) { continue; }
            $path = $file->getRealPath();
            $rel = ltrim( str_replace( '\\', '/', substr( $path, strlen( $source ) ) ), '/' );
            $files[] = array( 'path'=>$path, 'name'=>$prefix . '/' . $rel );
        }
        return self::create_named_files( $files, $destination );
    }

    public static function create_from_files( array $files, $destination ) {
        $list = array();
        foreach ( $files as $file ) { if ( is_file( $file ) ) { $list[] = array( 'path'=>$file, 'name'=>basename($file) ); } }
        if ( self::can_ziparchive_files() ) {
            $zip = new ZipArchive();
            if ( true !== $zip->open( $destination, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) { return new WP_Error( 'nine_zip_open', 'Could not create ZIP archive.' ); }
            foreach ( $list as $entry ) { $zip->addFile( $entry['path'], $entry['name'] ); }
            $zip->close();
            return file_exists( $destination ) ? true : new WP_Error( 'nine_zip_missing', 'ZIP archive was not written.' );
        }
        return self::create_file_entries_streaming( $destination, $list );
    }

    private static function create_file_entries_streaming( $destination, array $files ) {
        $out = @fopen( $destination, 'wb' );
        if ( ! $out ) { return new WP_Error( 'nine_zip_write', 'Could not open ZIP destination for writing.' ); }
        $central = array();
        $count = 0;
        foreach ( $files as $entry ) {
            $path = $entry['path'];
            $name = self::safe_entry_name( $entry['name'] );
            if ( ! $name || ! is_file( $path ) ) { continue; }
            $size = filesize( $path );
            $crc_hex = hash_file( 'crc32b', $path );
            $crc = hexdec( $crc_hex );
            $time = self::dos_time(); $date = self::dos_date();
            $offset = ftell( $out );
            fwrite( $out, pack( 'VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $time, $date, $crc, $size, $size, strlen($name), 0 ) . $name );
            $in = fopen( $path, 'rb' );
            if ( $in ) { stream_copy_to_stream( $in, $out ); fclose( $in ); }
            $central[] = array( $name,$crc,$size,$time,$date,$offset );
            $count++;
        }
        $central_offset = ftell( $out );
        foreach ( $central as $c ) {
            list($name,$crc,$size,$time,$date,$offset)=$c;
            fwrite( $out, pack( 'VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $time, $date, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset ) . $name );
        }
        $central_size = ftell( $out ) - $central_offset;
        fwrite( $out, pack( 'VvvvvVVv', 0x06054b50, 0, 0, $count, $count, $central_size, $central_offset, 0 ) );
        fclose( $out );
        return file_exists( $destination ) ? true : new WP_Error( 'nine_zip_missing', 'ZIP archive was not written.' );
    }

    public static function extract( $archive, $destination ) {
        if ( self::can_ziparchive_extract() ) {
            $zip = new ZipArchive();
            if ( true !== $zip->open( $archive ) ) { return new WP_Error( 'nine_zip_open', 'Could not open ZIP archive.' ); }
            for ( $i = 0; $i < $zip->numFiles; $i++ ) {
                $name = $zip->getNameIndex( $i );
                if ( ! self::is_safe_name( $name ) ) { $zip->close(); return new WP_Error( 'nine_zip_unsafe', 'ZIP contains an unsafe path.' ); }
            }
            $ok = $zip->extractTo( $destination );
            $zip->close();
            return $ok ? true : new WP_Error( 'nine_zip_extract', 'Could not extract ZIP archive.' );
        }

        $pclzip = ABSPATH . 'wp-admin/includes/class-pclzip.php';
        if ( ! file_exists( $pclzip ) ) { return new WP_Error( 'nine_zip_extract_support', 'No ZIP extraction engine is available.' ); }
        require_once $pclzip;
        $zip = new PclZip( $archive );
        $list = $zip->listContent();
        if ( 0 === $list ) { return new WP_Error( 'nine_zip_open', 'Could not read ZIP archive.' ); }
        foreach ( $list as $entry ) {
            if ( ! self::is_safe_name( $entry['filename'] ?? '' ) ) { return new WP_Error( 'nine_zip_unsafe', 'ZIP contains an unsafe path.' ); }
        }
        $result = $zip->extract( PCLZIP_OPT_PATH, $destination );
        return 0 === $result ? new WP_Error( 'nine_zip_extract', 'Could not extract ZIP archive.' ) : true;
    }

    private static function can_ziparchive_strings() {
        return class_exists( 'ZipArchive' )
            && defined( 'ZipArchive::CREATE' )
            && defined( 'ZipArchive::OVERWRITE' )
            && method_exists( 'ZipArchive', 'open' )
            && method_exists( 'ZipArchive', 'addFromString' )
            && method_exists( 'ZipArchive', 'close' );
    }

    private static function can_ziparchive_files() {
        return class_exists( 'ZipArchive' )
            && defined( 'ZipArchive::CREATE' )
            && defined( 'ZipArchive::OVERWRITE' )
            && method_exists( 'ZipArchive', 'open' )
            && method_exists( 'ZipArchive', 'addFile' )
            && method_exists( 'ZipArchive', 'close' );
    }

    private static function can_ziparchive_extract() {
        return class_exists( 'ZipArchive' )
            && method_exists( 'ZipArchive', 'open' )
            && method_exists( 'ZipArchive', 'getNameIndex' )
            && method_exists( 'ZipArchive', 'extractTo' )
            && method_exists( 'ZipArchive', 'close' );
    }

    private static function safe_entry_name( $name ) {
        $name = ltrim( str_replace( '\\', '/', (string) $name ), '/' );
        return self::is_safe_name( $name ) ? $name : '';
    }

    private static function is_safe_name( $name ) {
        if ( '' === $name || false !== strpos( $name, '../' ) || 0 === strpos( $name, '/' ) || preg_match( '/^[A-Za-z]:[\\\\\/]/', $name ) ) { return false; }
        return true;
    }

    private static function dos_time() {
        $t = getdate();
        return ( $t['hours'] << 11 ) | ( $t['minutes'] << 5 ) | (int) floor( $t['seconds'] / 2 );
    }

    private static function dos_date() {
        $t = getdate();
        $year = max( 1980, $t['year'] );
        return ( ( $year - 1980 ) << 9 ) | ( $t['mon'] << 5 ) | $t['mday'];
    }
}
