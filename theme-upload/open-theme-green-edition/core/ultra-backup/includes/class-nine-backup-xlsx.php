<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Dependency-free XLSX writer for flat report tables.
 * Uses inline strings, so there is no sharedStrings.xml dependency.
 */
class Nine_Backup_XLSX {
    public static function write( $path, array $rows, $sheet_name = 'Report' ) {
        return self::write_book( $path, array( $sheet_name => $rows ) );
    }

    /** Several sheets in one workbook: array( sheet name => rows ). */
    public static function write_book( $path, array $sheets ) {
        $names = array();
        $entries = array();
        $i = 1;
        foreach ( $sheets as $name => $rows ) {
            $name = self::sanitize_sheet_name( $name );
            while ( in_array( strtolower( $name ), array_map( 'strtolower', $names ), true ) ) { $name = self::sanitize_sheet_name( substr( $name, 0, 28 ) . '-' . $i ); }
            $names[] = $name;
            $entries[ 'xl/worksheets/sheet' . $i . '.xml' ] = self::sheet_xml( (array) $rows );
            $i++;
        }
        if ( ! $names ) { $names[] = 'Report'; $entries['xl/worksheets/sheet1.xml'] = self::sheet_xml( array() ); }
        $entries = array(
            '[Content_Types].xml' => self::content_types( count( $names ) ),
            '_rels/.rels' => self::root_rels(),
            'xl/workbook.xml' => self::workbook( $names ),
            'xl/_rels/workbook.xml.rels' => self::workbook_rels( count( $names ) ),
            'xl/styles.xml' => self::styles(),
        ) + $entries;
        return Nine_Backup_Zip::create_entries( $path, $entries );
    }

    /**
     * Read every sheet of a workbook: array( sheet name => array of rows ).
     * Handles inline strings (our files) and shared strings (files saved by Excel,
     * LibreOffice or Google Sheets). Formulas are read as their cached values.
     */
    public static function read( $path ) {
        $files = self::zip_files( $path, array( 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/sharedStrings.xml' ), '#^xl/worksheets/[^/]+\.xml$#' );
        if ( is_wp_error( $files ) ) { return $files; }
        if ( empty( $files['xl/workbook.xml'] ) ) { return new WP_Error( 'nine_xlsx_invalid', 'This is not an Excel workbook (.xlsx).' ); }
        $options = LIBXML_NONET | LIBXML_COMPACT;
        $workbook = simplexml_load_string( $files['xl/workbook.xml'], 'SimpleXMLElement', $options );
        $rels = ! empty( $files['xl/_rels/workbook.xml.rels'] ) ? simplexml_load_string( $files['xl/_rels/workbook.xml.rels'], 'SimpleXMLElement', $options ) : null;
        if ( ! $workbook ) { return new WP_Error( 'nine_xlsx_invalid', 'The workbook could not be read.' ); }
        $targets = array();
        if ( $rels ) {
            foreach ( $rels->Relationship as $rel ) { $targets[ (string) $rel['Id'] ] = ltrim( preg_replace( '#^/?xl/#', '', (string) $rel['Target'] ), '/' ); }
        }
        $shared = array();
        if ( ! empty( $files['xl/sharedStrings.xml'] ) ) {
            $sst = simplexml_load_string( $files['xl/sharedStrings.xml'], 'SimpleXMLElement', $options );
            if ( $sst ) { foreach ( $sst->si as $si ) { $shared[] = self::rich_text( $si ); } }
        }
        $out = array();
        $n = 1;
        foreach ( $workbook->sheets->sheet as $sheet ) {
            $rid = (string) $sheet->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )->id;
            $target = isset( $targets[ $rid ] ) ? 'xl/' . $targets[ $rid ] : 'xl/worksheets/sheet' . $n . '.xml';
            $n++;
            if ( empty( $files[ $target ] ) ) { continue; }
            $xml = simplexml_load_string( $files[ $target ], 'SimpleXMLElement', $options );
            if ( ! $xml || ! isset( $xml->sheetData ) ) { continue; }
            $rows = array();
            foreach ( $xml->sheetData->row as $row ) {
                $cells = array();
                foreach ( $row->c as $c ) {
                    $index = self::col_index( preg_replace( '/\d+/', '', (string) $c['r'] ) );
                    $type = (string) $c['t'];
                    if ( 'inlineStr' === $type ) { $value = self::rich_text( $c->is ); }
                    elseif ( 's' === $type ) { $value = isset( $shared[ (int) $c->v ] ) ? $shared[ (int) $c->v ] : ''; }
                    elseif ( 'b' === $type ) { $value = '1' === (string) $c->v ? '1' : ''; }
                    else { $value = (string) $c->v; }
                    $cells[ $index ] = $value;
                }
                if ( ! $cells ) { $rows[] = array(); continue; }
                $max = max( array_keys( $cells ) );
                $line = array();
                for ( $i = 0; $i <= $max; $i++ ) { $line[] = isset( $cells[ $i ] ) ? $cells[ $i ] : ''; }
                $rows[] = $line;
            }
            $out[ (string) $sheet['name'] ] = $rows;
        }
        return $out;
    }

    private static function rich_text( $node ) {
        if ( ! $node ) { return ''; }
        if ( isset( $node->t ) && ! isset( $node->r ) ) { return (string) $node->t; }
        $text = '';
        foreach ( $node->r as $run ) { $text .= (string) $run->t; }
        return $text;
    }

    private static function col_index( $letters ) {
        $index = 0;
        foreach ( str_split( strtoupper( $letters ) ) as $char ) { $index = $index * 26 + ( ord( $char ) - 64 ); }
        return max( 0, $index - 1 );
    }

    /** Read named entries (and those matching $pattern) from a ZIP without extracting to disk when possible. */
    private static function zip_files( $path, array $names, $pattern ) {
        $out = array();
        if ( class_exists( 'ZipArchive' ) ) {
            $zip = new ZipArchive();
            if ( true !== $zip->open( $path ) ) { return new WP_Error( 'nine_xlsx_open', 'The workbook could not be opened.' ); }
            for ( $i = 0; $i < $zip->numFiles; $i++ ) {
                $name = $zip->getNameIndex( $i );
                if ( in_array( $name, $names, true ) || preg_match( $pattern, $name ) ) { $out[ $name ] = $zip->getFromIndex( $i ); }
            }
            $zip->close();
            return $out;
        }
        $dir = trailingslashit( get_temp_dir() ) . 'nine-xlsx-' . wp_generate_password( 10, false, false );
        $done = Nine_Backup_Zip::extract( $path, $dir );
        if ( is_wp_error( $done ) ) { return $done; }
        foreach ( $names as $name ) { if ( is_file( $dir . '/' . $name ) ) { $out[ $name ] = file_get_contents( $dir . '/' . $name ); } }
        foreach ( (array) glob( $dir . '/xl/worksheets/*.xml' ) as $file ) { $out[ 'xl/worksheets/' . basename( $file ) ] = file_get_contents( $file ); }
        if ( function_exists( 'wp_delete_file' ) ) { array_map( 'wp_delete_file', (array) glob( $dir . '/{,*/,*/*/}*', GLOB_BRACE ) ); }
        return $out;
    }

    private static function sanitize_sheet_name( $name ) {
        $name = str_replace( array( '\\', '/', '?', '*', '[', ']', ':' ), '-', (string) $name );
        $name = trim( $name );
        if ( '' === $name ) { $name = 'Report'; }
        return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 31 ) : substr( $name, 0, 31 );
    }

    private static function xml( $value ) {
        return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
    }

    private static function col_name( $index ) {
        $index++;
        $name = '';
        while ( $index > 0 ) {
            $index--;
            $name = chr( 65 + ( $index % 26 ) ) . $name;
            $index = intdiv( $index, 26 );
        }
        return $name;
    }

    private static function sheet_xml( array $rows ) {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $r = 1;
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) { $row = array( $row ); }
            $xml .= '<row r="' . $r . '">';
            $c = 0;
            foreach ( array_values( $row ) as $value ) {
                $ref = self::col_name( $c ) . $r;
                if ( is_bool( $value ) ) { $value = $value ? 'true' : 'false'; }
                if ( is_array( $value ) || is_object( $value ) ) {
                    $value = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
                }
                if ( is_int( $value ) || is_float( $value ) || ( is_string( $value ) && preg_match( '/^-?(?:0|[1-9]\d{0,14})(?:\.\d{0,9}[1-9])?$/', $value ) ) ) {
                    $xml .= '<c r="' . $ref . '" t="n"><v>' . self::xml( $value ) . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . self::xml( $value ) . '</t></is></c>';
                }
                $c++;
            }
            $xml .= '</row>';
            $r++;
        }
        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private static function content_types( $count = 1 ) {
        $sheets = '';
        for ( $i = 1; $i <= $count; $i++ ) { $sheets .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'; }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $sheets
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function root_rels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbook( array $names ) {
        $sheets = '';
        foreach ( array_values( $names ) as $i => $name ) { $sheets .= '<sheet name="' . self::xml( $name ) . '" sheetId="' . ( $i + 1 ) . '" r:id="rId' . ( $i + 1 ) . '"/>'; }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheets . '</sheets></workbook>';
    }

    private static function workbook_rels( $count = 1 ) {
        $rels = '';
        for ( $i = 1; $i <= $count; $i++ ) { $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>'; }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '<Relationship Id="rId' . ( $count + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function styles() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '<dxfs count="0"/><tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>'
            . '</styleSheet>';
    }
}
