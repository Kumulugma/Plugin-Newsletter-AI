<?php
/**
 * Newsletter AI Frontend Consent Management
 */
if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_AI_Frontend_Consent {

    private $consent_field = 'newsletter_ai_consent';

    public function __construct() {
        $this->consent_field = get_option('nai_consent_field', 'newsletter_ai_consent');

        // Hooki dla różnych miejsc
        $this->init_registration_hooks();
        $this->init_checkout_hooks();
        $this->init_myaccount_hooks();

        // Style frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));

        add_action('wp_ajax_nai_export_guests_csv', array($this, 'ajax_export_guests_csv'));
    }

    /**
     * Inicjalizuj hooki dla rejestracji
     */
    private function init_registration_hooks() {
        if (get_option('nai_show_on_registration', true)) {
            // WordPress rejestracja
            add_action('register_form', array($this, 'add_registration_consent_field'));
            add_filter('registration_errors', array($this, 'validate_registration_consent'), 10, 3);
            add_action('user_register', array($this, 'save_registration_consent'));

            // WooCommerce rejestracja
            add_action('woocommerce_register_form', array($this, 'add_woocommerce_registration_field'));
            add_filter('woocommerce_registration_errors', array($this, 'validate_woocommerce_registration'), 10, 4);
            add_action('woocommerce_created_customer', array($this, 'save_woocommerce_registration_consent'));
        }
    }

    /**
     * Inicjalizuj hooki dla checkout
     */
    private function init_checkout_hooks() {
        if (get_option('nai_show_on_checkout', true)) {
            // Pokaż pole zgody
            add_action('woocommerce_checkout_billing', array($this, 'add_checkout_consent_field'), 20);

            // Walidacja
            add_action('woocommerce_checkout_process', array($this, 'validate_checkout_consent'));

            // Zapis dla zalogowanych użytkowników
            add_action('woocommerce_checkout_update_user_meta', array($this, 'save_checkout_consent'), 10, 2);

            // NOWE: Zapis dla gości - hook na utworzenie zamówienia
            add_action('woocommerce_checkout_order_processed', array($this, 'save_guest_consent_to_order'), 10, 1);

            // NOWE: Wyświetlanie w admin zamówienia
            add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_newsletter_consent_in_admin'));

            // NOWE: Kolumna w liście zamówień
            add_filter('manage_edit-shop_order_columns', array($this, 'add_newsletter_consent_column'));
            add_action('manage_shop_order_posts_custom_column', array($this, 'display_newsletter_consent_column'), 10, 2);
        }
    }

    /**
     * Inicjalizuj hooki dla MyAccount
     */
    private function init_myaccount_hooks() {
        if (get_option('nai_show_in_myaccount', true)) {
            add_action('woocommerce_edit_account_form', array($this, 'add_myaccount_consent_field'));
            add_action('woocommerce_save_account_details_errors', array($this, 'validate_myaccount_consent'), 10, 1);
            add_action('woocommerce_save_account_details', array($this, 'save_myaccount_consent'));
        }
    }

    /**
     * Dodaj pole zgody do rejestracji WordPress
     */
    public function add_registration_consent_field() {
        $text = get_option('nai_consent_text', __('Wyrażam zgodę na otrzymywanie newslettera', 'newsletter-ai'));
        $required = get_option('nai_consent_required', false);
        ?>
        <p class="nai-consent-wrapper">
            <label for="newsletter_consent">
                <input type="checkbox" 
                       name="newsletter_consent" 
                       id="newsletter_consent" 
                       value="yes" 
                       class="nai-consent-checkbox"
                       <?php echo $required ? 'required' : ''; ?> />
                       <?php echo esc_html($text); ?>
                       <?php if ($required): ?>
                    <span class="required">*</span>
                <?php endif; ?>
            </label>
        </p>
        <?php
    }

    /**
     * Dodaj pole zgody do rejestracji WooCommerce
     */
    public function add_woocommerce_registration_field() {
        $text = get_option('nai_consent_text', __('Wyrażam zgodę na otrzymywanie newslettera', 'newsletter-ai'));
        $required = get_option('nai_consent_required', false);
        ?>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide nai-consent-wrapper">
            <label for="newsletter_consent" class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                <input type="checkbox" 
                       class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox nai-consent-checkbox" 
                       name="newsletter_consent" 
                       id="newsletter_consent" 
                       value="yes"
                       <?php echo $required ? 'required' : ''; ?> />
                <span class="woocommerce-form__label-text"><?php echo esc_html($text); ?></span>
                <?php if ($required): ?>
                    <span class="required">*</span>
                <?php endif; ?>
            </label>
        </p>
        <?php
    }

    /**
     * Dodaj pole zgody do checkout (tylko jeśli brak zgody)
     */
    public function add_checkout_consent_field($checkout) {
        $text = get_option('nai_consent_text', __('Wyrażam zgodę na otrzymywanie newslettera', 'newsletter-ai'));
        $required = get_option('nai_consent_required', false);

        $show_field = true;
        $default_value = '';

        // Sprawdź czy użytkownik jest zalogowany i ma już zgodę
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $consent_value = get_user_meta($user_id, $this->consent_field, true);
            $consent_values = get_option('nai_consent_values', array('yes', '1', 'true', 'on'));

            if (in_array(strtolower($consent_value), array_map('strtolower', $consent_values))) {
                // Użytkownik ma już zgodę - pokaż jako zaznaczone
                $default_value = 'yes';
            }

            $customer_type = __('zalogowany użytkownik', 'newsletter-ai');
        } else {
            // GOŚĆ - domyślnie brak zgody
            $default_value = '';
            $customer_type = __('gość', 'newsletter-ai');
        }

        echo '<div class="nai-checkout-consent">';
        echo '<h3>' . __('Newsletter', 'newsletter-ai') . '</h3>';

        if (is_user_logged_in()) {
            echo '<p class="nai-checkout-info"><small>' . __('Twoje preferencje newslettera możesz zmienić w ustawieniach konta.', 'newsletter-ai') . '</small></p>';
        } else {
            echo '<p class="nai-checkout-info"><small>' . __('Jako gość możesz wyrazić zgodę na otrzymywanie newslettera.', 'newsletter-ai') . '</small></p>';
        }

        woocommerce_form_field('newsletter_consent', array(
            'type' => 'checkbox',
            'class' => array('form-row-wide', 'nai-consent-wrapper'),
            'label' => $text,
            'required' => $required,
            'clear' => true
                ), $default_value);

        echo '</div>';
    }

    /**
     * Dodaj pole zgody do checkout dla gości (backup method)
     */
    public function add_checkout_consent_field_for_guests($checkout) {
        // Sprawdź czy nie jest zalogowany
        if (is_user_logged_in()) {
            return;
        }

        $text = get_option('nai_consent_text', __('Wyrażam zgodę na otrzymywanie newslettera', 'newsletter-ai'));
        $required = get_option('nai_consent_required', false);

        echo '<div class="nai-checkout-consent-guest">';
        echo '<h3>' . __('Newsletter', 'newsletter-ai') . '</h3>';

        // Dla gości używamy prostego HTML
        echo '<p class="form-row form-row-wide nai-consent-wrapper">';
        echo '<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">';
        echo '<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox nai-consent-checkbox" name="newsletter_consent" id="newsletter_consent_guest" value="yes"' . ($required ? ' required' : '') . ' />';
        echo '<span class="woocommerce-form__label-text">' . esc_html($text) . '</span>';
        if ($required)
            echo '<span class="required">*</span>';
        echo '</label>';
        echo '</p>';
        echo '</div>';
    }

    /**
     * Dodaj pole zgody do MyAccount
     */
    public function add_myaccount_consent_field() {
        $user_id = get_current_user_id();
        if (!$user_id)
            return;

        $consent_value = get_user_meta($user_id, $this->consent_field, true);
        $consent_values = get_option('nai_consent_values', array('yes', '1', 'true', 'on'));
        $has_consent = in_array(strtolower($consent_value), array_map('strtolower', $consent_values));

        $text = get_option('nai_consent_text', __('Wyrażam zgodę na otrzymywanie newslettera', 'newsletter-ai'));
        ?>
        <fieldset class="nai-myaccount-consent">
            <legend><?php _e('Preferencje newslettera', 'newsletter-ai'); ?></legend>

            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="newsletter_consent" class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                    <input type="checkbox" 
                           class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox nai-consent-checkbox" 
                           name="newsletter_consent" 
                           id="newsletter_consent" 
                           value="yes"
        <?php checked($has_consent, true); ?> />
                    <span class="woocommerce-form__label-text"><?php echo esc_html($text); ?></span>
                </label>
            </p>

            <p class="description">
        <?php _e('Możesz w każdej chwili wycofać zgodę w ustawieniach konta.', 'newsletter-ai'); ?>
                <br>
                <small>
        <?php _e('Aktualna wartość:', 'newsletter-ai'); ?> 
                    <code><?php echo esc_html($consent_value ?: __('brak', 'newsletter-ai')); ?></code>
                </small>
            </p>
        </fieldset>
        <?php
    }

    /**
     * Walidacja rejestracji WordPress
     */
    public function validate_registration_consent($errors, $sanitized_user_login, $user_email) {
        $required = get_option('nai_consent_required', false);

        if ($required && empty($_POST['newsletter_consent'])) {
            $errors->add('newsletter_consent_error', __('Zgoda na newsletter jest wymagana.', 'newsletter-ai'));
        }

        return $errors;
    }

    /**
     * Walidacja rejestracji WooCommerce
     */
    public function validate_woocommerce_registration($validation_error, $username, $password, $email) {
        $required = get_option('nai_consent_required', false);

        if ($required && empty($_POST['newsletter_consent'])) {
            $validation_error->add('newsletter_consent_error', __('Zgoda na newsletter jest wymagana.', 'newsletter-ai'));
        }

        return $validation_error;
    }

    /**
     * Walidacja checkout
     */
    public function validate_checkout_consent() {
        $required = get_option('nai_consent_required', false);

        // Sprawdź czy pole jest wymagane i nie zostało zaznaczone
        if ($required && empty($_POST['newsletter_consent'])) {
            // Sprawdź czy użytkownik ma już zgodę
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $consent_value = get_user_meta($user_id, $this->consent_field, true);
                $consent_values = get_option('nai_consent_values', array('yes', '1', 'true', 'on'));

                if (!in_array(strtolower($consent_value), array_map('strtolower', $consent_values))) {
                    wc_add_notice(__('Zgoda na newsletter jest wymagana.', 'newsletter-ai'), 'error');
                }
            } else {
                wc_add_notice(__('Zgoda na newsletter jest wymagana.', 'newsletter-ai'), 'error');
            }
        }
    }

    /**
     * Walidacja MyAccount
     */
    public function validate_myaccount_consent($errors) {
        // MyAccount nie wymaga walidacji - użytkownik może zmienić zgodę
        return $errors;
    }

    /**
     * Zapisz zgodę z rejestracji WordPress
     */
    public function save_registration_consent($user_id) {
        $consent_value = isset($_POST['newsletter_consent']) ? 'yes' : 'no';
        update_user_meta($user_id, $this->consent_field, $consent_value);

        // Log
        if (get_option('nai_debug_mode', false)) {
            error_log("Newsletter AI: Rejestracja WP - user $user_id, consent: $consent_value");
        }
    }

    /**
     * Zapisz zgodę z rejestracji WooCommerce
     */
    public function save_woocommerce_registration_consent($customer_id) {
        $consent_value = isset($_POST['newsletter_consent']) ? 'yes' : 'no';
        update_user_meta($customer_id, $this->consent_field, $consent_value);

        // Log
        if (get_option('nai_debug_mode', false)) {
            error_log("Newsletter AI: Rejestracja WC - user $customer_id, consent: $consent_value");
        }
    }

    /**
     * Zapisz zgodę z checkout
     */
    public function save_checkout_consent($user_id, $data) {
        if (!$user_id) {
            return; // To był gość
        }

        $consent_value = isset($_POST['newsletter_consent']) ? 'yes' : 'no';
        $old_value = get_user_meta($user_id, $this->consent_field, true);

        update_user_meta($user_id, $this->consent_field, $consent_value);

        // Zapisz historię jeśli zmieniono
        if ($old_value !== $consent_value) {
            $this->save_consent_history($user_id, $old_value, $consent_value, 'checkout');
        }

        // Log
        if (get_option('nai_debug_mode', false)) {
            error_log("Newsletter AI: User checkout - user $user_id, consent: $old_value -> $consent_value");
        }
    }

    public function save_guest_consent_to_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Sprawdź czy to zamówienie gościa
        if ($order->get_user_id() > 0) {
            return; // To nie gość
        }

        $consent_value = isset($_POST['newsletter_consent']) ? 'yes' : 'no';

        // Zapisz zgodę w meta zamówienia
        $order->update_meta_data('_newsletter_consent', $consent_value);
        $order->update_meta_data('_newsletter_consent_timestamp', current_time('mysql'));
        $order->update_meta_data('_newsletter_consent_ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $order->save();

        // Log
        if (get_option('nai_debug_mode', false)) {
            error_log("Newsletter AI: Guest checkout - order $order_id, email: " . $order->get_billing_email() . ", consent: $consent_value");
        }
    }

    /**
     * Zapisz zgodę z MyAccount
     */
    public function save_myaccount_consent($user_id) {
        $consent_value = isset($_POST['newsletter_consent']) ? 'yes' : 'no';
        $old_value = get_user_meta($user_id, $this->consent_field, true);

        update_user_meta($user_id, $this->consent_field, $consent_value);

        // Zapisz do historii jeśli zmieniono
        if ($old_value !== $consent_value) {
            $history = get_user_meta($user_id, $this->consent_field . '_history', true);
            if (!is_array($history)) {
                $history = array();
            }

            $history[] = array(
                'timestamp' => current_time('mysql'),
                'old_value' => $old_value,
                'new_value' => $consent_value,
                'changed_by' => $user_id,
                'source' => 'myaccount',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            );

            // Zachowaj tylko ostatnie 20 zmian
            if (count($history) > 20) {
                $history = array_slice($history, -20);
            }

            update_user_meta($user_id, $this->consent_field . '_history', $history);
        }

        // Log
        if (get_option('nai_debug_mode', false)) {
            error_log("Newsletter AI: MyAccount - user $user_id, consent: $old_value -> $consent_value");
        }

        // Pokaż komunikat
        wc_add_notice(__('Preferencje newslettera zostały zaktualizowane.', 'newsletter-ai'), 'success');
    }

    /**
     * Enqueue frontend styles
     */
    public function enqueue_frontend_styles() {
        if (get_option('nai_load_frontend_styles', true)) {
            wp_add_inline_style('woocommerce-general', $this->get_frontend_css());
        }
    }

    /**
     * Pobierz CSS dla frontend
     */
    private function get_frontend_css() {
        $primary_color = get_option('nai_frontend_primary_color', '#0073aa');
        $border_radius = get_option('nai_frontend_border_radius', '4px');

        $css = "
        /* Newsletter AI Frontend Styles */
        .nai-consent-wrapper {
            margin: 15px 0;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: {$border_radius};
            background: #f9f9f9;
        }
        
        .nai-consent-checkbox {
            margin-right: 8px;
            transform: scale(1.1);
        }
        
        .nai-consent-checkbox:checked {
            accent-color: {$primary_color};
        }
        
        .nai-checkout-consent,
        .nai-checkout-consent-guest {
            margin: 20px 0;
            padding: 15px;
            border: 2px solid {$primary_color};
            border-radius: {$border_radius};
            background: #f8f9fa;
        }
        
        .nai-checkout-consent h3,
        .nai-checkout-consent-guest h3 {
            margin-top: 0;
            color: {$primary_color};
        }
        
        .nai-myaccount-consent {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: {$border_radius};
            background: #fff;
        }
        
        .nai-myaccount-consent legend {
            font-weight: 600;
            color: {$primary_color};
            padding: 0 10px;
        }
        
        .nai-myaccount-consent .description {
            font-size: 0.9em;
            color: #666;
            margin-top: 8px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nai-consent-wrapper,
            .nai-checkout-consent,
            .nai-checkout-consent-guest,
            .nai-myaccount-consent {
                padding: 10px;
                margin: 10px 0;
            }
        }
        ";

        return $css;
    }

    public function display_newsletter_consent_in_admin($order) {
        if (!$order) {
            return;
        }

        $order_obj = is_numeric($order) ? wc_get_order($order) : $order;
        if (!$order_obj) {
            return;
        }

        echo '<div class="nai-order-newsletter-consent" style="margin-top: 15px; padding: 12px; background: #f8f9fa; border: 1px solid #c3c4c7; border-radius: 4px;">';
        echo '<h4 style="margin: 0 0 8px 0; color: #1d2327;">📧 Newsletter</h4>';

        if ($order_obj->get_user_id() > 0) {
            // Zarejestrowany użytkownik
            $user_id = $order_obj->get_user_id();
            $consent_value = get_user_meta($user_id, $this->consent_field, true);
            $consent_values = get_option('nai_consent_values', array('yes', '1', 'true', 'on'));
            $has_consent = in_array(strtolower(trim($consent_value)), array_map('strtolower', $consent_values));

            echo '<p><strong>Typ klienta:</strong> Zarejestrowany użytkownik (ID: ' . $user_id . ')</p>';
            echo '<p><strong>Zgoda na newsletter:</strong> ';

            if ($has_consent) {
                echo '<span style="color: #00a32a; font-weight: 600;">✅ TAK</span>';
            } else {
                echo '<span style="color: #d63638; font-weight: 600;">❌ NIE</span>';
            }

            echo '</p>';
            echo '<p><strong>Wartość pola:</strong> <code>' . esc_html($consent_value ?: 'brak') . '</code></p>';

            // Link do profilu użytkownika
            echo '<p><a href="' . admin_url('user-edit.php?user_id=' . $user_id) . '" class="button button-secondary" target="_blank">👤 Edytuj profil użytkownika</a></p>';
        } else {
            // Gość
            $guest_consent = $order_obj->get_meta('_newsletter_consent');
            $consent_timestamp = $order_obj->get_meta('_newsletter_consent_timestamp');
            $consent_ip = $order_obj->get_meta('_newsletter_consent_ip');

            echo '<p><strong>Typ klienta:</strong> Gość</p>';
            echo '<p><strong>Zgoda na newsletter:</strong> ';

            if ($guest_consent === 'yes') {
                echo '<span style="color: #00a32a; font-weight: 600;">✅ TAK</span>';
            } elseif ($guest_consent === 'no') {
                echo '<span style="color: #d63638; font-weight: 600;">❌ NIE</span>';
            } else {
                echo '<span style="color: #646970; font-weight: 600;">❓ BRAK DANYCH</span>';
                echo '<br><small style="color: #d63638;">⚠️ To zamówienie zostało złożone przed implementacją systemu zgód</small>';
            }

            echo '</p>';

            if ($consent_timestamp) {
                echo '<p><strong>Data wyrażenia zgody:</strong> ' . esc_html($consent_timestamp) . '</p>';
            }

            if ($consent_ip && $consent_ip !== 'unknown') {
                echo '<p><strong>IP:</strong> <code>' . esc_html($consent_ip) . '</code></p>';
            }
        }

        echo '</div>';
    }

    /**
     * NOWE: Dodaj kolumnę "Newsletter" w liście zamówień
     */
    public function add_newsletter_consent_column($columns) {
        $new_columns = array();

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            // Dodaj kolumnę newsletter po kolumnie "Status"
            if ($key === 'order_status') {
                $new_columns['newsletter_consent'] = '📧 Newsletter';
            }
        }

        return $new_columns;
    }

    /**
     * NOWE: Wyświetl zgodę w kolumnie listy zamówień
     */
    public function display_newsletter_consent_column($column, $post_id) {
        if ($column !== 'newsletter_consent') {
            return;
        }

        $order = wc_get_order($post_id);
        if (!$order) {
            echo '<span style="color: #646970;">—</span>';
            return;
        }

        if ($order->get_user_id() > 0) {
            // Zarejestrowany użytkownik
            $user_id = $order->get_user_id();
            $consent_value = get_user_meta($user_id, $this->consent_field, true);
            $consent_values = get_option('nai_consent_values', array('yes', '1', 'true', 'on'));
            $has_consent = in_array(strtolower(trim($consent_value)), array_map('strtolower', $consent_values));

            if ($has_consent) {
                echo '<span style="color: #00a32a; font-weight: 600;" title="Zarejestrowany użytkownik wyraził zgodę">✅</span>';
            } else {
                echo '<span style="color: #d63638; font-weight: 600;" title="Zarejestrowany użytkownik nie wyraził zgody">❌</span>';
            }
        } else {
            // Gość
            $guest_consent = $order->get_meta('_newsletter_consent');

            if ($guest_consent === 'yes') {
                echo '<span style="color: #00a32a; font-weight: 600;" title="Gość wyraził zgodę">✅🔓</span>';
            } elseif ($guest_consent === 'no') {
                echo '<span style="color: #d63638; font-weight: 600;" title="Gość nie wyraził zgody">❌🔓</span>';
            } else {
                echo '<span style="color: #646970;" title="Brak danych o zgodzie (stare zamówienie)">❓</span>';
            }
        }
    }

    /**
     * NOWE: Zapisz historię zgody
     */
    private function save_consent_history($user_id, $old_value, $new_value, $source = 'unknown') {
        $history = get_user_meta($user_id, $this->consent_field . '_history', true);
        if (!is_array($history)) {
            $history = array();
        }

        $history[] = array(
            'timestamp' => current_time('mysql'),
            'old_value' => $old_value,
            'new_value' => $new_value,
            'changed_by' => get_current_user_id(),
            'source' => $source,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );

        // Zachowaj tylko ostatnie 20 zmian
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }

        update_user_meta($user_id, $this->consent_field . '_history', $history);
    }

    /**
     * NOWE: Pobierz statystyki zgód gości
     */
    public function get_guest_consent_stats() {
        global $wpdb;

        $stats = $wpdb->get_results("
        SELECT 
            pm_consent.meta_value as consent,
            COUNT(*) as count
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_consent ON p.ID = pm_consent.post_id AND pm_consent.meta_key = '_newsletter_consent'
        LEFT JOIN {$wpdb->postmeta} pm_customer_user ON p.ID = pm_customer_user.post_id AND pm_customer_user.meta_key = '_customer_user'
        WHERE p.post_type = 'shop_order'
        AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
        AND (pm_customer_user.meta_value = '0' OR pm_customer_user.meta_value IS NULL)
        AND pm_consent.meta_value IS NOT NULL
        GROUP BY pm_consent.meta_value
    ");

        $result = array(
            'guests_with_consent' => 0,
            'guests_without_consent' => 0,
            'guests_total' => 0
        );

        foreach ($stats as $stat) {
            if ($stat->consent === 'yes') {
                $result['guests_with_consent'] = (int) $stat->count;
            } elseif ($stat->consent === 'no') {
                $result['guests_without_consent'] = (int) $stat->count;
            }
        }

        $result['guests_total'] = $result['guests_with_consent'] + $result['guests_without_consent'];

        return $result;
    }

    /**
     * NOWE: AJAX handler dla eksportu gości do CSV
     */
    public function ajax_export_guests_csv() {
        // Sprawdź nonce
        if (!wp_verify_nonce($_POST['nonce'], 'newsletter_ai_nonce')) {
            wp_send_json_error(__('Błąd bezpieczeństwa', 'newsletter-ai'));
        }

        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Brak uprawnień', 'newsletter-ai'));
        }

        try {
            $export_data = $this->export_guests_to_csv();

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
     * NOWE: Eksportuj gości do CSV
     */
    public function export_guests_to_csv() {
        global $wpdb;

        // Pobierz wszystkich gości z zgodami
        $guest_orders = $wpdb->get_results("
        SELECT p.ID as order_id, p.post_date,
               pm_consent.meta_value as consent,
               pm_consent_time.meta_value as consent_timestamp,
               pm_consent_ip.meta_value as consent_ip,
               pm_email.meta_value as billing_email,
               pm_first_name.meta_value as billing_first_name,
               pm_last_name.meta_value as billing_last_name,
               pm_phone.meta_value as billing_phone,
               pm_postcode.meta_value as billing_postcode,
               pm_city.meta_value as billing_city,
               pm_country.meta_value as billing_country
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_consent ON p.ID = pm_consent.post_id AND pm_consent.meta_key = '_newsletter_consent'
        LEFT JOIN {$wpdb->postmeta} pm_consent_time ON p.ID = pm_consent_time.post_id AND pm_consent_time.meta_key = '_newsletter_consent_timestamp'
        LEFT JOIN {$wpdb->postmeta} pm_consent_ip ON p.ID = pm_consent_ip.post_id AND pm_consent_ip.meta_key = '_newsletter_consent_ip'
        LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
        LEFT JOIN {$wpdb->postmeta} pm_first_name ON p.ID = pm_first_name.post_id AND pm_first_name.meta_key = '_billing_first_name'
        LEFT JOIN {$wpdb->postmeta} pm_last_name ON p.ID = pm_last_name.post_id AND pm_last_name.meta_key = '_billing_last_name'
        LEFT JOIN {$wpdb->postmeta} pm_phone ON p.ID = pm_phone.post_id AND pm_phone.meta_key = '_billing_phone'
        LEFT JOIN {$wpdb->postmeta} pm_postcode ON p.ID = pm_postcode.post_id AND pm_postcode.meta_key = '_billing_postcode'
        LEFT JOIN {$wpdb->postmeta} pm_city ON p.ID = pm_city.post_id AND pm_city.meta_key = '_billing_city'
        LEFT JOIN {$wpdb->postmeta} pm_country ON p.ID = pm_country.post_id AND pm_country.meta_key = '_billing_country'
        LEFT JOIN {$wpdb->postmeta} pm_customer_user ON p.ID = pm_customer_user.post_id AND pm_customer_user.meta_key = '_customer_user'
        WHERE p.post_type = 'shop_order'
        AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending')
        AND (pm_customer_user.meta_value = '0' OR pm_customer_user.meta_value IS NULL)
        AND pm_consent.meta_value IS NOT NULL
        AND pm_email.meta_value IS NOT NULL
        AND pm_email.meta_value != ''
        ORDER BY p.post_date DESC
    ");

        if (empty($guest_orders)) {
            return false;
        }

        // Utwórz katalog uploads jeśli nie istnieje
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['basedir'];

        if (!is_dir($upload_path) || !is_writable($upload_path)) {
            return false;
        }

        $filename = 'newsletter_guests_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = $upload_path . '/' . $filename;

        $file = fopen($filepath, 'w');
        if (!$file) {
            return false;
        }

        // Dodaj BOM dla prawidłowego kodowania UTF-8 w Excel
        fwrite($file, "\xEF\xBB\xBF");

        // Nagłówki CSV
        $headers = array(
            'ID Zamówienia',
            'Data zamówienia',
            'Email',
            'Imię',
            'Nazwisko',
            'Telefon',
            'Kod pocztowy',
            'Miasto',
            'Kraj',
            'Zgoda na newsletter',
            'Data zgody',
            'IP zgody'
        );

        fputcsv($file, $headers, ';');

        // Dane gości
        foreach ($guest_orders as $guest) {
            $row = array(
                $guest->order_id,
                $guest->post_date,
                $guest->billing_email,
                $guest->billing_first_name ?: '',
                $guest->billing_last_name ?: '',
                $guest->billing_phone ?: '',
                $guest->billing_postcode ?: '',
                $guest->billing_city ?: '',
                $guest->billing_country ?: '',
                $guest->consent === 'yes' ? 'Tak' : 'Nie',
                $guest->consent_timestamp ?: '',
                $guest->consent_ip ?: ''
            );

            fputcsv($file, $row, ';');
        }

        fclose($file);

        return array(
            'filename' => $filename,
            'url' => $upload_dir['baseurl'] . '/' . $filename,
            'path' => $filepath,
            'size' => filesize($filepath),
            'count' => count($guest_orders)
        );
    }

    /**
     * NOWE: Pobierz unikalne emale gości z zgodami (do przyszłego XML)
     */
    public function get_unique_guest_emails_with_consent() {
        global $wpdb;

        // Pobierz najnowszą zgodę dla każdego emaila gościa
        $unique_guests = $wpdb->get_results("
        SELECT 
            pm_email.meta_value as email,
            pm_first_name.meta_value as first_name,
            pm_last_name.meta_value as last_name,
            pm_phone.meta_value as phone,
            pm_postcode.meta_value as zip_code,
            p.post_date as order_date,
            pm_consent.meta_value as consent
        FROM {$wpdb->posts} p
        INNER JOIN (
            SELECT 
                pm_email_inner.meta_value as email,
                MAX(p_inner.post_date) as latest_date
            FROM {$wpdb->posts} p_inner
            LEFT JOIN {$wpdb->postmeta} pm_email_inner ON p_inner.ID = pm_email_inner.post_id AND pm_email_inner.meta_key = '_billing_email'
            LEFT JOIN {$wpdb->postmeta} pm_customer_user_inner ON p_inner.ID = pm_customer_user_inner.post_id AND pm_customer_user_inner.meta_key = '_customer_user'
            WHERE p_inner.post_type = 'shop_order'
            AND p_inner.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
            AND (pm_customer_user_inner.meta_value = '0' OR pm_customer_user_inner.meta_value IS NULL)
            AND pm_email_inner.meta_value IS NOT NULL
            AND pm_email_inner.meta_value != ''
            GROUP BY pm_email_inner.meta_value
        ) latest_orders ON p.post_date = latest_orders.latest_date
        LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
        LEFT JOIN {$wpdb->postmeta} pm_first_name ON p.ID = pm_first_name.post_id AND pm_first_name.meta_key = '_billing_first_name'
        LEFT JOIN {$wpdb->postmeta} pm_last_name ON p.ID = pm_last_name.post_id AND pm_last_name.meta_key = '_billing_last_name'
        LEFT JOIN {$wpdb->postmeta} pm_phone ON p.ID = pm_phone.post_id AND pm_phone.meta_key = '_billing_phone'
        LEFT JOIN {$wpdb->postmeta} pm_postcode ON p.ID = pm_postcode.post_id AND pm_postcode.meta_key = '_billing_postcode'
        LEFT JOIN {$wpdb->postmeta} pm_consent ON p.ID = pm_consent.post_id AND pm_consent.meta_key = '_newsletter_consent'
        LEFT JOIN {$wpdb->postmeta} pm_customer_user ON p.ID = pm_customer_user.post_id AND pm_customer_user.meta_key = '_customer_user'
        WHERE p.post_type = 'shop_order'
        AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
        AND (pm_customer_user.meta_value = '0' OR pm_customer_user.meta_value IS NULL)
        AND pm_email.meta_value = latest_orders.email
        AND pm_consent.meta_value = 'yes'
        ORDER BY p.post_date DESC
    ");

        return $unique_guests;
    }

}
?>