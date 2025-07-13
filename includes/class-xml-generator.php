<?php
/**
 * Klasa generatora XML dla Newsletter AI
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_AI_XML_Generator {
    
    /**
     * Częstotliwości newslettera
     */
    private $newsletter_frequencies = array(
        'everyDay' => 'every day',
        'special' => 'special occasions',
        'never' => 'never'
    );
    
    /**
     * Wygeneruj plik XML
     */
    public function generate_xml_file($ajax_mode = true) {
        if (!is_plugin_active('woocommerce/woocommerce.php')) {
            if ($ajax_mode) {
                wp_die(__('WooCommerce nie jest aktywne', 'newsletter-ai'));
            }
            return false;
        }
        
        global $wpdb;
        
        $consent_field = get_option('nai_consent_field', 'newsletter_ai_consent');
        $consent_values = get_option('nai_consent_values', array('yes', '1', 'true', 'on'));
        $debug_mode = get_option('nai_debug_mode', false);
        
        if ($debug_mode) {
            $this->log('Rozpoczynanie generowania XML z sprawdzaniem zgód');
        }
        
        $user_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->users} WHERE 1=%d", 1));
        if ($user_count <= 0) {
            if ($ajax_mode) {
                wp_die(__('Błąd: nie znaleziono użytkowników', 'newsletter-ai'));
            }
            return false;
        }
        
        $temp_user_list = array();
        $last_users_page = 0;
        $total_users_checked = 0;
        $users_with_consent = 0;
        $users_without_consent = 0;
        
        $default_meta_data = array(
            'meta' => array(
                'first_name' => '',
                'last_name' => '',
                'billing_first_name' => '',
                'billing_last_name' => '',
                'billing_email' => '',
                'billing_phone' => '',
                'billing_country' => '',
                'billing_postcode' => '',
                $consent_field => ''
            )
        );
        
        // Sprawdź tryb trial
        $trial_mode = 'off';
        if (function_exists('sambaaiprefix_getTrialMode')) {
            $trial_mode = sambaaiprefix_getTrialMode(false);
        }
        
        if ($trial_mode == 'checked') {
            $user_count = 200;
        }
        
        // Przetwarzaj użytkowników w blokach po 200
        while ($last_users_page * 200 < $user_count) {
            $offset = $last_users_page * 200;
            
            $wordpress_users = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, user_nicename, user_email, user_registered, display_name 
                FROM {$wpdb->users} 
                WHERE 1=%d 
                LIMIT %d OFFSET %d",
                1, 200, $offset
            ), ARRAY_A);
            
            if (count($wordpress_users) <= 0) {
                break;
            }
            
            $wp_users_ids = array();
            foreach ($wordpress_users as $wp_user) {
                $wp_users_ids[] = $wp_user['ID'];
                $temp_user_list[$wp_user['ID']] = array_merge($wp_user, $default_meta_data);
            }
            
            // Pobierz metadane użytkowników
            $meta_keys = array(
                'first_name', 'last_name', 'billing_first_name',
                'billing_last_name', 'billing_email', 'billing_phone',
                'billing_country', 'billing_postcode', 'hell-salesmanago-is-employee',
                $consent_field
            );
            
            $meta_keys_placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
            $users_placeholders = implode(',', array_fill(0, count($wp_users_ids), '%d'));
            
            $query = $wpdb->prepare(
                "SELECT user_id, meta_key, meta_value 
                FROM {$wpdb->usermeta} 
                WHERE meta_key IN ($meta_keys_placeholders) 
                AND user_id IN ($users_placeholders)",
                array_merge($meta_keys, $wp_users_ids)
            );
            
            $wordpress_users_meta = $wpdb->get_results($query, ARRAY_A);
            
            foreach ($wordpress_users_meta as $wp_user_meta) {
                $temp_user_list[$wp_user_meta['user_id']]['meta'][$wp_user_meta['meta_key']] = $wp_user_meta['meta_value'];
            }
            
            $total_users_checked += count($wordpress_users);
            $last_users_page += 1;
        }
        
        // Wygeneruj XML
        $xml_content = $this->build_xml_content($temp_user_list, $consent_field, $consent_values, $users_with_consent, $users_without_consent);
        
        // Zapisz plik
        $file_saved = $this->save_xml_file($xml_content);
        
        if (!$file_saved) {
            if ($ajax_mode) {
                wp_die(__('Błąd podczas zapisywania pliku XML', 'newsletter-ai'));
            }
            return false;
        }
        
        // Nadpisz oryginalny plik jeśli podano URL
        $this->override_original_file($xml_content);
        
        // Zapisz statystyki
        $this->save_generation_stats($total_users_checked, $users_with_consent, $users_without_consent, $consent_field);
        
        if ($debug_mode) {
            $this->log("XML wygenerowany. Łącznie: $total_users_checked, Z zgodą: $users_with_consent, Bez zgody: $users_without_consent");
        }
        
        if ($ajax_mode) {
            wp_send_json_success(array(
                'message' => sprintf(
                    __('XML wygenerowany pomyślnie! Użytkownicy z zgodą: %d, bez zgody: %d', 'newsletter-ai'),
                    $users_with_consent,
                    $users_without_consent
                ),
                'stats' => array(
                    'total' => $total_users_checked,
                    'with_consent' => $users_with_consent,
                    'without_consent' => $users_without_consent
                )
            ));
        }
        
        return true;
    }
    
    /**
     * Zbuduj zawartość XML
     */
    private function build_xml_content($user_list, $consent_field, $consent_values, &$users_with_consent, &$users_without_consent) {
        $xml_content = '<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL;
        $xml_content .= '<CUSTOMERS>' . PHP_EOL;
        
        $users_with_consent = 0;
        $users_without_consent = 0;
        
        foreach ($user_list as $temp_user) {
            // Sprawdź czy użytkownik ma wymagane dane
            if ((!isset($temp_user['ID']) || $temp_user['ID'] == '') && 
                (!isset($temp_user['meta']['billing_email']) || $temp_user['meta']['billing_email'] == '') && 
                (!isset($temp_user['user_email']) || $temp_user['user_email'] == '')) {
                continue;
            }
            
            // Przygotuj dane użytkownika
            $customer_data = $this->prepare_customer_data($temp_user);
            
            // Pomiń jeśli email jest nieprawidłowy
            if (!is_email($customer_data['email'])) {
                continue;
            }
            
            // Sprawdź zgodę na newsletter
            $newsletter_consent = $this->check_newsletter_consent($temp_user, $consent_field, $consent_values);
            
            if ($newsletter_consent) {
                $users_with_consent++;
                $newsletter_frequency = $this->newsletter_frequencies['everyDay'];
                $sms_frequency = 'every day';
            } else {
                $users_without_consent++;
                $newsletter_frequency = $this->newsletter_frequencies['never'];
                $sms_frequency = 'never';
            }
            
            // Dodaj do XML
            $xml_content .= $this->build_customer_xml($customer_data, $newsletter_frequency, $sms_frequency);
        }
        
        $xml_content .= '</CUSTOMERS>';
        
        return $xml_content;
    }
    
    /**
     * Przygotuj dane klienta
     */
    private function prepare_customer_data($temp_user) {
    $data = array();
    
    if (isset($temp_user['ID']) && $temp_user['ID'] != '') {
        // Zarejestrowany użytkownik
        $data['id'] = $temp_user['ID'];
        $data['email'] = $temp_user['meta']['billing_email'] !== '' ? $temp_user['meta']['billing_email'] : $temp_user['user_email'];
        $data['registration_date'] = date(DATE_RFC3339_EXTENDED, strtotime($temp_user['user_registered']));
        $data['first_name'] = isset($temp_user['meta']['billing_first_name']) && $temp_user['meta']['billing_first_name'] !== '' 
                            ? $temp_user['meta']['billing_first_name'] 
                            : (isset($temp_user['meta']['first_name']) ? $temp_user['meta']['first_name'] : '');
        $data['last_name'] = isset($temp_user['meta']['billing_last_name']) && $temp_user['meta']['billing_last_name'] !== '' 
                           ? $temp_user['meta']['billing_last_name'] 
                           : (isset($temp_user['meta']['last_name']) ? $temp_user['meta']['last_name'] : '');
        $data['phone'] = isset($temp_user['meta']['billing_phone']) ? $temp_user['meta']['billing_phone'] : '';
        // DODAJ TO:
        $data['zip_code'] = isset($temp_user['meta']['billing_postcode']) ? $temp_user['meta']['billing_postcode'] : '';
    } else {
        // Niezarejestrowany użytkownik
        $data['id'] = '';
        $data['email'] = $temp_user['meta']['billing_email'];
        $data['registration_date'] = '';
        $data['first_name'] = isset($temp_user['meta']['billing_first_name']) ? $temp_user['meta']['billing_first_name'] : '';
        $data['last_name'] = isset($temp_user['meta']['billing_last_name']) ? $temp_user['meta']['billing_last_name'] : '';
        $data['phone'] = isset($temp_user['meta']['billing_phone']) ? $temp_user['meta']['billing_phone'] : '';
        // DODAJ TO:
        $data['zip_code'] = isset($temp_user['meta']['billing_postcode']) ? $temp_user['meta']['billing_postcode'] : '';
    }
    
    return $data;
}
    
    /**
     * Zbuduj XML dla pojedynczego klienta
     */
    private function build_customer_xml($customer_data, $newsletter_frequency, $sms_frequency) {
    $xml = '<CUSTOMER>';
    
    if ($customer_data['id'] != '') {
        $xml .= '<CUSTOMER_ID>' . esc_xml($customer_data['id']) . '</CUSTOMER_ID>';
    }
    
    $xml .= '<EMAIL>' . esc_xml($customer_data['email']) . '</EMAIL>';
    
    if ($customer_data['registration_date'] != '') {
        $xml .= '<REGISTRATION>' . esc_xml($customer_data['registration_date']) . '</REGISTRATION>';
    }
    
    $xml .= '<NEWSLETTER_FREQUENCY>' . esc_xml($newsletter_frequency) . '</NEWSLETTER_FREQUENCY>';
    
    if ($customer_data['first_name'] != '') {
        $xml .= '<FIRST_NAME>' . esc_xml($customer_data['first_name']) . '</FIRST_NAME>';
    }
    
    if ($customer_data['last_name'] != '') {
        $xml .= '<LAST_NAME>' . esc_xml($customer_data['last_name']) . '</LAST_NAME>';
    }
    
    if ($customer_data['phone'] != '') {
        $xml .= '<PHONE>' . esc_xml($customer_data['phone']) . '</PHONE>';
    }
    
    if ($customer_data['zip_code'] != '') {
        $xml .= '<ZIP_CODE>' . esc_xml($customer_data['zip_code']) . '</ZIP_CODE>';
    }
    
    $xml .= '<SMS_FREQUENCY>' . esc_xml($sms_frequency) . '</SMS_FREQUENCY>';
    $xml .= '</CUSTOMER>' . PHP_EOL;
    
    return $xml;
}
    
    /**
     * Sprawdź zgodę użytkownika na newsletter
     */
    private function check_newsletter_consent($user, $consent_field, $consent_values) {
        if (isset($user['meta'][$consent_field]) && !empty($user['meta'][$consent_field])) {
            $consent_value = strtolower(trim($user['meta'][$consent_field]));
            return in_array($consent_value, array_map('strtolower', $consent_values));
        }
        
        return false;
    }
    
    /**
     * Zapisz plik XML
     */
    private function save_xml_file($xml_content) {
        $export_dir = WP_CONTENT_DIR . '/sambaAiExport';
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }
        
        $file_path = $export_dir . '/sambaAiCustomers.xml';
        $result = file_put_contents($file_path, $xml_content);
        
        return $result !== false;
    }
    
    /**
     * Nadpisz oryginalny plik
     */
    private function override_original_file($xml_content) {
        $original_url = get_option('nai_original_xml_url', '');
        if (empty($original_url)) {
            return;
        }
        
        // Tutaj można dodać logikę nadpisywania oryginalnego pliku
        // Na przykład przez FTP, SFTP lub API
        
        $this->log('Próba nadpisania oryginalnego pliku: ' . $original_url);
        
        // Przykład: zapis do lokalnego pliku jeśli URL wskazuje na lokalny plik
        if (strpos($original_url, site_url()) === 0) {
            $local_path = str_replace(site_url(), ABSPATH, $original_url);
            if (is_writable(dirname($local_path))) {
                file_put_contents($local_path, $xml_content);
                $this->log('Oryginalny plik nadpisany: ' . $local_path);
            }
        }
    }
    
    /**
     * Zapisz statystyki generowania
     */
    private function save_generation_stats($total_users, $users_with_consent, $users_without_consent, $consent_field) {
        $stats = array(
            'total_users' => $total_users,
            'users_with_consent' => $users_with_consent,
            'users_without_consent' => $users_without_consent,
            'timestamp' => current_time('mysql'),
            'consent_field_used' => $consent_field
        );
        
        update_option('nai_last_generation_stats', $stats);
    }
    
    /**
     * AJAX handler dla generowania XML
     */
    public function ajax_generate_xml() {
        // Sprawdź nonce
        if (!wp_verify_nonce($_POST['nonce'], 'newsletter_ai_nonce')) {
            wp_die(__('Błąd bezpieczeństwa', 'newsletter-ai'));
        }
        
        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_die(__('Brak uprawnień', 'newsletter-ai'));
        }
        
        $this->generate_xml_file(true);
    }
    
    /**
     * Pobierz statystyki ostatniego generowania
     */
    public function get_last_generation_stats() {
        return get_option('nai_last_generation_stats', array());
    }
    
    /**
     * Sprawdź czy plik XML istnieje
     */
    public function xml_file_exists() {
        $file_path = WP_CONTENT_DIR . '/sambaAiExport/sambaAiCustomers.xml';
        return file_exists($file_path);
    }
    
    /**
     * Pobierz informacje o pliku XML
     */
    public function get_xml_file_info() {
        $file_path = WP_CONTENT_DIR . '/sambaAiExport/sambaAiCustomers.xml';
        if (!file_exists($file_path)) {
            return false;
        }
        
        return array(
            'size' => filesize($file_path),
            'modified' => filemtime($file_path),
            'url' => content_url('sambaAiExport/sambaAiCustomers.xml')
        );
    }
    
    /**
     * Logowanie
     */
    private function log($message) {
        if (get_option('nai_debug_mode', false)) {
            $timestamp = current_time('Y-m-d H:i:s');
            error_log("[Newsletter AI XML Generator {$timestamp}] {$message}");
        }
    }
}
?>