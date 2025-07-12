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
            // Checkout tylko jeśli użytkownik nie ma zgody
            add_action('woocommerce_checkout_billing', array($this, 'add_checkout_consent_field'), 20);
            add_action('woocommerce_checkout_process', array($this, 'validate_checkout_consent'));
            add_action('woocommerce_checkout_update_user_meta', array($this, 'save_checkout_consent'), 10, 2);
            
            // Dodatkowy hook dla gości
            add_action('woocommerce_after_checkout_billing_form', array($this, 'add_checkout_consent_field_for_guests'), 10);
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
        // Sprawdź czy użytkownik jest zalogowany i ma zgodę
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $consent_value = get_user_meta($user_id, $this->consent_field, true);
            $consent_values = get_option('nai_consent_values', array('yes', '1', 'true', 'on'));
            
            if (in_array(strtolower($consent_value), array_map('strtolower', $consent_values))) {
                return; // Użytkownik już ma zgodę
            }
        }
        
        $text = get_option('nai_consent_text', __('Wyrażam zgodę na otrzymywanie newslettera', 'newsletter-ai'));
        $required = get_option('nai_consent_required', false);
        
        echo '<div class="nai-checkout-consent">';
        echo '<h3>' . __('Newsletter', 'newsletter-ai') . '</h3>';
        
        // Sprawdź czy $checkout jest obiektem
        $default_value = '';
        if (is_object($checkout) && method_exists($checkout, 'get_value')) {
            $default_value = $checkout->get_value('newsletter_consent');
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
        if ($required) echo '<span class="required">*</span>';
        echo '</label>';
        echo '</p>';
        echo '</div>';
    }
    
    /**
     * Dodaj pole zgody do MyAccount
     */
    public function add_myaccount_consent_field() {
        $user_id = get_current_user_id();
        if (!$user_id) return;
        
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
            return; // Brak ID użytkownika
        }
        
        $consent_value = isset($_POST['newsletter_consent']) ? 'yes' : 'no';
        update_user_meta($user_id, $this->consent_field, $consent_value);
        
        // Log
        if (get_option('nai_debug_mode', false)) {
            error_log("Newsletter AI: Checkout - user $user_id, consent: $consent_value");
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
}
?>