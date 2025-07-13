<?php

/**
 * Newsletter AI Customer Validator
 * Waliduje dane klientów przed eksportem do Samba.AI
 */
if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_AI_Customer_Validator {

    /**
     * Reguły walidacji
     */
    private $validation_rules = array(
        'FIRST_NAME' => array(
            'fields' => array('billing_first_name', 'first_name'),
            'required' => true,
            'min_length' => 2,
            'message' => 'Brak imienia'
        ),
        'LAST_NAME' => array(
            'fields' => array('billing_last_name', 'last_name'),
            'required' => true,
            'min_length' => 2,
            'message' => 'Brak nazwiska'
        ),
        'PHONE' => array(
            'fields' => array('billing_phone'),
            'required' => false,
            'min_length' => 9,
            'pattern' => '/^[\+]?[0-9\s\-\(\)]{9,}$/',
            'message' => 'Brak numeru telefonu'
        ),
        'ZIP_CODE' => array(
            'fields' => array('billing_postcode'),
            'required' => false,
            'min_length' => 3,
            'pattern' => '/^[0-9A-Za-z\s\-]{3,}$/',
            'message' => 'Brak kodu pocztowego'
        ),
        'EMAIL' => array(
            'fields' => array('billing_email', 'user_email'),
            'required' => true,
            'validation' => 'email',
            'message' => 'Nieprawidłowy email'
        )
    );

    /**
     * Lista ignorowanych klientów
     */
    private $ignored_customers = array();

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->ignored_customers = get_option('nai_ignored_customers', array());

        // AJAX hooki
        add_action('wp_ajax_nai_validate_customers', array($this, 'ajax_validate_customers'));
        add_action('wp_ajax_nai_toggle_ignore_customer', array($this, 'ajax_toggle_ignore_customer'));
        add_action('wp_ajax_nai_fix_customer_data', array($this, 'ajax_fix_customer_data'));
        add_action('wp_ajax_nai_get_validation_details', array($this, 'ajax_get_validation_details'));
    }

    /**
     * Waliduj wszystkich klientów
     */
    public function validate_all_customers($page = 1, $per_page = 50, $filter = 'all') {
        global $wpdb;

        $consent_field = get_option('nai_consent_field', 'newsletter_ai_consent');
        $consent_values = get_option('nai_consent_values', array('yes', '1', 'true', 'on'));

        $offset = ($page - 1) * $per_page;

        // Buduj zapytanie w zależności od filtra
        $where_conditions = array("1=1");
        $query_params = array();

        // Pobierz użytkowników
        $users_query = $wpdb->prepare("
            SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered
            FROM {$wpdb->users} u
            WHERE 1=1
            ORDER BY u.ID DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset);

        $users = $wpdb->get_results($users_query);

        if (empty($users)) {
            return array(
                'customers' => array(),
                'total' => 0,
                'pages' => 0,
                'current_page' => $page,
                'summary' => array()
            );
        }

        // Pobierz IDs użytkowników
        $user_ids = array_column($users, 'ID');
        $users_placeholders = implode(',', array_fill(0, count($user_ids), '%d'));

        // Pobierz metadane wszystkich użytkowników naraz
        $meta_query = $wpdb->prepare("
            SELECT user_id, meta_key, meta_value 
            FROM {$wpdb->usermeta} 
            WHERE user_id IN ($users_placeholders)
            AND meta_key IN ('first_name', 'last_name', 'billing_first_name', 'billing_last_name', 
                            'billing_email', 'billing_phone', 'billing_postcode', %s)
        ", array_merge($user_ids, array($consent_field)));

        $user_meta = $wpdb->get_results($meta_query);

        // Grupuj metadane po user_id
        $meta_by_user = array();
        foreach ($user_meta as $meta) {
            $meta_by_user[$meta->user_id][$meta->meta_key] = $meta->meta_value;
        }

        // Waliduj każdego klienta
        $validated_customers = array();
        $summary = array(
            'total' => 0,
            'valid' => 0,
            'warnings' => 0,
            'errors' => 0,
            'ignored' => 0
        );

        foreach ($users as $user) {
            $user_meta_data = isset($meta_by_user[$user->ID]) ? $meta_by_user[$user->ID] : array();

            // Sprawdź zgodę na newsletter
            $consent_value = isset($user_meta_data[$consent_field]) ? $user_meta_data[$consent_field] : '';
            $has_consent = in_array(strtolower(trim($consent_value)), array_map('strtolower', $consent_values));

            // Waliduj dane klienta
            $validation_result = $this->validate_customer_data($user, $user_meta_data);

            $customer = array(
                'ID' => $user->ID,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'display_name' => $user->display_name,
                'user_registered' => $user->user_registered,
                'has_consent' => $has_consent,
                'consent_value' => $consent_value,
                'validation' => $validation_result,
                'is_ignored' => in_array($user->ID, $this->ignored_customers),
                'meta_data' => $user_meta_data
            );

            // Filtruj wyniki
            $include_customer = true;
            switch ($filter) {
                case 'errors':
                    $include_customer = !empty($validation_result['errors']);
                    break;
                case 'warnings':
                    $include_customer = !empty($validation_result['warnings']);
                    break;
                case 'valid':
                    $include_customer = empty($validation_result['errors']) && empty($validation_result['warnings']);
                    break;
                case 'ignored':
                    $include_customer = $customer['is_ignored'];
                    break;
            }

            if ($include_customer) {
                $validated_customers[] = $customer;
            }

            // Aktualizuj statystyki
            $summary['total']++;
            if ($customer['is_ignored']) {
                $summary['ignored']++;
            } elseif (!empty($validation_result['errors'])) {
                $summary['errors']++;
            } elseif (!empty($validation_result['warnings'])) {
                $summary['warnings']++;
            } else {
                $summary['valid']++;
            }
        }

        // Policz łączną liczbę użytkowników dla paginacji
        $total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");

        return array(
            'customers' => $validated_customers,
            'total' => $total_users,
            'pages' => ceil($total_users / $per_page),
            'current_page' => $page,
            'summary' => $summary
        );
    }

    /**
     * Waliduj dane pojedynczego klienta
     */
    public function validate_customer_data($user, $user_meta) {
        $errors = array();
        $warnings = array();
        $valid_fields = array();

        foreach ($this->validation_rules as $field_name => $rule) {
            $field_value = $this->get_field_value($user, $user_meta, $rule['fields']);

            $validation_result = $this->validate_field($field_name, $field_value, $rule);

            if ($validation_result['status'] === 'error') {
                $errors[] = $validation_result;
            } elseif ($validation_result['status'] === 'warning') {
                $warnings[] = $validation_result;
            } else {
                $valid_fields[] = $validation_result;
            }
        }

        return array(
            'errors' => $errors,
            'warnings' => $warnings,
            'valid' => $valid_fields,
            'score' => $this->calculate_validation_score($errors, $warnings, $valid_fields)
        );
    }

    /**
     * Waliduj pojedyncze pole
     */
    private function validate_field($field_name, $value, $rule) {
        $result = array(
            'field' => $field_name,
            'value' => $value,
            'message' => '',
            'status' => 'valid'
        );

        // Sprawdź czy pole jest wymagane
        if ($rule['required'] && empty($value)) {
            $result['status'] = 'error';
            $result['message'] = $rule['message'];
            return $result;
        }

        // Jeśli pole nie jest wymagane i jest puste, to ostrzeżenie
        if (!$rule['required'] && empty($value)) {
            $result['status'] = 'warning';
            $result['message'] = $rule['message'];
            return $result;
        }

        // Sprawdź minimalną długość
        if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
            $result['status'] = 'warning';
            $result['message'] = $rule['message'] . ' (za krótkie)';
            return $result;
        }

        // Sprawdź wzorzec
        if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
            $result['status'] = 'warning';
            $result['message'] = $rule['message'] . ' (nieprawidłowy format)';
            return $result;
        }

        // Sprawdź email
        if (isset($rule['validation']) && $rule['validation'] === 'email' && !is_email($value)) {
            $result['status'] = 'error';
            $result['message'] = $rule['message'];
            return $result;
        }

        return $result;
    }

    /**
     * Pobierz wartość pola z dostępnych źródeł
     */
    private function get_field_value($user, $user_meta, $possible_fields) {
        foreach ($possible_fields as $field) {
            if ($field === 'user_email') {
                return $user->user_email;
            }

            if (isset($user_meta[$field]) && !empty($user_meta[$field])) {
                return $user_meta[$field];
            }
        }

        return '';
    }

    /**
     * Oblicz wynik walidacji (0-100)
     */
    private function calculate_validation_score($errors, $warnings, $valid_fields) {
        $total_fields = count($this->validation_rules);
        $error_count = count($errors);
        $warning_count = count($warnings);
        $valid_count = count($valid_fields);

        // Błędy = -20 punktów, ostrzeżenia = -10 punktów
        $score = 100 - ($error_count * 20) - ($warning_count * 10);

        return max(0, min(100, $score));
    }

    /**
     * Przełącz status ignorowania klienta
     */
    public function toggle_ignore_customer($customer_id) {
        $key = array_search($customer_id, $this->ignored_customers);

        if ($key !== false) {
            // Usuń z listy ignorowanych
            unset($this->ignored_customers[$key]);
            $this->ignored_customers = array_values($this->ignored_customers);
            $action = 'unignored';
        } else {
            // Dodaj do listy ignorowanych
            $this->ignored_customers[] = $customer_id;
            $action = 'ignored';
        }

        update_option('nai_ignored_customers', $this->ignored_customers);

        return $action;
    }

    /**
     * Napraw dane klienta
     */
    public function fix_customer_data($customer_id, $field_data) {
        foreach ($field_data as $field => $value) {
            $value = sanitize_text_field($value);
            update_user_meta($customer_id, $field, $value);
        }

        return true;
    }

    /**
     * AJAX - waliduj klientów
     */
    public function ajax_validate_customers() {
        if (!wp_verify_nonce($_POST['nonce'], 'newsletter_ai_nonce')) {
            wp_send_json_error(__('Błąd bezpieczeństwa', 'newsletter-ai'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień', 'newsletter-ai'));
        }

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';

        try {
            $data = $this->validate_all_customers($page, 50, $filter);
            wp_send_json_success($data);
        } catch (Exception $e) {
            wp_send_json_error('Błąd walidacji: ' . $e->getMessage());
        }
    }

    /**
     * AJAX - przełącz ignorowanie klienta
     */
    public function ajax_toggle_ignore_customer() {
        if (!wp_verify_nonce($_POST['nonce'], 'newsletter_ai_nonce')) {
            wp_send_json_error(__('Błąd bezpieczeństwa', 'newsletter-ai'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień', 'newsletter-ai'));
        }

        $customer_id = intval($_POST['customer_id']);

        try {
            $action = $this->toggle_ignore_customer($customer_id);
            wp_send_json_success(array(
                'action' => $action,
                'message' => $action === 'ignored' ? 'Klient dodany do ignorowanych' : 'Klient usunięty z ignorowanych'
            ));
        } catch (Exception $e) {
            wp_send_json_error('Błąd: ' . $e->getMessage());
        }
    }

    /**
     * AJAX - napraw dane klienta
     */
    public function ajax_fix_customer_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'newsletter_ai_nonce')) {
            wp_send_json_error(__('Błąd bezpieczeństwa', 'newsletter-ai'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień', 'newsletter-ai'));
        }

        $customer_id = intval($_POST['customer_id']);
        $field_data = $_POST['field_data'];

        try {
            $this->fix_customer_data($customer_id, $field_data);
            wp_send_json_success(array(
                'message' => 'Dane klienta zostały zaktualizowane'
            ));
        } catch (Exception $e) {
            wp_send_json_error('Błąd aktualizacji: ' . $e->getMessage());
        }
    }

    /**
     * AJAX - pobierz szczegóły walidacji
     */
    public function ajax_get_validation_details() {
        if (!wp_verify_nonce($_POST['nonce'], 'newsletter_ai_nonce')) {
            wp_send_json_error(__('Błąd bezpieczeństwa', 'newsletter-ai'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień', 'newsletter-ai'));
        }

        $customer_id = intval($_POST['customer_id']);

        try {
            $user = get_userdata($customer_id);
            if (!$user) {
                wp_send_json_error('Użytkownik nie istnieje');
            }

            // Pobierz metadane użytkownika
            $user_meta = get_user_meta($customer_id);
            $meta_flattened = array();
            foreach ($user_meta as $key => $value) {
                $meta_flattened[$key] = is_array($value) ? $value[0] : $value;
            }

            $validation = $this->validate_customer_data($user, $meta_flattened);

            wp_send_json_success(array(
                'user' => array(
                    'ID' => $user->ID,
                    'user_login' => $user->user_login,
                    'user_email' => $user->user_email,
                    'display_name' => $user->display_name
                ),
                'meta' => $meta_flattened,
                'validation' => $validation
            ));
        } catch (Exception $e) {
            wp_send_json_error('Błąd: ' . $e->getMessage());
        }
    }

    /**
     * Pobierz statystyki walidacji
     */
    public function get_validation_summary() {
        // Szybkie sprawdzenie wszystkich użytkowników
        $result = $this->validate_all_customers(1, 999999);
        return $result['summary'];
    }

    /**
     * Eksportuj problemy do CSV
     */
    public function export_problems_to_csv() {
        $data = $this->validate_all_customers(1, 999999, 'all');

        $upload_dir = wp_upload_dir();
        $filename = 'newsletter_problems_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = $upload_dir['basedir'] . '/' . $filename;

        $file = fopen($filepath, 'w');
        fwrite($file, "\xEF\xBB\xBF"); // BOM dla UTF-8
        // Nagłówki
        fputcsv($file, array(
            'ID użytkownika',
            'Login',
            'Email',
            'Wynik walidacji',
            'Błędy',
            'Ostrzeżenia',
            'Status ignorowania'
                ), ';');

        foreach ($data['customers'] as $customer) {
            $errors = array();
            foreach ($customer['validation']['errors'] as $error) {
                $errors[] = $error['message'];
            }

            $warnings = array();
            foreach ($customer['validation']['warnings'] as $warning) {
                $warnings[] = $warning['message'];
            }

            fputcsv($file, array(
                $customer['ID'],
                $customer['user_login'],
                $customer['user_email'],
                $customer['validation']['score'] . '%',
                implode(', ', $errors),
                implode(', ', $warnings),
                $customer['is_ignored'] ? 'Ignorowany' : 'Aktywny'
                    ), ';');
        }

        fclose($file);

        return array(
            'filename' => $filename,
            'url' => $upload_dir['baseurl'] . '/' . $filename,
            'path' => $filepath
        );
    }

}

?>