<?php
/**
 * Newsletter AI Dashboard Widget
 * Wyświetla problematycznych klientów na dashboardzie
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_AI_Dashboard_Widget {
    
    /**
     * Konstruktor
     */
    public function __construct() {
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_styles'));
    }
    
    /**
     * Dodaj widget do dashboardu
     */
    public function add_dashboard_widget() {
        // Tylko dla administratorów
        if (!current_user_can('manage_options')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'newsletter_ai_problems_widget',
            '📧 Newsletter AI - Problemy z danymi klientów',
            array($this, 'render_dashboard_widget'),
            null,
            null,
            'normal',
            'high'
        );
    }
    
    /**
     * Renderuj widget dashboardu
     */
    public function render_dashboard_widget() {
        // Sprawdź czy klasa validator istnieje
        if (!class_exists('Newsletter_AI_Customer_Validator')) {
            echo '<p style="color: #d63638;">⚠️ Błąd: Newsletter AI Customer Validator nie jest dostępny.</p>';
            return;
        }
        
        try {
            $validator = new Newsletter_AI_Customer_Validator();
            
            // Pobierz statystyki
            $summary = $validator->get_validation_summary();
            
            // Pobierz 10 najnowszych problematycznych klientów
            $problematic_customers = $this->get_recent_problematic_customers($validator);
            
            $this->render_widget_content($summary, $problematic_customers);
            
        } catch (Exception $e) {
            echo '<p style="color: #d63638;">⚠️ Błąd podczas ładowania danych: ' . esc_html($e->getMessage()) . '</p>';
        }
    }
    
    /**
     * Pobierz najnowszych problematycznych klientów
     */
    private function get_recent_problematic_customers($validator) {
        global $wpdb;
        
        $consent_field = get_option('nai_consent_field', 'newsletter_ai_consent');
        $consent_values = get_option('nai_consent_values', array('yes', '1', 'true', 'on'));
        
        // Pobierz najnowszych użytkowników
        $users = $wpdb->get_results($wpdb->prepare("
            SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered
            FROM {$wpdb->users} u
            WHERE 1=1
            ORDER BY u.user_registered DESC
            LIMIT %d
        ", 50), ARRAY_A);
        
        if (empty($users)) {
            return array();
        }
        
        // Pobierz IDs użytkowników
        $user_ids = array_column($users, 'ID');
        $users_placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
        
        // Pobierz metadane
        $meta_query = $wpdb->prepare("
            SELECT user_id, meta_key, meta_value 
            FROM {$wpdb->usermeta} 
            WHERE user_id IN ($users_placeholders)
            AND meta_key IN ('first_name', 'last_name', 'billing_first_name', 'billing_last_name', 
                            'billing_email', 'billing_phone', 'billing_postcode', %s)
        ", array_merge($user_ids, array($consent_field)));
        
        $user_meta = $wpdb->get_results($meta_query);
        
        // Grupuj metadane po user_id
        $meta_by_user = array();
        foreach ($user_meta as $meta) {
            $meta_by_user[$meta->user_id][$meta->meta_key] = $meta->meta_value;
        }
        
        // Sprawdź każdego użytkownika i zbierz tylko tych z problemami
        $problematic_customers = array();
        
        foreach ($users as $user) {
            $user_meta_data = isset($meta_by_user[$user['ID']]) ? $meta_by_user[$user['ID']] : array();
            
            // Stwórz obiekt użytkownika dla walidacji
            $user_obj = (object) $user;
            
            // Waliduj dane klienta
            $validation_result = $validator->validate_customer_data($user_obj, $user_meta_data);
            
            // Sprawdź czy użytkownik ma problemy
            $has_problems = !empty($validation_result['errors']) || !empty($validation_result['warnings']);
            
            if ($has_problems) {
                // Sprawdź zgodę na newsletter
                $consent_value = isset($user_meta_data[$consent_field]) ? $user_meta_data[$consent_field] : '';
                $has_consent = in_array(strtolower(trim($consent_value)), array_map('strtolower', $consent_values));
                
                $problematic_customers[] = array(
                    'ID' => $user['ID'],
                    'user_login' => $user['user_login'],
                    'user_email' => $user['user_email'],
                    'display_name' => $user['display_name'],
                    'user_registered' => $user['user_registered'],
                    'has_consent' => $has_consent,
                    'validation' => $validation_result
                );
                
                // Ograniczamy do 10
                if (count($problematic_customers) >= 10) {
                    break;
                }
            }
        }
        
        return $problematic_customers;
    }
    
    /**
     * Renderuj zawartość widgetu
     */
    private function render_widget_content($summary, $problematic_customers) {
        ?>
        <div class="nai-dashboard-widget">
            <!-- Szybkie statystyki -->
            <div class="nai-dashboard-stats">
                <div class="nai-dashboard-stat nai-stat-errors">
                    <span class="nai-stat-number"><?php echo esc_html($summary['errors']); ?></span>
                    <span class="nai-stat-label">Błędy</span>
                </div>
                <div class="nai-dashboard-stat nai-stat-warnings">
                    <span class="nai-stat-number"><?php echo esc_html($summary['warnings']); ?></span>
                    <span class="nai-stat-label">Ostrzeżenia</span>
                </div>
                <div class="nai-dashboard-stat nai-stat-total">
                    <span class="nai-stat-number"><?php echo esc_html($summary['total']); ?></span>
                    <span class="nai-stat-label">Łącznie</span>
                </div>
            </div>
            
            <!-- Problematyczni klienci -->
            <?php if (!empty($problematic_customers)): ?>
            <div class="nai-dashboard-problems">
                <h4 style="margin: 15px 0 10px 0; font-size: 14px; color: #1d2327;">
                    🚨 Najnowsi klienci z problemami
                </h4>
                
                <div class="nai-dashboard-customers">
                    <?php foreach ($problematic_customers as $customer): ?>
                    <div class="nai-dashboard-customer">
                        <div class="nai-customer-info">
                            <strong><?php echo esc_html($customer['user_login']); ?></strong>
                            <br>
                            <small><?php echo esc_html($customer['user_email']); ?></small>
                            <br>
                            <small style="color: #646970;">
                                ID: <?php echo esc_html($customer['ID']); ?> | 
                                <?php echo esc_html(date('Y-m-d', strtotime($customer['user_registered']))); ?>
                            </small>
                        </div>
                        <div class="nai-customer-problems">
                            <?php 
                            $problem_count = count($customer['validation']['errors']) + count($customer['validation']['warnings']);
                            $score = $customer['validation']['score'];
                            $score_class = $this->get_score_class($score);
                            ?>
                            <div class="nai-problem-badge nai-score-<?php echo esc_attr($score_class); ?>">
                                <?php echo esc_html($score); ?>%
                            </div>
                            <div class="nai-problem-details">
                                <?php if (!empty($customer['validation']['errors'])): ?>
                                    <div class="nai-problem-line nai-error">
                                        ❌ <?php echo count($customer['validation']['errors']); ?> błędów
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($customer['validation']['warnings'])): ?>
                                    <div class="nai-problem-line nai-warning">
                                        ⚠️ <?php echo count($customer['validation']['warnings']); ?> ostrzeżeń
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="nai-dashboard-no-problems">
                <div style="text-align: center; padding: 20px; color: #00a32a;">
                    <div style="font-size: 48px; margin-bottom: 10px;">✅</div>
                    <p><strong>Świetnie!</strong></p>
                    <p>Wszyscy najnowsi klienci mają poprawne dane.</p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Akcje -->
            <div class="nai-dashboard-actions">
                <a href="<?php echo admin_url('admin.php?page=newsletter-ai-test'); ?>" class="button button-primary">
                    🔍 Sprawdź wszystkich klientów
                </a>
                <a href="<?php echo admin_url('admin.php?page=newsletter-ai'); ?>" class="button button-secondary">
                    ⚙️ Ustawienia XML
                </a>
                <?php if (!empty($problematic_customers)): ?>
                <span style="margin-left: 10px; color: #d63638; font-size: 12px;">
                    <?php 
                    $total_problems = $summary['errors'] + $summary['warnings'];
                    if ($total_problems > 10) {
                        printf('i %d więcej...', $total_problems - count($problematic_customers));
                    }
                    ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .nai-dashboard-widget {
            font-size: 13px;
        }
        
        .nai-dashboard-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #c3c4c7;
        }
        
        .nai-dashboard-stat {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: #f6f7f7;
            border-radius: 4px;
        }
        
        .nai-dashboard-stat.nai-stat-errors {
            border-left: 4px solid #d63638;
        }
        
        .nai-dashboard-stat.nai-stat-warnings {
            border-left: 4px solid #dba617;
        }
        
        .nai-dashboard-stat.nai-stat-total {
            border-left: 4px solid #2271b1;
        }
        
        .nai-stat-number {
            display: block;
            font-size: 18px;
            font-weight: 700;
            line-height: 1;
        }
        
        .nai-stat-label {
            display: block;
            font-size: 11px;
            color: #646970;
            text-transform: uppercase;
            margin-top: 2px;
        }
        
        .nai-dashboard-customers {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .nai-dashboard-customer {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f1;
        }
        
        .nai-dashboard-customer:last-child {
            border-bottom: none;
        }
        
        .nai-customer-info {
            flex: 1;
            min-width: 0;
        }
        
        .nai-customer-info strong {
            color: #1d2327;
        }
        
        .nai-customer-problems {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        
        .nai-problem-badge {
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            min-width: 30px;
            text-align: center;
        }
        
        .nai-problem-badge.nai-score-excellent { background: #d1ecf1; color: #0c5460; }
        .nai-problem-badge.nai-score-good { background: #d4edda; color: #155724; }
        .nai-problem-badge.nai-score-poor { background: #fff3cd; color: #856404; }
        .nai-problem-badge.nai-score-bad { background: #f8d7da; color: #721c24; }
        
        .nai-problem-details {
            font-size: 10px;
        }
        
        .nai-problem-line {
            line-height: 1.2;
        }
        
        .nai-problem-line.nai-error { color: #d63638; }
        .nai-problem-line.nai-warning { color: #dba617; }
        
        .nai-dashboard-actions {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #c3c4c7;
            text-align: center;
        }
        
        .nai-dashboard-actions .button {
            margin: 0 5px;
        }
        
        @media (max-width: 782px) {
            .nai-dashboard-stats {
                flex-direction: column;
                gap: 8px;
            }
            
            .nai-dashboard-customer {
                flex-direction: column;
                gap: 5px;
            }
            
            .nai-customer-problems {
                justify-content: flex-start;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Pobierz klasę CSS dla wyniku
     */
    private function get_score_class($score) {
        if ($score >= 90) return 'excellent';
        if ($score >= 70) return 'good';
        if ($score >= 50) return 'poor';
        return 'bad';
    }
    
    /**
     * Enqueue styles dla dashboardu
     */
    public function enqueue_dashboard_styles($hook) {
        // Tylko na dashboardzie
        if ($hook !== 'index.php') {
            return;
        }
        
        // Dodaj inline styles jeśli potrzebne
        $custom_css = "
        .nai-dashboard-widget .nai-dashboard-customers::-webkit-scrollbar {
            width: 6px;
        }
        .nai-dashboard-widget .nai-dashboard-customers::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        .nai-dashboard-widget .nai-dashboard-customers::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        .nai-dashboard-widget .nai-dashboard-customers::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }
        ";
        
        wp_add_inline_style('dashboard', $custom_css);
    }
    
}
?>