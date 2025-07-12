<?php
/**
 * Główna klasa wtyczki Newsletter AI
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_AI {
    
    /**
     * Wersja wtyczki
     */
    public $version = NEWSLETTER_AI_VERSION;
    
    /**
     * Instancje klas
     */
    public $admin_pages;
    public $xml_generator;
    public $consent_manager;
    public $user_profile;
    public $frontend_consent;
    
    /**
     * Konstruktor
     */
    public function __construct() {
        $this->define_admin_hooks();
        $this->init_classes();
    }
    
    /**
     * Uruchom wtyczkę
     */
    public function run() {
        $this->init();
        $this->define_hooks();
    }
    
    /**
     * Inicjalizacja
     */
    private function init() {
        // Załaduj teksty
        add_action('init', array($this, 'load_textdomain'));
        
        // Inicjalizuj klasy - POPRAWKA KOLEJNOŚCI
        $this->xml_generator = new Newsletter_AI_XML_Generator();
        $this->consent_manager = new Newsletter_AI_Consent_Manager();
        $this->admin_pages = new Newsletter_AI_Admin_Pages();
        $this->user_profile = new Newsletter_AI_User_Profile();
        $this->frontend_consent = new Newsletter_AI_Frontend_Consent();
        
        // Debug
        error_log('Newsletter AI: Klasy zainicjalizowane - XML: ' . (is_object($this->xml_generator) ? 'OK' : 'BŁĄD'));
        error_log('Newsletter AI: Klasy zainicjalizowane - Consent: ' . (is_object($this->consent_manager) ? 'OK' : 'BŁĄD'));
        error_log('Newsletter AI: Klasy zainicjalizowane - User Profile: ' . (is_object($this->user_profile) ? 'OK' : 'BŁĄD'));
        error_log('Newsletter AI: Klasy zainicjalizowane - Frontend: ' . (is_object($this->frontend_consent) ? 'OK' : 'BŁĄD'));
    }
    
    /**
     * Inicjalizuj klasy pomocnicze
     */
    private function init_classes() {
        // Te klasy będą załadowane przez autoloader
    }
    
    /**
     * Zdefiniuj hooki administracyjne
     */
    private function define_admin_hooks() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'admin_init'));
    }
    
    /**
     * Zdefiniuj główne hooki
     */
    private function define_hooks() {
        // Hook przy rejestracji użytkownika
        add_action('user_register', array($this, 'on_user_register'));
        
        // Hook przy aktualizacji profilu
        add_action('profile_update', array($this, 'on_profile_update'));
        
        // AJAX hooki - SPRAWDŹ CZY SĄ
        add_action('wp_ajax_nai_generate_xml', array($this->xml_generator, 'ajax_generate_xml'));
        add_action('wp_ajax_nai_bulk_create_consent_field', array($this->consent_manager, 'ajax_bulk_create_consent_field'));
        add_action('wp_ajax_nai_update_user_consent', array($this->consent_manager, 'ajax_update_user_consent'));
        add_action('wp_ajax_nai_get_users_table', array($this->consent_manager, 'ajax_get_users_table'));
        
        // Debug - sprawdź czy klasy istnieją
        if (!$this->xml_generator) {
            error_log('Newsletter AI: xml_generator nie istnieje');
        }
        if (!$this->consent_manager) {
            error_log('Newsletter AI: consent_manager nie istnieje');
        }
    }
    
    /**
     * Załaduj pliki językowe
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'newsletter-ai',
            false,
            dirname(NEWSLETTER_AI_PLUGIN_BASENAME) . '/languages/'
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Tylko na stronach naszej wtyczki
        if (strpos($hook, 'newsletter-ai') === false) {
            return;
        }
        
        // Sprawdź czy plik JS istnieje
        $js_file = NEWSLETTER_AI_PLUGIN_DIR . 'admin/js/admin.js';
        if (!file_exists($js_file)) {
            error_log('Newsletter AI: Plik admin.js nie istnieje: ' . $js_file);
            return;
        }
        
        // JavaScript - WPRAWDŹ ZALEŻNOŚCI
        wp_enqueue_script(
            'newsletter-ai-admin',
            NEWSLETTER_AI_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery', 'wp-util'), // Upewnij się że jQuery jest załadowane
            $this->version,
            true // Ładuj w footer
        );
        
        // Sprawdź czy jQuery jest dostępne
        wp_add_inline_script('newsletter-ai-admin', '
            if (typeof jQuery === "undefined") {
                console.error("Newsletter AI: jQuery nie jest dostępne!");
            } else {
                console.log("Newsletter AI: jQuery dostępne", jQuery.fn.jquery);
            }
        ', 'before');
        
        // Przekaż dane do JS
        wp_localize_script('newsletter-ai-admin', 'newsletterAI', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('newsletter_ai_nonce'),
            'strings' => array(
                'confirm_bulk_create' => __('Czy na pewno chcesz utworzyć pole zgody dla wszystkich użytkowników bez tego pola?', 'newsletter-ai'),
                'generating_xml' => __('Generowanie XML...', 'newsletter-ai'),
                'xml_generated' => __('XML wygenerowany pomyślnie!', 'newsletter-ai'),
                'error_occurred' => __('Wystąpił błąd. Spróbuj ponownie.', 'newsletter-ai')
            ),
            'debug' => array(
                'js_file_exists' => file_exists($js_file),
                'plugin_url' => NEWSLETTER_AI_PLUGIN_URL,
                'xml_generator_exists' => is_object($this->xml_generator),
                'consent_manager_exists' => is_object($this->consent_manager)
            )
        ));
        
        // CSS
        wp_enqueue_style(
            'newsletter-ai-admin',
            NEWSLETTER_AI_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            $this->version
        );
        
        // WordPress core styles
        wp_enqueue_style('wp-jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-dialog');
    }
    
    /**
     * Admin init
     */
    public function admin_init() {
        // Zarejestruj ustawienia
        $this->register_settings();
    }
    
    /**
     * Zarejestruj ustawienia
     */
    private function register_settings() {
        // Grupa ustawień XML
        register_setting('nai_xml_settings', 'nai_consent_field');
        register_setting('nai_xml_settings', 'nai_consent_values');
        register_setting('nai_xml_settings', 'nai_original_xml_url');
        register_setting('nai_xml_settings', 'nai_auto_regenerate');
        register_setting('nai_xml_settings', 'nai_debug_mode');
        
        // Przetwarzanie wartości zgody
        add_filter('pre_update_option_nai_consent_values', array($this, 'process_consent_values'));
    }
    
    /**
     * Przetwórz wartości zgody z string na array
     */
    public function process_consent_values($value) {
        if (is_string($value)) {
            return array_map('trim', explode(',', $value));
        }
        return $value;
    }
    
    /**
     * Hook przy rejestracji użytkownika
     */
    public function on_user_register($user_id) {
        if ($this->consent_manager) {
            $this->consent_manager->ensure_user_has_consent_field($user_id);
        }
        
        // Auto-regeneruj XML jeśli włączone
        if (get_option('nai_auto_regenerate', true)) {
            $this->schedule_xml_regeneration();
        }
    }
    
    /**
     * Hook przy aktualizacji profilu
     */
    public function on_profile_update($user_id) {
        // Auto-regeneruj XML jeśli włączone i zmieniono zgodę
        if (get_option('nai_auto_regenerate', true)) {
            $this->schedule_xml_regeneration();
        }
    }
    
    /**
     * Zaplanuj regenerację XML
     */
    private function schedule_xml_regeneration() {
        if (!wp_next_scheduled('nai_regenerate_xml')) {
            wp_schedule_single_event(time() + 30, 'nai_regenerate_xml');
        }
    }
    
    /**
     * Pobierz instancję klasy
     */
    public static function get_instance() {
        static $instance = null;
        if (null === $instance) {
            $instance = new self();
        }
        return $instance;
    }
    
    /**
     * Logowanie debugowe
     */
    public function log($message, $level = 'info') {
        if (get_option('nai_debug_mode', false)) {
            $timestamp = current_time('Y-m-d H:i:s');
            error_log("[Newsletter AI {$timestamp}] [{$level}] {$message}");
        }
    }
    
    /**
     * Sprawdź czy użytkownik ma uprawnienia
     */
    public function current_user_can_manage() {
        return current_user_can('manage_options');
    }
    
    /**
     * Pobierz URL do pliku w katalogu wtyczki
     */
    public function get_plugin_url($path = '') {
        return NEWSLETTER_AI_PLUGIN_URL . ltrim($path, '/');
    }
    
    /**
     * Pobierz ścieżkę do pliku w katalogu wtyczki
     */
    public function get_plugin_path($path = '') {
        return NEWSLETTER_AI_PLUGIN_DIR . ltrim($path, '/');
    }
}
?>