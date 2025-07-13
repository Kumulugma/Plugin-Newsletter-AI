<?php
/**
 * Newsletter AI Email Export Manager
 * Eksportuje adresy email z zamówień w różnych kategoriach
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_AI_Email_Export {
    
    /**
     * Konstruktor
     */
    public function __construct() {
        // AJAX hooki
        add_action('wp_ajax_nai_export_emails_csv', array($this, 'ajax_export_emails_csv'));
        add_action('wp_ajax_nai_get_emails_text', array($this, 'ajax_get_emails_text'));
    }
    
    /**
     * Pobierz wszystkie adresy email z zamówień
     */
    public function get_all_order_emails() {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT DISTINCT pm_email.meta_value as email,
                   p.post_date as order_date,
                   p.ID as order_id
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending')
            AND pm_email.meta_value IS NOT NULL
            AND pm_email.meta_value != ''
            AND pm_email.meta_value LIKE '%@%.%'
            ORDER BY p.post_date DESC
        ");
    }
    
    /**
     * Pobierz adresy email z zgodą na newsletter
     */
    public function get_emails_with_consent() {
        global $wpdb;
        
        $consent_field = get_option('nai_consent_field', 'newsletter_ai_consent');
        $consent_values = get_option('nai_consent_values', array('yes', '1', 'true', 'on'));
        
        // Email z zarejestrowanych użytkowników z zgodą
        $registered_emails = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT pm_email.meta_value as email,
                   'registered' as source,
                   p.post_date as order_date,
                   p.ID as order_id,
                   u.user_email as user_email
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
            LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
            LEFT JOIN {$wpdb->users} u ON pm_customer.meta_value = u.ID
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = %s
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending')
            AND pm_customer.meta_value > 0
            AND pm_email.meta_value IS NOT NULL
            AND pm_email.meta_value != ''
            AND pm_email.meta_value LIKE '%@%.%'
            AND um.meta_value IN (" . implode(',', array_fill(0, count($consent_values), '%s')) . ")
            ORDER BY p.post_date DESC
        ", array_merge(array($consent_field), $consent_values)));
        
        // Email z gości z zgodą
        $guest_emails = $wpdb->get_results("
            SELECT DISTINCT pm_email.meta_value as email,
                   'guest' as source,
                   p.post_date as order_date,
                   p.ID as order_id,
                   pm_consent_time.meta_value as consent_timestamp
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
            LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
            LEFT JOIN {$wpdb->postmeta} pm_consent ON p.ID = pm_consent.post_id AND pm_consent.meta_key = '_newsletter_consent'
            LEFT JOIN {$wpdb->postmeta} pm_consent_time ON p.ID = pm_consent_time.post_id AND pm_consent_time.meta_key = '_newsletter_consent_timestamp'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending')
            AND (pm_customer.meta_value = '0' OR pm_customer.meta_value IS NULL)
            AND pm_consent.meta_value = 'yes'
            AND pm_email.meta_value IS NOT NULL
            AND pm_email.meta_value != ''
            AND pm_email.meta_value LIKE '%@%.%'
            ORDER BY p.post_date DESC
        ");
        
        return array_merge($registered_emails, $guest_emails);
    }
    
    /**
     * Pobierz adresy email sprzed wdrożenia zgód
     */
    public function get_emails_before_consent_implementation() {
        global $wpdb;
        
        // Data wdrożenia systemu zgód (można to skonfigurować)
        $implementation_date = get_option('nai_consent_implementation_date', '2024-01-01 00:00:00');
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT pm_email.meta_value as email,
                   p.post_date as order_date,
                   p.ID as order_id,
                   'legacy' as source
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
            LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending')
            AND p.post_date < %s
            AND pm_email.meta_value IS NOT NULL
            AND pm_email.meta_value != ''
            AND pm_email.meta_value LIKE '%@%.%'
            AND (pm_customer.meta_value = '0' OR pm_customer.meta_value IS NULL)
            ORDER BY p.post_date DESC
        ", $implementation_date));
    }
    
    /**
     * Eksportuj emaile do CSV
     */
    public function export_emails_to_csv($type) {
        switch ($type) {
            case 'all':
                $emails = $this->get_all_order_emails();
                $filename = 'wszystkie_emaile_zamowienia';
                break;
            case 'consent':
                $emails = $this->get_emails_with_consent();
                $filename = 'emaile_z_zgoda';
                break;
            case 'legacy':
                $emails = $this->get_emails_before_consent_implementation();
                $filename = 'emaile_sprzed_wdrozenia';
                break;
            default:
                return false;
        }
        
        if (empty($emails)) {
            return false;
        }
        
        // Utwórz plik CSV
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['basedir'];
        
        if (!is_dir($upload_path) || !is_writable($upload_path)) {
            return false;
        }
        
        $filename = $filename . '_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = $upload_path . '/' . $filename;
        
        $file = fopen($filepath, 'w');
        if (!$file) {
            return false;
        }
        
        // Dodaj BOM dla UTF-8
        fwrite($file, "\xEF\xBB\xBF");
        
        // Zapisz tylko emaile jako pojedyncze wiersze
        $unique_emails = array();
        foreach ($emails as $email_data) {
            $email = trim(strtolower($email_data->email));
            if (!in_array($email, $unique_emails) && is_email($email)) {
                $unique_emails[] = $email;
                fwrite($file, $email . "\n");
            }
        }
        
        fclose($file);
        
        return array(
            'filename' => $filename,
            'url' => $upload_dir['baseurl'] . '/' . $filename,
            'path' => $filepath,
            'size' => filesize($filepath),
            'count' => count($unique_emails)
        );
    }
    
    /**
     * Pobierz emaile jako tekst
     */
    public function get_emails_as_text($type) {
        switch ($type) {
            case 'all':
                $emails = $this->get_all_order_emails();
                break;
            case 'consent':
                $emails = $this->get_emails_with_consent();
                break;
            case 'legacy':
                $emails = $this->get_emails_before_consent_implementation();
                break;
            default:
                return array('emails' => array(), 'count' => 0);
        }
        
        // Usuń duplikaty i waliduj
        $unique_emails = array();
        foreach ($emails as $email_data) {
            $email = trim(strtolower($email_data->email));
            if (!in_array($email, $unique_emails) && is_email($email)) {
                $unique_emails[] = $email;
            }
        }
        
        return array(
            'emails' => $unique_emails,
            'count' => count($unique_emails),
            'text' => implode("\n", $unique_emails)
        );
    }
    
    /**
     * Pobierz statystyki emaili
     */
    public function get_email_statistics() {
        $all_emails = $this->get_all_order_emails();
        $consent_emails = $this->get_emails_with_consent();
        $legacy_emails = $this->get_emails_before_consent_implementation();
        
        // Usuń duplikaty dla każdej kategorii
        $unique_all = array();
        $unique_consent = array();
        $unique_legacy = array();
        
        foreach ($all_emails as $email_data) {
            $email = trim(strtolower($email_data->email));
            if (!in_array($email, $unique_all) && is_email($email)) {
                $unique_all[] = $email;
            }
        }
        
        foreach ($consent_emails as $email_data) {
            $email = trim(strtolower($email_data->email));
            if (!in_array($email, $unique_consent) && is_email($email)) {
                $unique_consent[] = $email;
            }
        }
        
        foreach ($legacy_emails as $email_data) {
            $email = trim(strtolower($email_data->email));
            if (!in_array($email, $unique_legacy) && is_email($email)) {
                $unique_legacy[] = $email;
            }
        }
        
        return array(
            'all_emails' => count($unique_all),
            'consent_emails' => count($unique_consent),
            'legacy_emails' => count($unique_legacy),
            'without_consent' => count($unique_all) - count($unique_consent) - count($unique_legacy)
        );
    }
    
    /**
     * AJAX handler dla eksportu CSV
     */
    public function ajax_export_emails_csv() {
        // Sprawdź nonce
        if (!wp_verify_nonce($_POST['nonce'], 'newsletter_ai_nonce')) {
            wp_send_json_error(__('Błąd bezpieczeństwa', 'newsletter-ai'));
        }
        
        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień', 'newsletter-ai'));
        }
        
        $type = sanitize_text_field($_POST['type']);
        
        try {
            $export_data = $this->export_emails_to_csv($type);
            
            if ($export_data) {
                wp_send_json_success($export_data);
            } else {
                wp_send_json_error(__('Nie udało się wygenerować pliku CSV', 'newsletter-ai'));
            }
        } catch (Exception $e) {
            wp_send_json_error('Błąd: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler dla pobierania emaili jako tekst
     */
    public function ajax_get_emails_text() {
        // Sprawdź nonce
        if (!wp_verify_nonce($_POST['nonce'], 'newsletter_ai_nonce')) {
            wp_send_json_error(__('Błąd bezpieczeństwa', 'newsletter-ai'));
        }
        
        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień', 'newsletter-ai'));
        }
        
        $type = sanitize_text_field($_POST['type']);
        
        try {
            $emails_data = $this->get_emails_as_text($type);
            wp_send_json_success($emails_data);
        } catch (Exception $e) {
            wp_send_json_error('Błąd: ' . $e->getMessage());
        }
    }
}

