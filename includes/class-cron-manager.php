<?php
/**
 * Newsletter AI Cron Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_AI_Cron_Manager {
    
    /**
     * Hook name dla cron
     */
    private $cron_hook = 'newsletter_ai_cron_hook';
    
    /**
     * Konstruktor
     */
    public function __construct() {
        $this->init_cron();
        $this->register_hooks();
    }
    
    /**
     * Inicjalizuj cron
     */
    public function init_cron() {
        // Zarejestruj akcję cron
        add_action($this->cron_hook, array($this, 'execute_cron_tasks'));
        
        // Ustaw cron job jeśli nie jest zaplanowany
        $this->setup_cron_schedule();
    }
    
    /**
     * Zarejestruj hooki
     */
    public function register_hooks() {
        // Hook do zapisywania ustawień cron
        add_action('admin_init', array($this, 'handle_cron_settings_save'));
        
        // Hook do manualnego uruchomienia cron
        add_action('wp_ajax_nai_run_cron_manually', array($this, 'ajax_run_cron_manually'));
    }
    
    /**
     * Ustaw harmonogram cron
     */
    public function setup_cron_schedule() {
        // Sprawdź czy cron jest włączony
        $cron_enabled = get_option('nai_cron_enabled', true);
        
        if (!$cron_enabled) {
            $this->clear_cron_schedule();
            return;
        }
        
        // Pobierz godzinę uruchomienia
        $cron_time = get_option('nai_cron_time', '01:10');
        
        // Wyczyść istniejący cron
        $this->clear_cron_schedule();
        
        // Ustaw nowy cron
        $timestamp = $this->calculate_next_run_timestamp($cron_time);
        
        wp_schedule_event(
            $timestamp,
            'daily',
            $this->cron_hook
        );
        
        $this->log("Cron zaplanowany na: " . date('Y-m-d H:i:s', $timestamp) . " (lokalne: $cron_time)");
    }
    
    /**
     * Oblicz timestamp następnego uruchomienia
     */
    private function calculate_next_run_timestamp($time) {
        // Pobierz lokalną strefę czasową
        $gmt_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
        
        // Dzisiaj o danej godzinie
        $today_timestamp = strtotime(date('Y-m-d') . ' ' . $time);
        
        // Jeśli już minęła dzisiaj, ustaw na jutro
        if ($today_timestamp <= current_time('timestamp')) {
            $today_timestamp = strtotime('+1 day', $today_timestamp);
        }
        
        // Konwertuj na UTC dla WordPress cron
        return $today_timestamp - $gmt_offset;
    }
    
    /**
     * Wyczyść harmonogram cron
     */
    public function clear_cron_schedule() {
        $timestamp = wp_next_scheduled($this->cron_hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $this->cron_hook);
        }
        wp_clear_scheduled_hook($this->cron_hook);
    }
    
    /**
     * Wykonaj zadania cron
     */
    public function execute_cron_tasks() {
        $start_time = microtime(true);
        $this->log("=== CRON START ===");
        
        $results = array();
        
        try {
            // 1. Generuj XML klientów
            if (get_option('nai_cron_generate_customers', true)) {
                $results['customers'] = $this->generate_customers_xml();
            }
            
            // 2. Generuj XML zamówień (przyszłość)
            if (get_option('nai_cron_generate_orders', false)) {
                $results['orders'] = $this->generate_orders_xml();
            }
            
            // 3. Generuj XML produktów (przyszłość)
            if (get_option('nai_cron_generate_products', false)) {
                $results['products'] = $this->generate_products_xml();
            }
            
            // Zapisz wyniki ostatniego uruchomienia
            $this->save_last_run_results($results, $start_time);
            
            $this->log("=== CRON SUCCESS === (" . round(microtime(true) - $start_time, 2) . "s)");
            
        } catch (Exception $e) {
            $this->log("=== CRON ERROR === " . $e->getMessage());
            $this->save_last_run_results(array('error' => $e->getMessage()), $start_time);
        }
    }
    
    /**
     * Generuj XML klientów
     */
    private function generate_customers_xml() {
        if (!class_exists('Newsletter_AI_XML_Generator')) {
            throw new Exception('Klasa Newsletter_AI_XML_Generator nie istnieje');
        }
        
        $generator = new Newsletter_AI_XML_Generator();
        $result = $generator->generate_xml_file(false); // false = nie AJAX
        
        if (!$result) {
            throw new Exception('Nie udało się wygenerować XML klientów');
        }
        
        return array(
            'status' => 'success',
            'message' => 'XML klientów wygenerowany pomyślnie',
            'file' => 'sambaAiCustomers.xml'
        );
    }
    
    /**
     * Generuj XML zamówień (placeholder)
     */
    private function generate_orders_xml() {
        // TODO: Implementacja w przyszłości
        $file_path = WP_CONTENT_DIR . '/sambaAiExport/sambaAiOrders.xml';
        
        $xml_content = '<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL;
        $xml_content .= '<ORDERS>' . PHP_EOL;
        $xml_content .= '<!-- Placeholder - implementacja w przyszłości -->' . PHP_EOL;
        $xml_content .= '</ORDERS>';
        
        file_put_contents($file_path, $xml_content);
        
        return array(
            'status' => 'placeholder',
            'message' => 'XML zamówień (placeholder)',
            'file' => 'sambaAiOrders.xml'
        );
    }
    
    /**
     * Generuj XML produktów (placeholder)
     */
    private function generate_products_xml() {
        // TODO: Implementacja w przyszłości
        $file_path = WP_CONTENT_DIR . '/sambaAiExport/sambaAiProducts.xml';
        
        $xml_content = '<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL;
        $xml_content .= '<PRODUCTS>' . PHP_EOL;
        $xml_content .= '<!-- Placeholder - implementacja w przyszłości -->' . PHP_EOL;
        $xml_content .= '</PRODUCTS>';
        
        file_put_contents($file_path, $xml_content);
        
        return array(
            'status' => 'placeholder',
            'message' => 'XML produktów (placeholder)',
            'file' => 'sambaAiProducts.xml'
        );
    }
    
    /**
     * Zapisz wyniki ostatniego uruchomienia
     */
    private function save_last_run_results($results, $start_time) {
        $execution_time = round(microtime(true) - $start_time, 2);
        
        $last_run = array(
            'timestamp' => current_time('mysql'),
            'execution_time' => $execution_time,
            'results' => $results,
            'next_run' => $this->get_next_scheduled_run()
        );
        
        update_option('nai_cron_last_run', $last_run);
    }
    
    /**
     * Pobierz następne zaplanowane uruchomienie
     */
    public function get_next_scheduled_run() {
        $timestamp = wp_next_scheduled($this->cron_hook);
        if (!$timestamp) {
            return false;
        }
        
        // Konwertuj na lokalny czas
        $gmt_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
        $local_timestamp = $timestamp + $gmt_offset;
        
        return date('Y-m-d H:i:s', $local_timestamp);
    }
    
    /**
     * Pobierz wyniki ostatniego uruchomienia
     */
    public function get_last_run_results() {
        return get_option('nai_cron_last_run', array());
    }
    
    /**
     * Sprawdź czy cron jest aktywny
     */
    public function is_cron_active() {
        return wp_next_scheduled($this->cron_hook) !== false;
    }
    
    /**
     * Obsługa zapisywania ustawień cron
     */
    public function handle_cron_settings_save() {
        if (!isset($_POST['nai_save_cron_settings']) || !current_user_can('manage_options')) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['nai_cron_nonce'], 'nai_cron_settings')) {
            return;
        }
        
        // Zapisz ustawienia
        $cron_enabled = isset($_POST['nai_cron_enabled']);
        $cron_time = sanitize_text_field($_POST['nai_cron_time']);
        $generate_customers = isset($_POST['nai_cron_generate_customers']);
        $generate_orders = isset($_POST['nai_cron_generate_orders']);
        $generate_products = isset($_POST['nai_cron_generate_products']);
        
        update_option('nai_cron_enabled', $cron_enabled);
        update_option('nai_cron_time', $cron_time);
        update_option('nai_cron_generate_customers', $generate_customers);
        update_option('nai_cron_generate_orders', $generate_orders);
        update_option('nai_cron_generate_products', $generate_products);
        
        // Przeorganizuj cron
        $this->setup_cron_schedule();
        
        add_settings_error('nai_settings', 'cron_settings_saved', 
            __('Ustawienia cron zostały zapisane.', 'newsletter-ai'), 'updated');
    }
    
    /**
     * AJAX - manualne uruchomienie cron
     */
    public function ajax_run_cron_manually() {
        // Sprawdź nonce
        if (!wp_verify_nonce($_POST['nonce'], 'newsletter_ai_nonce')) {
            wp_send_json_error(__('Błąd bezpieczeństwa', 'newsletter-ai'));
        }
        
        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień', 'newsletter-ai'));
        }
        
        try {
            $this->execute_cron_tasks();
            
            wp_send_json_success(array(
                'message' => __('Zadania cron wykonane pomyślnie!', 'newsletter-ai'),
                'last_run' => $this->get_last_run_results()
            ));
        } catch (Exception $e) {
            wp_send_json_error('Błąd wykonania cron: ' . $e->getMessage());
        }
    }
    
    /**
     * Logowanie
     */
    private function log($message) {
        if (get_option('nai_debug_mode', false)) {
            $timestamp = current_time('Y-m-d H:i:s');
            error_log("[Newsletter AI Cron {$timestamp}] {$message}");
        }
    }
    
    /**
     * Cleanup - usuń zaplanowane zadania
     */
    public function cleanup() {
        $this->clear_cron_schedule();
        $this->log("Cron cleanup wykonany");
    }
}