<?php
/**
 * Plugin Name: Newsletter AI
 * Description: Kompletny system zarządzania zgodami na newsletter z nadpisywaniem pliku XML Samba.AI
 * Version: 1.0.0
 * Author: Kumulugma
 * Text Domain: newsletter-ai
 * Domain Path: /languages
 */

// Zapobiegaj bezpośredniemu dostępowi
if (!defined('ABSPATH')) {
    exit;
}

// Definiuj stałe wtyczki
define('NEWSLETTER_AI_VERSION', '1.0.0');
define('NEWSLETTER_AI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NEWSLETTER_AI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NEWSLETTER_AI_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Główna klasa aktywacji wtyczki
 */
class Newsletter_AI_Activator {
    
    /**
     * Aktywacja wtyczki
     */
    public static function activate() {
        // Utwórz domyślne opcje
        add_option('nai_consent_field', 'newsletter_ai_consent');
        add_option('nai_consent_values', array('yes', '1', 'true', 'on'));
        add_option('nai_auto_regenerate', true);
        add_option('nai_original_xml_url', '');
        add_option('nai_debug_mode', false);
        
        // Opcje cron
        add_option('nai_cron_enabled', true);
        add_option('nai_cron_time', '01:10');
        add_option('nai_cron_generate_customers', true);
        add_option('nai_cron_generate_orders', true);        
        add_option('nai_cron_generate_products', false);
        
        // Utwórz katalog eksportu
        $export_dir = WP_CONTENT_DIR . '/sambaAiExport';
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }
        
        // Wyczyść stary cron
        wp_clear_scheduled_hook('nai_daily_xml_generation');
        wp_clear_scheduled_hook('nai_regenerate_xml');
    }
    
    /**
     * Deaktywacja wtyczki
     */
    public static function deactivate() {
        // DODAJ CLEANUP NOWEGO CRON:
        wp_clear_scheduled_hook('newsletter_ai_cron_hook');
        
        // Usuń stare zaplanowane zadania
        wp_clear_scheduled_hook('nai_daily_xml_generation');
        wp_clear_scheduled_hook('nai_regenerate_xml');
    }
}

/**
 * Załaduj wszystkie klasy wtyczki
 */
function newsletter_ai_load_classes() {
    $includes_dir = NEWSLETTER_AI_PLUGIN_DIR . 'includes/';
    
    $classes = array(
        'class-newsletter-ai.php',
        'class-xml-generator.php', 
        'class-consent-manager.php',
        'class-cron-manager.php',  
        'class-customer-validator.php',
        'class-dashboard-widget.php',
        'class-orders-generator.php',
        'class-admin-pages.php',
        'class-user-profile.php',
        'class-frontend-consent.php'
    );
    
    foreach ($classes as $class_file) {
        $file_path = $includes_dir . $class_file;
        if (file_exists($file_path)) {
            require_once $file_path;
        } else {
            error_log('Newsletter AI: Nie można znaleźć pliku: ' . $file_path);
        }
    }
}

// Załaduj klasy
newsletter_ai_load_classes();

/**
 * Główna funkcja uruchamiająca wtyczkę
 */
function run_newsletter_ai() {
    // Sprawdź czy WooCommerce jest aktywne
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>Newsletter AI wymaga aktywnej wtyczki WooCommerce.</p></div>';
        });
        return;
    }
    
    // Uruchom główną klasę
    $plugin = new Newsletter_AI();
    $plugin->run();
}

// Hooking aktywacji/deaktywacji
register_activation_hook(__FILE__, array('Newsletter_AI_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('Newsletter_AI_Activator', 'deactivate'));

// Uruchom wtyczkę po załadowaniu wszystkich wtyczek
add_action('plugins_loaded', 'run_newsletter_ai');

?>