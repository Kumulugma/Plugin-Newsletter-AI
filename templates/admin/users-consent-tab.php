<?php
/**
 * Template zakładki użytkowników i zgód
 */

if (!defined('ABSPATH')) {
    exit;
}

$consent_field = get_option('nai_consent_field', 'newsletter_ai_consent');
?>

<div class="nai-users-consent">
    
    <!-- Statystyki zgód -->
    <div class="nai-stats-grid">
        <div class="nai-stat-box">
            <div class="nai-stat-number"><?php echo esc_html($statistics['total_users']); ?></div>
            <div class="nai-stat-label"><?php _e('Łącznie użytkowników', 'newsletter-ai'); ?></div>
        </div>
        <div class="nai-stat-box">
            <div class="nai-stat-number"><?php echo esc_html($statistics['users_with_field']); ?></div>
            <div class="nai-stat-label"><?php _e('Z polem zgody', 'newsletter-ai'); ?></div>
        </div>
        <div class="nai-stat-box">
            <div class="nai-stat-number"><?php echo esc_html($statistics['users_without_field']); ?></div>
            <div class="nai-stat-label"><?php _e('Bez pola zgody', 'newsletter-ai'); ?></div>
        </div>
        <div class="nai-stat-box">
            <div class="nai-stat-number"><?php echo esc_html($statistics['users_with_consent']); ?></div>
            <div class="nai-stat-label"><?php _e('Wyraziło zgodę', 'newsletter-ai'); ?></div>
        </div>
        <div class="nai-stat-box">
            <div class="nai-stat-number"><?php echo esc_html($statistics['consent_percentage']); ?>%</div>
            <div class="nai-stat-label"><?php _e('Procent zgód', 'newsletter-ai'); ?></div>
        </div>
    </div>
    
    <!-- Informacje o aktualnym polu zgody -->
    <div class="nai-info-box">
        <h4><?php _e('Aktualne pole zgody', 'newsletter-ai'); ?></h4>
        <p><strong><?php _e('Pole meta:', 'newsletter-ai'); ?></strong> <code><?php echo esc_html($consent_field); ?></code></p>
        <p><strong><?php _e('Wartości oznaczające zgodę:', 'newsletter-ai'); ?></strong> 
            <?php 
            $consent_values = get_option('nai_consent_values', array('yes', '1', 'true', 'on'));
            echo '<code>' . esc_html(implode(', ', $consent_values)) . '</code>';
            ?>
        </p>
        
        <?php if (!empty($sample_values)): ?>
        <p><strong><?php _e('Przykładowe wartości w bazie:', 'newsletter-ai'); ?></strong></p>
        <ul>
            <?php foreach ($sample_values as $value): ?>
                <li><code><?php echo esc_html($value->meta_value); ?></code> (<?php echo esc_html($value->count); ?> <?php _e('użytkowników', 'newsletter-ai'); ?>)</li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    
    <!-- Narzędzia zarządzania -->
    <div class="nai-users-controls">
        <h3><?php _e('Narzędzia zarządzania', 'newsletter-ai'); ?></h3>
        
        <div style="margin-bottom: 15px;">
            <button type="button" id="nai-bulk-create-fields" class="button button-secondary">
                <?php _e('Utwórz pole zgody dla wszystkich użytkowników bez tego pola', 'newsletter-ai'); ?>
            </button>
            <p class="description">
                <?php _e('Ta akcja utworzy pole zgody z wartością "no" dla wszystkich użytkowników, którzy jeszcze go nie mają.', 'newsletter-ai'); ?>
            </p>
        </div>
        
        <div style="margin-bottom: 15px;">
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=newsletter-ai-users&action=export_users'), 'export_users'); ?>" class="button button-secondary">
                <?php _e('Eksportuj do CSV', 'newsletter-ai'); ?>
            </a>
            <p class="description">
                <?php _e('Pobierz plik CSV ze wszystkimi użytkownikami i informacją o ich zgodach.', 'newsletter-ai'); ?>
            </p>
        </div>
        
        <?php if ($statistics['users_without_field'] > 0): ?>
        <div class="nai-warning-box">
            <p><strong><?php _e('Uwaga!', 'newsletter-ai'); ?></strong> 
            <?php printf(__('Masz %d użytkowników bez pola zgody. Kliknij przycisk powyżej aby je utworzyć.', 'newsletter-ai'), $statistics['users_without_field']); ?>
            </p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Wyszukiwarka użytkowników -->
    <div class="nai-search-box">
        <h3><?php _e('Lista użytkowników', 'newsletter-ai'); ?></h3>
        <label for="nai-user-search"><?php _e('Wyszukaj użytkownika:', 'newsletter-ai'); ?></label>
        <input type="text" id="nai-user-search" placeholder="<?php _e('Login, email lub nazwa...', 'newsletter-ai'); ?>" />
    </div>
    
    <!-- Tabela użytkowników -->
    <div id="nai-users-table-container">
        <p><?php _e('Ładowanie użytkowników...', 'newsletter-ai'); ?></p>
    </div>
    
    <!-- Dostępne pola zgody -->
    <?php if (!empty($consent_fields)): ?>
    <div class="nai-info-box">
        <h4><?php _e('Wszystkie dostępne pola związane z zgodami', 'newsletter-ai'); ?></h4>
        <table class="wp-list-table widefat">
            <thead>
                <tr>
                    <th><?php _e('Nazwa pola', 'newsletter-ai'); ?></th>
                    <th><?php _e('Liczba użytkowników', 'newsletter-ai'); ?></th>
                    <th><?php _e('Akcje', 'newsletter-ai'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($consent_fields as $field): ?>
                <tr>
                    <td><code><?php echo esc_html($field->meta_key); ?></code></td>
                    <td><?php echo esc_html($field->count); ?></td>
                    <td>
                        <a href="<?php echo admin_url('options-general.php?page=newsletter-ai&field=' . urlencode($field->meta_key)); ?>" class="button button-small">
                            <?php _e('Użyj jako pole zgody', 'newsletter-ai'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Instrukcje -->
    <div class="nai-warning-box">
        <h4><?php _e('Jak zarządzać zgodami użytkowników', 'newsletter-ai'); ?></h4>
        <ol>
            <li><?php _e('Upewnij się, że wszyscy użytkownicy mają pole zgody (użyj przycisku "Utwórz pole zgody")', 'newsletter-ai'); ?></li>
            <li><?php _e('Sprawdź statusy zgód w tabeli poniżej', 'newsletter-ai'); ?></li>
            <li><?php _e('Aby zmienić zgodę użytkownika, kliknij przycisk "Edytuj" i przejdź do profilu użytkownika', 'newsletter-ai'); ?></li>
            <li><?php _e('Po zmianach wygeneruj ponownie XML w zakładce "Ustawienia XML"', 'newsletter-ai'); ?></li>
            <li><?php _e('Eksportuj dane do CSV aby mieć kopię zapasową', 'newsletter-ai'); ?></li>
        </ol>
    </div>
</div>
?>