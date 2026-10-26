<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Dependency-free XLSX writer for flat report tables.
 * Uses inline strings, so there is no sharedStrings.xml dependency.
 */
class Nine_Backup_XLSX {
    public static function write( $path, array $rows, $sheet_name = 'Report' ) {
        $sheet_name = self::sanitize_sheet_name( $sheet_name );
        $entries = array(
            '[Content_Types].xml' => self::content_types(),
            '_rels/.rels' => self::root_rels(),
            'xl/workbook.xml' => self::workbook( $sheet_name ),
            'xl/_rels/workbook.xml.rels' => self::workbook_rels(),
            'xl/styles.xml' => self::styles(),
            'xl/worksheets/sheet1.xml' => self::sheet_xml( $rows ),
        );
        return Nine_Backup_Zip::create_entries( $path, $entries );
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
                if ( is_numeric( $value ) && ! preg_match( '/^0\d+/', (string) $value ) ) {
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

    private static function content_types() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function root_rels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbook( $sheet_name ) {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::xml( $sheet_name ) . '" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private static function workbook_rels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
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
