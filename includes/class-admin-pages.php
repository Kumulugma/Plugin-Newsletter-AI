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
    
    // Debug dla eksportu
    if (isset($_GET['action']) && $_GET['action'] === 'export_users') {
        error_log('Newsletter AI: Export CSV triggered');
        error_log('Newsletter AI: GET params: ' . print_r($_GET, true));
        
        if (!isset($_GET['nonce'])) {
            error_log('Newsletter AI: Export - brak nonce');
            wp_die('Błąd: brak nonce');
        }
        
        if (!wp_verify_nonce($_GET['nonce'], 'export_users')) {
            error_log('Newsletter AI: Export - nieprawidłowy nonce');
            wp_die('Błąd bezpieczeństwa: nieprawidłowy nonce');
        }
        
        error_log('Newsletter AI: Export - nonce OK, uruchamianie eksportu');
        $this->handle_export_users();
        return; // Ważne - zatrzymaj dalsze przetwarzanie
    }
    
    // Obsługa zapisu ustawień XML
    if (isset($_POST['nai_save_xml_settings']) && wp_verify_nonce($_POST['nai_xml_nonce'], 'nai_xml_settings')) {
        $this->save_xml_settings();
    }
    
    // Obsługa zapisu ustawień frontend
    if (isset($_POST['nai_save_frontend_settings']) && wp_verify_nonce($_POST['nai_frontend_nonce'], 'nai_frontend_settings')) {
        $this->save_frontend_settings();
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
    error_log('Newsletter AI: handle_export_users() started');
    
    try {
        // Sprawdź czy klasa istnieje
        if (!class_exists('Newsletter_AI_Consent_Manager')) {
            error_log('Newsletter AI: Klasa Newsletter_AI_Consent_Manager nie istnieje');
            wp_die('Błąd: Klasa Newsletter_AI_Consent_Manager nie została załadowana');
        }
        
        $consent_manager = new Newsletter_AI_Consent_Manager();
        error_log('Newsletter AI: Consent manager utworzony');
        
        // Sprawdź czy metoda eksportu istnieje
        if (!method_exists($consent_manager, 'export_users_to_csv')) {
            error_log('Newsletter AI: Metoda export_users_to_csv nie istnieje');
            wp_die('Błąd: Metoda export_users_to_csv nie została znaleziona');
        }
        
        $export_data = $consent_manager->export_users_to_csv();
        error_log('Newsletter AI: Export data: ' . print_r($export_data, true));
        
        if (!$export_data || !isset($export_data['url'])) {
            error_log('Newsletter AI: Eksport zwrócił nieprawidłowe dane');
            wp_die('Błąd: Nie udało się wygenerować pliku CSV');
        }
        
        // Sprawdź czy plik istnieje
        if (isset($export_data['path']) && !file_exists($export_data['path'])) {
            error_log('Newsletter AI: Plik CSV nie istnieje: ' . $export_data['path']);
            wp_die('Błąd: Plik CSV nie został utworzony');
        }
        
        error_log('Newsletter AI: Przekierowanie do: ' . $export_data['url']);
        
        // Wymuś pobranie pliku
        if (isset($export_data['path']) && file_exists($export_data['path'])) {
            $filename = basename($export_data['path']);
            
            // Wyczyść bufory
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Ustaw nagłówki
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($export_data['path']));
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            
            // Wyślij plik
            readfile($export_data['path']);
            
            // Usuń tymczasowy plik (opcjonalnie)
            // unlink($export_data['path']);
            
            exit;
        } else {
            // Fallback - przekieruj do URL
            wp_redirect($export_data['url']);
            exit;
        }
        
    } catch (Exception $e) {
        error_log('Newsletter AI: Błąd eksportu: ' . $e->getMessage());
        wp_die('Błąd podczas eksportu: ' . $e->getMessage());
    }
}

    /**
     * Główna strona (ustawienia XML)
     */
    public function main_page() {
        // Pobierz dane do wyświetlenia
        $xml_generator = new Newsletter_AI_XML_Generator();
        $consent_manager = new Newsletter_AI_Consent_Manager();

        $stats = $xml_generator->get_last_generation_stats();
        $xml_file_info = $xml_generator->get_xml_file_info();
        $consent_fields = $consent_manager->find_existing_consent_fields();
        ?>
        <div class="wrap nai-admin-page">
            <h1><?php _e('Newsletter AI - Ustawienia XML', 'newsletter-ai'); ?></h1>

            <?php settings_errors('nai_settings'); ?>

            <div class="nai-grid nai-grid-2">
                <!-- Kolumna lewa - główne ustawienia -->
                <div>
                    <!-- Statystyki ostatniego generowania -->
                    <?php if (!empty($stats)): ?>
                        <div class="nai-metabox">
                            <div class="nai-metabox-header secondary">
                                <h3>📊 <?php _e('Ostatnie generowanie XML', 'newsletter-ai'); ?></h3>
                            </div>
                            <div class="nai-metabox-content">
                                <div class="nai-stats-grid">
                                    <div class="nai-stat-card">
                                        <div class="nai-stat-number"><?php echo esc_html($stats['total_users']); ?></div>
                                        <div class="nai-stat-label"><?php _e('Łącznie użytkowników', 'newsletter-ai'); ?></div>
                                    </div>
                                    <div class="nai-stat-card">
                                        <div class="nai-stat-number"><?php echo esc_html($stats['users_with_consent']); ?></div>
                                        <div class="nai-stat-label"><?php _e('Z zgodą', 'newsletter-ai'); ?></div>
                                    </div>
                                    <div class="nai-stat-card">
                                        <div class="nai-stat-number"><?php echo esc_html($stats['users_without_consent']); ?></div>
                                        <div class="nai-stat-label"><?php _e('Bez zgody', 'newsletter-ai'); ?></div>
                                    </div>
                                </div>
                                <p><strong><?php _e('Data:', 'newsletter-ai'); ?></strong> <?php echo esc_html($stats['timestamp']); ?></p>
                                <p><strong><?php _e('Użyte pole zgody:', 'newsletter-ai'); ?></strong> <code><?php echo esc_html($stats['consent_field_used']); ?></code></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Informacje o pliku XML -->
                    <div class="nai-metabox">
                        <div class="nai-metabox-header <?php echo $xml_file_info ? 'success' : 'warning'; ?>">
                            <h3>📄 <?php _e('Informacje o pliku XML', 'newsletter-ai'); ?></h3>
                        </div>
                        <div class="nai-metabox-content">
                            <?php if ($xml_file_info): ?>
                                <p><strong><?php _e('Rozmiar:', 'newsletter-ai'); ?></strong> <?php echo size_format($xml_file_info['size']); ?></p>
                                <p><strong><?php _e('Ostatnia modyfikacja:', 'newsletter-ai'); ?></strong> <?php echo date('Y-m-d H:i:s', $xml_file_info['modified']); ?></p>
                                <p><strong><?php _e('URL:', 'newsletter-ai'); ?></strong> <a href="<?php echo esc_url($xml_file_info['url']); ?>" target="_blank" class="nai-btn nai-btn-small nai-btn-secondary"><?php _e('Otwórz plik', 'newsletter-ai'); ?></a></p>
                            <?php else: ?>
                                <div class="nai-notice nai-notice-warning">
                                    <span>⚠️</span>
                                    <p><?php _e('Plik XML jeszcze nie został wygenerowany.', 'newsletter-ai'); ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="nai-text-center" style="margin-top: 20px;">
                                <button type="button" id="nai-generate-xml" class="nai-btn nai-btn-primary nai-btn-large">
                                    ⚡ <?php _e('Wygeneruj XML teraz', 'newsletter-ai'); ?>
                                </button>
                                <div id="nai-xml-generation-status" style="margin-top: 10px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kolumna prawa - ustawienia -->
                <div>
                    <!-- Formularz ustawień -->
                    <div class="nai-metabox">
                        <div class="nai-metabox-header">
                            <h3>⚙️ <?php _e('Konfiguracja XML', 'newsletter-ai'); ?></h3>
                        </div>
                        <div class="nai-metabox-content">
                            <form method="post" action="">
                                <?php wp_nonce_field('nai_xml_settings', 'nai_xml_nonce'); ?>

                                <div class="nai-form-field">
                                    <label for="nai_consent_field"><?php _e('Pole zgody na newsletter', 'newsletter-ai'); ?></label>
                                    <input type="text" id="nai_consent_field" name="nai_consent_field" 
                                           value="<?php echo esc_attr(get_option('nai_consent_field', 'newsletter_ai_consent')); ?>" />
                                    <p class="description"><?php _e('Nazwa pola meta przechowującego zgodę użytkownika na newsletter', 'newsletter-ai'); ?></p>
                                </div>

                                <div class="nai-form-field">
                                    <label for="nai_consent_values"><?php _e('Wartości oznaczające zgodę', 'newsletter-ai'); ?></label>
                                    <input type="text" id="nai_consent_values" name="nai_consent_values" 
                                           value="<?php echo esc_attr(implode(',', get_option('nai_consent_values', array('yes', '1', 'true', 'on')))); ?>" />
                                    <p class="description"><?php _e('Wartości oddzielone przecinkami, które oznaczają zgodę użytkownika (np: yes,1,true,on)', 'newsletter-ai'); ?></p>
                                </div>

                                <div class="nai-form-field">
                                    <label for="nai_original_xml_url"><?php _e('URL oryginalnego pliku XML', 'newsletter-ai'); ?></label>
                                    <input type="url" id="nai_original_xml_url" name="nai_original_xml_url" 
                                           value="<?php echo esc_attr(get_option('nai_original_xml_url', '')); ?>" />
                                    <p class="description"><?php _e('URL do oryginalnego pliku XML, który ma zostać nadpisany (opcjonalne)', 'newsletter-ai'); ?></p>
                                </div>

                                <div class="nai-form-field">
                                    <label>
                                        <input type="checkbox" name="nai_auto_regenerate" value="1" 
                                               <?php checked(get_option('nai_auto_regenerate', true), true); ?> />
                                               <?php _e('Automatyczne regenerowanie', 'newsletter-ai'); ?>
                                    </label>
                                    <p class="description"><?php _e('Automatycznie regeneruj XML przy rejestracji nowego użytkownika lub zmianie zgody', 'newsletter-ai'); ?></p>
                                </div>

                                <div class="nai-form-field">
                                    <label>
                                        <input type="checkbox" name="nai_debug_mode" value="1" 
                                               <?php checked(get_option('nai_debug_mode', false), true); ?> />
                                               <?php _e('Tryb debugowania', 'newsletter-ai'); ?>
                                    </label>
                                    <p class="description"><?php _e('Włącz szczegółowe logowanie do pliku debug.log', 'newsletter-ai'); ?></p>
                                </div>

                                <button type="submit" name="nai_save_xml_settings" class="nai-btn nai-btn-primary">
                                    💾 <?php _e('Zapisz ustawienia', 'newsletter-ai'); ?>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Dostępne pola meta -->
                    <?php if (!empty($consent_fields)): ?>
                        <div class="nai-metabox">
                            <div class="nai-metabox-header secondary">
                                <h3>🔍 <?php _e('Dostępne pola zgody', 'newsletter-ai'); ?></h3>
                            </div>
                            <div class="nai-metabox-content">
                                <p><?php _e('Znalezione pola w bazie danych:', 'newsletter-ai'); ?></p>
                                <div style="max-height: 200px; overflow-y: auto;">
                                    <?php foreach ($consent_fields as $field): ?>
                                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 5px 0; border-bottom: 1px solid #f0f0f1;">
                                            <div>
                                                <strong><?php echo esc_html($field->meta_key); ?></strong>
                                                <small>(<?php echo esc_html($field->count); ?> <?php _e('użytkowników', 'newsletter-ai'); ?>)</small>
                                            </div>
                                            <button type="button" class="nai-btn nai-btn-small nai-btn-secondary" 
                                                    onclick="setConsentField('<?php echo esc_js($field->meta_key); ?>')">
                                                        <?php _e('Użyj', 'newsletter-ai'); ?>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Instrukcje -->
            <div class="nai-metabox">
                <div class="nai-metabox-header warning">
                    <h3>📖 <?php _e('Instrukcje użytkowania', 'newsletter-ai'); ?></h3>
                </div>
                <div class="nai-metabox-content">
                    <ol>
                        <li><?php _e('Skonfiguruj pole zgody - wybierz z listy powyżej lub wprowadź własne', 'newsletter-ai'); ?></li>
                        <li><?php _e('Ustaw wartości które oznaczają zgodę użytkownika', 'newsletter-ai'); ?></li>
                        <li><?php _e('Opcjonalnie podaj URL oryginalnego pliku XML do nadpisania', 'newsletter-ai'); ?></li>
                        <li><?php _e('Zapisz ustawienia i wygeneruj XML', 'newsletter-ai'); ?></li>
                        <li><?php _e('Sprawdź zakładkę "Użytkownicy i zgody" aby zarządzać zgodami', 'newsletter-ai'); ?></li>
                    </ol>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Strona użytkowników
     */

    /**
     * Strona użytkowników - wersja finalna
     */
    public function users_page() {
        $consent_manager = new Newsletter_AI_Consent_Manager();

        // Pobierz statystyki
        $statistics = $consent_manager->get_consent_statistics();
        $consent_fields = $consent_manager->find_existing_consent_fields();
        $sample_values = $consent_manager->get_sample_consent_values();
        ?>
        <div class="wrap nai-admin-page nai-users-consent">
            <h1><?php _e('Newsletter AI - Użytkownicy i zgody', 'newsletter-ai'); ?></h1>

            <!-- Statystyki zgód -->
            <div class="nai-metabox">
                <div class="nai-metabox-header primary">
                    <h3>📊 <?php _e('Statystyki zgód', 'newsletter-ai'); ?></h3>
                </div>
                <div class="nai-metabox-content">
                    <div class="nai-stats-grid">
                        <div class="nai-stat-card">
                            <div class="nai-stat-number"><?php echo esc_html($statistics['total_users']); ?></div>
                            <div class="nai-stat-label"><?php _e('Łącznie użytkowników', 'newsletter-ai'); ?></div>
                        </div>
                        <div class="nai-stat-card">
                            <div class="nai-stat-number"><?php echo esc_html($statistics['users_with_field']); ?></div>
                            <div class="nai-stat-label"><?php _e('Z polem zgody', 'newsletter-ai'); ?></div>
                        </div>
                        <div class="nai-stat-card">
                            <div class="nai-stat-number"><?php echo esc_html($statistics['users_without_field']); ?></div>
                            <div class="nai-stat-label"><?php _e('Bez pola zgody', 'newsletter-ai'); ?></div>
                        </div>
                        <div class="nai-stat-card">
                            <div class="nai-stat-number"><?php echo esc_html($statistics['users_with_consent']); ?></div>
                            <div class="nai-stat-label"><?php _e('Wyraziło zgodę', 'newsletter-ai'); ?></div>
                        </div>
                        <div class="nai-stat-card">
                            <div class="nai-stat-number"><?php echo esc_html($statistics['consent_percentage']); ?>%</div>
                            <div class="nai-stat-label"><?php _e('Procent zgód', 'newsletter-ai'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="nai-users-layout">
                <!-- Kolumna lewa - lista użytkowników (75%) -->
                <div class="nai-users-main">
                    <!-- Wyszukiwarka użytkowników -->
                    <div class="nai-search-box">
                        <h3><?php _e('Wyszukaj użytkownika', 'newsletter-ai'); ?></h3>
                        <input type="text" id="nai-user-search" class="nai-search-input" 
                               placeholder="<?php _e('Login, email lub nazwa...', 'newsletter-ai'); ?>" />
                    </div>

                    <!-- Tabela użytkowników -->
                    <div class="nai-metabox">
                        <div class="nai-metabox-header primary">
                            <h3>👥 <?php _e('Lista użytkowników', 'newsletter-ai'); ?></h3>
                        </div>
                        <div class="nai-metabox-content nai-p-0">
                            <div id="nai-users-table-container">
                                <div class="nai-loading">
                                    <div class="nai-spinner"></div>
                                    <?php _e('Ładowanie użytkowników...', 'newsletter-ai'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kolumna prawa - narzędzia zarządzania (25%) -->
                <div class="nai-users-sidebar">
                    <!-- Informacje o aktualnym polu zgody -->
                    <div class="nai-metabox">
                        <div class="nai-metabox-header secondary">
                            <h3>ℹ️ <?php _e('Aktualne pole zgody', 'newsletter-ai'); ?></h3>
                        </div>
                        <div class="nai-metabox-content">
                            <?php
                            $consent_field = get_option('nai_consent_field', 'newsletter_ai_consent');
                            $consent_values = get_option('nai_consent_values', array('yes', '1', 'true', 'on'));
                            ?>
                            <p><strong><?php _e('Pole meta:', 'newsletter-ai'); ?></strong><br><code><?php echo esc_html($consent_field); ?></code></p>
                            <p><strong><?php _e('Wartości zgody:', 'newsletter-ai'); ?></strong><br>
                                <code><?php echo esc_html(implode(', ', $consent_values)); ?></code>
                            </p>

                            <?php if (!empty($sample_values)): ?>
                                <details style="margin-top: 15px;">
                                    <summary><strong><?php _e('Przykładowe wartości', 'newsletter-ai'); ?></strong></summary>
                                    <div style="margin-top: 10px;">
                                        <?php foreach ($sample_values as $value): ?>
                                            <div style="display: flex; justify-content: space-between; padding: 3px 0; font-size: 12px;">
                                                <code><?php echo esc_html($value->meta_value); ?></code>
                                                <small>(<?php echo esc_html($value->count); ?>)</small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Narzędzia zarządzania -->
                    <div class="nai-metabox">
                        <div class="nai-metabox-header primary">
                            <h3>🛠️ <?php _e('Narzędzia', 'newsletter-ai'); ?></h3>
                        </div>
                        <div class="nai-metabox-content">
                            <div class="nai-form-field">
                                <button type="button" id="nai-bulk-create-fields" class="nai-btn nai-btn-primary" style="width: 100%;">
                                    ➕ <?php _e('Utwórz pola zgody', 'newsletter-ai'); ?>
                                </button>
                                <p class="description">
                                    <?php _e('Utworzy pole zgody dla użytkowników, którzy go nie mają.', 'newsletter-ai'); ?>
                                </p>
                            </div>

                            <div class="nai-form-field">
    <!-- Istniejący link z debugowaniem -->
    <?php 
    $export_url = wp_nonce_url(
        admin_url('admin.php?page=newsletter-ai-users&action=export_users'), 
        'export_users'
    );
    ?>
    
    <!-- Alternatywny przycisk przez AJAX -->
    <button type="button" 
            id="nai-export-csv-ajax" 
            class="nai-btn nai-btn-secondary" 
            style="width: 100%; margin-top: 5px;">
        📥 <?php _e('Eksportuj CSV (AJAX)', 'newsletter-ai'); ?>
    </button>
    
    <p class="description">
        <?php _e('Pobierz plik CSV ze wszystkimi danymi.', 'newsletter-ai'); ?>
        <br><small>URL: <code><?php echo esc_html($export_url); ?></code></small>
    </p>
</div>

<script>
jQuery(document).ready(function($) {
    // Obsługa alternatywnego eksportu przez AJAX
    $('#nai-export-csv-ajax').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var originalText = $button.html();
        
        console.log('Newsletter AI: Rozpoczynanie eksportu CSV przez AJAX');
        
        $button.prop('disabled', true).html('📥 Generowanie...');
        
        $.ajax({
            url: newsletterAI.ajax_url,
            type: 'POST',
            data: {
                action: 'nai_export_csv',
                nonce: newsletterAI.nonce
            },
            timeout: 60000, // 60 sekund
            success: function(response) {
                console.log('Newsletter AI: Export response:', response);
                
                if (response.success && response.data.url) {
                    // Utwórz tymczasowy link do pobrania
                    var link = document.createElement('a');
                    link.href = response.data.url;
                    link.download = response.data.filename || 'newsletter_export.csv';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    alert('Plik CSV został wygenerowany i pobrany!');
                } else {
                    alert('Błąd: ' + (response.data || 'Nie udało się wygenerować pliku'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Newsletter AI: AJAX error:', xhr, status, error);
                alert('Błąd połączenia podczas eksportu: ' + error);
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>

                            <?php if ($statistics['users_without_field'] > 0): ?>
                                <div class="nai-notice nai-notice-warning">
                                    <span>⚠️</span>
                                    <p style="margin: 0;"><strong><?php _e('Uwaga!', 'newsletter-ai'); ?></strong><br>
                                        <?php printf(__('%d użytkowników bez pola zgody.', 'newsletter-ai'), $statistics['users_without_field']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Dostępne pola zgody -->
                    <?php if (!empty($consent_fields)): ?>
                        <div class="nai-metabox">
                            <div class="nai-metabox-header secondary">
                                <h3>🔍 <?php _e('Inne pola zgody', 'newsletter-ai'); ?></h3>
                            </div>
                            <div class="nai-metabox-content">
                                <div style="max-height: 200px; overflow-y: auto;">
                                    <?php foreach ($consent_fields as $field): ?>
                                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 5px 0; border-bottom: 1px solid #f0f0f1; font-size: 12px;">
                                            <div>
                                                <code style="font-size: 11px;"><?php echo esc_html($field->meta_key); ?></code>
                                                <br><small>(<?php echo esc_html($field->count); ?> <?php _e('użytkowników', 'newsletter-ai'); ?>)</small>
                                            </div>
                                            <a href="<?php echo admin_url('options-general.php?page=newsletter-ai&field=' . urlencode($field->meta_key)); ?>" 
                                               class="nai-btn nai-btn-small nai-btn-secondary">
                                                   <?php _e('Użyj', 'newsletter-ai'); ?>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Instrukcje -->
            <div class="nai-metabox">
                <div class="nai-metabox-header warning">
                    <h3>📖 <?php _e('Jak zarządzać zgodami użytkowników', 'newsletter-ai'); ?></h3>
                </div>
                <div class="nai-metabox-content">
                    <ol>
                        <li><?php _e('Upewnij się, że wszyscy użytkownicy mają pole zgody (użyj przycisku "Utwórz pola zgody")', 'newsletter-ai'); ?></li>
                        <li><?php _e('Sprawdź statusy zgód w tabeli powyżej', 'newsletter-ai'); ?></li>
                        <li><?php _e('Aby zmienić zgodę użytkownika, kliknij przycisk "Profil" i przejdź do profilu użytkownika', 'newsletter-ai'); ?></li>
                        <li><?php _e('Po zmianach wygeneruj ponownie XML w zakładce "Ustawienia XML"', 'newsletter-ai'); ?></li>
                        <li><?php _e('Eksportuj dane do CSV aby mieć kopię zapasową', 'newsletter-ai'); ?></li>
                    </ol>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Strona frontend
     */
    public function frontend_page() {
        // Pobierz aktualne ustawienia
        $consent_text = get_option('nai_consent_text', __('Wyrażam zgodę na otrzymywanie newslettera', 'newsletter-ai'));
        $consent_required = get_option('nai_consent_required', false);
        $show_on_registration = get_option('nai_show_on_registration', true);
        $show_on_checkout = get_option('nai_show_on_checkout', true);
        $show_in_myaccount = get_option('nai_show_in_myaccount', true);
        $load_frontend_styles = get_option('nai_load_frontend_styles', true);
        $primary_color = get_option('nai_frontend_primary_color', '#0073aa');
        $border_radius = get_option('nai_frontend_border_radius', '4px');

        // Pobierz statystyki
        $frontend_stats = get_option('nai_frontend_stats', array());
        ?>
        <div class="wrap nai-admin-page">
            <h1><?php _e('Newsletter AI - Ustawienia Frontend', 'newsletter-ai'); ?></h1>

        <?php settings_errors('nai_settings'); ?>

            <!-- Statystyki frontend -->
            <?php if (!empty($frontend_stats)): ?>
                <div class="nai-metabox">
                    <div class="nai-metabox-header secondary">
                        <h3>📊 <?php _e('Statystyki zgód z frontend', 'newsletter-ai'); ?></h3>
                    </div>
                    <div class="nai-metabox-content">
                        <div class="nai-stats-grid">
                            <div class="nai-stat-card">
                                <div class="nai-stat-number"><?php echo esc_html($frontend_stats['registrations_with_consent'] ?? 0); ?></div>
                                <div class="nai-stat-label"><?php _e('Rejestracje z zgodą', 'newsletter-ai'); ?></div>
                            </div>
                            <div class="nai-stat-card">
                                <div class="nai-stat-number"><?php echo esc_html($frontend_stats['checkout_with_consent'] ?? 0); ?></div>
                                <div class="nai-stat-label"><?php _e('Checkout z zgodą', 'newsletter-ai'); ?></div>
                            </div>
                            <div class="nai-stat-card">
                                <div class="nai-stat-number"><?php echo esc_html($frontend_stats['myaccount_changes'] ?? 0); ?></div>
                                <div class="nai-stat-label"><?php _e('Zmiany w MyAccount', 'newsletter-ai'); ?></div>
                            </div>
                        </div>
                        <p><strong><?php _e('Ostatnia aktualizacja:', 'newsletter-ai'); ?></strong> <?php echo esc_html($frontend_stats['last_update'] ?? 'nigdy'); ?></p>
                    </div>
                </div>
        <?php endif; ?>

            <div class="nai-nav-tabs">
                <ul>
                    <li><a href="#tab-content" class="nai-nav-tab active" data-tab="content"><?php _e('Treść i wymagania', 'newsletter-ai'); ?></a></li>
                    <li><a href="#tab-display" class="nai-nav-tab" data-tab="display"><?php _e('Miejsca wyświetlania', 'newsletter-ai'); ?></a></li>
                    <li><a href="#tab-style" class="nai-nav-tab" data-tab="style"><?php _e('Wygląd i style', 'newsletter-ai'); ?></a></li>
                    <li><a href="#tab-preview" class="nai-nav-tab" data-tab="preview"><?php _e('Podgląd i testowanie', 'newsletter-ai'); ?></a></li>
                </ul>
            </div>

            <form method="post" action="">
        <?php wp_nonce_field('nai_frontend_settings', 'nai_frontend_nonce'); ?>

                <!-- Tab: Treść i wymagania -->
                <div id="tab-content" class="nai-tab-content">
                    <div class="nai-metabox">
                        <div class="nai-metabox-header">
                            <h3>📝 <?php _e('Treść zgody', 'newsletter-ai'); ?></h3>
                        </div>
                        <div class="nai-metabox-content">
                            <div class="nai-form-field">
                                <label for="nai_consent_text"><?php _e('Tekst zgody na newsletter', 'newsletter-ai'); ?></label>
                                <input type="text" id="nai_consent_text" name="nai_consent_text" 
                                       value="<?php echo esc_attr($consent_text); ?>" />
                                <p class="description"><?php _e('Tekst wyświetlany przy checkbox\'ie zgody na newsletter', 'newsletter-ai'); ?></p>
                            </div>

                            <div class="nai-form-field">
                                <label>
                                    <input type="checkbox" name="nai_consent_required" value="1" <?php checked($consent_required, true); ?> />
        <?php _e('Wymagana zgoda', 'newsletter-ai'); ?>
                                </label>
                                <p class="description"><?php _e('Jeśli zaznaczone, użytkownicy muszą wyrazić zgodę aby się zarejestrować/złożyć zamówienie', 'newsletter-ai'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Miejsca wyświetlania -->
                <div id="tab-display" class="nai-tab-content" style="display: none;">
                    <div class="nai-metabox">
                        <div class="nai-metabox-header">
                            <h3>📍 <?php _e('Miejsca wyświetlania', 'newsletter-ai'); ?></h3>
                        </div>
                        <div class="nai-metabox-content">
                            <div class="nai-form-field">
                                <label>
                                    <input type="checkbox" name="nai_show_on_registration" value="1" <?php checked($show_on_registration, true); ?> />
        <?php _e('Strona rejestracji', 'newsletter-ai'); ?>
                                </label>
                                <p class="description"><?php _e('Pokaż checkbox zgody na stronie rejestracji WordPress i WooCommerce', 'newsletter-ai'); ?></p>
                            </div>

                            <div class="nai-form-field">
                                <label>
                                    <input type="checkbox" name="nai_show_on_checkout" value="1" <?php checked($show_on_checkout, true); ?> />
        <?php _e('Checkout (kasa)', 'newsletter-ai'); ?>
                                </label>
                                <p class="description"><?php _e('Pokaż checkbox zgody podczas checkout (tylko jeśli użytkownik nie ma zgody)', 'newsletter-ai'); ?></p>
                            </div>

                            <div class="nai-form-field">
                                <label>
                                    <input type="checkbox" name="nai_show_in_myaccount" value="1" <?php checked($show_in_myaccount, true); ?> />
        <?php _e('MyAccount (moje konto)', 'newsletter-ai'); ?>
                                </label>
                                <p class="description"><?php _e('Pokaż przełącznik zgody w profilu klienta WooCommerce', 'newsletter-ai'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Wygląd i style -->
                <div id="tab-style" class="nai-tab-content" style="display: none;">
                    <div class="nai-metabox">
                        <div class="nai-metabox-header">
                            <h3>🎨 <?php _e('Wygląd i style', 'newsletter-ai'); ?></h3>
                        </div>
                        <div class="nai-metabox-content">
                            <div class="nai-form-field">
                                <label>
                                    <input type="checkbox" name="nai_load_frontend_styles" value="1" <?php checked($load_frontend_styles, true); ?> />
        <?php _e('Ładuj style CSS', 'newsletter-ai'); ?>
                                </label>
                                <p class="description"><?php _e('Automatycznie ładuj style CSS dla pól zgody', 'newsletter-ai'); ?></p>
                            </div>

                            <div class="nai-grid nai-grid-2">
                                <div class="nai-form-field">
                                    <label for="nai_frontend_primary_color"><?php _e('Kolor główny', 'newsletter-ai'); ?></label>
                                    <input type="color" id="nai_frontend_primary_color" name="nai_frontend_primary_color" 
                                           value="<?php echo esc_attr($primary_color); ?>" />
                                    <p class="description"><?php _e('Kolor używany dla ramek, nagłówków i akcentów', 'newsletter-ai'); ?></p>
                                </div>

                                <div class="nai-form-field">
                                    <label for="nai_frontend_border_radius"><?php _e('Zaokrąglenie rogów', 'newsletter-ai'); ?></label>
                                    <input type="text" id="nai_frontend_border_radius" name="nai_frontend_border_radius" 
                                           value="<?php echo esc_attr($border_radius); ?>" />
                                    <p class="description"><?php _e('Zaokrąglenie rogów dla pól zgody (np. 4px, 8px, 0px)', 'newsletter-ai'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Podgląd i testowanie -->
                <div id="tab-preview" class="nai-tab-content" style="display: none;">
                    <div class="nai-grid nai-grid-2">
                        <div>
                            <!-- Podgląd na żywo -->
                            <div class="nai-metabox">
                                <div class="nai-metabox-header success">
                                    <h3>👁️ <?php _e('Podgląd na żywo', 'newsletter-ai'); ?></h3>
                                </div>
                                <div class="nai-metabox-content">
                                    <div id="nai-live-preview">
                                        <div class="nai-preview-section">
                                            <h5><?php _e('Rejestracja:', 'newsletter-ai'); ?></h5>
                                            <div class="nai-consent-wrapper">
                                                <label>
                                                    <input type="checkbox" class="nai-consent-checkbox" id="preview-registration" />
                                                    <span id="preview-text-registration"><?php echo esc_html($consent_text); ?></span>
                                                    <span id="preview-required-registration" class="required" style="<?php echo $consent_required ? '' : 'display:none'; ?>">*</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="nai-preview-section">
                                            <h5><?php _e('Checkout:', 'newsletter-ai'); ?></h5>
                                            <div class="nai-checkout-consent">
                                                <h3><?php _e('Newsletter', 'newsletter-ai'); ?></h3>
                                                <label>
                                                    <input type="checkbox" class="nai-consent-checkbox" id="preview-checkout" />
                                                    <span id="preview-text-checkout"><?php echo esc_html($consent_text); ?></span>
                                                    <span id="preview-required-checkout" class="required" style="<?php echo $consent_required ? '' : 'display:none'; ?>">*</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="nai-preview-section">
                                            <h5><?php _e('MyAccount:', 'newsletter-ai'); ?></h5>
                                            <fieldset class="nai-myaccount-consent">
                                                <legend><?php _e('Preferencje newslettera', 'newsletter-ai'); ?></legend>
                                                <label>
                                                    <input type="checkbox" class="nai-consent-checkbox" id="preview-myaccount" />
                                                    <span id="preview-text-myaccount"><?php echo esc_html($consent_text); ?></span>
                                                </label>
                                                <p class="description"><?php _e('Możesz w każdej chwili wycofać zgodę w ustawieniach konta.', 'newsletter-ai'); ?></p>
                                            </fieldset>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <!-- Linki testowe -->
                            <div class="nai-metabox">
                                <div class="nai-metabox-header">
                                    <h3>🔗 <?php _e('Linki testowe', 'newsletter-ai'); ?></h3>
                                </div>
                                <div class="nai-metabox-content">
                                    <div class="nai-form-field">
                                        <a href="<?php echo wp_registration_url(); ?>" target="_blank" class="nai-btn nai-btn-secondary">
                                            🔗 <?php _e('Otwórz stronę rejestracji', 'newsletter-ai'); ?>
                                        </a>
                                    </div>

        <?php if (class_exists('WooCommerce')): ?>
                                        <div class="nai-form-field">
                                            <a href="<?php echo wc_get_page_permalink('myaccount'); ?>" target="_blank" class="nai-btn nai-btn-secondary">
                                                👤 <?php _e('Otwórz MyAccount', 'newsletter-ai'); ?>
                                            </a>
                                        </div>

                                        <div class="nai-form-field">
                                            <a href="<?php echo wc_get_page_permalink('checkout'); ?>" target="_blank" class="nai-btn nai-btn-secondary">
                                                🛒 <?php _e('Otwórz Checkout', 'newsletter-ai'); ?>
                                            </a>
                                        </div>
        <?php endif; ?>

                                    <p class="description"><?php _e('Sprawdź jak wyglądają pola zgody na poszczególnych stronach', 'newsletter-ai'); ?></p>
                                </div>
                            </div>

                            <!-- Własne style CSS -->
                            <div class="nai-metabox">
                                <div class="nai-metabox-header warning">
                                    <h3>💻 <?php _e('Własne style CSS', 'newsletter-ai'); ?></h3>
                                </div>
                                <div class="nai-metabox-content">
                                    <p><?php _e('Jeśli chcesz używać własnych stylów, wyłącz automatyczne ładowanie CSS i dodaj te selektory do swojego motywu:', 'newsletter-ai'); ?></p>
                                    <pre style="background: #f1f1f1; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px;"><code>.nai-consent-wrapper { /* kontener checkbox'a */ }
                .nai-consent-checkbox { /* sam checkbox */ }
                .nai-checkout-consent { /* sekcja w checkout */ }
                .nai-myaccount-consent { /* sekcja w MyAccount */ }</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="nai-text-center" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #c3c4c7;">
                    <button type="submit" name="nai_save_frontend_settings" class="nai-btn nai-btn-primary nai-btn-large">
                        💾 <?php _e('Zapisz ustawienia frontend', 'newsletter-ai'); ?>
                    </button>
                </div>
            </form>

            <!-- Instrukcje -->
            <div class="nai-metabox">
                <div class="nai-metabox-header warning">
                    <h3>📖 <?php _e('Jak działa system zgód frontend', 'newsletter-ai'); ?></h3>
                </div>
                <div class="nai-metabox-content">
                    <ol>
                        <li><?php _e('Checkbox zgody pojawia się w miejscach które zaznaczysz w zakładce "Miejsca wyświetlania"', 'newsletter-ai'); ?></li>
                        <li><?php _e('Użytkownicy mogą wyrazić lub wycofać zgodę w różnych momentach', 'newsletter-ai'); ?></li>
                        <li><?php _e('Wszystkie zmiany są zapisywane w historii dla każdego użytkownika', 'newsletter-ai'); ?></li>
                        <li><?php _e('Checkout pokazuje pole tylko użytkownikom bez zgody', 'newsletter-ai'); ?></li>
                        <li><?php _e('MyAccount pozwala użytkownikom zarządzać zgodą samodzielnie', 'newsletter-ai'); ?></li>
                    </ol>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                // Obsługa tabów
                $('.nai-nav-tab').on('click', function (e) {
                    e.preventDefault();

                    var tab = $(this).data('tab');

                    // Usuń aktywne klasy
                    $('.nai-nav-tab').removeClass('active');
                    $('.nai-tab-content').hide();

                    // Dodaj aktywne klasy
                    $(this).addClass('active');
                    $('#tab-' + tab).show();
                });

                // Live preview aktualizacji tekstu
                $('#nai_consent_text').on('input', function () {
                    var newText = $(this).val();
                    $('#preview-text-registration, #preview-text-checkout, #preview-text-myaccount').text(newText);
                });

                // Live preview wymagalności
                $('input[name="nai_consent_required"]').on('change', function () {
                    var isRequired = $(this).is(':checked');
                    $('#preview-required-registration, #preview-required-checkout').toggle(isRequired);
                });

                // Live preview kolorów
                $('#nai_frontend_primary_color').on('change', function () {
                    var color = $(this).val();
                    updatePreviewStyles(color, $('#nai_frontend_border_radius').val());
                });

                $('#nai_frontend_border_radius').on('input', function () {
                    var radius = $(this).val();
                    updatePreviewStyles($('#nai_frontend_primary_color').val(), radius);
                });

                function updatePreviewStyles(color, radius) {
                    var css = `
                    .nai-consent-wrapper {
                        border-color: ${color}20;
                        border-radius: ${radius};
                    }
                    .nai-checkout-consent {
                        border-color: ${color};
                        border-radius: ${radius};
                    }
                    .nai-checkout-consent h3,
                    .nai-myaccount-consent legend {
                        color: ${color};
                    }
                    .nai-myaccount-consent {
                        border-radius: ${radius};
                    }
                    .nai-consent-checkbox:checked {
                        accent-color: ${color};
                    }
                `;

                    // Usuń poprzedni preview style
                    $('#nai-preview-styles').remove();

                    // Dodaj nowy
                    $('<style id="nai-preview-styles">' + css + '</style>').appendTo('head');
                }

                // Inicjalna aktualizacja stylów
                updatePreviewStyles($('#nai_frontend_primary_color').val(), $('#nai_frontend_border_radius').val());
            });
        </script>

        <style>
            /* Style dla podglądu */
            .nai-preview-section {
                margin: 15px 0;
                padding: 15px;
                border: 1px dashed #ccc;
                border-radius: 4px;
                background: #fafafa;
            }

            .nai-preview-section h5 {
                margin: 0 0 10px 0;
                font-weight: 600;
                color: #555;
            }

            /* Default preview styles */
            .nai-consent-wrapper {
                margin: 15px 0;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #f9f9f9;
            }

            .nai-consent-checkbox {
                margin-right: 8px;
                transform: scale(1.1);
            }

            .nai-checkout-consent {
                margin: 20px 0;
                padding: 15px;
                border: 2px solid #0073aa;
                border-radius: 4px;
                background: #f8f9fa;
            }

            .nai-checkout-consent h3 {
                margin-top: 0;
                color: #0073aa;
            }

            .nai-myaccount-consent {
                margin: 20px 0;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #fff;
            }

            .nai-myaccount-consent legend {
                font-weight: 600;
                color: #0073aa;
                padding: 0 10px;
            }

            .nai-myaccount-consent .description {
                font-size: 0.9em;
                color: #666;
                margin-top: 8px;
            }

            .required {
                color: #d63638;
                font-weight: bold;
            }
        </style>
        <?php
    }

}
