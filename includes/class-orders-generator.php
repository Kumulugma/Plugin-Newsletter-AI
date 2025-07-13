<?php
/**
 * Newsletter AI Orders XML Generator
 * Generuje plik sambaAiOrders.xml tylko dla zamówień od klientów z zgodą na newsletter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_AI_Orders_Generator {
    
    /**
     * Statusy zamówień do eksportu
     */
    private $order_statuses = array(
        'created' => array('wc-pending', 'wc-processing', 'wc-on-hold'),
        'send' => array('wc-completed'),
        'canceled' => array('wc-cancelled', 'wc-refunded', 'wc-failed')
    );
    
    /**
     * Konstruktor
     */
    public function __construct() {
        // Załaduj statusy z opcji jeśli istnieją (kompatybilność z oryginalną wtyczką)
        $custom_statuses = json_decode(get_option('sambaAiOrdersStatuses', ''), true);
        if ($custom_statuses && is_array($custom_statuses)) {
            $this->order_statuses = $custom_statuses;
        }
        
        // AJAX hook
        add_action('wp_ajax_nai_generate_orders_xml', array($this, 'ajax_generate_orders_xml'));
    }
    
    /**
     * Wygeneruj plik XML zamówień
     */
    public function generate_orders_xml_file($ajax_mode = true) {
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        if ($ajax_mode) {
            wp_send_json_error(__('WooCommerce nie jest aktywne', 'newsletter-ai'));
        }
        return false;
    }
    
    global $wpdb;
    
    $debug_mode = get_option('nai_debug_mode', false);
    $consent_field = get_option('nai_consent_field', 'newsletter_ai_consent');
    $consent_values = get_option('nai_consent_values', array('yes', '1', 'true', 'on'));
    
    if ($debug_mode) {
        $this->log('Rozpoczynanie generowania XML zamówień z filtrowaniem zgód');
    }
    
    // Przygotuj katalog
    $export_dir = WP_CONTENT_DIR . '/sambaAiExport';
    if (!file_exists($export_dir)) {
        wp_mkdir_p($export_dir);
    }
    
    $per_file = 100;
    $loop = 0;
    
    // Pobierz wszystkie statusy zamówień do eksportu
    $all_statuses = array();
    foreach ($this->order_statuses as $status_group) {
        if (is_array($status_group)) {
            $all_statuses = array_merge($all_statuses, $status_group);
        }
    }
    
    // NAPRAWKA: Policz łączną liczbę zamówień w prawidłowych statusach
    $total_orders = $this->count_total_orders($all_statuses);
    
    if ($debug_mode) {
        $this->log("Łączna liczba zamówień do sprawdzenia: $total_orders");
        $this->log('Sprawdzane statusy: ' . implode(', ', $all_statuses));
    }
    
    // Rozpocznij plik XML
    file_put_contents(WP_CONTENT_DIR . '/sambaAiExport/sambaAiOrders.xml', '<?xml version="1.0" encoding="utf-8"?><ORDERS>');
    
    $processed_orders = 0;
    $skipped_orders = 0;
    
    // NAPRAWKA: Przetwarzaj ALL zamówienia w blokach
    while ($loop * $per_file < $total_orders) {
        
        $orders = $this->get_orders_batch($per_file, $loop * $per_file, $all_statuses);
        
        if (empty($orders)) {
            if ($debug_mode) {
                $this->log("Koniec zamówień na pętli $loop (offset: " . ($loop * $per_file) . ")");
            }
            break;
        }
        
        if ($debug_mode) {
            $this->log("Pętla $loop: sprawdzanie " . count($orders) . " zamówień (offset: " . ($loop * $per_file) . ")");
        }
        
        $xml_content = $this->process_orders_batch($orders, $consent_field, $consent_values, $processed_orders, $skipped_orders);
        
        if (!empty($xml_content)) {
            file_put_contents(WP_CONTENT_DIR . '/sambaAiExport/sambaAiOrders.xml', $xml_content, FILE_APPEND);
        }
        
        wp_cache_flush();
        $loop += 1;
        
        // Zabezpieczenie przed nieskończoną pętlą
        if ($loop > 500) { // 500 * 100 = 50,000 zamówień max
            if ($debug_mode) {
                $this->log("Przerwano na pętli 500 - zabezpieczenie przed nieskończoną pętlą");
            }
            break;
        }
    }
    
    // Zamknij plik XML
    file_put_contents(WP_CONTENT_DIR . '/sambaAiExport/sambaAiOrders.xml', '</ORDERS>', FILE_APPEND);
    
    // Zapisz statystyki
    $this->save_orders_generation_stats($processed_orders, $skipped_orders);
    
    if ($debug_mode) {
        $this->log("XML zamówień wygenerowany. Sprawdzono: " . ($processed_orders + $skipped_orders) . ", Przetworzono: $processed_orders, Pominięto: $skipped_orders");
    }
    
    if ($ajax_mode) {
        wp_send_json_success(array(
            'message' => sprintf(
                __('XML zamówień wygenerowany pomyślnie! Sprawdzono: %d, przetworzono: %d zamówień, pominięto: %d (brak zgody)', 'newsletter-ai'),
                $processed_orders + $skipped_orders,
                $processed_orders,
                $skipped_orders
            ),
            'stats' => array(
                'total_checked' => $processed_orders + $skipped_orders,
                'processed' => $processed_orders,
                'skipped' => $skipped_orders
            )
        ));
    }
    
    return true;
}

/**
 * DODAJ NOWĄ metodę do class-orders-generator.php
 */
private function count_total_orders($statuses) {
    global $wpdb;
    
    $statuses_placeholders = implode(',', array_fill(0, count($statuses), '%s'));
    
    return (int) $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$wpdb->posts}
        WHERE post_type = 'shop_order'
        AND post_status IN ($statuses_placeholders)
    ", $statuses));
}


    
    /**
     * Policz zamówienia z zgodą na newsletter
     */
    private function count_orders_with_consent($statuses, $consent_field, $consent_values) {
        global $wpdb;
        
        $statuses_placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        
        // Zapytanie dla zarejestrowanych użytkowników
        $registered_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
            LEFT JOIN {$wpdb->usermeta} um ON pm_customer.meta_value = um.user_id AND um.meta_key = %s
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ($statuses_placeholders)
            AND pm_customer.meta_value > 0
            AND um.meta_value IN (" . implode(',', array_fill(0, count($consent_values), '%s')) . ")
        ", array_merge(array($consent_field), $statuses, $consent_values)));
        
        // Zapytanie dla gości z zgodą
        $guest_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
            LEFT JOIN {$wpdb->postmeta} pm_consent ON p.ID = pm_consent.post_id AND pm_consent.meta_key = '_newsletter_consent'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ($statuses_placeholders)
            AND (pm_customer.meta_value = '0' OR pm_customer.meta_value IS NULL)
            AND pm_consent.meta_value = 'yes'
        ", $statuses));
        
        return (int) $registered_count + (int) $guest_count;
    }
    
    /**
     * Pobierz batch zamówień
     */
    private function get_orders_batch($limit, $offset, $statuses) {
        return wc_get_orders(array(
            'limit' => $limit,
            'offset' => $offset,
            'status' => $statuses,
            'orderby' => 'ID',
            'order' => 'ASC'
        ));
    }
    
    /**
     * Przetwórz batch zamówień
     */
    private function process_orders_batch($orders, $consent_field, $consent_values, &$processed_orders, &$skipped_orders) {
    $xml_content = '';
    $debug_mode = get_option('nai_debug_mode', false);
    
    foreach ($orders as $order) {
        $order_id = $order->get_id();
        
        // Sprawdź zgodę klienta
        $has_consent = $this->order_has_newsletter_consent($order, $consent_field, $consent_values);
        
        if ($debug_mode && ($processed_orders + $skipped_orders) % 50 == 0) {
            // Log co 50 zamówień żeby nie spamować
            $customer_type = $order->get_user_id() > 0 ? 'user' : 'guest';
            $this->log("Zamówienie #$order_id ($customer_type): zgoda = " . ($has_consent ? 'TAK' : 'NIE'));
        }
        
        if (!$has_consent) {
            $skipped_orders++;
            continue;
        }
        
        $order_xml = $this->generate_order_xml($order);
        if ($order_xml) {
            $xml_content .= $order_xml;
            $processed_orders++;
        }
    }
    
    return $xml_content;
}
    
    /**
     * Sprawdź czy zamówienie ma zgodę na newsletter
     */
    private function order_has_newsletter_consent($order, $consent_field, $consent_values) {
        $customer_id = $order->get_user_id();
        
        if ($customer_id > 0) {
            // Zarejestrowany użytkownik
            $consent_value = get_user_meta($customer_id, $consent_field, true);
            return in_array(strtolower(trim($consent_value)), array_map('strtolower', $consent_values));
        } else {
            // Gość
            $guest_consent = $order->get_meta('_newsletter_consent');
            return $guest_consent === 'yes';
        }
    }
    
    /**
     * Wygeneruj XML dla pojedynczego zamówienia
     */
    private function generate_order_xml($order) {
        $order_items = $order->get_items();
        $filtered_order_items = array();
        
        // Przetwórz produkty zamówienia
        if (count($order_items)) {
            foreach ($order_items as $order_item) {
                $order_item_id = $order_item->get_product_id();
                
                // Pomiń nieprawidłowe produkty
                if ($order_item_id < 1) {
                    continue;
                }
                
                $order_item_product = wc_get_product($order_item_id);
                
                // Pomiń nieobsługiwane typy (na razie)
                if ($order_item_product && $order_item_product->is_type(array('variable', 'grouped', 'external'))) {
                    continue;
                }
                
                $order_item_variation_id = $order_item->get_variation_id();
                $order_item_quantity = $order_item->get_quantity();
                
                $filtered_order_items[] = array(
                    'PRODUCT_ID' => $order_item_variation_id > 0 ? $order_item_variation_id : $order_item_id,
                    'AMOUNT' => $order_item_quantity,
                    'PRICE' => $order->get_item_total($order_item, true, true) * $order_item_quantity,
                );
            }
        }
        
        // Przygotuj dane zamówienia
        $order_customer_id = (int) $order->get_user_id();
        $order_status = $order->get_status();
        $order_date_created = $order->get_date_created();
        
        if (is_a($order, 'WC_Order_Refund')) {
            // WC_Order_Refund ma brakujące funkcje
            $order_date_completed = get_post_meta($order->get_id(), '_date_completed', true);
            
            if (!$order_date_completed) {
                $order_date_completed = get_post_meta($order->get_id(), '_completed_date', true);
            }
            
            if ($order_date_completed) {
                $order_date_completed = wc_string_to_datetime($order_date_completed);
            }
            
            $order_billing_email = get_post_meta($order->get_id(), '_billing_email', true);
            $order_billing_phone = get_post_meta($order->get_id(), '_billing_phone', true);
            $order_shipping_postcode = str_replace(' ', '', get_post_meta($order->get_id(), '_shipping_postcode', true));
            $order_shipping_country = get_post_meta($order->get_id(), '_shipping_country', true);
        } else {
            $order_date_completed = $order->get_date_completed();
            $order_billing_email = $order->get_billing_email();
            $order_billing_phone = $order->get_billing_phone();
            $order_shipping_postcode = str_replace(' ', '', $order->get_shipping_postcode());
            $order_shipping_country = $order->get_shipping_country();
        }
        
        // Zmapuj status zamówienia
        $mapped_status = $this->map_order_status($order_status);
        
        // Zbuduj XML
        $xml = '<ORDER>';
        $xml .= '<ORDER_ID>' . esc_xml($order->get_id()) . '</ORDER_ID>';
        
        if ($order_customer_id > 0) {
            $xml .= '<CUSTOMER_ID>' . esc_xml($order_customer_id) . '</CUSTOMER_ID>';
        }
        
        $xml .= '<CREATED_ON>' . esc_xml($order_date_created->format(DATE_RFC3339_EXTENDED)) . '</CREATED_ON>';
        
        if ($order_date_completed) {
            $xml .= '<FINISHED_ON>' . esc_xml($order_date_completed->format(DATE_RFC3339_EXTENDED)) . '</FINISHED_ON>';
        }
        
        $xml .= '<STATUS>' . esc_xml($mapped_status) . '</STATUS>';
        $xml .= '<EMAIL>' . esc_xml($order_billing_email) . '</EMAIL>';
        
        if ($order_billing_phone) {
            $xml .= '<PHONE>' . esc_xml($order_billing_phone) . '</PHONE>';
        }
        
        if ($order_shipping_postcode) {
            $xml .= '<ZIP_CODE>' . esc_xml($order_shipping_postcode) . '</ZIP_CODE>';
        }
        
        if ($order_shipping_country) {
            $xml .= '<COUNTRY_CODE>' . esc_xml($order_shipping_country) . '</COUNTRY_CODE>';
        }
        
        // Dodaj produkty
        $xml .= '<ITEMS>';
        foreach ($filtered_order_items as $filtered_order_item) {
            $xml .= '<ITEM>';
            foreach ($filtered_order_item as $item_attribute_key => $item_attribute) {
                $xml .= '<' . $item_attribute_key . '>' . esc_xml($item_attribute) . '</' . $item_attribute_key . '>';
            }
            $xml .= '</ITEM>';
        }
        $xml .= '</ITEMS>';
        
        $xml .= '</ORDER>' . PHP_EOL;
        
        return $xml;
    }
    
    /**
     * Zmapuj status zamówienia
     */
    private function map_order_status($wc_status) {
        $prefixed_status = 'wc-' . $wc_status;
        
        foreach ($this->order_statuses as $mapped_status => $statuses) {
            if (is_array($statuses) && in_array($prefixed_status, $statuses)) {
                return $mapped_status;
            }
        }
        
        // Domyślny status
        return 'created';
    }
    
    /**
     * Zapisz statystyki generowania
     */
    private function save_orders_generation_stats($processed_orders, $skipped_orders) {
        $stats = array(
            'processed_orders' => $processed_orders,
            'skipped_orders' => $skipped_orders,
            'total_checked' => $processed_orders + $skipped_orders,
            'timestamp' => current_time('mysql'),
            'consent_field_used' => get_option('nai_consent_field', 'newsletter_ai_consent')
        );
        
        update_option('nai_last_orders_generation_stats', $stats);
    }
    
    /**
     * Pobierz statystyki ostatniego generowania
     */
    public function get_last_orders_generation_stats() {
        return get_option('nai_last_orders_generation_stats', array());
    }
    
    /**
     * Sprawdź czy plik XML zamówień istnieje
     */
    public function orders_xml_file_exists() {
        $file_path = WP_CONTENT_DIR . '/sambaAiExport/sambaAiOrders.xml';
        return file_exists($file_path);
    }
    
    /**
     * Pobierz informacje o pliku XML zamówień
     */
    public function get_orders_xml_file_info() {
        $file_path = WP_CONTENT_DIR . '/sambaAiExport/sambaAiOrders.xml';
        if (!file_exists($file_path)) {
            return false;
        }
        
        return array(
            'size' => filesize($file_path),
            'modified' => filemtime($file_path),
            'url' => content_url('sambaAiExport/sambaAiOrders.xml')
        );
    }
    
    /**
     * AJAX handler dla generowania XML zamówień
     */
    public function ajax_generate_orders_xml() {
        // Sprawdź nonce
        if (!wp_verify_nonce($_POST['nonce'], 'newsletter_ai_nonce')) {
            wp_send_json_error(__('Błąd bezpieczeństwa', 'newsletter-ai'));
        }
        
        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień', 'newsletter-ai'));
        }
        
        $this->generate_orders_xml_file(true);
    }
    
    /**
     * Logowanie
     */
    private function log($message) {
        if (get_option('nai_debug_mode', false)) {
            $timestamp = current_time('Y-m-d H:i:s');
            error_log("[Newsletter AI Orders Generator {$timestamp}] {$message}");
        }
    }
}
?>