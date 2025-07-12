<?php
/**
 * Newsletter AI User Profile Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_AI_User_Profile {
    
    private $consent_field = 'newsletter_ai_consent';
    
    public function __construct() {
        $this->consent_field = get_option('nai_consent_field', 'newsletter_ai_consent');
        
        // Hooki dla profilu użytkownika
        add_action('show_user_profile', array($this, 'add_consent_field_to_profile'));
        add_action('edit_user_profile', array($this, 'add_consent_field_to_profile'));
        add_action('personal_options_update', array($this, 'save_consent_field'));
        add_action('edit_user_profile_update', array($this, 'save_consent_field'));
        
        // CSS dla profilu
        add_action('admin_head-profile.php', array($this, 'add_profile_styles'));
        add_action('admin_head-user-edit.php', array($this, 'add_profile_styles'));
    }
    
    /**
     * Dodaj pole zgody do profilu użytkownika
     */
    public function add_consent_field_to_profile($user) {
        // Sprawdź uprawnienia
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }
        
        $consent_value = get_user_meta($user->ID, $this->consent_field, true);
        $consent_values = get_option('nai_consent_values', array('yes', '1', 'true', 'on'));
        $has_consent = in_array(strtolower($consent_value), array_map('strtolower', $consent_values));
        
        // Pobierz statystyki
        $all_values = $this->get_consent_value_stats();
        ?>
        
        <h2 id="newsletter-ai-section"><?php _e('Newsletter AI - Zgoda na newsletter', 'newsletter-ai'); ?></h2>
        
        <table class="form-table nai-profile-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="<?php echo esc_attr($this->consent_field); ?>">
                        <?php _e('Zgoda na newsletter', 'newsletter-ai'); ?>
                    </label>
                </th>
                <td>
                    <div class="nai-consent-field">
                        <label class="nai-switch-profile">
                            <input type="checkbox" 
                                   name="<?php echo esc_attr($this->consent_field); ?>" 
                                   id="<?php echo esc_attr($this->consent_field); ?>"
                                   value="yes" 
                                   <?php checked($has_consent, true); ?> />
                            <span class="nai-slider-profile"></span>
                        </label>
                        <span class="nai-consent-label">
                            <?php echo $has_consent ? __('Użytkownik wyraził zgodę', 'newsletter-ai') : __('Użytkownik nie wyraził zgody', 'newsletter-ai'); ?>
                        </span>
                    </div>
                    
                    <p class="description">
                        <?php _e('Określa czy użytkownik wyraził zgodę na otrzymywanie newslettera.', 'newsletter-ai'); ?>
                        <br>
                        <strong><?php _e('Pole meta:', 'newsletter-ai'); ?></strong> <code><?php echo esc_html($this->consent_field); ?></code>
                        <br>
                        <strong><?php _e('Aktualna wartość:', 'newsletter-ai'); ?></strong> 
                        <code><?php echo esc_html($consent_value ?: __('brak', 'newsletter-ai')); ?></code>
                    </p>
                    
                    <?php if (!empty($all_values)): ?>
                    <details class="nai-consent-details">
                        <summary><?php _e('Możliwe wartości w systemie', 'newsletter-ai'); ?></summary>
                        <div class="nai-consent-values">
                            <?php foreach ($all_values as $value): ?>
                                <div class="nai-value-item">
                                    <code><?php echo esc_html($value->meta_value); ?></code>
                                    <span class="nai-value-count">(<?php echo $value->count; ?> <?php _e('użytkowników', 'newsletter-ai'); ?>)</span>
                                    <?php if (in_array(strtolower($value->meta_value), array_map('strtolower', $consent_values))): ?>
                                        <span class="nai-value-consent">✓ <?php _e('oznacza zgodę', 'newsletter-ai'); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <?php endif; ?>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Zaawansowane opcje', 'newsletter-ai'); ?></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><?php _e('Zaawansowane opcje zgody', 'newsletter-ai'); ?></legend>
                        
                        <label for="nai_consent_custom_value">
                            <input type="radio" name="nai_consent_mode" id="nai_consent_custom_value" value="custom" />
                            <?php _e('Ustaw własną wartość:', 'newsletter-ai'); ?>
                        </label>
                        <input type="text" 
                               name="nai_custom_consent_value" 
                               placeholder="<?php _e('np. no, false, 0', 'newsletter-ai'); ?>"
                               class="regular-text" />
                        
                        <br><br>
                        
                        <label for="nai_consent_toggle_value">
                            <input type="radio" name="nai_consent_mode" id="nai_consent_toggle_value" value="toggle" checked />
                            <?php _e('Użyj przełącznika powyżej', 'newsletter-ai'); ?>
                        </label>
                        
                        <p class="description">
                            <?php _e('Przełącznik automatycznie ustawia wartość "yes" lub "no".', 'newsletter-ai'); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Historia zmian', 'newsletter-ai'); ?></th>
                <td>
                    <?php $this->show_consent_history($user->ID); ?>
                </td>
            </tr>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            // Obsługa przełącznika zgody
            $('#<?php echo esc_js($this->consent_field); ?>').on('change', function() {
                var isChecked = $(this).is(':checked');
                $('.nai-consent-label').text(isChecked ? 'Użytkownik wyraził zgodę' : 'Użytkownik nie wyraził zgody');
            });
            
            // Obsługa trybu zaawansowanego
            $('input[name="nai_consent_mode"]').on('change', function() {
                var mode = $(this).val();
                if (mode === 'custom') {
                    $('#<?php echo esc_js($this->consent_field); ?>').prop('disabled', true);
                    $('input[name="nai_custom_consent_value"]').focus();
                } else {
                    $('#<?php echo esc_js($this->consent_field); ?>').prop('disabled', false);
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Zapisz pole zgody
     */
    public function save_consent_field($user_id) {
        // Sprawdź uprawnienia
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        
        // Sprawdź nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'update-user_' . $user_id)) {
            return;
        }
        
        $old_value = get_user_meta($user_id, $this->consent_field, true);
        
        // Sprawdź tryb
        $mode = isset($_POST['nai_consent_mode']) ? $_POST['nai_consent_mode'] : 'toggle';
        
        if ($mode === 'custom' && !empty($_POST['nai_custom_consent_value'])) {
            // Tryb zaawansowany - własna wartość
            $new_value = sanitize_text_field($_POST['nai_custom_consent_value']);
        } else {
            // Tryb przełącznika
            $new_value = isset($_POST[$this->consent_field]) ? 'yes' : 'no';
        }
        
        // Zapisz nową wartość
        update_user_meta($user_id, $this->consent_field, $new_value);
        
        // Zapisz historię zmian
        if ($old_value !== $new_value) {
            $this->save_consent_history($user_id, $old_value, $new_value);
        }
        
        // Log dla debugowania
        if (get_option('nai_debug_mode', false)) {
            error_log("Newsletter AI: Zmiana zgody użytkownika $user_id: '$old_value' -> '$new_value'");
        }
    }
    
    /**
     * Zapisz historię zmian zgody
     */
    private function save_consent_history($user_id, $old_value, $new_value) {
        $history = get_user_meta($user_id, $this->consent_field . '_history', true);
        if (!is_array($history)) {
            $history = array();
        }
        
        $history[] = array(
            'timestamp' => current_time('mysql'),
            'old_value' => $old_value,
            'new_value' => $new_value,
            'changed_by' => get_current_user_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
        
        // Zachowaj tylko ostatnie 20 zmian
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }
        
        update_user_meta($user_id, $this->consent_field . '_history', $history);
    }
    
    /**
     * Pokaż historię zmian zgody
     */
    private function show_consent_history($user_id) {
        $history = get_user_meta($user_id, $this->consent_field . '_history', true);
        
        if (empty($history) || !is_array($history)) {
            echo '<p class="description">' . __('Brak historii zmian.', 'newsletter-ai') . '</p>';
            return;
        }
        
        echo '<div class="nai-consent-history">';
        echo '<p><strong>' . __('Ostatnie zmiany:', 'newsletter-ai') . '</strong></p>';
        echo '<div class="nai-history-list">';
        
        // Pokaż ostatnie 5 zmian
        $recent_history = array_slice(array_reverse($history), 0, 5);
        
        foreach ($recent_history as $change) {
            $changer = get_userdata($change['changed_by']);
            $changer_name = $changer ? $changer->display_name : __('Nieznany', 'newsletter-ai');
            
            echo '<div class="nai-history-item">';
            echo '<span class="nai-history-date">' . esc_html($change['timestamp']) . '</span>';
            echo '<span class="nai-history-change">';
            echo '<code>' . esc_html($change['old_value'] ?: 'brak') . '</code>';
            echo ' → ';
            echo '<code>' . esc_html($change['new_value']) . '</code>';
            echo '</span>';
            echo '<span class="nai-history-user">(' . esc_html($changer_name) . ')</span>';
            echo '</div>';
        }
        
        if (count($history) > 5) {
            echo '<p class="description">' . sprintf(__('... i %d więcej zmian', 'newsletter-ai'), count($history) - 5) . '</p>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Pobierz statystyki wartości zgody
     */
    private function get_consent_value_stats() {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT meta_value, COUNT(*) as count
            FROM {$wpdb->usermeta}
            WHERE meta_key = %s AND meta_value != ''
            GROUP BY meta_value
            ORDER BY count DESC
            LIMIT 10
        ", $this->consent_field));
    }
    
    /**
     * Dodaj style CSS do profilu
     */
    public function add_profile_styles() {
        ?>
        <style>
        .nai-profile-table {
            border-top: 3px solid #2271b1;
        }
        
        .nai-consent-field {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        
        .nai-switch-profile {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 25px;
        }
        
        .nai-switch-profile input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .nai-slider-profile {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 25px;
        }
        
        .nai-slider-profile:before {
            position: absolute;
            content: "";
            height: 19px;
            width: 19px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        
        input:checked + .nai-slider-profile {
            background-color: #2271b1;
        }
        
        input:checked + .nai-slider-profile:before {
            transform: translateX(25px);
        }
        
        .nai-consent-label {
            font-weight: 600;
            color: #2c3338;
        }
        
        .nai-consent-details {
            margin-top: 12px;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 12px;
            background: #f6f7f7;
        }
        
        .nai-consent-details summary {
            font-weight: 600;
            cursor: pointer;
            padding: 4px 0;
        }
        
        .nai-consent-values {
            margin-top: 8px;
        }
        
        .nai-value-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
            border-bottom: 1px solid #dcdcde;
        }
        
        .nai-value-item:last-child {
            border-bottom: none;
        }
        
        .nai-value-count {
            color: #646970;
            font-size: 12px;
        }
        
        .nai-value-consent {
            color: #008a00;
            font-size: 12px;
            font-weight: 600;
        }
        
        .nai-consent-history {
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 12px;
            background: #fff;
            max-width: 600px;
        }
        
        .nai-history-list {
            font-family: monospace;
            font-size: 12px;
        }
        
        .nai-history-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 0;
            border-bottom: 1px solid #f0f0f1;
        }
        
        .nai-history-item:last-child {
            border-bottom: none;
        }
        
        .nai-history-date {
            color: #646970;
            min-width: 150px;
        }
        
        .nai-history-change {
            flex: 1;
        }
        
        .nai-history-user {
            color: #2271b1;
            font-style: italic;
        }
        
        #newsletter-ai-section {
            border-top: 1px solid #c3c4c7;
            padding-top: 20px;
            margin-top: 20px;
        }
        </style>
        <?php
    }
}
?>