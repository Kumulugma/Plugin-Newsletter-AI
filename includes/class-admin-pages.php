<?php
/**
 * Klasa stron administracyjnych Newsletter AI
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_AI_Admin_Pages {
    
    /**
     * Konstruktor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
    }
    
    /**
     * Dodaj menu administracyjne
     */
    public function add_admin_menu() {
        // Główna strona menu
        add_menu_page(
            __('Newsletter AI', 'newsletter-ai'),
            __('Newsletter AI', 'newsletter-ai'),
            'manage_options',
            'newsletter-ai',
            array($this, 'main_page'),
            'dashicons-email-alt',
            30
        );
        
        // Zakładka ustawień XML
        add_submenu_page(
            'newsletter-ai',
            __('Ustawienia XML', 'newsletter-ai'),
            __('Ustawienia XML', 'newsletter-ai'),
            'manage_options',
            'newsletter-ai',
            array($this, 'main_page')
        );
        
        // Zakładka użytkowników
        add_submenu_page(
            'newsletter-ai',
            __('Użytkownicy i zgody', 'newsletter-ai'),
            __('Użytkownicy i zgody', 'newsletter-ai'),
            'manage_options',
            'newsletter-ai-users',
            array($this, 'users_page')
        );
        
        // Zakładka frontend
        add_submenu_page(
            'newsletter-ai',
            __('Ustawienia Frontend', 'newsletter-ai'),
            __('Ustawienia Frontend', 'newsletter-ai'),
            'manage_options',
            'newsletter-ai-frontend',
            array($this, 'frontend_page')
        );
    }
    
    /**
     * Obsługa formularzy
     */
    public function handle_form_submissions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Obsługa zapisu ustawień XML
        if (isset($_POST['nai_save_xml_settings']) && wp_verify_nonce($_POST['nai_xml_nonce'], 'nai_xml_settings')) {
            $this->save_xml_settings();
        }
        
        // Obsługa zapisu ustawień frontend
        if (isset($_POST['nai_save_frontend_settings']) && wp_verify_nonce($_POST['nai_frontend_nonce'], 'nai_frontend_settings')) {
            $this->save_frontend_settings();
        }
        
        // Obsługa eksportu użytkowników
        if (isset($_GET['action']) && $_GET['action'] === 'export_users' && wp_verify_nonce($_GET['nonce'], 'export_users')) {
            $this->handle_export_users();
        }
    }
    
    /**
     * Zapisz ustawienia XML
     */
    private function save_xml_settings() {
        // Sanityzacja i zapis ustawień
        $consent_field = sanitize_text_field($_POST['nai_consent_field']);
        $consent_values = sanitize_text_field($_POST['nai_consent_values']);
        $original_xml_url = esc_url_raw($_POST['nai_original_xml_url']);
        $auto_regenerate = isset($_POST['nai_auto_regenerate']);
        $debug_mode = isset($_POST['nai_debug_mode']);
        
        update_option('nai_consent_field', $consent_field);
        update_option('nai_consent_values', array_map('trim', explode(',', $consent_values)));
        update_option('nai_original_xml_url', $original_xml_url);
        update_option('nai_auto_regenerate', $auto_regenerate);
        update_option('nai_debug_mode', $debug_mode);
        
        add_settings_error('nai_settings', 'settings_saved', __('Ustawienia zostały zapisane.', 'newsletter-ai'), 'updated');
    }
    
    /**
     * Zapisz ustawienia frontend
     */
    private function save_frontend_settings() {
        // Sanityzacja i zapis ustawień
        $consent_text = sanitize_text_field($_POST['nai_consent_text']);
        $consent_required = isset($_POST['nai_consent_required']);
        $show_on_registration = isset($_POST['nai_show_on_registration']);
        $show_on_checkout = isset($_POST['nai_show_on_checkout']);
        $show_in_myaccount = isset($_POST['nai_show_in_myaccount']);
        $load_frontend_styles = isset($_POST['nai_load_frontend_styles']);
        $primary_color = sanitize_hex_color($_POST['nai_frontend_primary_color']);
        $border_radius = sanitize_text_field($_POST['nai_frontend_border_radius']);
        
        update_option('nai_consent_text', $consent_text);
        update_option('nai_consent_required', $consent_required);
        update_option('nai_show_on_registration', $show_on_registration);
        update_option('nai_show_on_checkout', $show_on_checkout);
        update_option('nai_show_in_myaccount', $show_in_myaccount);
        update_option('nai_load_frontend_styles', $load_frontend_styles);
        update_option('nai_frontend_primary_color', $primary_color);
        update_option('nai_frontend_border_radius', $border_radius);
        
        add_settings_error('nai_settings', 'frontend_settings_saved', __('Ustawienia frontend zostały zapisane.', 'newsletter-ai'), 'updated');
    }
    
    /**
     * Obsługa eksportu użytkowników
     */
    private function handle_export_users() {
        $consent_manager = new Newsletter_AI_Consent_Manager();
        $export_data = $consent_manager->export_users_to_csv();
        
        // Przekieruj do pobrania pliku
        wp_redirect($export_data['url']);
        exit;
    }
    
    /**
     * Główna strona (ustawienia XML)
     */
    public function main_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'xml_settings';
        
        // Pobierz dane do wyświetlenia
        $xml_generator = new Newsletter_AI_XML_Generator();
        $consent_manager = new Newsletter_AI_Consent_Manager();
        
        $stats = $xml_generator->get_last_generation_stats();
        $xml_file_info = $xml_generator->get_xml_file_info();
        $consent_fields = $consent_manager->find_existing_consent_fields();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Newsletter AI - Ustawienia XML', 'newsletter-ai'); ?></h1>
            
            <?php settings_errors('nai_settings'); ?>
            
            <div class="nav-tab-wrapper">
                <a href="?page=newsletter-ai&tab=xml_settings" class="nav-tab <?php echo $active_tab === 'xml_settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Ustawienia XML', 'newsletter-ai'); ?>
                </a>
            </div>
            
            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'xml_settings':
                    default:
                        include NEWSLETTER_AI_PLUGIN_DIR . 'templates/admin/xml-settings-tab.php';
                        break;
                }
                ?>
            </div>
        </div>
        
        <style>
        .tab-content {
            margin-top: 20px;
        }
        .nai-stats-box {
            background: #f0f0f1;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        .nai-stats-box h3 {
            margin-top: 0;
        }
        .nai-info-box {
            background: #e7f3ff;
            border-left: 4px solid #00a0d2;
            padding: 12px;
            margin: 15px 0;
        }
        .nai-warning-box {
            background: #fff8e5;
            border-left: 4px solid #dba617;
            padding: 12px;
            margin: 15px 0;
        }
        </style>
        <?php
    }
    
    /**
     * Strona użytkowników
     */
    public function users_page() {
        $consent_manager = new Newsletter_AI_Consent_Manager();
        
        // Pobierz statystyki
        $statistics = $consent_manager->get_consent_statistics();
        $consent_fields = $consent_manager->find_existing_consent_fields();
        $sample_values = $consent_manager->get_sample_consent_values();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Newsletter AI - Użytkownicy i zgody', 'newsletter-ai'); ?></h1>
            
            <?php include NEWSLETTER_AI_PLUGIN_DIR . 'templates/admin/users-consent-tab.php'; ?>
        </div>
        <?php
    }
    
    /**
     * Strona frontend
     */
    public function frontend_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Newsletter AI - Ustawienia Frontend', 'newsletter-ai'); ?></h1>
            
            <?php settings_errors('nai_settings'); ?>
            
            <?php include NEWSLETTER_AI_PLUGIN_DIR . 'templates/admin/frontend-settings-tab.php'; ?>
        </div>
        <?php
    }
}
?>