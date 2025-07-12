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
        // DEBUG: Logowanie początku funkcji
        error_log('Newsletter AI DEBUG: ajax_get_users_table STARTED');
        error_log('Newsletter AI DEBUG: POST data: ' . print_r($_POST, true));

        // Sprawdź nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'newsletter_ai_nonce')) {
            error_log('Newsletter AI DEBUG: Nonce verification failed');
            error_log('Newsletter AI DEBUG: Received nonce: ' . ($_POST['nonce'] ?? 'brak'));
            error_log('Newsletter AI DEBUG: Expected nonce for: newsletter_ai_nonce');
            wp_send_json_error(__('Błąd bezpieczeństwa', 'newsletter-ai'));
            return;
        }

        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            error_log('Newsletter AI DEBUG: Permission denied for user: ' . get_current_user_id());
            wp_send_json_error(__('Brak uprawnień', 'newsletter-ai'));
            return;
        }

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        error_log('Newsletter AI DEBUG: Parameters - page: ' . $page . ', search: ' . $search);

        try {
            $data = $this->get_users_with_consent_info($page, 20, $search);
            error_log('Newsletter AI DEBUG: Data retrieved successfully. Users count: ' . count($data['users']));
            wp_send_json_success($data);
        } catch (Exception $e) {
            error_log('Newsletter AI DEBUG: Exception in get_users_with_consent_info: ' . $e->getMessage());
            wp_send_json_error('Błąd podczas pobierania danych: ' . $e->getMessage());
        }
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
    /**
 * Eksportuj użytkowników z zgodami do CSV - poprawiona wersja
 */
public function export_users_to_csv() {
    error_log('Newsletter AI: export_users_to_csv() started');
    
    try {
        // Pobierz wszystkich użytkowników
        $data = $this->get_users_with_consent_info(1, 999999); // Pobierz wszystkich
        error_log('Newsletter AI: Pobrano ' . count($data['users']) . ' użytkowników do eksportu');
        
        // Utwórz katalog uploads jeśli nie istnieje
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['basedir'];
        
        if (!is_dir($upload_path)) {
            error_log('Newsletter AI: Katalog uploads nie istnieje: ' . $upload_path);
            return false;
        }
        
        if (!is_writable($upload_path)) {
            error_log('Newsletter AI: Katalog uploads nie jest zapisywalny: ' . $upload_path);
            return false;
        }
        
        $filename = 'newsletter_consents_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = $upload_path . '/' . $filename;
        
        error_log('Newsletter AI: Próba utworzenia pliku: ' . $filepath);
        
        $file = fopen($filepath, 'w');
        if (!$file) {
            error_log('Newsletter AI: Nie można utworzyć pliku CSV: ' . $filepath);
            return false;
        }
        
        // Dodaj BOM dla prawidłowego kodowania UTF-8 w Excel
        fwrite($file, "\xEF\xBB\xBF");
        
        // Nagłówki CSV
        $headers = array(
            'ID', 
            'Login', 
            'Email', 
            'Nazwa wyświetlana', 
            'Data rejestracji', 
            'Wartość zgody', 
            'Ma zgodę', 
            'Pole istnieje'
        );
        
        fputcsv($file, $headers, ';'); // Użyj średnika jako separatora
        
        // Dane użytkowników
        foreach ($data['users'] as $user) {
            $row = array(
                $user['ID'],
                $user['user_login'],
                $user['user_email'],
                $user['display_name'],
                $user['user_registered'],
                $user['consent_value'] ?: 'brak',
                $user['has_consent'] ? 'Tak' : 'Nie',
                $user['consent_field_exists'] ? 'Tak' : 'Nie'
            );
            
            fputcsv($file, $row, ';');
        }
        
        fclose($file);
        
        error_log('Newsletter AI: Plik CSV utworzony pomyślnie: ' . $filepath);
        
        $result = array(
            'filename' => $filename,
            'url' => $upload_dir['baseurl'] . '/' . $filename,
            'path' => $filepath,
            'size' => filesize($filepath)
        );
        
        error_log('Newsletter AI: Export result: ' . print_r($result, true));
        
        return $result;
        
    } catch (Exception $e) {
        error_log('Newsletter AI: Błąd w export_users_to_csv: ' . $e->getMessage());
        return false;
    }
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
    
    /**
 * AJAX handler dla eksportu CSV
 */
public function ajax_export_csv() {
    error_log('Newsletter AI: ajax_export_csv() started');
    
    // Sprawdź nonce
    if (!wp_verify_nonce($_POST['nonce'], 'newsletter_ai_nonce')) {
        error_log('Newsletter AI: Export AJAX - błąd nonce');
        wp_send_json_error(__('Błąd bezpieczeństwa', 'newsletter-ai'));
    }
    
    // Sprawdź uprawnienia
    if (!current_user_can('manage_options')) {
        error_log('Newsletter AI: Export AJAX - brak uprawnień');
        wp_send_json_error(__('Brak uprawnień', 'newsletter-ai'));
    }
    
    try {
        $export_data = $this->export_users_to_csv();
        
        if ($export_data) {
            error_log('Newsletter AI: Export AJAX - sukces');
            wp_send_json_success($export_data);
        } else {
            error_log('Newsletter AI: Export AJAX - błąd generowania');
            wp_send_json_error(__('Nie udało się wygenerować pliku CSV', 'newsletter-ai'));
        }
    } catch (Exception $e) {
        error_log('Newsletter AI: Export AJAX - exception: ' . $e->getMessage());
        wp_send_json_error('Błąd: ' . $e->getMessage());
    }
}

}

?>