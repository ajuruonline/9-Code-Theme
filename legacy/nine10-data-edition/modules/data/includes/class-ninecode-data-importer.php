<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class NineCode_Data_Importer {
    private $history_changes = array();
    private $history_label = '';
    private $scope_guard = null;
    private $scope_lock_status = 'legacy';
    private $scope_lock_expected = false;

    public static function get_history() {
        return (array) get_option( 'ninecode_acf_import_history', array() );
    }

    public function import_uploaded_file( $file, $options = array() ) {
        $trusted_local = ! empty( $options['_trusted_local_file'] );
        $check = $this->validate_upload( $file, array( 'json', 'csv', 'xlsx' ), $trusted_local );
        if ( is_wp_error( $check ) ) { return $check; }
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        $this->history_label = sanitize_file_name( $file['name'] );
        $this->scope_guard = null;
        $this->scope_lock_status = 'legacy';
        $this->scope_lock_expected = false;
        if ( 'csv' === $ext ) {
            $records = $this->parse_csv_file( $file['tmp_name'] );
            if ( is_wp_error( $records ) ) { return $records; }
        } elseif ( 'xlsx' === $ext ) {
            if ( ! class_exists( 'NineCode_Excel' ) ) { return new WP_Error( 'excel_missing', 'Excel support is not available.' ); }
            $excel = new NineCode_Excel();
            $records = $excel->parse_workbook( $file['tmp_name'] );
            if ( is_wp_error( $records ) ) { return $records; }
            $this->scope_guard = $excel->get_last_scope_guard();
            $this->scope_lock_expected = $excel->get_scope_lock_expected();
        } else {
            $json = file_get_contents( $file['tmp_name'] );
            $data = json_decode( $json, true );
            if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) { return new WP_Error( 'invalid_json', 'The uploaded JSON file is invalid.' ); }
            $records = $this->records_from_json( $data );
            if ( is_wp_error( $records ) ) { return $records; }
            if ( isset( $data['scope_guard'] ) && is_array( $data['scope_guard'] ) ) { $this->scope_guard = $data['scope_guard']; }
            $this->scope_lock_expected = 'ninecode-acf-ai-pack' === ( $data['format'] ?? '' ) && intval( $data['version'] ?? 0 ) >= 2;
        }
        if ( $this->scope_lock_expected && ! $this->scope_guard ) {
            return new WP_Error( 'scope_lock_missing', '9Code Scope Lock is missing or unreadable. Export a fresh file and edit only its data values.' );
        }
        if ( $this->scope_guard && class_exists( 'NineCode_Scope_Lock' ) ) {
            $scope_check = NineCode_Scope_Lock::verify( $this->scope_guard, $records );
            if ( is_wp_error( $scope_check ) ) { return $scope_check; }
            $this->scope_lock_status = 'verified';
        }
        $options = wp_parse_args( $options, array( 'dry_run' => false, 'create_missing' => false, 'create_missing_records' => false, 'create_missing_terms' => false, 'import_identity' => false, 'import_term_identity' => true, 'import_terms' => true, 'capture_versions' => true, 'atomic' => true, 'approved_change_ids' => null ) );

        // Applied bulk imports are two-phase: validate the full pack first, then write it.
        // This prevents a structural error near the end of a workbook/AI pack from leaving earlier rows applied.
        if ( empty( $options['dry_run'] ) && ! empty( $options['atomic'] ) ) {
            $preview_options = $options;
            $preview_options['dry_run'] = true;
            $preview_options['capture_versions'] = false;

            // Recheck the complete reviewed proposal before applying a selected subset.
            // This ensures a live change anywhere in the reviewed proposal invalidates the old preview.
            if ( ! empty( $options['expected_change_fingerprint'] ) ) {
                $full_preview_options = $preview_options;
                $full_preview_options['approved_change_ids'] = null;
                $full_preview = $this->import_records( $records, $full_preview_options );
                if ( is_wp_error( $full_preview ) ) { return $full_preview; }
                if ( ! empty( $full_preview['errors_count'] ) ) {
                    $full_preview['messages'][] = 'Nothing was changed. Fix the validation errors, then preview the file again.';
                    $full_preview['atomic_status'] = 'validation_blocked';
                    return $full_preview;
                }
                if ( ! hash_equals( (string) $options['expected_change_fingerprint'], (string) ( $full_preview['change_fingerprint'] ?? '' ) ) ) {
                    return new WP_Error( 'preview_changed', 'Live data changed after your preview. Nothing was changed. Preview the file again so you can review the new Before → After result.' );
                }
            }

            $preview = $this->import_records( $records, $preview_options );
            if ( is_wp_error( $preview ) ) { return $preview; }
            if ( ! empty( $preview['errors_count'] ) ) {
                $preview['messages'][] = 'Nothing was changed. Fix the validation errors, then import the file again.';
                $preview['atomic_status'] = 'validation_blocked';
                return $preview;
            }
        }

        if ( empty( $options['dry_run'] ) && ! empty( $options['capture_versions'] ) && class_exists( 'NineCode_Data_Version_Manager' ) ) {
            NineCode_Data_Version_Manager::capture_records( $records, 'Before import: ' . $this->history_label, 'import' );
        }

        $batch_start = count( $this->history_changes );
        $report = $this->import_records( $records, $options );
        if ( ! is_wp_error( $report ) && empty( $options['dry_run'] ) && ! empty( $options['atomic'] ) && ! empty( $report['errors_count'] ) ) {
            $rollback = $this->rollback_current_batch( $batch_start );
            $report['rolled_back'] = true;
            $report['atomic_status'] = 'rolled_back';
            $report['attempted_changes'] = intval( $report['changed'] );
            $report['changed'] = 0;
            $report['messages'][] = 'Import stopped because a write failed. All changes made by this import were automatically rolled back.';
            if ( ! empty( $rollback['errors_count'] ) ) {
                $report['errors_count'] += intval( $rollback['errors_count'] );
                $report['messages'] = array_merge( $report['messages'], (array) $rollback['messages'] );
                $report['messages'][] = 'Automatic rollback reported an error. Use Versions to inspect and restore the affected record data.';
            }
            return $report;
        }
        if ( ! is_wp_error( $report ) && empty( $options['dry_run'] ) && $this->history_changes ) { $this->commit_history(); }
        if ( is_array( $report ) ) {
            $report['scope_lock'] = $this->scope_lock_status;
            if ( 'verified' === $this->scope_lock_status ) { $report['messages'][] = 'Scope Lock verified: this import stayed inside the records and fields that were exported.'; }
            if ( 'legacy' === $this->scope_lock_status ) { $report['messages'][] = 'Legacy import: this older file has no 9Code Scope Lock. Normal validation, versions and rollback still apply.'; }
            if ( empty( $options['dry_run'] ) && empty( $report['errors_count'] ) ) { $report['atomic_status'] = 'committed'; }
        }
        return $report;
    }

    public function import_records_atomic( $records, $options = array(), $label = 'bulk data change' ) {
        $options = wp_parse_args( $options, array(
            'dry_run' => false,
            'create_missing' => false,
            'create_missing_records' => false,
            'create_missing_terms' => false,
            'import_identity' => false,
            'import_term_identity' => false,
            'import_terms' => true,
            'capture_versions' => true,
        ) );
        $this->history_label = sanitize_text_field( $label ?: 'bulk data change' );

        $preview_options = $options;
        $preview_options['dry_run'] = true;
        $preview_options['capture_versions'] = false;
        $preview = $this->import_records( $records, $preview_options );
        if ( is_wp_error( $preview ) ) { return $preview; }
        if ( ! empty( $preview['errors_count'] ) ) {
            $preview['atomic_status'] = 'validation_blocked';
            $preview['messages'][] = 'Nothing was changed. Fix the validation errors, then try the bulk change again.';
            return $preview;
        }

        if ( ! empty( $options['capture_versions'] ) && class_exists( 'NineCode_Data_Version_Manager' ) ) {
            NineCode_Data_Version_Manager::capture_records( $records, 'Before ' . $this->history_label, 'bulk' );
        }

        $batch_start = count( $this->history_changes );
        $report = $this->import_records( $records, $options );
        if ( is_wp_error( $report ) ) { return $report; }
        if ( ! empty( $report['errors_count'] ) ) {
            $rollback = $this->rollback_current_batch( $batch_start );
            $report['rolled_back'] = true;
            $report['atomic_status'] = 'rolled_back';
            $report['attempted_changes'] = intval( $report['changed'] );
            $report['changed'] = 0;
            $report['messages'][] = 'The bulk change stopped because a write failed. Changes already made by this operation were rolled back.';
            if ( ! empty( $rollback['errors_count'] ) ) {
                $report['errors_count'] += intval( $rollback['errors_count'] );
                $report['messages'] = array_merge( $report['messages'], (array) $rollback['messages'] );
                $report['messages'][] = 'Rollback reported an error. Use Versions to inspect and restore the affected records.';
            }
            return $report;
        }
        if ( $this->history_changes ) { $this->commit_history(); }
        $report['atomic_status'] = 'committed';
        return $report;
    }

    public function import_staged_file( $path, $name, $options = array() ) {
        $real_path = realpath( $path );
        $temp_root = realpath( get_temp_dir() );
        if ( ! $real_path || ! $temp_root || 0 !== strpos( $real_path, trailingslashit( $temp_root ) ) || ! is_readable( $real_path ) ) {
            return new WP_Error( 'stage_missing', 'The reviewed import copy is no longer available. Preview the file again.' );
        }
        $file = array(
            'name' => sanitize_file_name( $name ),
            'tmp_name' => $real_path,
            'size' => filesize( $real_path ),
            'error' => 0,
        );
        $options['_trusted_local_file'] = true;
        return $this->import_uploaded_file( $file, $options );
    }

    private function validate_upload( $file, $allowed_exts, $trusted_local = false ) {
        if ( ! is_array( $file ) || empty( $file['tmp_name'] ) ) { return new WP_Error( 'upload_missing', 'The import file could not be read.' ); }
        $readable = $trusted_local ? is_readable( $file['tmp_name'] ) : is_uploaded_file( $file['tmp_name'] );
        if ( ! $readable ) { return new WP_Error( 'upload_missing', $trusted_local ? 'The reviewed import copy is no longer available. Preview the file again.' : 'The uploaded file could not be read.' ); }
        if ( ! empty( $file['error'] ) ) { return new WP_Error( 'upload_error', 'Upload failed with error code ' . intval( $file['error'] ) . '.' ); }
        if ( ! empty( $file['size'] ) && $file['size'] > 50 * MB_IN_BYTES ) { return new WP_Error( 'too_large', 'Import files are limited to 50 MB per request in this build.' ); }
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, $allowed_exts, true ) ) { return new WP_Error( 'bad_type', 'Unsupported import file type.' ); }
        return true;
    }

    private function records_from_json( $data ) {
        $format = $data['format'] ?? '';
        if ( 'ninecode-acf-ai-record' === $format && ! empty( $data['record'] ) ) { return array( $data['record'] ); }
        if ( 'ninecode-acf-ai-pack' === $format && isset( $data['records'] ) && is_array( $data['records'] ) ) { return $data['records']; }
        if ( isset( $data['records'] ) && is_array( $data['records'] ) ) { return $data['records']; }
        if ( isset( $data[0]['object'] ) ) { return $data; }
        return new WP_Error( 'unknown_format', 'JSON does not match a supported 9Code AI/data pack format.' );
    }

    private function parse_csv_file( $path ) {
        $handle = fopen( $path, 'r' );
        if ( ! $handle ) { return new WP_Error( 'csv_open', 'Could not open CSV file.' ); }
        $headers = fgetcsv( $handle, 0, ',', '"', '' );
        if ( ! $headers ) { fclose( $handle ); return new WP_Error( 'csv_headers', 'CSV has no header row.' ); }
        $headers = array_map( 'trim', $headers );
        $this->scope_lock_expected = in_array( '__ninecode_scope_guard', $headers, true );
        $records = array();
        while ( ( $cells = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false ) {
            if ( ! array_filter( $cells, function( $v ) { return '' !== trim( (string) $v ); } ) ) { continue; }
            $row = array();
            foreach ( $headers as $i => $header ) { $row[ $header ] = $cells[ $i ] ?? ''; }
            if ( ! $this->scope_guard && class_exists( 'NineCode_Scope_Lock' ) && ! empty( $row['__ninecode_scope_guard'] ) ) { $this->scope_guard = NineCode_Scope_Lock::decode( $row['__ninecode_scope_guard'] ); }
            $kind = sanitize_key( $row['kind'] ?? 'post' );
            $object = array(
                'kind' => $kind, 'id' => absint( $row['object_id'] ?? 0 ), 'slug' => sanitize_title( $row['slug'] ?? '' ), 'parent' => absint( $row['parent'] ?? 0 ),
            );
            if ( 'term' === $kind ) {
                $object['taxonomy'] = sanitize_key( $row['taxonomy'] ?? '' );
                $object['name'] = sanitize_text_field( $row['title_or_name'] ?? '' );
            } else {
                $object['post_type'] = sanitize_key( $row['post_type'] ?? 'post' );
                $object['title'] = sanitize_text_field( $row['title_or_name'] ?? '' );
                $object['status'] = sanitize_key( $row['status'] ?? 'draft' );
            }
            $record = array( 'object' => $object, 'fields' => array(), 'meta' => array(), 'taxonomies' => array() );
            foreach ( $row as $header => $value ) {
                if ( 0 === strpos( $header, 'acf:' ) ) {
                    $parts = explode( ':', $header, 3 );
                    $decoded = $this->decode_csv_value( $value );
                    $record['fields'][] = array( 'key' => sanitize_key( $parts[1] ?? '' ), 'name' => sanitize_key( $parts[2] ?? '' ), 'value' => $decoded );
                } elseif ( 0 === strpos( $header, 'meta:' ) ) {
                    $parts = explode( ':', $header, 3 );
                    $storage = 'multi' === ( $parts[1] ?? '' ) ? 'multi' : 'single';
                    $key = trim( (string) ( $parts[2] ?? '' ) );
                    if ( $key ) { $record['meta'][] = array( 'key' => $key, 'storage' => $storage, 'value' => $this->decode_csv_value( $value ) ); }
                } elseif ( 0 === strpos( $header, 'tax:' ) ) {
                    $tax = sanitize_key( substr( $header, 4 ) );
                    $terms = array_filter( array_map( 'trim', explode( '|', (string) $value ) ) );
                    $record['taxonomies'][ $tax ] = array_map( function( $slug ) use ( $tax ) { return array( 'slug' => sanitize_title( $slug ), 'taxonomy' => $tax ); }, $terms );
                }
            }
            $records[] = $record;
        }
        fclose( $handle );
        return $records;
    }

    private function decode_csv_value( $value ) {
        $trim = trim( (string) $value );
        if ( '' === $trim ) { return ''; }
        if ( in_array( substr( $trim, 0, 1 ), array( '[', '{' ), true ) ) {
            $decoded = json_decode( $trim, true );
            if ( JSON_ERROR_NONE === json_last_error() ) { return $decoded; }
        }
        if ( 'true' === strtolower( $trim ) ) { return true; }
        if ( 'false' === strtolower( $trim ) ) { return false; }
        return $value;
    }

    public function import_records( $records, $options, $media_map = array() ) {
        $options = wp_parse_args( $options, array( 'dry_run' => false, 'create_missing' => false, 'create_missing_records' => false, 'create_missing_terms' => false, 'import_identity' => false, 'import_term_identity' => false, 'import_terms' => true, 'capture_versions' => false, 'approved_change_ids' => null ) );
        if ( is_array( $options['approved_change_ids'] ) ) { $options['approved_change_ids'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', $options['approved_change_ids'] ) ) ) ); }
        $report = array( 'changed' => 0, 'created' => 0, 'conflicts' => 0, 'skipped' => 0, 'errors_count' => 0, 'messages' => array(), 'change_details' => array(), 'change_details_total' => 0, 'affected_records' => array(), 'change_types' => array( 'acf' => 0, 'meta' => 0, 'taxonomy' => 0, 'term' => 0 ), 'change_fingerprint_parts' => array(), 'change_ids' => array() );
        if ( ! function_exists( 'update_field' ) ) {
            $report['messages'][] = 'ACF is not active; ACF values cannot be imported.';
        }
        foreach ( (array) $records as $index => $record ) {
            if ( ! is_array( $record ) || empty( $record['object'] ) ) { $report['skipped']++; $report['messages'][] = 'Row ' . ( $index + 1 ) . ': missing object metadata.'; continue; }
            $resolved = $this->resolve_object( $record['object'], $options, $report );
            if ( is_wp_error( $resolved ) ) { $report['errors_count']++; $report['messages'][] = 'Row ' . ( $index + 1 ) . ': ' . $resolved->get_error_message(); continue; }

            $baseline = (array) ( $record['ninecode_original'] ?? array() );
            $baseline_object = ! empty( $baseline['available'] ) ? (array) ( $baseline['object'] ?? array() ) : array();
            $baseline_fields = ! empty( $baseline['available'] ) ? (array) ( $baseline['fields'] ?? array() ) : array();
            $baseline_taxonomies = ! empty( $baseline['available'] ) ? (array) ( $baseline['taxonomies'] ?? array() ) : array();
            $baseline_meta = ! empty( $baseline['available'] ) ? (array) ( $baseline['meta'] ?? array() ) : array();

            // Data-only firewall: post identity/publishing belongs to 9 Post Editor, never this plugin.
            // Taxonomy-item identity remains in scope because taxonomy data is owned here.
            if ( 'term' === $resolved['kind'] && ! empty( $options['import_term_identity'] ) ) {
                $this->import_identity( $resolved, $record['object'], $options, $report, $baseline_object );
            }
            if ( 'post' === $resolved['kind'] && ! empty( $options['import_terms'] ) && array_key_exists( 'taxonomies', $record ) ) {
                $this->import_taxonomy_allocations( $resolved, (array) $record['taxonomies'], $options, $report, $baseline_taxonomies );
            }

            foreach ( (array) ( $record['fields'] ?? array() ) as $field_row ) {
                $key = sanitize_key( $field_row['key'] ?? '' );
                if ( ! $key || 0 !== strpos( $key, 'field_' ) ) { $report['skipped']++; continue; }
                $field = $this->resolve_field( $key, $resolved['acf_id'] ?? false );
                if ( ! $field ) { $report['skipped']++; $report['messages'][] = 'Unknown ACF field key skipped: ' . $key; continue; }
                $value = array_key_exists( 'value', $field_row ) ? $field_row['value'] : null;
                $value = $this->normalize_reference_value( $value );
                if ( $media_map ) { $value = $this->rewrite_media_ids( $field, $value, $media_map ); }

                if ( ! empty( $resolved['preview_new'] ) ) {
                    $report['changed']++;
                    continue;
                }
                $before = function_exists( 'get_field' ) ? get_field( $key, $resolved['acf_id'], false ) : null;
                $has_original = array_key_exists( $key, $baseline_fields );
                if ( $has_original ) {
                    $original = $this->normalize_reference_value( $baseline_fields[$key] );
                    // The Excel cell was not changed. Do not touch live data even if live changed later.
                    if ( $this->values_equal( $value, $original ) ) { $report['skipped']++; continue; }
                    // Someone already made the same change on the live site.
                    if ( $this->values_equal( $before, $value ) ) { continue; }
                    // Both Excel and live WordPress changed differently after export: protect live data.
                    if ( ! $this->values_equal( $before, $original ) ) {
                        $report['conflicts']++;
                        $label = sanitize_text_field( $field['label'] ?? $field['name'] ?? $key );
                        $report['messages'][] = 'Conflict protected: ' . $resolved['label'] . ' → ' . $label . ' changed on the live site after this Excel file was exported. Live data was kept.';
                        continue;
                    }
                } elseif ( $this->values_equal( $before, $value ) ) {
                    continue;
                }

                $label = sanitize_text_field( $field['label'] ?? $field['name'] ?? $key );
                $change_id = $this->make_change_id( $resolved, 'acf', $key );
                if ( ! $this->change_is_approved( $change_id, $options ) ) { $report['skipped']++; continue; }
                $this->add_change_detail( $report, $resolved, 'acf', $label, $before, $value, $change_id );
                $report['changed']++;
                if ( ! empty( $options['dry_run'] ) ) { continue; }
                $this->history_changes[] = array( 'change_type' => 'acf', 'acf_id' => $resolved['acf_id'], 'field_key' => $key, 'before' => $before );
                $ok = update_field( $key, $value, $resolved['acf_id'] );
                if ( false === $ok && ! $this->values_equal( function_exists( 'get_field' ) ? get_field( $key, $resolved['acf_id'], false ) : null, $value ) ) {
                    $report['errors_count']++; $report['messages'][] = 'Could not update field ' . $key . ' on object ' . $resolved['label'] . '.';
                }
            }

            if ( 'post' === $resolved['kind'] && ! empty( $record['meta'] ) ) {
                $this->import_plugin_meta( $resolved, (array) $record['meta'], $baseline_meta, $options, $report );
            }
        }
        $report['affected_records_count'] = count( (array) $report['affected_records'] );
        $fingerprint_parts = array_values( array_unique( (array) ( $report['change_fingerprint_parts'] ?? array() ) ) );
        sort( $fingerprint_parts, SORT_STRING );
        $report['change_fingerprint'] = hash( 'sha256', implode( '|', $fingerprint_parts ) );
        unset( $report['affected_records'], $report['change_fingerprint_parts'] );
        if ( empty( $report['messages'] ) ) { $report['messages'][] = ! empty( $options['dry_run'] ) ? 'Validation complete. No structural errors found.' : 'Import completed.'; }
        return $report;
    }

    private function add_change_detail( &$report, $resolved, $type, $field_label, $before, $after, $change_id = '' ) {
        $type = in_array( $type, array( 'acf', 'meta', 'taxonomy', 'term' ), true ) ? $type : 'acf';
        $object_key = sanitize_key( ( $resolved['kind'] ?? 'post' ) . '_' . absint( $resolved['id'] ?? 0 ) );
        if ( $object_key ) { $report['affected_records'][ $object_key ] = true; }
        if ( ! isset( $report['change_types'][ $type ] ) ) { $report['change_types'][ $type ] = 0; }
        $report['change_types'][ $type ]++;
        $report['change_details_total']++;
        if ( $change_id ) { $report['change_ids'][] = sanitize_key( $change_id ); }
        $fingerprint_payload = array(
            'kind' => sanitize_key( $resolved['kind'] ?? 'post' ),
            'id' => absint( $resolved['id'] ?? 0 ),
            'type' => $type,
            'field' => sanitize_text_field( $field_label ),
            'before' => $this->normalize_reference_value( $before ),
            'after' => $this->normalize_reference_value( $after ),
        );
        $report['change_fingerprint_parts'][] = hash( 'sha256', wp_json_encode( $fingerprint_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
        if ( count( $report['change_details'] ) >= 250 ) { return; }
        $report['change_details'][] = array(
            'record' => sanitize_text_field( $resolved['label'] ?? (string) ( $resolved['id'] ?? '' ) ),
            'object_id' => absint( $resolved['id'] ?? 0 ),
            'kind' => sanitize_key( $resolved['kind'] ?? 'post' ),
            'type' => $type,
            'field' => sanitize_text_field( $field_label ),
            'before' => $this->review_value( $before ),
            'after' => $this->review_value( $after ),
            'change_id' => sanitize_key( $change_id ),
        );
    }

    private function make_change_id( $resolved, $type, $identifier ) {
        $payload = sanitize_key( $resolved['kind'] ?? 'post' ) . '|' . absint( $resolved['id'] ?? 0 ) . '|' . sanitize_key( $type ) . '|' . sanitize_text_field( (string) $identifier );
        return 'chg_' . substr( hash( 'sha256', $payload ), 0, 24 );
    }

    private function change_is_approved( $change_id, $options ) {
        if ( ! isset( $options['approved_change_ids'] ) || null === $options['approved_change_ids'] ) { return true; }
        return in_array( sanitize_key( $change_id ), (array) $options['approved_change_ids'], true );
    }

    private function review_value( $value ) {
        if ( is_bool( $value ) ) { return $value ? 'Yes' : 'No'; }
        if ( null === $value ) { return '—'; }
        if ( is_array( $value ) || is_object( $value ) ) {
            $value = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        }
        $value = trim( wp_strip_all_tags( (string) $value ) );
        if ( '' === $value ) { return '—'; }
        if ( function_exists( 'mb_strlen' ) && mb_strlen( $value ) > 500 ) { return mb_substr( $value, 0, 497 ) . '…'; }
        if ( strlen( $value ) > 500 ) { return substr( $value, 0, 497 ) . '...'; }
        return $value;
    }


    private function import_plugin_meta( $resolved, $rows, $baseline_meta, $options, &$report ) {
        foreach ( (array) $rows as $meta_row ) {
            if ( ! is_array( $meta_row ) ) { $report['skipped']++; continue; }
            $key = $this->clean_meta_key( $meta_row['key'] ?? '' );
            if ( ! $key || $this->is_protected_meta_key( $key ) || $this->is_acf_managed_meta_key( $resolved['id'], $key ) ) {
                $report['skipped']++;
                if ( $key ) { $report['messages'][] = 'Protected or ACF-managed meta skipped: ' . $key; }
                continue;
            }
            $storage = 'multi' === ( $meta_row['storage'] ?? '' ) ? 'multi' : 'single';
            $value = array_key_exists( 'value', $meta_row ) ? $this->normalize_reference_value( $meta_row['value'] ) : '';
            if ( 'multi' === $storage && ! is_array( $value ) ) { $value = array( $value ); }

            $current_rows = get_post_meta( $resolved['id'], $key, false );
            $exists_live = ! empty( $current_rows );
            $has_original = array_key_exists( $key, $baseline_meta );
            $registered = function_exists( 'get_registered_meta_keys' ) ? (array) get_registered_meta_keys( 'post', $resolved['post_type'] ) : array();
            if ( ! $exists_live && ! $has_original && ! array_key_exists( $key, $registered ) ) {
                $report['skipped']++;
                $report['messages'][] = 'Unknown plugin meta skipped: ' . $key . '. Export the record first or register the meta key before importing it.';
                continue;
            }

            $before = $this->read_meta_value( $resolved['id'], $key, $storage );
            if ( $has_original ) {
                $original_row = is_array( $baseline_meta[$key] ) && array_key_exists( 'value', $baseline_meta[$key] ) ? $baseline_meta[$key] : array( 'value' => $baseline_meta[$key], 'storage' => $storage );
                $original_storage = 'multi' === ( $original_row['storage'] ?? '' ) ? 'multi' : 'single';
                $original = $original_row['value'] ?? '';
                if ( 'multi' === $original_storage && ! is_array( $original ) ) { $original = array( $original ); }
                if ( $this->values_equal( $value, $original ) ) { $report['skipped']++; continue; }
                if ( $this->values_equal( $before, $value ) ) { continue; }
                if ( ! $this->values_equal( $before, $original ) ) {
                    $report['conflicts']++;
                    $report['messages'][] = 'Conflict protected: ' . $resolved['label'] . ' → ' . $this->friendly_meta_label( $key ) . ' changed on the live site after this file was exported. Live data was kept.';
                    continue;
                }
            } elseif ( $this->values_equal( $before, $value ) ) {
                continue;
            }

            $change_id = $this->make_change_id( $resolved, 'meta', $key );
            if ( ! $this->change_is_approved( $change_id, $options ) ) { $report['skipped']++; continue; }
            $this->add_change_detail( $report, $resolved, 'meta', $this->friendly_meta_label( $key ), $before, $value, $change_id );
            $report['changed']++;
            if ( ! empty( $options['dry_run'] ) ) { continue; }
            $this->history_changes[] = array( 'change_type' => 'meta', 'id' => $resolved['id'], 'meta_key' => $key, 'storage' => $storage, 'before' => $before );
            $ok = $this->write_meta_value( $resolved['id'], $key, $storage, $value );
            $after = $this->read_meta_value( $resolved['id'], $key, $storage );
            if ( ! $ok || ! $this->values_equal( $after, $value ) ) {
                $report['errors_count']++;
                $report['messages'][] = 'Could not update plugin meta ' . $this->friendly_meta_label( $key ) . ' on ' . $resolved['label'] . '.';
            }
        }
    }

    private function clean_meta_key( $key ) {
        $key = trim( (string) $key );
        if ( '' === $key || strlen( $key ) > 191 || ! preg_match( '/^[A-Za-z0-9_.:\-]+$/', $key ) ) { return ''; }
        return $key;
    }

    private function is_protected_meta_key( $key ) {
        $exact = array( '_edit_lock', '_edit_last', '_thumbnail_id', '_wp_old_slug', '_wp_trash_meta_status', '_wp_trash_meta_time' );
        $exact = (array) apply_filters( 'ninecode_data_engine_protected_meta_keys', $exact );
        if ( in_array( $key, $exact, true ) ) { return true; }
        $prefixes = array( '_elementor_', '_wp_', '_oembed_', '_menu_item_', '_customize_', '_transient_', '_site_transient_' );
        $prefixes = (array) apply_filters( 'ninecode_data_engine_protected_meta_prefixes', $prefixes );
        foreach ( $prefixes as $prefix ) { if ( '' !== $prefix && 0 === strpos( $key, (string) $prefix ) ) { return true; } }
        return (bool) apply_filters( 'ninecode_data_engine_is_protected_meta_key', false, $key );
    }

    private function is_acf_managed_meta_key( $post_id, $key ) {
        $raw = get_post_meta( $post_id, $key, true );
        if ( is_string( $raw ) && 0 === strpos( $raw, 'field_' ) && 0 === strpos( $key, '_' ) ) { return true; }
        $reference_key = 0 === strpos( $key, '_' ) ? $key : '_' . $key;
        $reference = get_post_meta( $post_id, $reference_key, true );
        return is_string( $reference ) && 0 === strpos( $reference, 'field_' );
    }

    private function read_meta_value( $post_id, $key, $storage = 'single' ) {
        if ( 'multi' === $storage ) { return array_map( 'maybe_unserialize', (array) get_post_meta( $post_id, $key, false ) ); }
        return maybe_unserialize( get_post_meta( $post_id, $key, true ) );
    }

    private function write_meta_value( $post_id, $key, $storage, $value ) {
        if ( 'multi' === $storage ) {
            if ( false === delete_post_meta( $post_id, $key ) && get_post_meta( $post_id, $key, false ) ) { return false; }
            foreach ( (array) $value as $item ) {
                if ( false === add_post_meta( $post_id, $key, $item, false ) ) { return false; }
            }
            return true;
        }
        $result = update_post_meta( $post_id, $key, $value );
        return false !== $result || $this->values_equal( $this->read_meta_value( $post_id, $key, 'single' ), $value );
    }

    private function friendly_meta_label( $key ) {
        $label = preg_replace( '/[_\-]+/', ' ', ltrim( (string) $key, '_' ) );
        $label = trim( preg_replace( '/\s+/', ' ', (string) $label ) );
        return $label ? ucwords( $label ) : (string) $key;
    }

    private function resolve_object( $object, $options, &$report ) {
        $kind = sanitize_key( $object['kind'] ?? 'post' );
        if ( 'term' === $kind ) {
            $taxonomy = sanitize_key( $object['taxonomy'] ?? '' );
            if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) { return new WP_Error( 'taxonomy_missing', 'Taxonomy ' . $taxonomy . ' is not registered.' ); }
            $id = absint( $object['id'] ?? 0 );
            $term = $id ? get_term( $id, $taxonomy ) : false;
            if ( ! $term || is_wp_error( $term ) ) {
                $slug = sanitize_title( $object['slug'] ?? '' );
                if ( $slug ) { $term = get_term_by( 'slug', $slug, $taxonomy ); }
            }
            if ( ! $term ) {
                if ( empty( $options['create_missing_terms'] ) && empty( $options['create_missing'] ) ) { return new WP_Error( 'term_missing', 'Taxonomy item not found.' ); }
                if ( ! empty( $options['dry_run'] ) ) {
                    $report['created']++;
                    return array( 'kind' => 'term', 'id' => 0, 'taxonomy' => $taxonomy, 'acf_id' => false, 'preview_new' => true, 'label' => $object['name'] ?? $object['slug'] ?? 'new term' );
                }
                $created = wp_insert_term( sanitize_text_field( $object['name'] ?? $object['slug'] ?? 'Imported term' ), $taxonomy, array( 'slug' => sanitize_title( $object['slug'] ?? '' ), 'description' => sanitize_textarea_field( $object['description'] ?? '' ) ) );
                if ( is_wp_error( $created ) ) { return $created; }
                $term = get_term( $created['term_id'], $taxonomy ); $report['created']++;
            }
            return array( 'kind' => 'term', 'id' => (int) $term->term_id, 'taxonomy' => $taxonomy, 'acf_id' => 'term_' . $term->term_id, 'term' => $term, 'label' => $term->name );
        }

        $post_type = sanitize_key( $object['post_type'] ?? 'post' );
        if ( ! post_type_exists( $post_type ) ) { return new WP_Error( 'post_type_missing', 'Post type ' . $post_type . ' is not registered.' ); }
        $id = absint( $object['id'] ?? 0 );
        $post = $id ? get_post( $id ) : false;
        if ( $post && $post->post_type !== $post_type ) { $post = false; }
        if ( ! $post ) {
            $slug = sanitize_title( $object['slug'] ?? '' );
            if ( $slug ) {
                $found = get_page_by_path( $slug, OBJECT, $post_type );
                if ( $found ) { $post = $found; }
            }
        }
        if ( ! $post ) {
            return new WP_Error( 'post_missing', 'Record not found. Create or restore the record in 9 Post Editor first, then import its ACF/taxonomy data here.' );
        }
        return array( 'kind' => 'post', 'id' => (int) $post->ID, 'post_type' => $post_type, 'acf_id' => (int) $post->ID, 'post' => $post, 'label' => $post->post_title ?: (string) $post->ID );
    }

    private function import_identity( $resolved, $object, $options, &$report, $original_object = array() ) {
        if ( ! empty( $resolved['preview_new'] ) ) { return; }
        if ( 'post' === $resolved['kind'] ) {
            // Intentionally blocked: title, slug, status and parent are Post Manager responsibilities.
            $report['skipped']++;
            return;
        }

        $term = get_term( $resolved['id'], $resolved['taxonomy'] ); if ( ! $term || is_wp_error( $term ) ) { return; }
        $before = array( 'name' => $term->name, 'slug' => $term->slug, 'description' => $term->description, 'parent' => (int) $term->parent );
        $after = $before;
        $changed_keys = array();
        $candidates = array();
        if ( array_key_exists( 'name', $object ) ) { $candidates['name'] = sanitize_text_field( $object['name'] ); }
        if ( array_key_exists( 'slug', $object ) ) { $candidates['slug'] = sanitize_title( $object['slug'] ); }
        if ( array_key_exists( 'description', $object ) ) { $candidates['description'] = sanitize_textarea_field( $object['description'] ); }
        if ( array_key_exists( 'parent', $object ) ) { $candidates['parent'] = absint( $object['parent'] ); }

        foreach ( $candidates as $key => $target ) {
            if ( array_key_exists( $key, $original_object ) ) {
                $original = $original_object[$key];
                if ( 'name' === $key ) { $original = sanitize_text_field( $original ); }
                elseif ( 'slug' === $key ) { $original = sanitize_title( $original ); }
                elseif ( 'description' === $key ) { $original = sanitize_textarea_field( $original ); }
                elseif ( 'parent' === $key ) { $original = absint( $original ); }

                if ( $this->values_equal( $target, $original ) ) { $report['skipped']++; continue; }
                if ( $this->values_equal( $before[$key], $target ) ) { continue; }
                if ( ! $this->values_equal( $before[$key], $original ) ) {
                    $report['conflicts']++;
                    $report['messages'][] = 'Conflict protected: ' . $resolved['label'] . ' → ' . ucwords( str_replace( '_', ' ', $key ) ) . ' changed on the live site after this Excel file was exported. Live data was kept.';
                    continue;
                }
            } elseif ( $this->values_equal( $before[$key], $target ) ) {
                continue;
            }
            $after[$key] = $target;
            $changed_keys[] = $key;
        }
        if ( ! $changed_keys ) { return; }
        $approved_keys = array();
        foreach ( $changed_keys as $changed_key ) {
            $change_id = $this->make_change_id( $resolved, 'term', $changed_key );
            if ( ! $this->change_is_approved( $change_id, $options ) ) { $report['skipped']++; $after[ $changed_key ] = $before[ $changed_key ]; continue; }
            $approved_keys[] = $changed_key;
            $this->add_change_detail( $report, $resolved, 'term', ucwords( str_replace( '_', ' ', $changed_key ) ), $before[ $changed_key ] ?? '', $after[ $changed_key ] ?? '', $change_id );
        }
        if ( ! $approved_keys ) { return; }
        $report['changed']++;
        if ( ! empty( $options['dry_run'] ) ) { return; }
        $this->history_changes[] = array( 'change_type' => 'identity_term', 'id' => $term->term_id, 'taxonomy' => $term->taxonomy, 'before' => $before );
        $updated = wp_update_term( $term->term_id, $term->taxonomy, $after );
        if ( is_wp_error( $updated ) ) {
            $report['errors_count']++;
            $report['messages'][] = 'Could not update taxonomy item ' . $resolved['label'] . ': ' . $updated->get_error_message();
        }
    }

    private function import_taxonomy_allocations( $resolved, $taxonomies, $options, &$report, $original_taxonomies = array() ) {
        foreach ( (array) $taxonomies as $taxonomy => $items ) {
            $taxonomy = sanitize_key( $taxonomy );
            if ( ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $resolved['post_type'], $taxonomy ) ) { $report['skipped']++; continue; }

            $target_slugs = $this->canonical_taxonomy_items( $items );
            $before_terms = wp_get_object_terms( $resolved['id'], $taxonomy, array( 'fields' => 'all' ) );
            $before_terms = is_wp_error( $before_terms ) ? array() : (array) $before_terms;
            $before_slugs = array();
            foreach ( $before_terms as $term ) { if ( is_object( $term ) && ! empty( $term->slug ) ) { $before_slugs[] = sanitize_title( $term->slug ); } }
            $before_slugs = array_values( array_unique( array_filter( $before_slugs ) ) ); sort( $before_slugs );

            if ( array_key_exists( $taxonomy, $original_taxonomies ) ) {
                $original_slugs = $this->canonical_taxonomy_items( $original_taxonomies[$taxonomy] );
                if ( $target_slugs === $original_slugs ) { $report['skipped']++; continue; }
                if ( $before_slugs === $target_slugs ) { continue; }
                if ( $before_slugs !== $original_slugs ) {
                    $report['conflicts']++;
                    $tax_obj = get_taxonomy( $taxonomy );
                    $tax_label = $tax_obj && ! empty( $tax_obj->labels->name ) ? $tax_obj->labels->name : ucwords( str_replace( '_', ' ', $taxonomy ) );
                    $report['messages'][] = 'Conflict protected: ' . $resolved['label'] . ' → ' . $tax_label . ' changed on the live site after this Excel file was exported. Live taxonomy values were kept.';
                    continue;
                }
            } elseif ( $before_slugs === $target_slugs ) {
                continue;
            }

            // Selective Apply must be decided before any supporting taxonomy item is created.
            $change_id = $this->make_change_id( $resolved, 'taxonomy', $taxonomy );
            if ( ! $this->change_is_approved( $change_id, $options ) ) { $report['skipped']++; continue; }

            // Only create missing taxonomy items after conflict and approval checks pass.
            $target_ids = array();
            foreach ( (array) $items as $item ) {
                if ( is_string( $item ) ) { $item = array( 'slug' => $item, 'name' => $item ); }
                $term = false;
                if ( ! empty( $item['slug'] ) ) { $term = get_term_by( 'slug', sanitize_title( $item['slug'] ), $taxonomy ); }
                if ( ! $term && ! empty( $item['name'] ) ) { $term = get_term_by( 'name', sanitize_text_field( $item['name'] ), $taxonomy ); }
                if ( ! $term && ( ! empty( $options['create_missing_terms'] ) || ! empty( $options['create_missing'] ) ) && ! empty( $item['name'] ) ) {
                    if ( ! empty( $options['dry_run'] ) ) { $report['created']++; continue; }
                    $created = wp_insert_term( sanitize_text_field( $item['name'] ), $taxonomy, array( 'slug' => sanitize_title( $item['slug'] ?? '' ) ) );
                    if ( is_wp_error( $created ) ) {
                        $report['errors_count']++;
                        $report['messages'][] = 'Could not create taxonomy item for ' . $resolved['label'] . ' → ' . $taxonomy . ': ' . $created->get_error_message();
                    } else {
                        $term = get_term( $created['term_id'], $taxonomy );
                        $this->history_changes[] = array( 'change_type' => 'created_term', 'id' => absint( $created['term_id'] ), 'taxonomy' => $taxonomy );
                        $report['created']++;
                    }
                }
                if ( $term && ! is_wp_error( $term ) ) { $target_ids[] = (int) $term->term_id; }
            }
            $target_ids = array_values( array_unique( array_map( 'intval', $target_ids ) ) ); sort( $target_ids );
            $before_ids = array(); foreach ( $before_terms as $term ) { if ( is_object( $term ) && isset( $term->term_id ) ) { $before_ids[] = (int) $term->term_id; } }
            $before_ids = array_values( array_unique( $before_ids ) ); sort( $before_ids );
            if ( $before_ids === $target_ids && empty( $options['dry_run'] ) ) { continue; }
            $tax_obj = get_taxonomy( $taxonomy );
            $tax_label = $tax_obj && ! empty( $tax_obj->labels->name ) ? $tax_obj->labels->name : ucwords( str_replace( '_', ' ', $taxonomy ) );
            $this->add_change_detail( $report, $resolved, 'taxonomy', $tax_label, $before_slugs, $target_slugs, $change_id );
            $report['changed']++;
            if ( ! empty( $options['dry_run'] ) ) { continue; }
            $this->history_changes[] = array( 'change_type' => 'terms', 'id' => $resolved['id'], 'taxonomy' => $taxonomy, 'before' => $before_ids );
            $assigned = wp_set_object_terms( $resolved['id'], $target_ids, $taxonomy, false );
            if ( is_wp_error( $assigned ) ) {
                $report['errors_count']++;
                $report['messages'][] = 'Could not update taxonomy values for ' . $resolved['label'] . ' → ' . $taxonomy . ': ' . $assigned->get_error_message();
            }
        }
    }

    private function canonical_taxonomy_items( $items ) {
        $slugs = array();
        foreach ( (array) $items as $item ) {
            if ( is_string( $item ) ) { $slug = sanitize_title( $item ); }
            else { $slug = sanitize_title( $item['slug'] ?? ( $item['name'] ?? '' ) ); }
            if ( $slug ) { $slugs[] = $slug; }
        }
        $slugs = array_values( array_unique( $slugs ) );
        sort( $slugs );
        return $slugs;
    }

    private function resolve_field( $key, $acf_id ) {
        $field = false;
        if ( function_exists( 'get_field_object' ) ) { $field = get_field_object( $key, $acf_id ?: false, false, false ); }
        if ( ! $field && function_exists( 'acf_get_field' ) ) { $field = acf_get_field( $key ); }
        return is_array( $field ) ? $field : false;
    }

    private function normalize_reference_value( $value ) {
        if ( is_array( $value ) && isset( $value['__type'], $value['id'] ) && in_array( $value['__type'], array( 'post_ref', 'term_ref', 'user_ref' ), true ) ) { return absint( $value['id'] ); }
        if ( is_array( $value ) ) { foreach ( $value as $k => $v ) { $value[ $k ] = $this->normalize_reference_value( $v ); } }
        return $value;
    }

    private function rewrite_media_ids( $field, $value, $map ) {
        $type = $field['type'] ?? '';
        if ( in_array( $type, array( 'image', 'file' ), true ) ) {
            if ( is_numeric( $value ) && isset( $map[ (int) $value ] ) ) { return $map[ (int) $value ]; }
            if ( is_array( $value ) && isset( $value['ID'] ) && isset( $map[ (int) $value['ID'] ] ) ) { $value['ID'] = $map[ (int) $value['ID'] ]; $value['id'] = $value['ID']; }
            return $value;
        }
        if ( 'gallery' === $type && is_array( $value ) ) {
            foreach ( $value as $i => $item ) {
                if ( is_numeric( $item ) && isset( $map[ (int) $item ] ) ) { $value[ $i ] = $map[ (int) $item ]; }
                elseif ( is_array( $item ) && ! empty( $item['ID'] ) && isset( $map[ (int) $item['ID'] ] ) ) { $value[ $i ]['ID'] = $map[ (int) $item['ID'] ]; $value[ $i ]['id'] = $value[ $i ]['ID']; }
            }
            return $value;
        }
        if ( ! is_array( $value ) ) { return $value; }
        if ( in_array( $type, array( 'repeater', 'group', 'clone' ), true ) ) {
            $subs = (array) ( $field['sub_fields'] ?? array() );
            if ( 'group' === $type ) {
                foreach ( $subs as $sub ) { $name = $sub['name'] ?? ''; $key = array_key_exists( $name, $value ) ? $name : ( array_key_exists( $sub['key'] ?? '', $value ) ? $sub['key'] : null ); if ( null !== $key ) { $value[ $key ] = $this->rewrite_media_ids( $sub, $value[ $key ], $map ); } }
            } else {
                foreach ( $value as $r => $row ) { if ( ! is_array( $row ) ) continue; foreach ( $subs as $sub ) { $name = $sub['name'] ?? ''; $key = array_key_exists( $name, $row ) ? $name : ( array_key_exists( $sub['key'] ?? '', $row ) ? $sub['key'] : null ); if ( null !== $key ) { $value[ $r ][ $key ] = $this->rewrite_media_ids( $sub, $row[ $key ], $map ); } } }
            }
        }
        if ( 'flexible_content' === $type ) {
            foreach ( $value as $r => $row ) {
                if ( ! is_array( $row ) ) continue; $layout_name = $row['acf_fc_layout'] ?? '';
                foreach ( (array) ( $field['layouts'] ?? array() ) as $layout ) {
                    if ( $layout_name !== ( $layout['name'] ?? '' ) ) continue;
                    foreach ( (array) ( $layout['sub_fields'] ?? array() ) as $sub ) { $name = $sub['name'] ?? ''; $key = array_key_exists( $name, $row ) ? $name : ( array_key_exists( $sub['key'] ?? '', $row ) ? $sub['key'] : null ); if ( null !== $key ) { $value[ $r ][ $key ] = $this->rewrite_media_ids( $sub, $row[ $key ], $map ); } }
                }
            }
        }
        return $value;
    }

    private function values_equal( $a, $b ) { return maybe_serialize( $a ) === maybe_serialize( $b ); }

    private function rollback_current_batch( $batch_start = 0 ) {
        $batch_start = max( 0, absint( $batch_start ) );
        $changes = array_slice( $this->history_changes, $batch_start );
        $report = array( 'changed' => 0, 'errors_count' => 0, 'messages' => array() );

        foreach ( array_reverse( $changes ) as $change ) {
            $type = $change['change_type'] ?? '';
            if ( 'acf' === $type ) {
                if ( ! function_exists( 'update_field' ) ) {
                    $report['errors_count']++;
                    $report['messages'][] = 'Rollback could not restore an ACF value because ACF is not active.';
                    continue;
                }
                $ok = update_field( $change['field_key'], $change['before'], $change['acf_id'] );
                $after = function_exists( 'get_field' ) ? get_field( $change['field_key'], $change['acf_id'], false ) : null;
                if ( false === $ok && ! $this->values_equal( $after, $change['before'] ) ) {
                    $report['errors_count']++;
                    $report['messages'][] = 'Rollback could not restore ACF field ' . sanitize_key( $change['field_key'] ) . '.';
                } else { $report['changed']++; }
                continue;
            }
            if ( 'meta' === $type ) {
                $ok = $this->write_meta_value( absint( $change['id'] ), (string) $change['meta_key'], (string) ( $change['storage'] ?? 'single' ), $change['before'] ?? '' );
                if ( ! $ok ) { $report['errors_count']++; $report['messages'][] = 'Rollback could not restore plugin meta ' . $this->friendly_meta_label( $change['meta_key'] ?? '' ) . '.'; }
                else { $report['changed']++; }
                continue;
            }
            if ( 'identity_term' === $type ) {
                $ok = wp_update_term( absint( $change['id'] ), sanitize_key( $change['taxonomy'] ), (array) $change['before'] );
                if ( is_wp_error( $ok ) ) { $report['errors_count']++; $report['messages'][] = 'Rollback could not restore taxonomy item #' . absint( $change['id'] ) . ': ' . $ok->get_error_message(); }
                else { $report['changed']++; }
                continue;
            }
            if ( 'terms' === $type ) {
                $ok = wp_set_object_terms( absint( $change['id'] ), array_map( 'intval', (array) $change['before'] ), sanitize_key( $change['taxonomy'] ), false );
                if ( is_wp_error( $ok ) ) { $report['errors_count']++; $report['messages'][] = 'Rollback could not restore taxonomy assignments on record #' . absint( $change['id'] ) . ': ' . $ok->get_error_message(); }
                else { $report['changed']++; }
                continue;
            }
            if ( 'created_term' === $type ) {
                $term = get_term( absint( $change['id'] ), sanitize_key( $change['taxonomy'] ) );
                if ( $term && ! is_wp_error( $term ) ) {
                    $ok = wp_delete_term( absint( $change['id'] ), sanitize_key( $change['taxonomy'] ) );
                    if ( is_wp_error( $ok ) || false === $ok ) { $report['errors_count']++; $report['messages'][] = 'Rollback could not remove newly created taxonomy item #' . absint( $change['id'] ) . '.'; }
                    else { $report['changed']++; }
                }
            }
        }

        $this->history_changes = array_slice( $this->history_changes, 0, $batch_start );
        return $report;
    }

    private function commit_history() {
        if ( ! $this->history_changes ) { return; }
        $history = self::get_history();
        array_unshift( $history, array( 'time' => current_time( 'mysql' ), 'user_id' => get_current_user_id(), 'label' => $this->history_label ?: 'import', 'change_count' => count( $this->history_changes ), 'changes' => array_slice( $this->history_changes, 0, 5000 ) ) );
        $history = array_slice( $history, 0, 5 );
        update_option( 'ninecode_acf_import_history', $history, false );
        $this->history_changes = array();
    }

    public function undo_latest() {
        $history = self::get_history();
        if ( ! $history || empty( $history[0]['changes'] ) ) { return new WP_Error( 'no_history', 'No import recovery snapshot is available.' ); }
        $entry = array_shift( $history );
        $report = array( 'changed' => 0, 'created' => 0, 'conflicts' => 0, 'skipped' => 0, 'errors_count' => 0, 'messages' => array(), 'change_details' => array(), 'change_details_total' => 0, 'affected_records' => array(), 'change_types' => array( 'acf' => 0, 'meta' => 0, 'taxonomy' => 0, 'term' => 0 ), 'change_fingerprint_parts' => array() );
        foreach ( array_reverse( $entry['changes'] ) as $change ) {
            switch ( $change['change_type'] ?? '' ) {
                case 'acf':
                    if ( function_exists( 'update_field' ) ) { update_field( $change['field_key'], $change['before'], $change['acf_id'] ); $report['changed']++; }
                    break;
                case 'meta':
                    if ( $this->write_meta_value( absint( $change['id'] ), (string) $change['meta_key'], (string) ( $change['storage'] ?? 'single' ), $change['before'] ?? '' ) ) { $report['changed']++; }
                    else { $report['errors_count']++; }
                    break;
                case 'identity_post':
                    // Legacy v0.5 history entry. v0.6+ enforces a strict data-only boundary.
                    $report['skipped']++;
                    $report['messages'][] = 'Skipped a legacy post identity rollback because title/slug/status/parent now belong exclusively to 9 Post Editor.';
                    break;
                case 'identity_term':
                    wp_update_term( absint( $change['id'] ), sanitize_key( $change['taxonomy'] ), (array) $change['before'] ); $report['changed']++; break;
                case 'terms':
                    wp_set_object_terms( absint( $change['id'] ), array_map( 'intval', (array) $change['before'] ), sanitize_key( $change['taxonomy'] ), false ); $report['changed']++; break;
                case 'created_term':
                    $deleted = wp_delete_term( absint( $change['id'] ), sanitize_key( $change['taxonomy'] ) );
                    if ( is_wp_error( $deleted ) || false === $deleted ) { $report['errors_count']++; }
                    else { $report['changed']++; }
                    break;
                default: $report['skipped']++;
            }
        }
        update_option( 'ninecode_acf_import_history', $history, false );
        $report['messages'][] = 'Restored the recorded values from import: ' . ( $entry['label'] ?? '' );
        return $report;
    }

    public function restore_backup_zip( $file, $options = array() ) {
        $check = $this->validate_upload( $file, array( 'zip' ) ); if ( is_wp_error( $check ) ) return $check;
        if ( ! class_exists( 'ZipArchive' ) ) return new WP_Error( 'zip_missing', 'PHP ZipArchive is required to restore ZIP data packs.' );
        $zip = new ZipArchive();
        if ( true !== $zip->open( $file['tmp_name'] ) ) return new WP_Error( 'zip_open', 'Could not open backup ZIP.' );
        $manifest = $this->zip_json( $zip, 'manifest.json' );
        if ( ! is_array( $manifest ) || 'ninecode-acf-data-backup' !== ( $manifest['format'] ?? '' ) ) { $zip->close(); return new WP_Error( 'bad_backup', 'This is not a recognized 9Code ACF Data Pack.' ); }
        $schema = $this->zip_json( $zip, 'schema/schema.json' );
        $posts = $this->zip_json( $zip, 'data/posts.json' );
        $terms = $this->zip_json( $zip, 'data/terms.json' );
        $media_index = $this->zip_json( $zip, 'media/index.json' );
        $options = wp_parse_args( $options, array( 'create_missing' => true, 'restore_registry' => true ) );
        $this->history_label = 'Backup restore ' . ( $manifest['created_at'] ?? '' );

        if ( ! empty( $options['restore_registry'] ) && is_array( $schema ) ) {
            if ( isset( $schema['managed_registry'] ) ) update_option( 'ninecode_acf_managed_registry', (array) $schema['managed_registry'], false );
            if ( isset( $schema['virtual_allocations'] ) ) update_option( 'ninecode_acf_allocations', (array) $schema['virtual_allocations'], false );
            NineCode_ACF_Data_Engine::instance()->register_managed_registry();
            $this->restore_acf_field_groups( $schema );
        }

        $media_map = is_array( $media_index ) ? $this->restore_media( $zip, $media_index ) : array();
        $zip->close();
        $combined_report = array( 'changed' => 0, 'created' => 0, 'conflicts' => 0, 'skipped' => 0, 'errors_count' => 0, 'messages' => array() );
        $import_opts = array( 'dry_run' => false, 'create_missing' => false, 'create_missing_records' => false, 'create_missing_terms' => ! empty( $options['create_missing'] ), 'import_identity' => false, 'import_term_identity' => true, 'import_terms' => true, 'capture_versions' => false );

        // Terms first so post taxonomy assignments can resolve against restored term slugs.
        foreach ( (array) $terms as $pack ) {
            if ( ! empty( $pack['records'] ) ) { if ( class_exists( 'NineCode_Data_Version_Manager' ) ) { NineCode_Data_Version_Manager::capture_records( $pack['records'], 'Before backup restore', 'restore' ); } $this->merge_report( $combined_report, $this->import_records( $pack['records'], $import_opts, $media_map ) ); }
        }
        foreach ( (array) $posts as $pack ) {
            if ( ! empty( $pack['records'] ) ) { if ( class_exists( 'NineCode_Data_Version_Manager' ) ) { NineCode_Data_Version_Manager::capture_records( $pack['records'], 'Before backup restore', 'restore' ); } $this->merge_report( $combined_report, $this->import_records( $pack['records'], $import_opts, $media_map ) ); }
        }
        if ( $this->history_changes ) $this->commit_history();
        $combined_report['messages'][] = 'Backup schema, allocations, ACF/taxonomy data and available referenced media processed. Post records and publishing identity were not created or changed.';
        return $combined_report;
    }

    private function zip_json( $zip, $name ) {
        $content = $zip->getFromName( $name ); if ( false === $content ) return null;
        $decoded = json_decode( $content, true ); return JSON_ERROR_NONE === json_last_error() ? $decoded : null;
    }

    private function restore_acf_field_groups( $schema ) {
        if ( empty( $schema['acf_import_field_groups'] ) || ! function_exists( 'acf_import_field_group' ) ) return;
        foreach ( (array) $schema['acf_import_field_groups'] as $group ) {
            if ( ! is_array( $group ) || empty( $group['key'] ) ) continue;
            // Do not try to overwrite PHP/local field groups; import database groups only when no local group masks the key.
            if ( function_exists( 'acf_is_local_field_group' ) && acf_is_local_field_group( $group['key'] ) ) continue;
            acf_import_field_group( $group );
        }
        if ( function_exists( 'acf_get_store' ) ) { acf_get_store( 'field-groups' )->reset(); acf_get_store( 'fields' )->reset(); }
    }

    private function restore_media( $zip, $index ) {
        $map = array();
        if ( ! function_exists( 'wp_upload_bits' ) ) require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        foreach ( $index as $item ) {
            $old_id = absint( $item['old_id'] ?? 0 ); $path = $item['path'] ?? '';
            if ( ! $old_id || ! $path || 0 !== strpos( $path, 'media/' ) || false !== strpos( $path, '../' ) ) continue;
            $existing = get_post( $old_id );
            if ( $existing && 'attachment' === $existing->post_type ) { $map[ $old_id ] = $old_id; continue; }
            $bytes = $zip->getFromName( $path ); if ( false === $bytes ) continue;
            $filename = sanitize_file_name( basename( $path ) );
            $uploaded = wp_upload_bits( $filename, null, $bytes );
            if ( ! empty( $uploaded['error'] ) ) continue;
            $attachment_id = wp_insert_attachment( array( 'post_mime_type' => sanitize_mime_type( $item['mime'] ?? '' ), 'post_title' => sanitize_text_field( $item['title'] ?? pathinfo( $filename, PATHINFO_FILENAME ) ), 'post_excerpt' => sanitize_textarea_field( $item['caption'] ?? '' ), 'post_content' => wp_kses_post( $item['description'] ?? '' ), 'post_status' => 'inherit' ), $uploaded['file'] );
            if ( ! is_wp_error( $attachment_id ) ) {
                $meta = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] ); if ( $meta ) wp_update_attachment_metadata( $attachment_id, $meta );
                if ( isset( $item['alt'] ) ) update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $item['alt'] ) );
                $map[ $old_id ] = (int) $attachment_id;
            }
        }
        return $map;
    }

    private function merge_report( &$into, $from ) {
        if ( ! is_array( $from ) ) return;
        foreach ( array( 'changed', 'created', 'conflicts', 'skipped', 'errors_count' ) as $key ) { $into[ $key ] += intval( $from[ $key ] ?? 0 ); }
        $into['messages'] = array_merge( $into['messages'], (array) ( $from['messages'] ?? array() ) );
    }
}
