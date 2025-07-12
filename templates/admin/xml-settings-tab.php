<?php
/**
 * Template zakładki ustawień XML
 */

if (!defined('ABSPATH')) {
    exit;
}

// Pobierz aktualne ustawienia
$consent_field = get_option('nai_consent_field', 'newsletter_ai_consent');
$consent_values = get_option('nai_consent_values', array('yes', '1', 'true', 'on'));
$original_xml_url = get_option('nai_original_xml_url', '');
$auto_regenerate = get_option('nai_auto_regenerate', true);
$debug_mode = get_option('nai_debug_mode', false);
?>

<div class="nai-xml-settings">
    
    <!-- Statystyki ostatniego generowania -->
    <?php if (!empty($stats)): ?>
    <div class="nai-stats-box">
        <h3><?php _e('Ostatnie generowanie XML', 'newsletter-ai'); ?></h3>
        <p><strong><?php _e('Data:', 'newsletter-ai'); ?></strong> <?php echo esc_html($stats['timestamp']); ?></p>
        <p><strong><?php _e('Łącznie użytkowników:', 'newsletter-ai'); ?></strong> <?php echo esc_html($stats['total_users']); ?></p>
        <p><strong><?php _e('Z zgodą:', 'newsletter-ai'); ?></strong> <?php echo esc_html($stats['users_with_consent']); ?></p>
        <p><strong><?php _e('Bez zgody:', 'newsletter-ai'); ?></strong> <?php echo esc_html($stats['users_without_consent']); ?></p>
        <p><strong><?php _e('Użyte pole zgody:', 'newsletter-ai'); ?></strong> <?php echo esc_html($stats['consent_field_used']); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Informacje o pliku XML -->
    <?php if ($xml_file_info): ?>
    <div class="nai-info-box">
        <h4><?php _e('Informacje o pliku XML', 'newsletter-ai'); ?></h4>
        <p><strong><?php _e('Rozmiar:', 'newsletter-ai'); ?></strong> <?php echo size_format($xml_file_info['size']); ?></p>
        <p><strong><?php _e('Ostatnia modyfikacja:', 'newsletter-ai'); ?></strong> <?php echo date('Y-m-d H:i:s', $xml_file_info['modified']); ?></p>
        <p><strong><?php _e('URL:', 'newsletter-ai'); ?></strong> <a href="<?php echo esc_url($xml_file_info['url']); ?>" target="_blank"><?php echo esc_html($xml_file_info['url']); ?></a></p>
    </div>
    <?php else: ?>
    <div class="nai-warning-box">
        <p><?php _e('Plik XML jeszcze nie został wygenerowany.', 'newsletter-ai'); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Przycisk generowania XML -->
    <div style="margin: 20px 0;">
        <button type="button" id="nai-generate-xml" class="button button-primary button-large">
            <?php _e('Wygeneruj XML teraz', 'newsletter-ai'); ?>
        </button>
        <span id="nai-xml-generation-status" style="margin-left: 10px;"></span>
    </div>
    
    <!-- Formularz ustawień -->
    <form method="post" action="">
        <?php wp_nonce_field('nai_xml_settings', 'nai_xml_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nai_consent_field"><?php _e('Pole zgody na newsletter', 'newsletter-ai'); ?></label>
                </th>
                <td>
                    <input type="text" id="nai_consent_field" name="nai_consent_field" value="<?php echo esc_attr($consent_field); ?>" class="regular-text" />
                    <p class="description"><?php _e('Nazwa pola meta przechowującego zgodę użytkownika na newsletter', 'newsletter-ai'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="nai_consent_values"><?php _e('Wartości oznaczające zgodę', 'newsletter-ai'); ?></label>
                </th>
                <td>
                    <input type="text" id="nai_consent_values" name="nai_consent_values" value="<?php echo esc_attr(implode(',', $consent_values)); ?>" class="regular-text" />
                    <p class="description"><?php _e('Wartości oddzielone przecinkami, które oznaczają zgodę użytkownika (np: yes,1,true,on)', 'newsletter-ai'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="nai_original_xml_url"><?php _e('URL oryginalnego pliku XML', 'newsletter-ai'); ?></label>
                </th>
                <td>
                    <input type="url" id="nai_original_xml_url" name="nai_original_xml_url" value="<?php echo esc_attr($original_xml_url); ?>" class="regular-text" />
                    <p class="description"><?php _e('URL do oryginalnego pliku XML, który ma zostać nadpisany (opcjonalne)', 'newsletter-ai'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Automatyczne regenerowanie', 'newsletter-ai'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="nai_auto_regenerate" value="1" <?php checked($auto_regenerate, true); ?> />
                        <?php _e('Automatycznie regeneruj XML przy rejestracji nowego użytkownika lub zmianie zgody', 'newsletter-ai'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Tryb debugowania', 'newsletter-ai'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="nai_debug_mode" value="1" <?php checked($debug_mode, true); ?> />
                        <?php _e('Włącz szczegółowe logowanie do pliku debug.log', 'newsletter-ai'); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="nai_save_xml_settings" class="button-primary" value="<?php _e('Zapisz ustawienia', 'newsletter-ai'); ?>" />
        </p>
    </form>
    
    <!-- Dostępne pola meta -->
    <div class="nai-info-box">
        <h4><?php _e('Dostępne pola meta związane z zgodami', 'newsletter-ai'); ?></h4>
        <?php if (!empty($consent_fields)): ?>
            <p><?php _e('Znalezione pola w bazie danych:', 'newsletter-ai'); ?></p>
            <ul>
                <?php foreach ($consent_fields as $field): ?>
                    <li>
                        <strong><?php echo esc_html($field->meta_key); ?></strong> 
                        (<?php echo esc_html($field->count); ?> <?php _e('użytkowników', 'newsletter-ai'); ?>)
                        <button type="button" class="button button-small" onclick="setConsentField('<?php echo esc_js($field->meta_key); ?>')">
                            <?php _e('Użyj tego pola', 'newsletter-ai'); ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p><?php _e('Nie znaleziono pól związanych z zgodami w bazie danych.', 'newsletter-ai'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Instrukcje -->
    <div class="nai-warning-box">
        <h4><?php _e('Instrukcje użytkowania', 'newsletter-ai'); ?></h4>
        <ol>
            <li><?php _e('Skonfiguruj pole zgody - wybierz z listy powyżej lub wprowadź własne', 'newsletter-ai'); ?></li>
            <li><?php _e('Ustaw wartości które oznaczają zgodę użytkownika', 'newsletter-ai'); ?></li>
            <li><?php _e('Opcjonalnie podaj URL oryginalnego pliku XML do nadpisania', 'newsletter-ai'); ?></li>
            <li><?php _e('Zapisz ustawienia i wygeneruj XML', 'newsletter-ai'); ?></li>
            <li><?php _e('Sprawdź zakładkę "Użytkownicy i zgody" aby zarządzać zgodami', 'newsletter-ai'); ?></li>
        </ol>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Obsługa generowania XML
    $('#nai-generate-xml').on('click', function() {
        var $button = $(this);
        var $status = $('#nai-xml-generation-status');
        
        $button.prop('disabled', true).text(newsletterAI.strings.generating_xml);
        $status.html('<span class="spinner is-active" style="float: none; margin: 0 5px;"></span>');
        
        $.post(newsletterAI.ajax_url, {
            action: 'nai_generate_xml',
            nonce: newsletterAI.nonce
        }, function(response) {
            if (response.success) {
                $status.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                
                // Odśwież stronę po 2 sekundach żeby pokazać nowe statystyki
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                $status.html('<span style="color: red;">✗ ' + (response.data || newsletterAI.strings.error_occurred) + '</span>');
            }
        }).fail(function() {
            $status.html('<span style="color: red;">✗ ' + newsletterAI.strings.error_occurred + '</span>');
        }).always(function() {
            $button.prop('disabled', false).text('<?php _e('Wygeneruj XML teraz', 'newsletter-ai'); ?>');
        });
    });
});

// Funkcja do ustawiania pola zgody
function setConsentField(fieldName) {
    document.getElementById('nai_consent_field').value = fieldName;
}
</script>