<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class NCU_Data_Excel {
    private $last_scope_guard = null;
    private $scope_lock_expected = false;

    public function get_scope_lock_expected() { return $this->scope_lock_expected; }
    public function get_last_scope_guard() { return $this->last_scope_guard; }

    public function create_workbook( $records, $scope_kind, $scope_name, $filename = 'ninecode-data.xlsx', $scope_guard = array() ) {
        $columns = $this->build_columns( $records, $scope_kind, $scope_name );
        $data_rows = array();
        $data_rows[] = array_map( function( $c ) { return $c['label']; }, $columns );
        foreach ( (array) $records as $record ) { $data_rows[] = $this->record_to_row( $record, $columns ); }

        // A protected baseline used for three-way merge protection on import.
        // It intentionally contains the same values as Data at export time.
        $original_rows = $data_rows;

        $map_rows = array(
            array( '9CODE MAP', 'DO NOT DELETE THIS SHEET' ),
            array( 'kind', $scope_kind ),
            array( 'scope', $scope_name ),
            array( 'scope_guard', class_exists( 'NCU_Data_Scope_Lock' ) && $scope_guard ? NCU_Data_Scope_Lock::encode( $scope_guard ) : '' ),
            array( 'column', 'label', 'mapping', 'field_type', 'instructions' ),
        );
        foreach ( $columns as $i => $column ) {
            $map_rows[] = array( $i + 1, $column['label'], $column['map'], $column['type'] ?? '', $column['instructions'] ?? '' );
        }

        $help_rows = array(
            array( '9Code Data Excel' ),
            array( 'Purpose', 'Edit the complete front-end record data — title, slug, excerpt, main content, featured image, status, ACF values, plugin fields and taxonomies — without opening the WordPress post editor.' ),
            array( 'How to use', 'Edit the white cells on the Data sheet. Keep Record ID, Data Type and other grey reference cells unchanged.' ),
            array( 'Featured images', 'Featured Image ID is editable. Choose an attachment ID from this WordPress site; the URL column is reference-only.' ),
            array( 'Publishing', 'Status accepts draft, pending, private or publish. Publishing still requires the current WordPress user to have permission.' ),
            array( 'Taxonomies', 'Separate multiple category/tag/taxonomy names with commas.' ),
            array( 'Structured data', 'Repeater, gallery, relationship and structured plugin-meta values are stored as JSON text. Keep valid JSON.' ),
            array( 'Scope Lock', 'This workbook is signed to its exported records and fields. Adding another record, field or taxonomy will be blocked before any WordPress data is changed.' ),
            array( 'Safe import', '9Code compares the original export, your Excel edit and the current live value. If the live value changed differently after export, it is protected and reported as a conflict.' ),
            array( 'Versions', 'Before applied imports, 9Code also saves data versions so earlier values can be restored.' ),
            array(),
            array( 'Column', 'Data type', 'Instructions' ),
        );
        foreach ( $columns as $column ) {
            if ( 0 === strpos( $column['map'], 'acf:' ) || 0 === strpos( $column['map'], 'meta:' ) || 0 === strpos( $column['map'], 'tax:' ) || 0 === strpos( $column['map'], 'term:' ) || 0 === strpos( $column['map'], 'object:' ) ) {
                $help_rows[] = array( $column['label'], $column['type'] ?? 'Data', $column['instructions'] ?? '' );
            }
        }

        $tmp = wp_tempnam( sanitize_file_name( $filename ) );
        if ( ! $tmp ) { return new WP_Error( 'temp_failed', 'Could not create a temporary Excel file.' ); }
        $path = $tmp . '.xlsx'; @unlink( $tmp );
        $files = array(
            '[Content_Types].xml' => $this->content_types_xml(),
            '_rels/.rels' => $this->root_rels_xml(),
            'docProps/app.xml' => $this->app_xml(),
            'docProps/core.xml' => $this->core_xml(),
            'xl/workbook.xml' => $this->workbook_xml(),
            'xl/_rels/workbook.xml.rels' => $this->workbook_rels_xml(),
            'xl/styles.xml' => $this->styles_xml(),
            'xl/worksheets/sheet1.xml' => $this->sheet_xml( $data_rows, $columns, true ),
            'xl/worksheets/sheet2.xml' => $this->sheet_xml( $help_rows, array(), false ),
            'xl/worksheets/sheet3.xml' => $this->sheet_xml( $map_rows, array(), false ),
            'xl/worksheets/sheet4.xml' => $this->sheet_xml( $original_rows, $columns, false ),
        );
        $written = $this->write_archive( $path, $files );
        if ( is_wp_error( $written ) ) { @unlink( $path ); return $written; }
        return $path;
    }

    public function parse_workbook( $path ) {
        $archive = $this->open_archive( $path );
        if ( is_wp_error( $archive ) ) { return $archive; }
        $shared = $this->read_shared_strings( $archive );
        $sheets = $this->resolve_sheets( $archive );
        if ( is_wp_error( $sheets ) ) { $this->close_archive( $archive ); return $sheets; }
        if ( empty( $sheets['Data'] ) || empty( $sheets['Map'] ) ) { $this->close_archive( $archive ); return new WP_Error( 'xlsx_structure', 'This Excel file is missing the 9Code Data or hidden Map sheet.' ); }
        $data = $this->read_sheet( $archive, $sheets['Data'], $shared );
        $map = $this->read_sheet( $archive, $sheets['Map'], $shared );
        $original = ! empty( $sheets['Original'] ) ? $this->read_sheet( $archive, $sheets['Original'], $shared ) : array();
        $this->last_scope_guard = null;
        $this->scope_lock_expected = isset( $map[3][0] ) && 'scope_guard' === trim( (string) $map[3][0] );
        if ( class_exists( 'NCU_Data_Scope_Lock' ) && $this->scope_lock_expected && isset( $map[3][1] ) ) {
            $this->last_scope_guard = NCU_Data_Scope_Lock::decode( $map[3][1] );
        }
        $this->close_archive( $archive );
        if ( is_wp_error( $data ) || is_wp_error( $map ) || is_wp_error( $original ) ) {
            if ( is_wp_error( $data ) ) { return $data; }
            if ( is_wp_error( $map ) ) { return $map; }
            return $original;
        }
        return $this->rows_to_records( $data, $map, $original );
    }

    private function build_columns( $records, $kind, $scope ) {
        $columns = array();
        $columns[] = array( 'label' => 'Record ID', 'map' => 'object:id', 'type' => 'Reference', 'readonly' => true, 'instructions' => 'Do not change.' );
        if ( 'term' === $kind ) {
            $columns[] = array( 'label' => 'Name', 'map' => 'term:name', 'type' => 'Taxonomy name', 'readonly' => false, 'instructions' => 'Visible taxonomy name.' );
            $columns[] = array( 'label' => 'Description', 'map' => 'term:description', 'type' => 'Paragraph', 'readonly' => false, 'instructions' => 'Visible taxonomy description.' );
            $columns[] = array( 'label' => 'Taxonomy', 'map' => 'object:type', 'type' => 'Reference', 'readonly' => true, 'instructions' => 'Do not change.' );
        } else {
            $columns[] = array( 'label' => 'Title', 'map' => 'object:title', 'type' => 'Text', 'readonly' => false, 'instructions' => 'Visible WordPress title.' );
            $columns[] = array( 'label' => 'Slug', 'map' => 'object:slug', 'type' => 'Slug', 'readonly' => false, 'instructions' => 'URL slug. Leave unchanged unless you deliberately want the record URL to change.' );
            $columns[] = array( 'label' => 'Excerpt', 'map' => 'object:excerpt', 'type' => 'Paragraph', 'readonly' => false, 'instructions' => 'Short summary/excerpt.' );
            $columns[] = array( 'label' => 'Main Content', 'map' => 'object:content', 'type' => 'Content', 'readonly' => false, 'instructions' => 'Main WordPress content. HTML/block markup is preserved as text.' );
            $columns[] = array( 'label' => 'Featured Image ID', 'map' => 'object:featured_image_id', 'type' => 'Media ID', 'readonly' => false, 'instructions' => 'WordPress Media Library attachment ID. Use 0 or blank to remove the featured image.' );
            $columns[] = array( 'label' => 'Featured Image URL', 'map' => 'object:featured_image_url', 'type' => 'Reference', 'readonly' => true, 'instructions' => 'Reference only. Use Featured Image ID to change the image.' );
            $columns[] = array( 'label' => 'Status', 'map' => 'object:status', 'type' => 'Status', 'readonly' => false, 'instructions' => 'draft, pending, private or publish.' );
            $columns[] = array( 'label' => 'Data Type', 'map' => 'object:type', 'type' => 'Reference', 'readonly' => true, 'instructions' => 'Do not change.' );
        }
        $tax_seen = array();
        $field_seen = array();
        $meta_seen = array();
        foreach ( (array) $records as $record ) {
            foreach ( (array) ( $record['taxonomies'] ?? array() ) as $tax => $items ) { $tax_seen[ $tax ] = true; }
            foreach ( (array) ( $record['fields'] ?? array() ) as $field ) {
                $key = sanitize_key( $field['key'] ?? '' );
                if ( $key && ! isset( $field_seen[ $key ] ) ) { $field_seen[ $key ] = $field; }
            }
            foreach ( (array) ( $record['meta'] ?? array() ) as $meta ) {
                $key = trim( (string) ( $meta['key'] ?? '' ) );
                if ( $key && ! isset( $meta_seen[ $key ] ) ) { $meta_seen[ $key ] = $meta; }
            }
        }
        foreach ( array_keys( $tax_seen ) as $tax ) {
            $obj = get_taxonomy( $tax );
            $columns[] = array( 'label' => $obj ? $obj->labels->name : ucwords( str_replace( '_', ' ', $tax ) ), 'map' => 'tax:' . $tax, 'type' => 'Taxonomy', 'readonly' => false, 'instructions' => 'Separate multiple names with commas.' );
        }
        foreach ( $field_seen as $key => $field ) {
            $columns[] = array(
                'label' => $field['label'] ?: ( $field['name'] ?: $key ),
                'map' => 'acf:' . $key,
                'type' => $this->friendly_type( $field['type'] ?? '' ),
                'readonly' => false,
                'instructions' => trim( (string) ( $field['instructions'] ?? '' ) ),
            );
        }
        foreach ( $meta_seen as $key => $meta ) {
            $storage = 'multi' === ( $meta['storage'] ?? '' ) ? 'multi' : 'single';
            $columns[] = array(
                'label' => 'Plugin field: ' . ( $meta['label'] ?? $key ),
                'map' => 'meta:' . $storage . ':' . $key,
                'type' => 'Plugin meta',
                'readonly' => false,
                'instructions' => 'Custom/plugin data stored in WordPress post meta. Structured values use JSON.',
            );
        }
        return $columns;
    }

    private function record_to_row( $record, $columns ) {
        $object = (array) ( $record['object'] ?? array() );
        $fields = array();
        foreach ( (array) ( $record['fields'] ?? array() ) as $field ) { if ( ! empty( $field['key'] ) ) $fields[ $field['key'] ] = $field['value'] ?? ''; }
        $meta_values = array();
        foreach ( (array) ( $record['meta'] ?? array() ) as $meta ) {
            $meta_key = trim( (string) ( $meta['key'] ?? '' ) );
            if ( $meta_key ) { $meta_values[ $meta_key ] = $meta['value'] ?? ''; }
        }
        $row = array();
        foreach ( $columns as $column ) {
            $map = $column['map']; $value = '';
            if ( 'object:id' === $map ) $value = $object['id'] ?? '';
            elseif ( 'object:label' === $map || 'object:title' === $map ) $value = $object['title'] ?? ( $object['name'] ?? '' );
            elseif ( 'object:slug' === $map ) $value = $object['slug'] ?? '';
            elseif ( 'object:excerpt' === $map ) $value = $object['excerpt'] ?? '';
            elseif ( 'object:content' === $map ) $value = $object['content'] ?? '';
            elseif ( 'object:featured_image_id' === $map ) $value = $object['featured_image_id'] ?? '';
            elseif ( 'object:featured_image_url' === $map ) $value = $object['featured_image_url'] ?? '';
            elseif ( 'object:status' === $map ) $value = $object['status'] ?? '';
            elseif ( 'object:type' === $map ) $value = $object['post_type'] ?? ( $object['taxonomy'] ?? '' );
            elseif ( 'term:name' === $map ) $value = $object['name'] ?? '';
            elseif ( 'term:description' === $map ) $value = $object['description'] ?? '';
            elseif ( 0 === strpos( $map, 'tax:' ) ) {
                $tax = substr( $map, 4 );
                $value = implode( ', ', array_filter( array_map( function( $t ) { return $t['name'] ?? $t['slug'] ?? ''; }, (array) ( $record['taxonomies'][ $tax ] ?? array() ) ) ) );
            } elseif ( 0 === strpos( $map, 'acf:' ) ) {
                $key = substr( $map, 4 ); $value = $fields[ $key ] ?? '';
            } elseif ( 0 === strpos( $map, 'meta:' ) ) {
                $parts = explode( ':', $map, 3 );
                $key = $parts[2] ?? '';
                $value = $meta_values[ $key ] ?? '';
            }
            if ( is_array( $value ) || is_object( $value ) ) $value = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            elseif ( is_bool( $value ) ) $value = $value ? 'true' : 'false';
            $row[] = (string) $value;
        }
        return $row;
    }

    private function rows_to_records( $data, $map, $original_rows = array() ) {
        $kind = sanitize_key( $map[1][1] ?? '' );
        $scope = sanitize_key( $map[2][1] ?? '' );
        if ( ! in_array( $kind, array( 'post', 'term' ), true ) || ! $scope ) return new WP_Error( 'xlsx_map', 'The hidden 9Code mapping sheet is invalid.' );
        $columns = array();
        for ( $i = 5; $i < count( $map ); $i++ ) {
            $index = absint( $map[$i][0] ?? 0 );
            $mapping = trim( (string) ( $map[$i][2] ?? '' ) );
            if ( $index && $mapping ) $columns[ $index - 1 ] = $mapping;
        }
        if ( ! $columns ) return new WP_Error( 'xlsx_map_columns', 'The hidden 9Code column map is missing.' );

        $original_by_id = array();
        if ( $original_rows ) {
            for ( $r = 1; $r < count( $original_rows ); $r++ ) {
                $cells = (array) $original_rows[$r];
                $values = array();
                foreach ( $columns as $idx => $mapping ) $values[$mapping] = $cells[$idx] ?? '';
                $id = absint( $values['object:id'] ?? 0 );
                if ( $id ) { $original_by_id[$id] = $values; }
            }
        }

        $records = array();
        for ( $r = 1; $r < count( $data ); $r++ ) {
            $cells = (array) $data[$r];
            if ( ! array_filter( $cells, function( $v ) { return '' !== trim( (string) $v ); } ) ) continue;
            $values = array();
            foreach ( $columns as $idx => $mapping ) $values[$mapping] = $cells[$idx] ?? '';
            $id = absint( $values['object:id'] ?? 0 );
            if ( ! $id ) continue;
            $original_values = (array) ( $original_by_id[$id] ?? array() );

            $object = array( 'kind' => $kind, 'id' => $id );
            $original_object = array( 'kind' => $kind, 'id' => $id );
            if ( 'term' === $kind ) {
                $object['taxonomy'] = $scope;
                $object['name'] = sanitize_text_field( $values['term:name'] ?? '' );
                $object['description'] = wp_kses_post( $values['term:description'] ?? '' );
                $original_object['taxonomy'] = $scope;
                if ( $original_values ) {
                    $original_object['name'] = sanitize_text_field( $original_values['term:name'] ?? '' );
                    $original_object['description'] = wp_kses_post( $original_values['term:description'] ?? '' );
                }
            } else {
                $object['post_type'] = $scope;
                $object['title'] = sanitize_text_field( $values['object:title'] ?? ( $values['object:label'] ?? '' ) );
                $object['slug'] = sanitize_title( $values['object:slug'] ?? '' );
                $object['excerpt'] = wp_kses_post( $values['object:excerpt'] ?? '' );
                $object['content'] = current_user_can( 'unfiltered_html' ) ? (string) ( $values['object:content'] ?? '' ) : wp_kses_post( $values['object:content'] ?? '' );
                $object['featured_image_id'] = absint( $values['object:featured_image_id'] ?? 0 );
                $object['featured_image_url'] = esc_url_raw( $values['object:featured_image_url'] ?? '' );
                $object['status'] = sanitize_key( $values['object:status'] ?? '' );
                $original_object['post_type'] = $scope;
                if ( $original_values ) {
                    $original_object['title'] = sanitize_text_field( $original_values['object:title'] ?? ( $original_values['object:label'] ?? '' ) );
                    $original_object['slug'] = sanitize_title( $original_values['object:slug'] ?? '' );
                    $original_object['excerpt'] = wp_kses_post( $original_values['object:excerpt'] ?? '' );
                    $original_object['content'] = current_user_can( 'unfiltered_html' ) ? (string) ( $original_values['object:content'] ?? '' ) : wp_kses_post( $original_values['object:content'] ?? '' );
                    $original_object['featured_image_id'] = absint( $original_values['object:featured_image_id'] ?? 0 );
                    $original_object['featured_image_url'] = esc_url_raw( $original_values['object:featured_image_url'] ?? '' );
                    $original_object['status'] = sanitize_key( $original_values['object:status'] ?? '' );
                }
            }
            $record = array( 'object' => $object, 'fields' => array(), 'meta' => array(), 'taxonomies' => array() );
            $baseline = array( 'object' => $original_object, 'fields' => array(), 'meta' => array(), 'taxonomies' => array(), 'available' => ! empty( $original_values ) );
            foreach ( $values as $mapping => $value ) {
                if ( 0 === strpos( $mapping, 'acf:' ) ) {
                    $key = sanitize_key( substr( $mapping, 4 ) );
                    $record['fields'][] = array( 'key' => $key, 'value' => $this->decode_cell_value( $value ) );
                    if ( $original_values ) {
                        $baseline['fields'][$key] = $this->decode_cell_value( $original_values[$mapping] ?? '' );
                    }
                } elseif ( 0 === strpos( $mapping, 'meta:' ) ) {
                    $parts = explode( ':', $mapping, 3 );
                    $storage = 'multi' === ( $parts[1] ?? '' ) ? 'multi' : 'single';
                    $key = trim( (string) ( $parts[2] ?? '' ) );
                    if ( $key ) {
                        $record['meta'][] = array( 'key' => $key, 'storage' => $storage, 'value' => $this->decode_cell_value( $value ) );
                        if ( $original_values ) {
                            $baseline['meta'][$key] = array( 'storage' => $storage, 'value' => $this->decode_cell_value( $original_values[$mapping] ?? '' ) );
                        }
                    }
                } elseif ( 0 === strpos( $mapping, 'tax:' ) ) {
                    $tax = sanitize_key( substr( $mapping, 4 ) );
                    $names = preg_split( '/\s*[,|]\s*/', trim( (string) $value ), -1, PREG_SPLIT_NO_EMPTY );
                    $record['taxonomies'][ $tax ] = array_map( function( $name ) use ( $tax ) { return array( 'name' => sanitize_text_field( $name ), 'slug' => sanitize_title( $name ), 'taxonomy' => $tax ); }, $names ?: array() );
                    if ( $original_values ) {
                        $original_names = preg_split( '/\s*[,|]\s*/', trim( (string) ( $original_values[$mapping] ?? '' ) ), -1, PREG_SPLIT_NO_EMPTY );
                        $baseline['taxonomies'][$tax] = array_map( function( $name ) use ( $tax ) { return array( 'name' => sanitize_text_field( $name ), 'slug' => sanitize_title( $name ), 'taxonomy' => $tax ); }, $original_names ?: array() );
                    }
                }
            }
            if ( ! empty( $baseline['available'] ) ) { $record['ninecode_original'] = $baseline; }
            $records[] = $record;
        }
        return $records;
    }

    private function decode_cell_value( $value ) {
        $trim = trim( (string) $value );
        if ( '' === $trim ) return '';
        if ( in_array( substr( $trim, 0, 1 ), array( '[', '{' ), true ) ) {
            $decoded = json_decode( $trim, true );
            if ( JSON_ERROR_NONE === json_last_error() ) return $decoded;
        }
        if ( 'true' === strtolower( $trim ) ) return true;
        if ( 'false' === strtolower( $trim ) ) return false;
        return $value;
    }

    private function write_archive( $path, $files ) {
        if ( class_exists( 'ZipArchive' ) ) {
            $zip = new ZipArchive();
            if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) { return new WP_Error( 'xlsx_open', 'Could not create the Excel workbook.' ); }
            foreach ( $files as $name => $content ) { $zip->addFromString( $name, $content ); }
            $zip->close();
            return true;
        }
        if ( class_exists( 'PharData' ) ) {
            try {
                @unlink( $path );
                $phar = new PharData( $path, 0, null, Phar::ZIP );
                foreach ( $files as $name => $content ) { $phar->addFromString( $name, $content ); }
                unset( $phar );
                return true;
            } catch ( Exception $e ) {
                return new WP_Error( 'xlsx_archive', 'Could not create the Excel workbook archive: ' . $e->getMessage() );
            }
        }
        return new WP_Error( 'xlsx_archive_missing', 'This server needs PHP ZipArchive or PharData to create Excel files.' );
    }

    private function open_archive( $path ) {
        if ( class_exists( 'ZipArchive' ) ) {
            $zip = new ZipArchive();
            if ( true !== $zip->open( $path ) ) { return new WP_Error( 'xlsx_open', 'Could not open the Excel workbook.' ); }
            return array( 'type' => 'zip', 'object' => $zip );
        }
        if ( class_exists( 'PharData' ) ) {
            try { return array( 'type' => 'phar', 'object' => new PharData( $path ) ); }
            catch ( Exception $e ) { return new WP_Error( 'xlsx_open', 'Could not open the Excel workbook: ' . $e->getMessage() ); }
        }
        return new WP_Error( 'xlsx_archive_missing', 'This server needs PHP ZipArchive or PharData to read Excel files.' );
    }

    private function archive_get( $archive, $name ) {
        if ( 'zip' === ( $archive['type'] ?? '' ) ) {
            $value = $archive['object']->getFromName( $name );
            return false === $value ? null : $value;
        }
        if ( 'phar' === ( $archive['type'] ?? '' ) ) {
            try {
                if ( ! isset( $archive['object'][ $name ] ) ) { return null; }
                return $archive['object'][ $name ]->getContent();
            } catch ( Exception $e ) { return null; }
        }
        return null;
    }

    private function close_archive( $archive ) {
        if ( 'zip' === ( $archive['type'] ?? '' ) && isset( $archive['object'] ) ) { $archive['object']->close(); }
    }

    private function resolve_sheets( $archive ) {
        $workbook = $this->archive_get( $archive, 'xl/workbook.xml' );
        $rels = $this->archive_get( $archive, 'xl/_rels/workbook.xml.rels' );
        if ( null === $workbook || null === $rels ) return new WP_Error( 'xlsx_structure', 'Excel workbook structure is incomplete.' );
        $relmap = array();
        if ( preg_match_all( '/<Relationship\b([^>]*)\/?\s*>/i', $rels, $matches ) ) {
            foreach ( $matches[1] as $attrs ) {
                $id = $this->xml_attr( $attrs, 'Id' ); $target = $this->xml_attr( $attrs, 'Target' );
                if ( $id && $target ) $relmap[$id] = $target;
            }
        }
        $out = array();
        if ( preg_match_all( '/<sheet\b([^>]*)\/?\s*>/i', $workbook, $matches ) ) {
            foreach ( $matches[1] as $attrs ) {
                $name = $this->xml_attr( $attrs, 'name' ); $rid = $this->xml_attr( $attrs, 'r:id' );
                if ( ! $name || ! $rid || empty( $relmap[$rid] ) ) continue;
                $target = ltrim( str_replace( '../', '', $relmap[$rid] ), '/' );
                if ( 0 !== strpos( $target, 'xl/' ) ) $target = 'xl/' . $target;
                $out[$name] = $target;
            }
        }
        return $out;
    }

    private function read_shared_strings( $archive ) {
        $xml = $this->archive_get( $archive, 'xl/sharedStrings.xml' ); if ( null === $xml ) return array();
        $out = array();
        if ( preg_match_all( '/<si\b[^>]*>(.*?)<\/si>/is', $xml, $items ) ) {
            foreach ( $items[1] as $item ) {
                $text = '';
                if ( preg_match_all( '/<t\b[^>]*>(.*?)<\/t>/is', $item, $parts ) ) foreach ( $parts[1] as $part ) $text .= $this->xml_decode( $part );
                $out[] = $text;
            }
        }
        return $out;
    }

    private function read_sheet( $archive, $path, $shared ) {
        $xml = $this->archive_get( $archive, $path ); if ( null === $xml ) return new WP_Error( 'xlsx_sheet', 'A worksheet could not be read.' );
        $indexed = array();
        if ( ! preg_match_all( '/<row\b([^>]*)>(.*?)<\/row>/is', $xml, $row_matches, PREG_SET_ORDER ) ) return array();
        $fallback_row = 1;
        foreach ( $row_matches as $row_match ) {
            $row_attrs = $row_match[1]; $row_xml = $row_match[2];
            $row_number = absint( $this->xml_attr( $row_attrs, 'r' ) );
            if ( ! $row_number ) { $row_number = $fallback_row; }
            $fallback_row = $row_number + 1;
            $values = array();
            if ( preg_match_all( '/<c\b([^>]*)>(.*?)<\/c>/is', $row_xml, $cells, PREG_SET_ORDER ) ) {
                foreach ( $cells as $cell ) {
                    $attrs = $cell[1]; $body = $cell[2]; $ref = $this->xml_attr( $attrs, 'r' );
                    if ( ! $ref ) continue;
                    $col = $this->column_index_from_ref( $ref ); $type = $this->xml_attr( $attrs, 't' ); $value = '';
                    if ( 'inlineStr' === $type ) {
                        if ( preg_match_all( '/<t\b[^>]*>(.*?)<\/t>/is', $body, $parts ) ) foreach ( $parts[1] as $part ) $value .= $this->xml_decode( $part );
                    } else {
                        $raw = '';
                        if ( preg_match( '/<v\b[^>]*>(.*?)<\/v>/is', $body, $vm ) ) $raw = $this->xml_decode( $vm[1] );
                        if ( 's' === $type ) $value = $shared[(int)$raw] ?? '';
                        elseif ( 'b' === $type ) $value = '1' === trim( $raw ) ? 'true' : 'false';
                        else $value = $raw;
                    }
                    $values[$col] = $value;
                }
            }
            if ( $values ) { $max = max( array_keys( $values ) ); $dense = array_fill( 0, $max + 1, '' ); foreach ( $values as $i => $v ) $dense[$i] = $v; $indexed[$row_number - 1] = $dense; }
            else $indexed[$row_number - 1] = array();
        }
        if ( ! $indexed ) return array();
        $max_row = max( array_keys( $indexed ) ); $rows = array_fill( 0, $max_row + 1, array() );
        foreach ( $indexed as $i => $row ) $rows[$i] = $row;
        return $rows;
    }

    private function xml_attr( $attributes, $name ) {
        $quoted = preg_quote( $name, '/' );
        if ( preg_match( '/(?:^|\s)' . $quoted . '\s*=\s*(["\'])(.*?)\1/is', $attributes, $m ) ) return $this->xml_decode( $m[2] );
        return '';
    }

    private function xml_decode( $value ) { return html_entity_decode( strip_tags( (string) $value ), ENT_QUOTES | ENT_XML1, 'UTF-8' ); }

    private function column_index_from_ref( $ref ) {
        preg_match( '/^([A-Z]+)/i', $ref, $m ); $letters = strtoupper( $m[1] ?? 'A' ); $n = 0;
        for ( $i = 0; $i < strlen( $letters ); $i++ ) $n = $n * 26 + ( ord( $letters[$i] ) - 64 );
        return max( 0, $n - 1 );
    }

    private function sheet_xml( $rows, $columns = array(), $is_data = false ) {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if ( $is_data ) {
            $xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
            $xml .= '<cols>';
            foreach ( $columns as $i => $col ) { $width = ! empty( $col['readonly'] ) ? 18 : 28; $xml .= '<col min="' . ( $i + 1 ) . '" max="' . ( $i + 1 ) . '" width="' . $width . '" customWidth="1"/>'; }
            $xml .= '</cols>';
        }
        $xml .= '<sheetData>';
        foreach ( $rows as $r => $row ) {
            $rownum = $r + 1; $xml .= '<row r="' . $rownum . '">';
            foreach ( (array) $row as $c => $value ) {
                $style = 0;
                if ( 0 === $r ) $style = 1;
                elseif ( $is_data && ! empty( $columns[$c]['readonly'] ) ) $style = 2;
                elseif ( $is_data ) $style = 3;
                $ref = $this->column_letters( $c + 1 ) . $rownum;
                $xml .= '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . $this->xml_text( $value ) . '</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';
        if ( $is_data && count( $rows ) > 1 && count( $columns ) ) $xml .= '<autoFilter ref="A1:' . $this->column_letters( count( $columns ) ) . count( $rows ) . '"/>';
        $xml .= '</worksheet>';
        return $xml;
    }

    private function styles_xml() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE7E7E7"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
    private function workbook_xml() { return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Data" sheetId="1" r:id="rId1"/><sheet name="Help" sheetId="2" r:id="rId2"/><sheet name="Map" sheetId="3" state="hidden" r:id="rId3"/><sheet name="Original" sheetId="4" state="veryHidden" r:id="rId4"/></sheets></workbook>'; }
    private function workbook_rels_xml() { return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/><Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet4.xml"/><Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>'; }
    private function root_rels_xml() { return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>'; }
    private function content_types_xml() { return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet4.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>'; }
    private function app_xml() { return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>9Code ACF Data Engine</Application></Properties>'; }
    private function core_xml() { $date = gmdate( 'Y-m-d\TH:i:s\Z' ); return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>9Code Data Export</dc:title><dc:creator>9Code ACF Data Engine</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . $date . '</dcterms:created></cp:coreProperties>'; }
    private function xml_text( $value ) { $value = preg_replace( '/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string) $value ); return htmlspecialchars( $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' ); }
    private function column_letters( $n ) { $s = ''; while ( $n > 0 ) { $n--; $s = chr( 65 + ( $n % 26 ) ) . $s; $n = intdiv( $n, 26 ); } return $s; }
    private function friendly_type( $type ) {
        $map = array( 'text'=>'Short text / heading','textarea'=>'Paragraph / long text','wysiwyg'=>'Rich text / paragraphs','number'=>'Number','range'=>'Number','email'=>'Email','url'=>'Link','image'=>'Image ID','file'=>'File ID','gallery'=>'Gallery JSON','select'=>'Choice','checkbox'=>'Multiple choices','radio'=>'Choice','button_group'=>'Choice','true_false'=>'Yes / No','date_picker'=>'Date','date_time_picker'=>'Date and time','time_picker'=>'Time','post_object'=>'Related record','relationship'=>'Related records JSON','taxonomy'=>'Taxonomy','user'=>'User','repeater'=>'Repeating data JSON','group'=>'Grouped data JSON','flexible_content'=>'Flexible content JSON','clone'=>'Reusable fields JSON' );
        return $map[$type] ?? ucwords( str_replace( '_', ' ', $type ) );
    }
}
