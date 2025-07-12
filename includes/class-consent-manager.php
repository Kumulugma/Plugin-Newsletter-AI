<?php
/**
 * Klasa zarządzania zgodami Newsletter AI
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_AI_Consent_Manager {
    
    /**
     * Domyślne pole zgody
     */
    private $default_consent_field = 'newsletter_ai_consent';
    
    /**
     * Upewnij się że użytkownik ma pole zgody
     */
    public function ensure_user_has_consent_field($user_id) {
        $consent_field = get_option('nai_consent_field', $this->default_consent_field);
        
        // Sprawdź czy użytkownik już ma to pole
        $existing_value = get_user_meta($user_id, $consent_field, true);
        
        if (empty($existing_value)) {
            // Ustaw domyślną wartość (brak zgody)
            update_user_meta($user_id, $consent_field, 'no');
            
            $this->log("Utworzono pole zgody dla użytkownika ID: $user_id");
        }
    }
    
    /**
     * Pobierz listę użytkowników z informacją o zgodzie
     */
    public function get_users_with_consent_info($page = 1, $per_page = 20, $search = '') {
        global $wpdb;
        
        $consent_field = get_option('nai_consent_field', $this->default_consent_field);
        $consent_values = get_option('nai_consent_values', array('yes', '1', 'true', 'on'));
        
        $offset = ($page - 1) * $per_page;
        
        // Buduj zapytanie
        $where_conditions = array("1=1");
        $query_params = array();
        
        // Dodaj wyszukiwanie
        if (!empty($search)) {
            $where_conditions[] = "(u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $query_params[] = $search_term;
            $query_params[] = $search_term;
            $query_params[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Zapytanie o użytkowników
        $users_query = $wpdb->prepare("
            SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered,
                   um.meta_value as consent_value
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = %s
            WHERE {$where_clause}
            ORDER BY u.ID DESC
            LIMIT %d OFFSET %d
        ", array_merge(array($consent_field), $query_params, array($per_page, $offset)));
        
        $users = $wpdb->get_results($users_query);
        
        // Pobierz łączną liczbę użytkowników
        $count_query = $wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->users} u
            WHERE {$where_clause}
        ", $query_params);
        
        $total_users = $wpdb->get_var($count_query);
        
        // Przetwórz wyniki
        $processed_users = array();
        foreach ($users as $user) {
            $consent_value = strtolower(trim($user->consent_value));
            $has_consent = in_array($consent_value, array_map('strtolower', $consent_values));
            
            $processed_users[] = array(
                'ID' => $user->ID,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'display_name' => $user->display_name,
                'user_registered' => $user->user_registered,
                'consent_value' => $user->consent_value,
                'has_consent' => $has_consent,
                'consent_field_exists' => !empty($user->consent_value)
            );
        }
        
        return array(
            'users' => $processed_users,
            'total' => $total_users,
            'pages' => ceil($total_users / $per_page),
            'current_page' => $page
        );
    }
    
    /**
     * Utwórz pole zgody dla wszystkich użytkowników którzy go nie mają
     */
    public function bulk_create_consent_field() {
        global $wpdb;
        
        $consent_field = get_option('nai_consent_field', $this->default_consent_field);
        
        // Znajdź użytkowników bez pola zgody
        $users_without_field = $wpdb->get_results($wpdb->prepare("
            SELECT u.ID
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = %s
            WHERE um.meta_value IS NULL
        ", $consent_field));
        
        $created_count = 0;
        
        foreach ($users_without_field as $user) {
            update_user_meta($user->ID, $consent_field, 'no');
            $created_count++;
        }
        
        $this->log("Utworzono pole zgody dla $created_count użytkowników");
        
        return $created_count;
    }
    
    /**
     * Aktualizuj zgodę użytkownika
     */
    public function update_user_consent($user_id, $consent_value) {
        $consent_field = get_option('nai_consent_field', $this->default_consent_field);
        
        $result = update_user_meta($user_id, $consent_field, $consent_value);
        
        if ($result) {
            $this->log("Zaktualizowano zgodę użytkownika ID: $user_id na: $consent_value");
        }
        
        return $result;
    }
    
    /**
     * Pobierz statystyki zgód
     */
    public function get_consent_statistics() {
        global $wpdb;
        
        $consent_field = get_option('nai_consent_field', $this->default_consent_field);
        $consent_values = get_option('nai_consent_values', array('yes', '1', 'true', 'on'));
        
        // Łączna liczba użytkowników
        $total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        
        // Użytkownicy z polem zgody
        $users_with_field = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->usermeta}
            WHERE meta_key = %s
        ", $consent_field));
        
        // Użytkownicy z zgodą
        $consent_values_string = "'" . implode("', '", array_map('esc_sql', $consent_values)) . "'";
        $users_with_consent = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->usermeta}
            WHERE meta_key = %s AND LOWER(meta_value) IN ({$consent_values_string})
        ", $consent_field));
        
        return array(
            'total_users' => (int) $total_users,
            'users_with_field' => (int) $users_with_field,
            'users_without_field' => (int) $total_users - (int) $users_with_field,
            'users_with_consent' => (int) $users_with_consent,
            'users_without_consent' => (int) $users_with_field - (int) $users_with_consent,
            'consent_percentage' => $users_with_field > 0 ? round(($users_with_consent / $users_with_field) * 100, 2) : 0
        );
    }
    
    /**
     * Sprawdź jakie pola związane z zgodami istnieją w bazie
     */
    public function find_existing_consent_fields() {
        global $wpdb;
        
        $fields = $wpdb->get_results("
            SELECT DISTINCT meta_key, COUNT(*) as count
            FROM {$wpdb->usermeta}
            WHERE meta_key LIKE '%newsletter%'
               OR meta_key LIKE '%consent%'
               OR meta_key LIKE '%marketing%'
               OR meta_key LIKE '%subscription%'
               OR meta_key LIKE '%gdpr%'
            GROUP BY meta_key
            ORDER BY count DESC
        ");
        
        return $fields;
    }
    
    /**
     * AJAX handler dla tworzenia pól zgody zbiorczej
     */
    public function ajax_bulk_create_consent_field() {
        // Sprawdź nonce
        if (!wp_verify_nonce($_POST['nonce'], 'newsletter_ai_nonce')) {
            wp_send_json_error(__('Błąd bezpieczeństwa', 'newsletter-ai'));
        }
        
        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień', 'newsletter-ai'));
        }
        
        $created_count = $this->bulk_create_consent_field();
        
        wp_send_json_success(array(
            'message' => sprintf(
                __('Utworzono pole zgody dla %d użytkowników', 'newsletter-ai'),
                $created_count
            ),
            'created_count' => $created_count
        ));
    }
    
    /**
     * AJAX handler dla aktualizacji zgody użytkownika
     */
    public function ajax_update_user_consent() {
        // Sprawdź nonce
        if (!wp_verify_nonce($_POST['nonce'], 'newsletter_ai_nonce')) {
            wp_send_json_error(__('Błąd bezpieczeństwa', 'newsletter-ai'));
        }
        
        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień', 'newsletter-ai'));
        }
        
        $user_id = intval($_POST['user_id']);
        $consent_value = sanitize_text_field($_POST['consent_value']);
        
        if (!$user_id) {
            wp_send_json_error(__('Nieprawidłowy ID użytkownika', 'newsletter-ai'));
        }
        
        $result = $this->update_user_consent($user_id, $consent_value);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Zgodę zaktualizowano pomyślnie', 'newsletter-ai')
            ));
        } else {
            wp_send_json_error(__('Błąd podczas aktualizacji zgody', 'newsletter-ai'));
        }
    }
    
    /**
     * AJAX handler dla pobierania tabeli użytkowników
     */
    public function ajax_get_users_table() {
        // Sprawdź nonce
        if (!wp_verify_nonce($_POST['nonce'], 'newsletter_ai_nonce')) {
            wp_send_json_error(__('Błąd bezpieczeństwa', 'newsletter-ai'));
        }
        
        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień', 'newsletter-ai'));
        }
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        $data = $this->get_users_with_consent_info($page, 20, $search);
        
        wp_send_json_success($data);
    }
    
    /**
     * Pobierz przykładowe wartości zgody z bazy danych
     */
    public function get_sample_consent_values() {
        global $wpdb;
        
        $consent_field = get_option('nai_consent_field', $this->default_consent_field);
        
        $values = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT meta_value, COUNT(*) as count
            FROM {$wpdb->usermeta}
            WHERE meta_key = %s AND meta_value != ''
            GROUP BY meta_value
            ORDER BY count DESC
            LIMIT 10
        ", $consent_field));
        
        return $values;
    }
    
    /**
     * Eksportuj użytkowników z zgodami do CSV
     */
    public function export_users_to_csv() {
        $data = $this->get_users_with_consent_info(1, 999999); // Pobierz wszystkich
        
        $filename = 'newsletter_consents_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = WP_CONTENT_DIR . '/uploads/' . $filename;
        
        $file = fopen($filepath, 'w');
        
        // Nagłówki CSV
        fputcsv($file, array(
            'ID', 'Login', 'Email', 'Nazwa wyświetlana', 
            'Data rejestracji', 'Wartość zgody', 'Ma zgodę', 'Pole istnieje'
        ));
        
        // Dane użytkowników
        foreach ($data['users'] as $user) {
            fputcsv($file, array(
                $user['ID'],
                $user['user_login'],
                $user['user_email'],
                $user['display_name'],
                $user['user_registered'],
                $user['consent_value'],
                $user['has_consent'] ? 'Tak' : 'Nie',
                $user['consent_field_exists'] ? 'Tak' : 'Nie'
            ));
        }
        
        fclose($file);
        
        return array(
            'filename' => $filename,
            'url' => content_url('uploads/' . $filename),
            'path' => $filepath
        );
    }
    
    /**
     * Logowanie
     */
    private function log($message) {
        if (get_option('nai_debug_mode', false)) {
            $timestamp = current_time('Y-m-d H:i:s');
            error_log("[Newsletter AI Consent Manager {$timestamp}] {$message}");
        }
    }
}
?>