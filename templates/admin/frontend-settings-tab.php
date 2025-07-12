<?php
/**
 * Template zakładki ustawień frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

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

<div class="nai-frontend-settings">
    
    <!-- Statystyki frontend -->
    <?php if (!empty($frontend_stats)): ?>
    <div class="nai-stats-box">
        <h3><?php _e('Statystyki zgód z frontend', 'newsletter-ai'); ?></h3>
        <p><strong><?php _e('Rejestracje z zgodą:', 'newsletter-ai'); ?></strong> <?php echo esc_html($frontend_stats['registrations_with_consent'] ?? 0); ?></p>
        <p><strong><?php _e('Checkout z zgodą:', 'newsletter-ai'); ?></strong> <?php echo esc_html($frontend_stats['checkout_with_consent'] ?? 0); ?></p>
        <p><strong><?php _e('Zmiany w MyAccount:', 'newsletter-ai'); ?></strong> <?php echo esc_html($frontend_stats['myaccount_changes'] ?? 0); ?></p>
        <p><strong><?php _e('Ostatnia aktualizacja:', 'newsletter-ai'); ?></strong> <?php echo esc_html($frontend_stats['last_update'] ?? 'nigdy'); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Formularz ustawień -->
    <form method="post" action="">
        <?php wp_nonce_field('nai_frontend_settings', 'nai_frontend_nonce'); ?>
        
        <h2><?php _e('Tekst zgody', 'newsletter-ai'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nai_consent_text"><?php _e('Tekst zgody na newsletter', 'newsletter-ai'); ?></label>
                </th>
                <td>
                    <input type="text" id="nai_consent_text" name="nai_consent_text" value="<?php echo esc_attr($consent_text); ?>" class="large-text" />
                    <p class="description"><?php _e('Tekst wyświetlany przy checkbox\'ie zgody na newsletter', 'newsletter-ai'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Wymagana zgoda', 'newsletter-ai'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="nai_consent_required" value="1" <?php checked($consent_required, true); ?> />
                        <?php _e('Zgoda na newsletter jest wymagana (pole obowiązkowe)', 'newsletter-ai'); ?>
                    </label>
                    <p class="description"><?php _e('Jeśli zaznaczone, użytkownicy muszą wyrazić zgodę aby się zarejestrować/złożyć zamówienie', 'newsletter-ai'); ?></p>
                </td>
            </tr>
        </table>
        
        <h2><?php _e('Miejsca wyświetlania', 'newsletter-ai'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Strona rejestracji', 'newsletter-ai'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="nai_show_on_registration" value="1" <?php checked($show_on_registration, true); ?> />
                        <?php _e('Pokaż checkbox zgody na stronie rejestracji WordPress i WooCommerce', 'newsletter-ai'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Checkout (kasa)', 'newsletter-ai'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="nai_show_on_checkout" value="1" <?php checked($show_on_checkout, true); ?> />
                        <?php _e('Pokaż checkbox zgody podczas checkout (tylko jeśli użytkownik nie ma zgody)', 'newsletter-ai'); ?>
                    </label>
                    <p class="description"><?php _e('Pole będzie widoczne tylko dla użytkowników którzy jeszcze nie wyrazili zgody', 'newsletter-ai'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('MyAccount (moje konto)', 'newsletter-ai'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="nai_show_in_myaccount" value="1" <?php checked($show_in_myaccount, true); ?> />
                        <?php _e('Pokaż przełącznik zgody w profilu klienta WooCommerce', 'newsletter-ai'); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <h2><?php _e('Wygląd i style', 'newsletter-ai'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Ładuj style CSS', 'newsletter-ai'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="nai_load_frontend_styles" value="1" <?php checked($load_frontend_styles, true); ?> />
                        <?php _e('Automatycznie ładuj style CSS dla pól zgody', 'newsletter-ai'); ?>
                    </label>
                    <p class="description"><?php _e('Wyłącz jeśli chcesz używać własnych stylów CSS', 'newsletter-ai'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="nai_frontend_primary_color"><?php _e('Kolor główny', 'newsletter-ai'); ?></label>
                </th>
                <td>
                    <input type="color" id="nai_frontend_primary_color" name="nai_frontend_primary_color" value="<?php echo esc_attr($primary_color); ?>" />
                    <p class="description"><?php _e('Kolor używany dla ramek, nagłówków i akcentów', 'newsletter-ai'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="nai_frontend_border_radius"><?php _e('Zaokrąglenie rogów', 'newsletter-ai'); ?></label>
                </th>
                <td>
                    <input type="text" id="nai_frontend_border_radius" name="nai_frontend_border_radius" value="<?php echo esc_attr($border_radius); ?>" class="small-text" />
                    <p class="description"><?php _e('Zaokrąglenie rogów dla pól zgody (np. 4px, 8px, 0px)', 'newsletter-ai'); ?></p>
                </td>
            </tr>
        </table>
        
        <h2><?php _e('Testowanie', 'newsletter-ai'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Podgląd checkbox\'a', 'newsletter-ai'); ?></th>
                <td>
                    <div class="nai-preview-wrapper">
                        <label>
                            <input type="checkbox" class="nai-preview-checkbox" />
                            <span class="nai-preview-text"><?php echo esc_html($consent_text); ?></span>
                            <?php if ($consent_required): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                    </div>
                    <p class="description"><?php _e('Tak będzie wyglądał checkbox na frontend', 'newsletter-ai'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Linki testowe', 'newsletter-ai'); ?></th>
                <td>
                    <p>
                        <a href="<?php echo wp_registration_url(); ?>" target="_blank" class="button button-secondary">
                            <?php _e('Otwórz stronę rejestracji', 'newsletter-ai'); ?>
                        </a>
                        
                        <?php if (class_exists('WooCommerce')): ?>
                        <a href="<?php echo wc_get_page_permalink('myaccount'); ?>" target="_blank" class="button button-secondary">
                            <?php _e('Otwórz MyAccount', 'newsletter-ai'); ?>
                        </a>
                        
                        <a href="<?php echo wc_get_page_permalink('checkout'); ?>" target="_blank" class="button button-secondary">
                            <?php _e('Otwórz Checkout', 'newsletter-ai'); ?>
                        </a>
                        <?php endif; ?>
                    </p>
                    <p class="description"><?php _e('Sprawdź jak wyglądają pola zgody na poszczególnych stronach', 'newsletter-ai'); ?></p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="nai_save_frontend_settings" class="button-primary" value="<?php _e('Zapisz ustawienia frontend', 'newsletter-ai'); ?>" />
        </p>
    </form>
    
    <!-- Instrukcje -->
    <div class="nai-info-box">
        <h4><?php _e('Jak działa system zgód frontend', 'newsletter-ai'); ?></h4>
        <ol>
            <li><?php _e('Checkbox zgody pojawia się w miejscach które zaznaczysz powyżej', 'newsletter-ai'); ?></li>
            <li><?php _e('Użytkownicy mogą wyrazić lub wycofać zgodę w różnych momentach', 'newsletter-ai'); ?></li>
            <li><?php _e('Wszystkie zmiany są zapisywane w historii dla każdego użytkownika', 'newsletter-ai'); ?></li>
            <li><?php _e('Checkout pokazuje pole tylko użytkownikom bez zgody', 'newsletter-ai'); ?></li>
            <li><?php _e('MyAccount pozwala użytkownikom zarządzać zgodą samodzielnie', 'newsletter-ai'); ?></li>
        </ol>
    </div>
    
    <!-- Custom CSS -->
    <div class="nai-warning-box">
        <h4><?php _e('Własne style CSS', 'newsletter-ai'); ?></h4>
        <p><?php _e('Jeśli chcesz używać własnych stylów, wyłącz automatyczne ładowanie CSS i dodaj te selektory do swojego motywu:', 'newsletter-ai'); ?></p>
        <pre><code>.nai-consent-wrapper { /* kontener checkbox'a */ }
.nai-consent-checkbox { /* sam checkbox */ }
.nai-checkout-consent { /* sekcja w checkout */ }
.nai-myaccount-consent { /* sekcja w MyAccount */ }</code></pre>
    </div>
    
    <!-- Live Preview -->
    <div class="nai-info-box">
        <h4><?php _e('Podgląd na żywo', 'newsletter-ai'); ?></h4>
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

<script>
jQuery(document).ready(function($) {
    // Live preview aktualizacji tekstu
    $('#nai_consent_text').on('input', function() {
        var newText = $(this).val();
        $('#preview-text-registration, #preview-text-checkout, #preview-text-myaccount').text(newText);
    });
    
    // Live preview wymagalności
    $('input[name="nai_consent_required"]').on('change', function() {
        var isRequired = $(this).is(':checked');
        $('#preview-required-registration, #preview-required-checkout').toggle(isRequired);
    });
    
    // Live preview kolorów
    $('#nai_frontend_primary_color').on('change', function() {
        var color = $(this).val();
        updatePreviewStyles(color, $('#nai_frontend_border_radius').val());
    });
    
    $('#nai_frontend_border_radius').on('input', function() {
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

pre {
    background: #f1f1f1;
    padding: 10px;
    border-radius: 4px;
    overflow-x: auto;
    font-size: 12px;
}
</style>
<?php