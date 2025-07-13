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
                __('XML - Klienci', 'newsletter-ai'), // ZMIENIONE Z: 'Ustawienia XML'
                __('XML - Klienci', 'newsletter-ai'), // ZMIENIONE Z: 'Ustawienia XML'
                'manage_options',
                'newsletter-ai',
                array($this, 'main_page')
        );

        add_submenu_page(
                'newsletter-ai',
                __('XML - Zamówienia', 'newsletter-ai'),
                __('XML - Zamówienia', 'newsletter-ai'),
                'manage_options',
                'newsletter-ai-orders',
                array($this, 'orders_page')
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

        add_submenu_page(
                'newsletter-ai',
                __('Goście - Newsletter', 'newsletter-ai'),
                __('Goście - Newsletter', 'newsletter-ai'),
                'manage_options',
                'newsletter-ai-guests',
                array($this, 'guests_page')
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

        // Zakładka test klientów
        add_submenu_page(
                'newsletter-ai',
                __('Test - Klienci', 'newsletter-ai'),
                __('Test - Klienci', 'newsletter-ai'),
                'manage_options',
                'newsletter-ai-test',
                array($this, 'test_page')
        );

        // Zakładka cron
        add_submenu_page(
                'newsletter-ai',
                __('Ustawienia Cron', 'newsletter-ai'),
                __('Ustawienia Cron', 'newsletter-ai'),
                'manage_options',
                'newsletter-ai-cron',
                array($this, 'cron_page')
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
            <h1><?php _e('Newsletter AI - XML - Klienci', 'newsletter-ai'); ?></h1>

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
                                jQuery(document).ready(function ($) {
                                    // Obsługa alternatywnego eksportu przez AJAX
                                    $('#nai-export-csv-ajax').on('click', function (e) {
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
                                            success: function (response) {
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
                                            error: function (xhr, status, error) {
                                                console.error('Newsletter AI: AJAX error:', xhr, status, error);
                                                alert('Błąd połączenia podczas eksportu: ' + error);
                                            },
                                            complete: function () {
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

    /**
     * Strona ustawień cron
     */
    public function cron_page() {
        $cron_manager = new Newsletter_AI_Cron_Manager();

        // Pobierz aktualne ustawienia
        $cron_enabled = get_option('nai_cron_enabled', true);
        $cron_time = get_option('nai_cron_time', '01:10');
        $generate_customers = get_option('nai_cron_generate_customers', true);
        $generate_orders = get_option('nai_cron_generate_orders', false);
        $generate_products = get_option('nai_cron_generate_products', false);

        // Pobierz informacje o stanie cron
        $is_active = $cron_manager->is_cron_active();
        $next_run = $cron_manager->get_next_scheduled_run();
        $last_run = $cron_manager->get_last_run_results();
        ?>
        <div class="wrap nai-admin-page">
            <h1><?php _e('Newsletter AI - Ustawienia Cron', 'newsletter-ai'); ?></h1>

            <?php settings_errors('nai_settings'); ?>

            <div class="nai-grid nai-grid-2">
                <!-- Kolumna lewa - status i ostatnie uruchomienie -->
                <div>
                    <!-- Status cron -->
                    <div class="nai-metabox">
                        <div class="nai-metabox-header <?php echo $is_active ? 'success' : 'warning'; ?>">
                            <h3>⏰ <?php _e('Status automatycznego generowania', 'newsletter-ai'); ?></h3>
                        </div>
                        <div class="nai-metabox-content">
                            <?php if ($is_active): ?>
                                <div class="nai-notice nai-notice-success">
                                    <span>✅</span>
                                    <div>
                                        <p><strong><?php _e('Cron jest aktywny', 'newsletter-ai'); ?></strong></p>
                                        <p><?php _e('Następne uruchomienie:', 'newsletter-ai'); ?> <code><?php echo esc_html($next_run); ?></code></p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="nai-notice nai-notice-warning">
                                    <span>⚠️</span>
                                    <div>
                                        <p><strong><?php _e('Cron jest nieaktywny', 'newsletter-ai'); ?></strong></p>
                                        <p><?php _e('Automatyczne generowanie plików XML jest wyłączone.', 'newsletter-ai'); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="nai-text-center" style="margin-top: 20px;">
                                <button type="button" id="nai-run-cron-manually" class="nai-btn nai-btn-primary">
                                    ▶️ <?php _e('Uruchom teraz ręcznie', 'newsletter-ai'); ?>
                                </button>
                                <div id="nai-cron-execution-status" style="margin-top: 10px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Ostatnie uruchomienie -->
                    <?php if (!empty($last_run)): ?>
                        <div class="nai-metabox">
                            <div class="nai-metabox-header secondary">
                                <h3>📊 <?php _e('Ostatnie uruchomienie', 'newsletter-ai'); ?></h3>
                            </div>
                            <div class="nai-metabox-content">
                                <p><strong><?php _e('Data:', 'newsletter-ai'); ?></strong> <?php echo esc_html($last_run['timestamp'] ?? 'brak'); ?></p>
                                <p><strong><?php _e('Czas wykonania:', 'newsletter-ai'); ?></strong> <?php echo esc_html($last_run['execution_time'] ?? 'brak'); ?>s</p>

                                <?php if (isset($last_run['results'])): ?>
                                    <div style="margin-top: 15px;">
                                        <strong><?php _e('Wyniki:', 'newsletter-ai'); ?></strong>
                                        <div style="margin-top: 8px;">
                                            <?php foreach ($last_run['results'] as $task => $result): ?>
                                                <?php if (is_array($result)): ?>
                                                    <div class="nai-result-item" style="padding: 8px; margin: 5px 0; background: #f6f7f7; border-radius: 4px;">
                                                        <strong><?php echo esc_html(ucfirst($task)); ?>:</strong>
                                                        <span class="nai-status-badge nai-status-<?php echo esc_attr($result['status'] ?? 'unknown'); ?>">
                                                            <?php echo esc_html($result['status'] ?? 'unknown'); ?>
                                                        </span>
                                                        <br>
                                                        <small><?php echo esc_html($result['message'] ?? ''); ?></small>
                                                        <?php if (isset($result['file'])): ?>
                                                            <br><code><?php echo esc_html($result['file']); ?></code>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="nai-result-item" style="padding: 8px; margin: 5px 0; background: #f8d7da; border-radius: 4px;">
                                                        <strong><?php echo esc_html($task); ?>:</strong> <?php echo esc_html($result); ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Kolumna prawa - ustawienia -->
                <div>
                    <!-- Formularz ustawień cron -->
                    <div class="nai-metabox">
                        <div class="nai-metabox-header">
                            <h3>⚙️ <?php _e('Konfiguracja automatycznego generowania', 'newsletter-ai'); ?></h3>
                        </div>
                        <div class="nai-metabox-content">
                            <form method="post" action="">
                                <?php wp_nonce_field('nai_cron_settings', 'nai_cron_nonce'); ?>

                                <div class="nai-form-field">
                                    <label>
                                        <input type="checkbox" name="nai_cron_enabled" value="1" 
                                               <?php checked($cron_enabled, true); ?> />
                                               <?php _e('Włącz automatyczne generowanie', 'newsletter-ai'); ?>
                                    </label>
                                    <p class="description"><?php _e('Automatycznie generuj pliki XML codziennie o określonej godzinie', 'newsletter-ai'); ?></p>
                                </div>

                                <div class="nai-form-field">
                                    <label for="nai_cron_time"><?php _e('Godzina uruchomienia', 'newsletter-ai'); ?></label>
                                    <input type="time" id="nai_cron_time" name="nai_cron_time" 
                                           value="<?php echo esc_attr($cron_time); ?>" />
                                    <p class="description">
                                        <?php _e('Godzina codziennego uruchomienia (czas lokalny serwera)', 'newsletter-ai'); ?>
                                        <br><small><?php printf(__('Strefa czasowa serwera: %s'), get_option('timezone_string') ?: 'UTC' . get_option('gmt_offset')); ?></small>
                                    </p>
                                </div>

                                <fieldset style="border: 1px solid #c3c4c7; padding: 15px; border-radius: 4px; margin: 20px 0;">
                                    <legend style="font-weight: 600; padding: 0 8px;"><?php _e('Pliki do generowania', 'newsletter-ai'); ?></legend>

                                    <div class="nai-form-field">
                                        <label>
                                            <input type="checkbox" name="nai_cron_generate_customers" value="1" 
                                                   <?php checked($generate_customers, true); ?> />
                                                   <?php _e('sambaAiCustomers.xml', 'newsletter-ai'); ?>
                                        </label>
                                        <p class="description"><?php _e('Plik z danymi klientów i zgodami na newsletter', 'newsletter-ai'); ?></p>
                                    </div>

                                    <div class="nai-form-field">
                                        <label>
                                            <input type="checkbox" name="nai_cron_generate_orders" value="1" 
                                                   <?php checked($generate_orders, true); ?> />
                                                   <?php _e('sambaAiOrders.xml', 'newsletter-ai'); ?> 
                                            <span style="color: #646970; font-style: italic;">(<?php _e('przyszłość', 'newsletter-ai'); ?>)</span>
                                        </label>
                                        <p class="description"><?php _e('Plik z danymi zamówień (implementacja w przyszłości)', 'newsletter-ai'); ?></p>
                                    </div>

                                    <div class="nai-form-field">
                                        <label>
                                            <input type="checkbox" name="nai_cron_generate_products" value="1" 
                                                   <?php checked($generate_products, true); ?> />
                                                   <?php _e('sambaAiProducts.xml', 'newsletter-ai'); ?>
                                            <span style="color: #646970; font-style: italic;">(<?php _e('przyszłość', 'newsletter-ai'); ?>)</span>
                                        </label>
                                        <p class="description"><?php _e('Plik z danymi produktów (implementacja w przyszłości)', 'newsletter-ai'); ?></p>
                                    </div>
                                </fieldset>

                                <button type="submit" name="nai_save_cron_settings" class="nai-btn nai-btn-primary">
                                    💾 <?php _e('Zapisz ustawienia cron', 'newsletter-ai'); ?>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Informacje o cron -->
                    <div class="nai-metabox">
                        <div class="nai-metabox-header warning">
                            <h3>ℹ️ <?php _e('Informacje o systemie cron', 'newsletter-ai'); ?></h3>
                        </div>
                        <div class="nai-metabox-content">
                            <p><?php _e('System cron WordPress działa tylko wtedy, gdy ktoś odwiedza Twoją stronę.', 'newsletter-ai'); ?></p>
                            <p><?php _e('Dla bardziej niezawodnego działania rozważ skonfigurowanie prawdziwego cron na serwerze.', 'newsletter-ai'); ?></p>

                            <details style="margin-top: 15px;">
                                <summary><strong><?php _e('Informacje techniczne', 'newsletter-ai'); ?></strong></summary>
                                <div style="margin-top: 10px; font-family: monospace; font-size: 12px;">
                                    <p><strong>Hook cron:</strong> <code>newsletter_ai_cron_hook</code></p>
                                    <p><strong>Lokalizacja plików:</strong> <code><?php echo esc_html(WP_CONTENT_DIR . '/sambaAiExport/'); ?></code></p>
                                    <p><strong>WordPress cron status:</strong> 
                                        <?php if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON): ?>
                                            <span style="color: #d63638;">Wyłączony (DISABLE_WP_CRON = true)</span>
                                        <?php else: ?>
                                            <span style="color: #00a32a;">Włączony</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Instrukcje -->
            <div class="nai-metabox">
                <div class="nai-metabox-header warning">
                    <h3>📖 <?php _e('Jak działa automatyczne generowanie', 'newsletter-ai'); ?></h3>
                </div>
                <div class="nai-metabox-content">
                    <ol>
                        <li><?php _e('Włącz automatyczne generowanie i ustaw godzinę uruchomienia', 'newsletter-ai'); ?></li>
                        <li><?php _e('Wybierz które pliki XML mają być generowane automatycznie', 'newsletter-ai'); ?></li>
                        <li><?php _e('System codziennie o wybranej godzinie wygeneruje i nadpisze pliki XML', 'newsletter-ai'); ?></li>
                        <li><?php _e('Sprawdzaj status w tej zakładce lub używaj "Uruchom teraz ręcznie" do testów', 'newsletter-ai'); ?></li>
                        <li><?php _e('Wszystkie operacje są logowane (jeśli włączony jest tryb debug)', 'newsletter-ai'); ?></li>
                    </ol>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                // Obsługa manualnego uruchomienia cron
                $('#nai-run-cron-manually').on('click', function (e) {
                    e.preventDefault();

                    var $button = $(this);
                    var $status = $('#nai-cron-execution-status');
                    var originalText = $button.html();

                    $button.prop('disabled', true).html('⏳ <?php _e('Wykonywanie...', 'newsletter-ai'); ?>');
                    $status.html('<div class="nai-notice nai-notice-info"><div class="nai-spinner"></div> <?php _e('Wykonywanie zadań cron...', 'newsletter-ai'); ?></div>');

                    $.post(newsletterAI.ajax_url, {
                        action: 'nai_run_cron_manually',
                        nonce: newsletterAI.nonce
                    })
                            .done(function (response) {
                                if (response.success) {
                                    $status.html('<div class="nai-notice nai-notice-success">✅ ' + response.data.message + '</div>');

                                    // Odśwież stronę po 3 sekundach żeby pokazać nowe wyniki
                                    setTimeout(function () {
                                        location.reload();
                                    }, 3000);
                                } else {
                                    $status.html('<div class="nai-notice nai-notice-error">❌ ' + response.data + '</div>');
                                }
                            })
                            .fail(function (xhr, status, error) {
                                $status.html('<div class="nai-notice nai-notice-error">❌ <?php _e('Błąd połączenia:', 'newsletter-ai'); ?> ' + error + '</div>');
                            })
                            .always(function () {
                                $button.prop('disabled', false).html(originalText);
                            });
                });
            });
        </script>

        <style>
            .nai-status-badge {
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
            }

            .nai-status-success {
                background: #d1ecf1;
                color: #0c5460;
            }

            .nai-status-placeholder {
                background: #fff3cd;
                color: #856404;
            }

            .nai-status-error {
                background: #f8d7da;
                color: #721c24;
            }

            .nai-result-item {
                font-size: 13px;
                line-height: 1.4;
            }
        </style>
        <?php
    }

    /**
     * Strona testowania klientów
     */
    public function test_page() {
        $validator = new Newsletter_AI_Customer_Validator();
        $summary = $validator->get_validation_summary();
        ?>
        <div class="wrap nai-admin-page nai-test-page">
            <h1><?php _e('Newsletter AI - Test - Klienci', 'newsletter-ai'); ?></h1>

            <!-- Statystyki walidacji -->
            <div class="nai-metabox">
                <div class="nai-metabox-header primary">
                    <h3>🔍 <?php _e('Statystyki walidacji danych', 'newsletter-ai'); ?></h3>
                </div>
                <div class="nai-metabox-content">
                    <div class="nai-stats-grid">
                        <div class="nai-stat-card">
                            <div class="nai-stat-number"><?php echo esc_html($summary['total']); ?></div>
                            <div class="nai-stat-label"><?php _e('Łącznie klientów', 'newsletter-ai'); ?></div>
                        </div>
                        <div class="nai-stat-card nai-stat-valid">
                            <div class="nai-stat-number"><?php echo esc_html($summary['valid']); ?></div>
                            <div class="nai-stat-label"><?php _e('Poprawne dane', 'newsletter-ai'); ?></div>
                        </div>
                        <div class="nai-stat-card nai-stat-warning">
                            <div class="nai-stat-number"><?php echo esc_html($summary['warnings']); ?></div>
                            <div class="nai-stat-label"><?php _e('Ostrzeżenia', 'newsletter-ai'); ?></div>
                        </div>
                        <div class="nai-stat-card nai-stat-error">
                            <div class="nai-stat-number"><?php echo esc_html($summary['errors']); ?></div>
                            <div class="nai-stat-label"><?php _e('Błędy', 'newsletter-ai'); ?></div>
                        </div>
                        <div class="nai-stat-card nai-stat-ignored">
                            <div class="nai-stat-number"><?php echo esc_html($summary['ignored']); ?></div>
                            <div class="nai-stat-label"><?php _e('Ignorowane', 'newsletter-ai'); ?></div>
                        </div>
                    </div>

                    <div style="margin-top: 20px;">
                        <p><strong><?php _e('Problemy podobne do Samba.AI:', 'newsletter-ai'); ?></strong></p>
                        <ul style="margin-left: 20px;">
                            <li><?php _e('Missing <FIRST_NAME> - Brak imienia', 'newsletter-ai'); ?></li>
                            <li><?php _e('Missing <LAST_NAME> - Brak nazwiska', 'newsletter-ai'); ?></li>
                            <li><?php _e('Missing <PHONE> - Brak numeru telefonu', 'newsletter-ai'); ?></li>
                            <li><?php _e('Missing <ZIP_CODE> - Brak kodu pocztowego', 'newsletter-ai'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="nai-grid nai-grid-4-1">
                <!-- Kolumna główna - lista klientów -->
                <div class="nai-test-main">
                    <!-- Filtry -->
                    <div class="nai-test-filters">
                        <div class="nai-filter-buttons">
                            <button type="button" class="nai-filter-btn active" data-filter="all">
                                🔍 <?php _e('Wszyscy', 'newsletter-ai'); ?> (<span id="count-all"><?php echo $summary['total']; ?></span>)
                            </button>
                            <button type="button" class="nai-filter-btn" data-filter="errors">
                                ❌ <?php _e('Błędy', 'newsletter-ai'); ?> (<span id="count-errors"><?php echo $summary['errors']; ?></span>)
                            </button>
                            <button type="button" class="nai-filter-btn" data-filter="warnings">
                                ⚠️ <?php _e('Ostrzeżenia', 'newsletter-ai'); ?> (<span id="count-warnings"><?php echo $summary['warnings']; ?></span>)
                            </button>
                            <button type="button" class="nai-filter-btn" data-filter="valid">
                                ✅ <?php _e('Poprawne', 'newsletter-ai'); ?> (<span id="count-valid"><?php echo $summary['valid']; ?></span>)
                            </button>
                            <button type="button" class="nai-filter-btn" data-filter="ignored">
                                🚫 <?php _e('Ignorowane', 'newsletter-ai'); ?> (<span id="count-ignored"><?php echo $summary['ignored']; ?></span>)
                            </button>
                        </div>
                    </div>

                    <!-- Lista klientów -->
                    <div class="nai-metabox">
                        <div class="nai-metabox-header primary">
                            <h3>👥 <?php _e('Lista klientów', 'newsletter-ai'); ?></h3>
                        </div>
                        <div class="nai-metabox-content nai-p-0">
                            <div id="nai-customers-validation-container">
                                <div class="nai-loading">
                                    <div class="nai-spinner"></div>
                                    <?php _e('Ładowanie i walidacja klientów...', 'newsletter-ai'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar - narzędzia -->
                <div class="nai-test-sidebar">
                    <!-- Akcje masowe -->
                    <div class="nai-metabox">
                        <div class="nai-metabox-header">
                            <h3>🛠️ <?php _e('Akcje masowe', 'newsletter-ai'); ?></h3>
                        </div>
                        <div class="nai-metabox-content">
                            <div class="nai-form-field">
                                <button type="button" id="nai-refresh-validation" class="nai-btn nai-btn-primary" style="width: 100%;">
                                    🔄 <?php _e('Odśwież walidację', 'newsletter-ai'); ?>
                                </button>
                                <p class="description"><?php _e('Ponownie sprawdź wszystkich klientów', 'newsletter-ai'); ?></p>
                            </div>

                        </div>
                    </div>

                    <!-- Reguły walidacji -->
                    <div class="nai-metabox">
                        <div class="nai-metabox-header secondary">
                            <h3>📋 <?php _e('Reguły walidacji', 'newsletter-ai'); ?></h3>
                        </div>
                        <div class="nai-metabox-content">
                            <div class="nai-validation-rules">
                                <div class="nai-rule">
                                    <strong>FIRST_NAME</strong>
                                    <small>Wymagane, min. 2 znaki</small>
                                </div>
                                <div class="nai-rule">
                                    <strong>LAST_NAME</strong>
                                    <small>Wymagane, min. 2 znaki</small>
                                </div>
                                <div class="nai-rule">
                                    <strong>EMAIL</strong>
                                    <small>Wymagane, prawidłowy format</small>
                                </div>
                                <div class="nai-rule">
                                    <strong>PHONE</strong>
                                    <small>Opcjonalne, min. 9 znaków</small>
                                </div>
                                <div class="nai-rule">
                                    <strong>ZIP_CODE</strong>
                                    <small>Opcjonalne, min. 3 znaki</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Legenda -->
                    <div class="nai-metabox">
                        <div class="nai-metabox-header secondary">
                            <h3>🏷️ <?php _e('Legenda', 'newsletter-ai'); ?></h3>
                        </div>
                        <div class="nai-metabox-content">
                            <div class="nai-legend">
                                <div class="nai-legend-item">
                                    <span class="nai-score-badge nai-score-excellent">90-100</span>
                                    <span><?php _e('Doskonałe', 'newsletter-ai'); ?></span>
                                </div>
                                <div class="nai-legend-item">
                                    <span class="nai-score-badge nai-score-good">70-89</span>
                                    <span><?php _e('Dobre', 'newsletter-ai'); ?></span>
                                </div>
                                <div class="nai-legend-item">
                                    <span class="nai-score-badge nai-score-poor">50-69</span>
                                    <span><?php _e('Słabe', 'newsletter-ai'); ?></span>
                                </div>
                                <div class="nai-legend-item">
                                    <span class="nai-score-badge nai-score-bad">0-49</span>
                                    <span><?php _e('Bardzo słabe', 'newsletter-ai'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal do szczegółów klienta -->
            <div id="nai-customer-details-modal" class="nai-modal" style="display: none;">
                <div class="nai-modal-content">
                    <div class="nai-modal-header">
                        <h3><?php _e('Szczegóły klienta', 'newsletter-ai'); ?></h3>
                        <span class="nai-modal-close">&times;</span>
                    </div>
                    <div class="nai-modal-body">
                        <div id="nai-customer-details-content">
                            <!-- Zawartość ładowana przez AJAX -->
                        </div>
                    </div>
                    <div class="nai-modal-footer">
                        <button type="button" class="nai-btn nai-btn-secondary" id="nai-modal-close-btn">
                            <?php _e('Zamknij', 'newsletter-ai'); ?>
                        </button>
                        <button type="button" class="nai-btn nai-btn-primary" id="nai-save-customer-data">
                            💾 <?php _e('Zapisz zmiany', 'newsletter-ai'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Instrukcje -->
            <div class="nai-metabox">
                <div class="nai-metabox-header warning">
                    <h3>📖 <?php _e('Jak używać testera klientów', 'newsletter-ai'); ?></h3>
                </div>
                <div class="nai-metabox-content">
                    <ol>
                        <li><?php _e('Przejrzyj statystyki walidacji na górze strony', 'newsletter-ai'); ?></li>
                        <li><?php _e('Użyj filtrów aby zobaczyć klientów z konkretnymi problemami', 'newsletter-ai'); ?></li>
                        <li><?php _e('Kliknij na klienta aby zobaczyć szczegóły i edytować dane', 'newsletter-ai'); ?></li>
                        <li><?php _e('Użyj przycisku "Ignoruj" dla klientów których nie chcesz eksportować', 'newsletter-ai'); ?></li>
                        <li><?php _e('Wypróbuj auto-naprawę dla automatycznego uzupełnienia brakujących danych', 'newsletter-ai'); ?></li>
                        <li><?php _e('Po naprawie problemów wygeneruj ponownie XML w zakładce "XML - Klienci"', 'newsletter-ai'); ?></li>
                    </ol>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                var currentFilter = 'all';
                var currentPage = 1;

                // Załaduj początkowe dane
                loadCustomersValidation();

                // Obsługa filtrów
                $('.nai-filter-btn').on('click', function () {
                    $('.nai-filter-btn').removeClass('active');
                    $(this).addClass('active');
                    currentFilter = $(this).data('filter');
                    currentPage = 1;
                    loadCustomersValidation();
                });

                // Odśwież walidację
                $('#nai-refresh-validation').on('click', function () {
                    loadCustomersValidation();
                });

                // Obsługa modalu
                $('.nai-modal-close, #nai-modal-close-btn').on('click', function () {
                    $('#nai-customer-details-modal').hide();
                });

                // Zamknij modal po kliknięciu w tło
                $('#nai-customer-details-modal').on('click', function (e) {
                    if (e.target === this) {
                        $(this).hide();
                    }
                });

                // Obsługa kliknięć w paginację - ZMIENIONE
                $(document).on('click', '.nai-pagination-link', function (e) {
                    e.preventDefault();
                    var page = $(this).data('page');
                    if (page && page !== currentPage) {
                        currentPage = page;
                        loadCustomersValidation();
                    }
                });

                // Funkcja ładowania klientów
                function loadCustomersValidation() {
                    console.log('Loading customers with filter:', currentFilter, 'page:', currentPage);

                    var $container = $('#nai-customers-validation-container');
                    $container.html('<div class="nai-loading"><div class="nai-spinner"></div> Walidacja klientów...</div>');

                    $.ajax({
                        url: newsletterAI.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'nai_validate_customers',
                            nonce: newsletterAI.nonce,
                            page: currentPage,
                            filter: currentFilter
                        },
                        timeout: 60000
                    })
                            .done(function (response) {
                                if (response.success) {
                                    $container.html(buildCustomersValidationTable(response.data));
                                } else {
                                    showError('Błąd walidacji: ' + response.data);
                                }
                            })
                            .fail(function (xhr, status, error) {
                                showError('Błąd połączenia: ' + error);
                            });
                }

                // Buduj tabelę klientów
                function buildCustomersValidationTable(data) {
                    var html = '<table class="wp-list-table widefat fixed striped nai-validation-table">';

                    // Nagłówek
                    html += '<thead><tr>';
                    html += '<th class="column-score">Wynik</th>';
                    html += '<th class="column-customer">Klient</th>';
                    html += '<th class="column-problems">Problemy</th>';
                    html += '<th class="column-consent">Zgoda</th>';
                    html += '<th class="column-actions">Akcje</th>';
                    html += '</tr></thead>';

                    html += '<tbody>';
                    if (data.customers && data.customers.length > 0) {
                        $.each(data.customers, function (i, customer) {
                            html += buildCustomerRow(customer);
                        });
                    } else {
                        html += '<tr><td colspan="5" class="nai-text-center" style="padding: 40px 20px;">';
                        html += '<div style="font-size: 48px; margin-bottom: 10px;">🔍</div>';
                        html += '<p>Brak klientów w tej kategorii</p>';
                        html += '</td></tr>';
                    }
                    html += '</tbody></table>';

                    // Dodaj paginację jeśli potrzebna
                    if (data.pages > 1) {
                        html += buildPagination(data);
                    }

                    return html;
                }

                // Buduj wiersz klienta
                function buildCustomerRow(customer) {
                    var html = '<tr class="nai-customer-row' + (customer.is_ignored ? ' nai-ignored' : '') + '">';

                    // Wynik walidacji
                    html += '<td class="nai-text-center">';
                    html += '<span class="nai-score-badge nai-score-' + getScoreClass(customer.validation.score) + '">';
                    html += customer.validation.score + '%';
                    html += '</span>';
                    html += '</td>';

                    // Dane klienta
                    html += '<td>';
                    html += '<strong>' + escapeHtml(customer.user_login) + '</strong><br>';
                    html += '<small>' + escapeHtml(customer.user_email) + '</small><br>';
                    html += '<small>ID: ' + customer.ID + '</small>';
                    if (customer.display_name) {
                        html += '<br><small>' + escapeHtml(customer.display_name) + '</small>';
                    }
                    html += '</td>';

                    // Problemy
                    html += '<td>';
                    var problems = [];
                    if (customer.validation.errors.length > 0) {
                        $.each(customer.validation.errors, function (i, error) {
                            problems.push('<span class="nai-problem nai-error">❌ ' + escapeHtml(error.message) + '</span>');
                        });
                    }
                    if (customer.validation.warnings.length > 0) {
                        $.each(customer.validation.warnings, function (i, warning) {
                            problems.push('<span class="nai-problem nai-warning">⚠️ ' + escapeHtml(warning.message) + '</span>');
                        });
                    }
                    if (problems.length === 0) {
                        problems.push('<span class="nai-problem nai-valid">✅ Brak problemów</span>');
                    }
                    html += problems.join('<br>');
                    html += '</td>';

                    // Zgoda
                    html += '<td class="nai-text-center">';
                    if (customer.has_consent) {
                        html += '<span class="nai-consent-yes">TAK</span>';
                    } else {
                        html += '<span class="nai-consent-no">NIE</span>';
                    }
                    html += '<br><small>(' + escapeHtml(customer.consent_value || 'brak') + ')</small>';
                    html += '</td>';

                    // Akcje
                    html += '<td class="nai-text-center">';
                    html += '<button type="button" class="nai-btn nai-btn-small nai-btn-secondary nai-view-details" data-customer-id="' + customer.ID + '">';
                    html += '👁️ Szczegóły';
                    html += '</button><br>';

                    var ignoreText = customer.is_ignored ? '🔄 Przywróć' : '🚫 Ignoruj';
                    var ignoreClass = customer.is_ignored ? 'nai-btn-success' : 'nai-btn-warning';
                    html += '<button type="button" class="nai-btn nai-btn-small ' + ignoreClass + ' nai-toggle-ignore" data-customer-id="' + customer.ID + '" style="margin-top: 5px;">';
                    html += ignoreText;
                    html += '</button>';
                    html += '</td>';

                    html += '</tr>';
                    return html;
                }

                // Pobierz klasę CSS dla wyniku
                function getScoreClass(score) {
                    if (score >= 90)
                        return 'excellent';
                    if (score >= 70)
                        return 'good';
                    if (score >= 50)
                        return 'poor';
                    return 'bad';
                }

                // Obsługa szczegółów klienta
                $(document).on('click', '.nai-view-details', function () {
                    var customerId = $(this).data('customer-id');
                    showCustomerDetails(customerId);
                });

                // Obsługa ignorowania klienta
                $(document).on('click', '.nai-toggle-ignore', function () {
                    var $button = $(this);
                    var customerId = $button.data('customer-id');

                    $.ajax({
                        url: newsletterAI.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'nai_toggle_ignore_customer',
                            nonce: newsletterAI.nonce,
                            customer_id: customerId
                        }
                    })
                            .done(function (response) {
                                if (response.success) {
                                    showSuccess(response.data.message);
                                    loadCustomersValidation(); // Odśwież listę
                                } else {
                                    showError(response.data);
                                }
                            })
                            .fail(function () {
                                showError('Błąd podczas zmiany statusu ignorowania');
                            });
                });

                // Pokaż szczegóły klienta
                function showCustomerDetails(customerId) {
                    var $modal = $('#nai-customer-details-modal');
                    var $content = $('#nai-customer-details-content');

                    $content.html('<div class="nai-loading"><div class="nai-spinner"></div> Ładowanie szczegółów...</div>');
                    $modal.show();

                    $.ajax({
                        url: newsletterAI.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'nai_get_validation_details',
                            nonce: newsletterAI.nonce,
                            customer_id: customerId
                        }
                    })
                            .done(function (response) {
                                if (response.success) {
                                    $content.html(buildCustomerDetailsForm(response.data));
                                    $modal.data('customer-id', customerId);
                                } else {
                                    $content.html('<div class="nai-notice nai-notice-error">Błąd: ' + response.data + '</div>');
                                }
                            })
                            .fail(function () {
                                $content.html('<div class="nai-notice nai-notice-error">Błąd połączenia</div>');
                            });
                }

                // Buduj formularz szczegółów klienta
                function buildCustomerDetailsForm(data) {
                    var html = '<div class="nai-customer-details">';

                    // Podstawowe informacje
                    html += '<div class="nai-customer-basic">';
                    html += '<h4>Podstawowe informacje</h4>';
                    html += '<p><strong>ID:</strong> ' + data.user.ID + '</p>';
                    html += '<p><strong>Login:</strong> ' + escapeHtml(data.user.user_login) + '</p>';
                    html += '<p><strong>Email:</strong> ' + escapeHtml(data.user.user_email) + '</p>';
                    html += '<p><strong>Nazwa:</strong> ' + escapeHtml(data.user.display_name || 'brak') + '</p>';
                    html += '</div>';

                    // Wynik walidacji
                    html += '<div class="nai-validation-summary">';
                    html += '<h4>Wynik walidacji: <span class="nai-score-badge nai-score-' + getScoreClass(data.validation.score) + '">' + data.validation.score + '%</span></h4>';

                    if (data.validation.errors.length > 0) {
                        html += '<div class="nai-validation-group">';
                        html += '<strong style="color: #d63638;">Błędy:</strong>';
                        html += '<ul>';
                        $.each(data.validation.errors, function (i, error) {
                            html += '<li>❌ ' + escapeHtml(error.message) + ' (' + error.field + ')</li>';
                        });
                        html += '</ul>';
                        html += '</div>';
                    }

                    if (data.validation.warnings.length > 0) {
                        html += '<div class="nai-validation-group">';
                        html += '<strong style="color: #dba617;">Ostrzeżenia:</strong>';
                        html += '<ul>';
                        $.each(data.validation.warnings, function (i, warning) {
                            html += '<li>⚠️ ' + escapeHtml(warning.message) + ' (' + warning.field + ')</li>';
                        });
                        html += '</ul>';
                        html += '</div>';
                    }
                    html += '</div>';

                    // Formularz edycji
                    html += '<div class="nai-customer-edit-form">';
                    html += '<h4>Edytuj dane klienta</h4>';
                    html += '<form id="nai-customer-edit-form">';

                    var fieldsToEdit = ['billing_first_name', 'billing_last_name', 'billing_phone', 'billing_postcode'];
                    var fieldLabels = {
                        'billing_first_name': 'Imię',
                        'billing_last_name': 'Nazwisko',
                        'billing_phone': 'Telefon',
                        'billing_postcode': 'Kod pocztowy'
                    };

                    $.each(fieldsToEdit, function (i, field) {
                        var value = data.meta[field] || '';
                        html += '<div class="nai-form-field">';
                        html += '<label for="edit_' + field + '">' + fieldLabels[field] + ':</label>';
                        html += '<input type="text" id="edit_' + field + '" name="' + field + '" value="' + escapeHtml(value) + '" class="regular-text" />';
                        html += '</div>';
                    });

                    html += '</form>';
                    html += '</div>';

                    html += '</div>';
                    return html;
                }

                // Zapisz zmiany klienta
                $('#nai-save-customer-data').on('click', function () {
                    var customerId = $('#nai-customer-details-modal').data('customer-id');
                    var fieldData = {};

                    $('#nai-customer-edit-form input').each(function () {
                        fieldData[$(this).attr('name')] = $(this).val();
                    });

                    $.ajax({
                        url: newsletterAI.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'nai_fix_customer_data',
                            nonce: newsletterAI.nonce,
                            customer_id: customerId,
                            field_data: fieldData
                        }
                    })
                            .done(function (response) {
                                if (response.success) {
                                    showSuccess(response.data.message);
                                    $('#nai-customer-details-modal').hide();
                                    loadCustomersValidation(); // Odśwież listę
                                } else {
                                    showError(response.data);
                                }
                            })
                            .fail(function () {
                                showError('Błąd podczas zapisywania zmian');
                            });
                });

                // Funkcje pomocnicze
                function showSuccess(message) {
                    showNotice(message, 'success');
                }

                function showError(message) {
                    showNotice(message, 'error');
                }

                function showNotice(message, type) {
                    var $notice = $('<div class="nai-notice nai-notice-' + type + '"><p>' + escapeHtml(message) + '</p></div>');
                    $('.nai-admin-page h1').first().after($notice);
                    $notice.hide().slideDown(300);
                    setTimeout(function () {
                        $notice.slideUp(300, function () {
                            $(this).remove();
                        });
                    }, 5000);
                }

                function escapeHtml(text) {
                    if (typeof text !== 'string')
                        return text;
                    var map = {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    };
                    return text.replace(/[&<>"']/g, function (m) {
                        return map[m];
                    });
                }

                // Buduj paginację
                function buildPagination(data) {
                    var html = '<div class="tablenav bottom">';
                    html += '<div class="tablenav-pages">';
                    html += '<span class="displaying-num">' + data.total + ' elementów</span>';

                    if (data.pages > 1) {
                        html += '<span class="pagination-links">';

                        // Pierwsza strona
                        if (data.current_page > 1) {
                            html += '<a class="first-page button nai-pagination-link" href="#" data-page="1" title="Pierwsza strona">&laquo;</a>';
                            html += '<a class="prev-page button nai-pagination-link" href="#" data-page="' + (data.current_page - 1) + '" title="Poprzednia strona">&lsaquo;</a>';
                        } else {
                            html += '<span class="tablenav-pages-navspan button disabled">&laquo;</span>';
                            html += '<span class="tablenav-pages-navspan button disabled">&lsaquo;</span>';
                        }

                        // Numery stron
                        var startPage = Math.max(1, data.current_page - 2);
                        var endPage = Math.min(data.pages, data.current_page + 2);

                        for (var i = startPage; i <= endPage; i++) {
                            if (i === data.current_page) {
                                html += '<span class="paging-input"><span class="tablenav-paging-text">' + i + ' z <span class="total-pages">' + data.pages + '</span></span></span>';
                            } else {
                                html += '<a class="page-numbers button nai-pagination-link" href="#" data-page="' + i + '">' + i + '</a>';
                            }
                        }

                        // Ostatnia strona
                        if (data.current_page < data.pages) {
                            html += '<a class="next-page button nai-pagination-link" href="#" data-page="' + (data.current_page + 1) + '" title="Następna strona">&rsaquo;</a>';
                            html += '<a class="last-page button nai-pagination-link" href="#" data-page="' + data.pages + '" title="Ostatnia strona">&raquo;</a>';
                        } else {
                            html += '<span class="tablenav-pages-navspan button disabled">&rsaquo;</span>';
                            html += '<span class="tablenav-pages-navspan button disabled">&raquo;</span>';
                        }

                        html += '</span>';
                    }

                    html += '</div></div>';
                    return html;
                }

                // Zmień stronę
                function changePage(page) {
                    currentPage = page;
                    loadCustomersValidation();
                }

            });


        </script>
        <?php
    }

    public function guests_page() {
        // Sprawdź czy frontend consent istnieje
        if (!class_exists('Newsletter_AI_Frontend_Consent')) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Błąd: klasa Newsletter_AI_Frontend_Consent nie jest dostępna.</p></div></div>';
            return;
        }

        $frontend_consent = new Newsletter_AI_Frontend_Consent();
        $guest_stats = $frontend_consent->get_guest_consent_stats();

        // Pobierz najnowsze zamówienia gości
        $recent_guest_orders = $this->get_recent_guest_orders_with_consent();
        ?>
        <div class="wrap nai-admin-page nai-guests-page">
            <h1><?php _e('Newsletter AI - Goście z zgodami', 'newsletter-ai'); ?></h1>

            <!-- Statystyki gości -->
            <div class="nai-metabox">
                <div class="nai-metabox-header primary">
                    <h3>📊 <?php _e('Statystyki zgód gości', 'newsletter-ai'); ?></h3>
                </div>
                <div class="nai-metabox-content">
                    <div class="nai-stats-grid">
                        <div class="nai-stat-card">
                            <div class="nai-stat-number"><?php echo esc_html($guest_stats['guests_total']); ?></div>
                            <div class="nai-stat-label"><?php _e('Łącznie zamówień gości', 'newsletter-ai'); ?></div>
                        </div>
                        <div class="nai-stat-card nai-stat-valid">
                            <div class="nai-stat-number"><?php echo esc_html($guest_stats['guests_with_consent']); ?></div>
                            <div class="nai-stat-label"><?php _e('Z zgodą na newsletter', 'newsletter-ai'); ?></div>
                        </div>
                        <div class="nai-stat-card nai-stat-error">
                            <div class="nai-stat-number"><?php echo esc_html($guest_stats['guests_without_consent']); ?></div>
                            <div class="nai-stat-label"><?php _e('Bez zgody', 'newsletter-ai'); ?></div>
                        </div>
                        <div class="nai-stat-card">
                            <div class="nai-stat-number">
                                <?php
                                $percentage = $guest_stats['guests_total'] > 0 ? round(($guest_stats['guests_with_consent'] / $guest_stats['guests_total']) * 100, 1) : 0;
                                echo esc_html($percentage) . '%';
                                ?>
                            </div>
                            <div class="nai-stat-label"><?php _e('Procent zgód', 'newsletter-ai'); ?></div>
                        </div>
                    </div>

                    <?php if ($guest_stats['guests_total'] === 0): ?>
                        <div class="nai-notice nai-notice-info">
                            <span>ℹ️</span>
                            <p><?php _e('Brak danych o zgodach gości. Pole zgody zostanie dodane do nowych zamówień.', 'newsletter-ai'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Najnowsi goście z zgodami -->
            <?php if (!empty($recent_guest_orders)): ?>
                <div class="nai-metabox">
                    <div class="nai-metabox-header success">
                        <h3>🎯 <?php _e('Najnowsi goście z zgodą na newsletter', 'newsletter-ai'); ?></h3>
                    </div>
                    <div class="nai-metabox-content nai-p-0">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Zamówienie', 'newsletter-ai'); ?></th>
                                    <th><?php _e('Email', 'newsletter-ai'); ?></th>
                                    <th><?php _e('Imię i nazwisko', 'newsletter-ai'); ?></th>
                                    <th><?php _e('Data zamówienia', 'newsletter-ai'); ?></th>
                                    <th><?php _e('Zgoda', 'newsletter-ai'); ?></th>
                                    <th><?php _e('Akcje', 'newsletter-ai'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_guest_orders as $order_data): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?php echo esc_html($order_data->order_id); ?></strong>
                                            <br>
                                            <small><?php echo esc_html($order_data->order_status); ?></small>
                                        </td>
                                        <td>
                                            <?php echo esc_html($order_data->billing_email); ?>
                                            <?php if (!empty($order_data->billing_phone)): ?>
                                                <br><small>📞 <?php echo esc_html($order_data->billing_phone); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $full_name = trim($order_data->billing_first_name . ' ' . $order_data->billing_last_name);
                                            echo esc_html($full_name ?: '—');
                                            ?>
                                            <?php if (!empty($order_data->billing_postcode)): ?>
                                                <br><small>📍 <?php echo esc_html($order_data->billing_postcode); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo esc_html(date('Y-m-d H:i', strtotime($order_data->post_date))); ?>
                                            <br>
                                            <small><?php echo esc_html(human_time_diff(strtotime($order_data->post_date), current_time('timestamp'))); ?> <?php _e('temu', 'newsletter-ai'); ?></small>
                                        </td>
                                        <td class="nai-text-center">
                                            <?php if ($order_data->consent === 'yes'): ?>
                                                <span class="nai-consent-yes">✅ TAK</span>
                                            <?php else: ?>
                                                <span class="nai-consent-no">❌ NIE</span>
                                            <?php endif; ?>
                                            <?php if ($order_data->consent_timestamp): ?>
                                                <br><small><?php echo esc_html(date('H:i', strtotime($order_data->consent_timestamp))); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="nai-text-center">
                                            <a href="<?php echo admin_url('post.php?post=' . $order_data->order_id . '&action=edit'); ?>" 
                                               class="button button-secondary button-small" 
                                               target="_blank">
                                                👁️ <?php _e('Zamówienie', 'newsletter-ai'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Narzędzia eksportu -->
            <div class="nai-metabox">
                <div class="nai-metabox-header">
                    <h3>🛠️ <?php _e('Narzędzia', 'newsletter-ai'); ?></h3>
                </div>
                <div class="nai-metabox-content">
                    <p><?php _e('Eksportuj dane gości z zgodami na newsletter:', 'newsletter-ai'); ?></p>

                    <div class="nai-grid nai-grid-3">
                        <div>
                            <button type="button" id="nai-export-guests-csv" class="nai-btn nai-btn-primary" style="width: 100%;">
                                📥 <?php _e('Eksportuj CSV', 'newsletter-ai'); ?>
                            </button>
                            <p class="description"><?php _e('Pobierz wszystkich gości z zgodami jako plik CSV', 'newsletter-ai'); ?></p>
                        </div>

                        <div>
                            <a href="<?php echo admin_url('edit.php?post_type=shop_order'); ?>" class="nai-btn nai-btn-secondary" style="width: 100%; text-align: center; display: block;">
                                📋 <?php _e('Lista zamówień', 'newsletter-ai'); ?>
                            </a>
                            <p class="description"><?php _e('Zobacz wszystkie zamówienia z kolumną Newsletter', 'newsletter-ai'); ?></p>
                        </div>

                        <div>
                            <a href="<?php echo admin_url('admin.php?page=newsletter-ai-frontend'); ?>" class="nai-btn nai-btn-secondary" style="width: 100%; text-align: center; display: block;">
                                ⚙️ <?php _e('Ustawienia', 'newsletter-ai'); ?>
                            </a>
                            <p class="description"><?php _e('Konfiguruj pole zgody w checkout', 'newsletter-ai'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Instrukcje -->
            <div class="nai-metabox">
                <div class="nai-metabox-header warning">
                    <h3>📖 <?php _e('Jak działa system zgód gości', 'newsletter-ai'); ?></h3>
                </div>
                <div class="nai-metabox-content">
                    <ol>
                        <li><?php _e('Goście podczas zamawiania widzą pole zgody na newsletter w checkout', 'newsletter-ai'); ?></li>
                        <li><?php _e('Domyślnie pole jest odznaczone - gość musi świadomie wyrazić zgodę', 'newsletter-ai'); ?></li>
                        <li><?php _e('Zgoda jest zapisywana w meta zamówienia wraz z timestampem i IP', 'newsletter-ai'); ?></li>
                        <li><?php _e('W zapleczu WooCommerce każde zamówienie pokazuje status zgody na newsletter', 'newsletter-ai'); ?></li>
                        <li><?php _e('Lista zamówień ma nową kolumnę "📧 Newsletter" z szybkim podglądem', 'newsletter-ai'); ?></li>
                        <li><?php _e('Ikony: ✅ = zgoda, ❌ = brak zgody, 🔓 = gość, ❓ = brak danych (stare zamówienia)', 'newsletter-ai'); ?></li>
                    </ol>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                $('#nai-export-guests-csv').on('click', function () {
                    var $button = $(this);
                    var originalText = $button.html();

                    $button.prop('disabled', true).html('📥 Generowanie...');

                    $.ajax({
                        url: newsletterAI.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'nai_export_guests_csv',
                            nonce: newsletterAI.nonce
                        },
                        success: function (response) {
                            if (response.success && response.data.url) {
                                // Utwórz link do pobrania
                                var link = document.createElement('a');
                                link.href = response.data.url;
                                link.download = response.data.filename || 'guests_newsletter.csv';
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);

                                alert('Plik CSV został wygenerowany i pobrany!');
                            } else {
                                alert('Błąd: ' + (response.data || 'Nie udało się wygenerować pliku'));
                            }
                        },
                        error: function () {
                            alert('Błąd połączenia podczas eksportu');
                        },
                        complete: function () {
                            $button.prop('disabled', false).html(originalText);
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * NOWE: Pobierz najnowsze zamówienia gości z danymi o zgodzie
     */
    private function get_recent_guest_orders_with_consent($limit = 20) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
        SELECT p.ID as order_id, p.post_date, p.post_status as order_status,
               pm_consent.meta_value as consent,
               pm_consent_time.meta_value as consent_timestamp,
               pm_email.meta_value as billing_email,
               pm_first_name.meta_value as billing_first_name,
               pm_last_name.meta_value as billing_last_name,
               pm_phone.meta_value as billing_phone,
               pm_postcode.meta_value as billing_postcode
        FROM {$wpdb->posts} p
               LEFT JOIN {$wpdb->postmeta} pm_consent ON p.ID = pm_consent.post_id AND pm_consent.meta_key = '_newsletter_consent'
               LEFT JOIN {$wpdb->postmeta} pm_consent_time ON p.ID = pm_consent_time.post_id AND pm_consent_time.meta_key = '_newsletter_consent_timestamp'
               LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
               LEFT JOIN {$wpdb->postmeta} pm_first_name ON p.ID = pm_first_name.post_id AND pm_first_name.meta_key = '_billing_first_name'
               LEFT JOIN {$wpdb->postmeta} pm_last_name ON p.ID = pm_last_name.post_id AND pm_last_name.meta_key = '_billing_last_name'
               LEFT JOIN {$wpdb->postmeta} pm_phone ON p.ID = pm_phone.post_id AND pm_phone.meta_key = '_billing_phone'
               LEFT JOIN {$wpdb->postmeta} pm_postcode ON p.ID = pm_postcode.post_id AND pm_postcode.meta_key = '_billing_postcode'
               LEFT JOIN {$wpdb->postmeta} pm_customer_user ON p.ID = pm_customer_user.post_id AND pm_customer_user.meta_key = '_customer_user'
        WHERE p.post_type = 'shop_order'
        AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending')
        AND (pm_customer_user.meta_value = '0' OR pm_customer_user.meta_value IS NULL)
        AND pm_consent.meta_value IS NOT NULL
        AND pm_email.meta_value IS NOT NULL
        AND pm_email.meta_value != ''
        ORDER BY p.post_date DESC
        LIMIT %d
    ", $limit));
    }
    
    /**
 * NOWA: Strona XML zamówień
 */
public function orders_page() {
    // Sprawdź czy klasa generator istnieje
    if (!class_exists('Newsletter_AI_Orders_Generator')) {
        echo '<div class="wrap"><div class="notice notice-error"><p>Błąd: klasa Newsletter_AI_Orders_Generator nie jest dostępna.</p></div></div>';
        return;
    }
    
    $orders_generator = new Newsletter_AI_Orders_Generator();
    $consent_manager = new Newsletter_AI_Consent_Manager();
    
    $orders_stats = $orders_generator->get_last_orders_generation_stats();
    $orders_file_info = $orders_generator->get_orders_xml_file_info();
    $consent_stats = $consent_manager->get_consent_statistics();
    ?>
    <div class="wrap nai-admin-page">
        <h1><?php _e('Newsletter AI - XML - Zamówienia', 'newsletter-ai'); ?></h1>

        <?php settings_errors('nai_settings'); ?>

        <div class="nai-grid nai-grid-2">
            <!-- Kolumna lewa - główne informacje -->
            <div>
                <!-- Statystyki ostatniego generowania -->
                <?php if (!empty($orders_stats)): ?>
                    <div class="nai-metabox">
                        <div class="nai-metabox-header secondary">
                            <h3>📊 <?php _e('Ostatnie generowanie XML zamówień', 'newsletter-ai'); ?></h3>
                        </div>
                        <div class="nai-metabox-content">
                            <div class="nai-stats-grid">
                                <div class="nai-stat-card">
                                    <div class="nai-stat-number"><?php echo esc_html($orders_stats['total_checked']); ?></div>
                                    <div class="nai-stat-label"><?php _e('Sprawdzonych zamówień', 'newsletter-ai'); ?></div>
                                </div>
                                <div class="nai-stat-card nai-stat-valid">
                                    <div class="nai-stat-number"><?php echo esc_html($orders_stats['processed_orders']); ?></div>
                                    <div class="nai-stat-label"><?php _e('Wyeksportowanych', 'newsletter-ai'); ?></div>
                                </div>
                                <div class="nai-stat-card nai-stat-error">
                                    <div class="nai-stat-number"><?php echo esc_html($orders_stats['skipped_orders']); ?></div>
                                    <div class="nai-stat-label"><?php _e('Pominiętych (brak zgody)', 'newsletter-ai'); ?></div>
                                </div>
                            </div>
                            <p><strong><?php _e('Data:', 'newsletter-ai'); ?></strong> <?php echo esc_html($orders_stats['timestamp']); ?></p>
                            <p><strong><?php _e('Użyte pole zgody:', 'newsletter-ai'); ?></strong> <code><?php echo esc_html($orders_stats['consent_field_used']); ?></code></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Informacje o pliku XML -->
                <div class="nai-metabox">
                    <div class="nai-metabox-header <?php echo $orders_file_info ? 'success' : 'warning'; ?>">
                        <h3>📄 <?php _e('Plik XML zamówień', 'newsletter-ai'); ?></h3>
                    </div>
                    <div class="nai-metabox-content">
                        <?php if ($orders_file_info): ?>
                            <p><strong><?php _e('Rozmiar:', 'newsletter-ai'); ?></strong> <?php echo size_format($orders_file_info['size']); ?></p>
                            <p><strong><?php _e('Ostatnia modyfikacja:', 'newsletter-ai'); ?></strong> <?php echo date('Y-m-d H:i:s', $orders_file_info['modified']); ?></p>
                            <p><strong><?php _e('URL:', 'newsletter-ai'); ?></strong> <a href="<?php echo esc_url($orders_file_info['url']); ?>" target="_blank" class="nai-btn nai-btn-small nai-btn-secondary"><?php _e('Otwórz plik', 'newsletter-ai'); ?></a></p>
                        <?php else: ?>
                            <div class="nai-notice nai-notice-warning">
                                <span>⚠️</span>
                                <p><?php _e('Plik XML zamówień jeszcze nie został wygenerowany.', 'newsletter-ai'); ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="nai-text-center" style="margin-top: 20px;">
                            <button type="button" id="nai-generate-orders-xml" class="nai-btn nai-btn-primary nai-btn-large">
                                ⚡ <?php _e('Wygeneruj XML zamówień teraz', 'newsletter-ai'); ?>
                            </button>
                            <div id="nai-orders-xml-generation-status" style="margin-top: 10px;"></div>
                        </div>
                    </div>
                </div>

                <!-- Statystyki ogólne -->
                <div class="nai-metabox">
                    <div class="nai-metabox-header">
                        <h3>📈 <?php _e('Statystyki zgód (użytkownicy)', 'newsletter-ai'); ?></h3>
                    </div>
                    <div class="nai-metabox-content">
                        <div class="nai-stats-grid">
                            <div class="nai-stat-card">
                                <div class="nai-stat-number"><?php echo esc_html($consent_stats['total_users']); ?></div>
                                <div class="nai-stat-label"><?php _e('Łącznie użytkowników', 'newsletter-ai'); ?></div>
                            </div>
                            <div class="nai-stat-card nai-stat-valid">
                                <div class="nai-stat-number"><?php echo esc_html($consent_stats['users_with_consent']); ?></div>
                                <div class="nai-stat-label"><?php _e('Z zgodą', 'newsletter-ai'); ?></div>
                            </div>
                            <div class="nai-stat-card">
                                <div class="nai-stat-number"><?php echo esc_html($consent_stats['consent_percentage']); ?>%</div>
                                <div class="nai-stat-label"><?php _e('Procent zgód', 'newsletter-ai'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolumna prawa - ustawienia i informacje -->
            <div>
                <!-- Jak działa filtrowanie -->
                <div class="nai-metabox">
                    <div class="nai-metabox-header primary">
                        <h3>🔍 <?php _e('Filtrowanie zamówień', 'newsletter-ai'); ?></h3>
                    </div>
                    <div class="nai-metabox-content">
                        <p><?php _e('XML zamówień zawiera <strong>tylko</strong> zamówienia od klientów którzy wyrazili zgodę na newsletter:', 'newsletter-ai'); ?></p>
                        
                        <div class="nai-filter-examples">
                            <div class="nai-filter-example">
                                <h4>✅ <?php _e('Uwzględniane zamówienia:', 'newsletter-ai'); ?></h4>
                                <ul>
                                    <li>👤 <?php _e('Zarejestrowany użytkownik z zgodą na newsletter', 'newsletter-ai'); ?></li>
                                    <li>🔓 <?php _e('Gość który wyraził zgodę podczas zamówienia', 'newsletter-ai'); ?></li>
                                </ul>
                            </div>
                            
                            <div class="nai-filter-example">
                                <h4>❌ <?php _e('Pomijane zamówienia:', 'newsletter-ai'); ?></h4>
                                <ul>
                                    <li>👤 <?php _e('Zarejestrowany użytkownik bez zgody', 'newsletter-ai'); ?></li>
                                    <li>🔓 <?php _e('Gość który nie wyraził zgody', 'newsletter-ai'); ?></li>
                                    <li>📅 <?php _e('Stare zamówienia gości (przed implementacją)', 'newsletter-ai'); ?></li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="nai-notice nai-notice-info">
                            <span>ℹ️</span>
                            <p><strong><?php _e('GDPR Compliance:', 'newsletter-ai'); ?></strong><br>
                            <?php _e('Eksportujemy tylko dane klientów którzy świadomie wyrazili zgodę.', 'newsletter-ai'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Statusy zamówień -->
                <div class="nai-metabox">
                    <div class="nai-metabox-header secondary">
                        <h3>📋 <?php _e('Mapowanie statusów', 'newsletter-ai'); ?></h3>
                    </div>
                    <div class="nai-metabox-content">
                        <p><?php _e('Statusy WooCommerce są mapowane do statusów Samba.AI:', 'newsletter-ai'); ?></p>
                        
                        <div class="nai-status-mapping">
                            <div class="nai-status-group">
                                <strong>send</strong>
                                <div class="nai-status-items">
                                    <span class="nai-status-badge nai-status-success">Ukończone</span>
                                </div>
                            </div>
                            
                            <div class="nai-status-group">
                                <strong>canceled</strong>
                                <div class="nai-status-items">
                                    <span class="nai-status-badge nai-status-error">Anulowane</span>
                                    <span class="nai-status-badge nai-status-error">Zwrócone</span>
                                    <span class="nai-status-badge nai-status-error">Nieudane</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Instrukcje -->
        <div class="nai-metabox">
            <div class="nai-metabox-header warning">
                <h3>📖 <?php _e('Jak działa eksport zamówień z zgodami', 'newsletter-ai'); ?></h3>
            </div>
            <div class="nai-metabox-content">
                <ol>
                    <li><?php _e('System sprawdza wszystkie zamówienia w dozwolonych statusach', 'newsletter-ai'); ?></li>
                    <li><?php _e('Dla każdego zamówienia weryfikuje zgodę klienta na newsletter:', 'newsletter-ai'); ?>
                        <ul style="margin-left: 20px; margin-top: 8px;">
                            <li><?php _e('Zarejestrowany użytkownik: sprawdza pole w profilu użytkownika', 'newsletter-ai'); ?></li>
                            <li><?php _e('Gość: sprawdza pole _newsletter_consent w meta zamówienia', 'newsletter-ai'); ?></li>
                        </ul>
                    </li>
                    <li><?php _e('Eksportuje tylko zamówienia od klientów z ważną zgodą', 'newsletter-ai'); ?></li>
                    <li><?php _e('Zapisuje statystyki: ile zamówień sprawdzono vs ile wyeksportowano', 'newsletter-ai'); ?></li>
                    <li><?php _e('Plik XML jest kompatybilny z oryginalnym formatem Samba.AI', 'newsletter-ai'); ?></li>
                </ol>
                
                <div class="nai-notice nai-notice-info" style="margin-top: 20px;">
                    <span>💡</span>
                    <p><strong><?php _e('Wskazówka:', 'newsletter-ai'); ?></strong> <?php _e('Możesz automatyzować generowanie tego pliku przez Cron wraz z plikiem klientów.', 'newsletter-ai'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Obsługa generowania XML zamówień
        $('#nai-generate-orders-xml').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $status = $('#nai-orders-xml-generation-status');
            var originalText = $button.html();

            $button.prop('disabled', true).html('<div class="nai-spinner" style="display: inline-block; vertical-align: middle; margin-right: 5px;"></div> Generowanie XML zamówień...');
            $status.html('<div class="nai-notice nai-notice-info"><div class="nai-spinner"></div> Sprawdzanie zgód i generowanie pliku XML...</div>');

            $.post(newsletterAI.ajax_url, {
                action: 'nai_generate_orders_xml',
                nonce: newsletterAI.nonce
            })
            .done(function(response) {
                if (response && response.success) {
                    $status.html('<div class="nai-notice nai-notice-success">✅ ' + (response.data.message || 'XML zamówień wygenerowany pomyślnie') + '</div>');
                    
                    // Pokaż dodatkowe statystyki jeśli dostępne
                    if (response.data.stats) {
                        var statsHtml = '<div style="margin-top: 10px; font-size: 13px;">';
                        statsHtml += '<strong>Szczegóły:</strong><br>';
                        statsHtml += '• Sprawdzono zamówień: ' + response.data.stats.total_checked + '<br>';
                        statsHtml += '• Wyeksportowano: ' + response.data.stats.processed + '<br>';
                        statsHtml += '• Pominięto (brak zgody): ' + response.data.stats.skipped;
                        statsHtml += '</div>';
                        $status.find('.nai-notice').append(statsHtml);
                    }
                    
                    // Odśwież stronę po 4 sekundach żeby pokazać nowe statystyki
                    setTimeout(function() {
                        location.reload();
                    }, 4000);
                } else {
                    var errorMessage = (response && response.data) ? response.data : 'Wystąpił błąd podczas generowania XML zamówień.';
                    $status.html('<div class="nai-notice nai-notice-error">❌ ' + errorMessage + '</div>');
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Newsletter AI Orders: AJAX failed', xhr, status, error);
                $status.html('<div class="nai-notice nai-notice-error">❌ Błąd połączenia: ' + error + '</div>');
            })
            .always(function() {
                $button.prop('disabled', false).html(originalText);
            });
        });
    });
    </script>

    <style>
    .nai-filter-examples {
        margin: 15px 0;
    }
    
    .nai-filter-example {
        margin: 15px 0;
        padding: 12px;
        border-radius: 4px;
        background: #f6f7f7;
    }
    
    .nai-filter-example h4 {
        margin: 0 0 8px 0;
        font-size: 14px;
    }
    
    .nai-filter-example ul {
        margin: 8px 0 0 20px;
        font-size: 13px;
    }
    
    .nai-filter-example li {
        margin: 4px 0;
    }
    
    .nai-status-mapping {
        font-size: 13px;
    }
    
    .nai-status-group {
        margin: 12px 0;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 4px;
    }
    
    .nai-status-group strong {
        display: block;
        margin-bottom: 6px;
        color: #1d2327;
        font-family: monospace;
        background: #fff;
        padding: 4px 8px;
        border-radius: 3px;
        display: inline-block;
    }
    
    .nai-status-items {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
    }
    
    .nai-status-badge {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        background: #e7e8ea;
        color: #2c3338;
    }
    
    .nai-status-badge.nai-status-success {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .nai-status-badge.nai-status-error {
        background: #f8d7da;
        color: #721c24;
    }
    
    .nai-quick-links a {
        text-decoration: none !important;
        display: block !important;
        text-align: center;
    }
    </style>
    <?php
}

}
