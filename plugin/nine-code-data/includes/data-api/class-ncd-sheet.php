<?php
/**
 * Minimal, dependency-free CSV and XLSX reading/writing for Data Manager exchange files.
 * XLSX output uses inline strings (one data sheet + optional guide sheet); input accepts shared or
 * inline strings from Excel, LibreOffice and Google Sheets.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NCD_Sheet {
	const MAX_ROWS = 20000;

	/* ------------------------------------------------------------- CSV */

	public static function csv( array $headers, array $rows ) {
		$fh = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- in-memory buffer.
		fwrite( $fh, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM so Excel opens accents correctly.
		fputcsv( $fh, $headers, ',', '"', '\\' );
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $headers as $h ) { $line[] = self::guard_formula( (string) ( $row[ $h ] ?? '' ) ); }
			fputcsv( $fh, $line, ',', '"', '\\' );
		}
		rewind( $fh );
		$out = stream_get_contents( $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- in-memory buffer.
		return $out;
	}

	public static function read_csv( $path ) {
		$fh = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- reading an uploaded temp file.
		if ( ! $fh ) { return new WP_Error( 'ncd_csv', 'Could not read the CSV file.' ); }
		$first = fgets( $fh );
		$delim = ( substr_count( (string) $first, ';' ) > substr_count( (string) $first, ',' ) ) ? ';' : ',';
		rewind( $fh );
		$matrix = array();
		while ( false !== ( $row = fgetcsv( $fh, 0, $delim, '"', '\\' ) ) ) {
			if ( count( $matrix ) > self::MAX_ROWS ) { break; }
			$matrix[] = $row;
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- reading an uploaded temp file.
		if ( isset( $matrix[0][0] ) ) { $matrix[0][0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $matrix[0][0] ); }
		return self::matrix_to_rows( $matrix );
	}

	/** A leading = + - @ would be executed as a formula by spreadsheet apps (CSV injection). */
	private static function guard_formula( $v ) {
		return ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) && ! is_numeric( $v ) ) ? "'" . $v : $v;
	}

	private static function unguard( $v ) {
		return ( strlen( $v ) > 1 && "'" === $v[0] && in_array( $v[1], array( '=', '+', '-', '@' ), true ) ) ? substr( $v, 1 ) : $v;
	}

	/** First row = headers. Blank rows dropped. */
	public static function matrix_to_rows( array $matrix ) {
		$headers = array_map( static function ( $h ) { return trim( (string) $h ); }, (array) array_shift( $matrix ) );
		$rows = array();
		foreach ( $matrix as $line ) {
			if ( ! array_filter( (array) $line, static function ( $c ) { return '' !== trim( (string) $c ); } ) ) { continue; }
			$row = array();
			foreach ( $headers as $i => $h ) { if ( '' !== $h ) { $row[ $h ] = self::unguard( (string) ( $line[ $i ] ?? '' ) ); } }
			$rows[] = $row;
		}
		return array( 'headers' => $headers, 'rows' => $rows );
	}

	/* ------------------------------------------------------------- XLSX */

	public static function xlsx( array $headers, array $rows, array $guide = array() ) {
		if ( ! class_exists( 'ZipArchive' ) ) { return new WP_Error( 'ncd_zip', 'The PHP zip extension is required for Excel files. Use CSV or JSON instead.' ); }
		$sheets = array( array( 'name' => 'Data', 'xml' => self::sheet_xml( $headers, $rows ) ) );
		if ( $guide ) { $sheets[] = array( 'name' => 'Guide', 'xml' => self::sheet_xml( array_keys( reset( $guide ) ), $guide ) ); }
		$tmp = wp_tempnam( 'ncd-export.xlsx' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) { return new WP_Error( 'ncd_zip', 'Could not build the Excel file.' ); }
		$ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
		$wb_sheets = ''; $wb_rels = '';
		foreach ( $sheets as $i => $s ) {
			$n   = $i + 1;
			$ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
			$wb_sheets .= '<sheet name="' . self::x( $s['name'] ) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
			$wb_rels   .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
			$zip->addFromString( 'xl/worksheets/sheet' . $n . '.xml', $s['xml'] );
		}
		$n = count( $sheets ) + 1;
		$wb_rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
		$zip->addFromString( '[Content_Types].xml', $ct . '</Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $wb_sheets . '</sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $wb_rels . '</Relationships>' );
		$zip->addFromString( 'xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf fontId="0"/><xf fontId="1" applyFont="1"/></cellXfs></styleSheet>' );
		$zip->close();
		$data = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local temp file we just wrote.
		wp_delete_file( $tmp );
		return $data;
	}

	private static function x( $v ) {
		$v = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string) $v );
		return htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	private static function col( $i ) {
		$s = '';
		for ( $i++; $i > 0; $i = (int) ( ( $i - 1 ) / 26 ) ) { $s = chr( 65 + ( ( $i - 1 ) % 26 ) ) . $s; }
		return $s;
	}

	private static function sheet_xml( array $headers, array $rows ) {
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData>';
		$all = array_merge( array( array_combine( $headers, $headers ) ), $rows );
		foreach ( $all as $r => $row ) {
			$xml .= '<row r="' . ( $r + 1 ) . '">';
			foreach ( array_values( $headers ) as $c => $h ) {
				$v = (string) ( $row[ $h ] ?? '' );
				if ( '' === $v ) { continue; }
				$ref = self::col( $c ) . ( $r + 1 );
				$xml .= '<c r="' . $ref . '" t="inlineStr"' . ( 0 === $r ? ' s="1"' : '' ) . '><is><t xml:space="preserve">' . self::x( $v ) . '</t></is></c>';
			}
			$xml .= '</row>';
		}
		return $xml . '</sheetData></worksheet>';
	}

	public static function read_xlsx( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) { return new WP_Error( 'ncd_zip', 'The PHP zip extension is required to read Excel files.' ); }
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) { return new WP_Error( 'ncd_xlsx', 'This is not a valid .xlsx file.' ); }
		$shared = array();
		$ss = $zip->getFromName( 'xl/sharedStrings.xml' );
		if ( false !== $ss ) {
			$sx = self::load( $ss );
			if ( $sx ) {
				foreach ( $sx->si as $si ) { $shared[] = self::text( $si ); }
			}
		}
		$sheet_path = 'xl/worksheets/sheet1.xml';
		$wb   = self::load( (string) $zip->getFromName( 'xl/workbook.xml' ) );
		$rels = self::load( (string) $zip->getFromName( 'xl/_rels/workbook.xml.rels' ) );
		if ( $wb && $rels && isset( $wb->sheets->sheet[0] ) ) {
			$rid = (string) $wb->sheets->sheet[0]->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )->id;
			foreach ( $rels->Relationship as $rel ) { if ( (string) $rel['Id'] === $rid ) { $sheet_path = 'xl/' . ltrim( preg_replace( '#^/?xl/#', '', (string) $rel['Target'] ), '/' ); } }
		}
		$xml = $zip->getFromName( $sheet_path );
		$zip->close();
		$sheet = self::load( (string) $xml );
		if ( ! $sheet ) { return new WP_Error( 'ncd_xlsx', 'The first worksheet could not be read.' ); }
		$matrix = array();
		foreach ( $sheet->sheetData->row as $row ) {
			if ( count( $matrix ) > self::MAX_ROWS ) { break; }
			$line = array();
			foreach ( $row->c as $c ) {
				$ref = (string) $c['r'];
				$idx = self::col_index( preg_replace( '/\d+/', '', $ref ) );
				$t   = (string) $c['t'];
				if ( 's' === $t ) { $v = $shared[ (int) $c->v ] ?? ''; }
				elseif ( 'inlineStr' === $t ) { $v = self::text( $c->is ); }
				else { $v = (string) $c->v; }
				$line[ $idx ] = $v;
			}
			if ( $line ) { $max = max( array_keys( $line ) ); $matrix[] = array_replace( array_fill( 0, $max + 1, '' ), $line ); } else { $matrix[] = array(); }
		}
		return self::matrix_to_rows( $matrix );
	}

	private static function load( $xml ) {
		if ( '' === $xml ) { return null; }
		$prev = libxml_use_internal_errors( true );
		$sx   = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
		libxml_use_internal_errors( $prev );
		return $sx ?: null;
	}

	private static function text( $node ) {
		if ( ! $node ) { return ''; }
		if ( isset( $node->t ) && ! isset( $node->r ) ) { return (string) $node->t; }
		$out = '';
		foreach ( $node->r as $r ) { $out .= (string) $r->t; }
		return '' !== $out ? $out : (string) $node->t;
	}

	private static function col_index( $letters ) {
		$n = 0;
		foreach ( str_split( strtoupper( $letters ) ) as $ch ) { $n = $n * 26 + ( ord( $ch ) - 64 ); }
		return $n - 1;
	}
}
