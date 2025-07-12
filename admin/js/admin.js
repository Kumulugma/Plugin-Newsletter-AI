/**
 * Newsletter AI - Admin JavaScript
 * Z debugowaniem AJAX
 */

// Sprawdź czy jQuery jest dostępne globalnie
if (typeof jQuery !== 'undefined') {
    initNewsletterAI(jQuery);
} else if (typeof $ !== 'undefined') {
    initNewsletterAI($);
} else {
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof jQuery !== 'undefined') {
            initNewsletterAI(jQuery);
        } else {
            initNewsletterAIVanilla();
        }
    });
}

function initNewsletterAI($) {
    'use strict';
    
    $(document).ready(function() {
        // Debug: sprawdź czy obiekt newsletterAI istnieje
        console.log('Newsletter AI: Sprawdzanie obiektu newsletterAI', typeof newsletterAI);
        if (typeof newsletterAI !== 'undefined') {
            console.log('Newsletter AI: Obiekt newsletterAI:', newsletterAI);
        }
        
        initializeNewsletterAI();
    });
    
    function initializeNewsletterAI() {
        // Sprawdź czy obiekt newsletterAI istnieje
        if (typeof newsletterAI === 'undefined') {
            console.error('Newsletter AI: Brak obiektu newsletterAI - sprawdź wp_localize_script');
            showError('Błąd konfiguracji: brak danych AJAX');
            return;
        }
        
        // Debug: sprawdź zawartość newsletterAI
        console.log('Newsletter AI: ajax_url =', newsletterAI.ajax_url);
        console.log('Newsletter AI: nonce =', newsletterAI.nonce);
        
        // Inicjalizuj komponenty w zależności od strony
        if ($('.nai-users-consent').length) {
            console.log('Newsletter AI: Znaleziono .nai-users-consent - inicjalizacja strony użytkowników');
            initializeUsersPage();
        }
        
        if ($('.nai-xml-settings').length || $('#nai-generate-xml').length) {
            console.log('Newsletter AI: Inicjalizacja strony XML');
            initializeXMLPage();
        }
        
        if ($('.nai-nav-tabs').length) {
            initializeTabs();
        }
        
        // Globalne funkcje
        initializeGlobalComponents();
    }
    
    /**
     * Inicjalizacja tabów
     */
    function initializeTabs() {
        $('.nai-nav-tab').on('click', function(e) {
            e.preventDefault();
            
            var tab = $(this).data('tab');
            
            // Usuń aktywne klasy
            $('.nai-nav-tab').removeClass('active');
            $('.nai-tab-content').hide();
            
            // Dodaj aktywne klasy
            $(this).addClass('active');
            $('#tab-' + tab).show();
        });
    }
    
    /**
     * Inicjalizacja strony użytkowników
     */
    function initializeUsersPage() {
        console.log('Newsletter AI: Konfiguracja strony użytkowników');
        
        // Sprawdź czy kontener tabeli istnieje
        if ($('#nai-users-table-container').length === 0) {
            console.error('Newsletter AI: Nie znaleziono kontenera #nai-users-table-container');
            return;
        }
        
        // Załaduj tabelę użytkowników
        loadUsersTable(1);
        
        // Obsługa wyszukiwania z debounce
        var searchTimeout;
        $('#nai-user-search').on('keyup', function() {
            var search = $(this).val();
            console.log('Newsletter AI: Wyszukiwanie:', search);
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                loadUsersTable(1, search);
            }, 500);
        });
        
        // Obsługa zbiorczego tworzenia pól
        $('#nai-bulk-create-fields').off('click').on('click', function(e) {
            e.preventDefault();
            console.log('Newsletter AI: Kliknięto przycisk bulk create');
            
            var confirmMessage = 'Czy na pewno chcesz utworzyć pole zgody dla wszystkich użytkowników bez tego pola?';
            if (typeof newsletterAI !== 'undefined' && newsletterAI.strings && newsletterAI.strings.confirm_bulk_create) {
                confirmMessage = newsletterAI.strings.confirm_bulk_create;
            }
            
            if (confirm(confirmMessage)) {
                bulkCreateConsentFields();
            }
        });
    }
    
    /**
     * Inicjalizacja strony XML
     */
    function initializeXMLPage() {
        // Obsługa generowania XML
        $('#nai-generate-xml').off('click').on('click', function(e) {
            e.preventDefault();
            generateXML();
        });
    }
    
    /**
     * Inicjalizacja globalnych komponentów
     */
    function initializeGlobalComponents() {
        // Potwierdzenia dla niebezpiecznych akcji
        $('.nai-confirm-action').off('click').on('click', function(e) {
            var message = $(this).data('confirm') || 'Czy na pewno chcesz wykonać tę akcję?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    /**
     * Załaduj tabelę użytkowników
     */
    function loadUsersTable(page, search) {
        page = page || 1;
        search = search || '';
        
        console.log('Newsletter AI: loadUsersTable - page:', page, 'search:', search);
        
        var $container = $('#nai-users-table-container');
        if ($container.length === 0) {
            console.error('Newsletter AI: Kontener #nai-users-table-container nie istnieje');
            return;
        }
        
        $container.html('<div class="nai-loading"><div class="nai-spinner"></div> Ładowanie użytkowników...</div>');
        
        // Sprawdź czy mamy dane AJAX
        if (typeof newsletterAI === 'undefined') {
            console.error('Newsletter AI: newsletterAI object nie istnieje');
            showError('Błąd: brak obiektu newsletterAI');
            return;
        }
        
        if (!newsletterAI.ajax_url) {
            console.error('Newsletter AI: ajax_url nie istnieje:', newsletterAI.ajax_url);
            showError('Błąd: brak ajax_url');
            return;
        }
        
        if (!newsletterAI.nonce) {
            console.error('Newsletter AI: nonce nie istnieje:', newsletterAI.nonce);
            showError('Błąd: brak nonce');
            return;
        }
        
        console.log('Newsletter AI: Wysyłanie AJAX request do:', newsletterAI.ajax_url);
        console.log('Newsletter AI: Z danymi:', {
            action: 'nai_get_users_table',
            nonce: newsletterAI.nonce,
            page: page,
            search: search
        });
        
        $.ajax({
            url: newsletterAI.ajax_url,
            type: 'POST',
            data: {
                action: 'nai_get_users_table',
                nonce: newsletterAI.nonce,
                page: page,
                search: search
            },
            timeout: 30000, // 30 sekund timeout
            beforeSend: function() {
                console.log('Newsletter AI: AJAX request rozpoczęty');
            }
        })
        .done(function(response) {
            console.log('Newsletter AI: AJAX response otrzymane:', response);
            
            // Sprawdź typ odpowiedzi
            if (typeof response === 'string') {
                console.log('Newsletter AI: Odpowiedź jest stringiem, próba parsowania JSON');
                try {
                    response = JSON.parse(response);
                } catch (e) {
                    console.error('Newsletter AI: Błąd parsowania JSON:', e);
                    console.log('Newsletter AI: Raw response:', response);
                    showError('Błąd: nieprawidłowa odpowiedź serwera (nie JSON)');
                    return;
                }
            }
            
            if (response && response.success) {
                console.log('Newsletter AI: Sukces, budowanie tabeli z danymi:', response.data);
                $container.html(buildUsersTable(response.data));
            } else {
                console.error('Newsletter AI: AJAX error response:', response);
                var errorMsg = 'Błąd podczas ładowania użytkowników';
                if (response && response.data) {
                    errorMsg += ': ' + response.data;
                }
                showError(errorMsg);
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Newsletter AI: AJAX request failed');
            console.error('Newsletter AI: XHR:', xhr);
            console.error('Newsletter AI: Status:', status);
            console.error('Newsletter AI: Error:', error);
            console.error('Newsletter AI: Response Text:', xhr.responseText);
            
            var errorMessage = 'Błąd połączenia podczas ładowania użytkowników';
            if (status === 'timeout') {
                errorMessage = 'Timeout - żądanie trwało zbyt długo';
            } else if (xhr.status === 0) {
                errorMessage = 'Brak połączenia z serwerem';
            } else if (xhr.status === 404) {
                errorMessage = 'Nie znaleziono endpointu AJAX (404)';
            } else if (xhr.status === 500) {
                errorMessage = 'Błąd serwera (500)';
            } else if (error) {
                errorMessage += ': ' + error;
            }
            
            showError(errorMessage);
        })
        .always(function() {
            console.log('Newsletter AI: AJAX request zakończony');
        });
    }
    
    /**
     * Zbuduj HTML tabeli użytkowników
     */
    function buildUsersTable(data) {
        console.log('Newsletter AI: buildUsersTable z danymi:', data);
        
        var html = '<table class="wp-list-table widefat fixed striped nai-users-table">';
        
        // Nagłówek
        html += '<thead><tr>';
        html += '<th class="column-id">ID</th>';
        html += '<th class="column-login">Login</th>';
        html += '<th class="column-email">Email</th>';
        html += '<th class="column-name">Nazwa wyświetlana</th>';
        html += '<th class="column-field">Pole istnieje</th>';
        html += '<th class="column-consent">Zgoda</th>';
        html += '<th class="column-actions">Akcje</th>';
        html += '</tr></thead>';
        
        // Ciało tabeli
        html += '<tbody>';
        if (data && data.users && data.users.length > 0) {
            console.log('Newsletter AI: Budowanie tabeli dla', data.users.length, 'użytkowników');
            $.each(data.users, function(i, user) {
                html += '<tr>';
                html += '<td>' + escapeHtml(user.ID) + '</td>';
                html += '<td><strong>' + escapeHtml(user.user_login) + '</strong></td>';
                html += '<td>' + escapeHtml(user.user_email) + '</td>';
                html += '<td>' + escapeHtml(user.display_name || '-') + '</td>';
                html += '<td class="nai-text-center">';
                if (user.consent_field_exists) {
                    html += '<span class="dashicons dashicons-yes-alt" style="color: #00a32a;" title="Pole istnieje"></span>';
                } else {
                    html += '<span class="dashicons dashicons-dismiss" style="color: #d63638;" title="Pole nie istnieje"></span>';
                }
                html += '</td>';
                html += '<td>';
                if (user.consent_field_exists) {
                    // Tylko wartość zgody bez przełącznika
                    var consentText = user.has_consent ? 'TAK' : 'NIE';
                    var consentClass = user.has_consent ? 'nai-consent-yes' : 'nai-consent-no';
                    html += '<span class="' + consentClass + '">' + consentText + '</span>';
                    html += '<div class="nai-consent-value">(' + escapeHtml(user.consent_value || 'brak') + ')</div>';
                } else {
                    html += '<span style="color: #646970;">-</span>';
                }
                html += '</td>';
                html += '<td class="nai-text-center">';
                html += '<a href="user-edit.php?user_id=' + user.ID + '" class="nai-btn nai-btn-small nai-btn-secondary" title="Otwórz profil użytkownika" target="_blank">';
                html += '👤 Profil';
                html += '</a>';
                html += '</td>';
                html += '</tr>';
            });
        } else {
            console.log('Newsletter AI: Brak użytkowników do wyświetlenia');
            html += '<tr><td colspan="7" class="nai-text-center" style="padding: 40px 20px; color: #646970;">';
            html += '<div style="font-size: 48px; margin-bottom: 10px;">👥</div>';
            html += '<p>Brak użytkowników do wyświetlenia</p>';
            html += '</td></tr>';
        }
        html += '</tbody></table>';
        
        // Paginacja
        if (data && data.pages && data.pages > 1) {
            html += buildPagination(data);
        }
        
        console.log('Newsletter AI: Tabela zbudowana pomyślnie');
        return html;
    }
    
    /**
     * Zbuduj paginację
     */
    function buildPagination(data) {
        var html = '<div class="tablenav bottom">';
        html += '<div class="tablenav-pages">';
        html += '<span class="displaying-num">' + data.total + ' elementów</span>';
        
        if (data.pages > 1) {
            html += '<span class="pagination-links">';
            
            // Pierwsza strona
            if (data.current_page > 1) {
                html += '<a class="first-page button" href="#" onclick="loadUsersTable(1, jQuery(\'#nai-user-search\').val())" title="Pierwsza strona">&laquo;</a>';
                html += '<a class="prev-page button" href="#" onclick="loadUsersTable(' + (data.current_page - 1) + ', jQuery(\'#nai-user-search\').val())" title="Poprzednia strona">&lsaquo;</a>';
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
                    html += '<a class="page-numbers button" href="#" onclick="loadUsersTable(' + i + ', jQuery(\'#nai-user-search\').val())">' + i + '</a>';
                }
            }
            
            // Ostatnia strona
            if (data.current_page < data.pages) {
                html += '<a class="next-page button" href="#" onclick="loadUsersTable(' + (data.current_page + 1) + ', jQuery(\'#nai-user-search\').val())" title="Następna strona">&rsaquo;</a>';
                html += '<a class="last-page button" href="#" onclick="loadUsersTable(' + data.pages + ', jQuery(\'#nai-user-search\').val())" title="Ostatnia strona">&raquo;</a>';
            } else {
                html += '<span class="tablenav-pages-navspan button disabled">&rsaquo;</span>';
                html += '<span class="tablenav-pages-navspan button disabled">&raquo;</span>';
            }
            
            html += '</span>';
        }
        
        html += '</div></div>';
        return html;
    }
    
    /**
     * Zbiorczego tworzenia pól zgody
     */
    function bulkCreateConsentFields() {
        var $button = $('#nai-bulk-create-fields');
        var originalText = $button.html();
        
        $button.prop('disabled', true).html('<div class="nai-spinner" style="display: inline-block; vertical-align: middle; margin-right: 5px;"></div> Tworzenie pól...');
        
        $.post(newsletterAI.ajax_url, {
            action: 'nai_bulk_create_consent_field',
            nonce: newsletterAI.nonce
        })
        .done(function(response) {
            if (response && response.success) {
                showSuccess(response.data.message || 'Pola zgody zostały utworzone');
                // Odśwież statystyki i tabelę
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showError('Błąd: ' + (response.data || 'Nieznany błąd'));
            }
        })
        .fail(function(xhr, status, error) {
            showError('Błąd połączenia podczas tworzenia pól: ' + error);
        })
        .always(function() {
            $button.prop('disabled', false).html(originalText);
        });
    }
    
    /**
     * Generuj XML
     */
    function generateXML() {
        var $button = $('#nai-generate-xml');
        var $status = $('#nai-xml-generation-status');
        var originalText = $button.html();
        
        var generatingText = '<div class="nai-spinner" style="display: inline-block; vertical-align: middle; margin-right: 5px;"></div> Generowanie XML...';
        if (typeof newsletterAI !== 'undefined' && newsletterAI.strings && newsletterAI.strings.generating_xml) {
            generatingText = '<div class="nai-spinner" style="display: inline-block; vertical-align: middle; margin-right: 5px;"></div> ' + newsletterAI.strings.generating_xml;
        }
        
        $button.prop('disabled', true).html(generatingText);
        $status.html('<div class="nai-notice nai-notice-info"><div class="nai-spinner"></div> Trwa generowanie pliku XML...</div>');
        
        $.post(newsletterAI.ajax_url, {
            action: 'nai_generate_xml',
            nonce: newsletterAI.nonce
        })
        .done(function(response) {
            if (response && response.success) {
                $status.html('<div class="nai-notice nai-notice-success">✅ ' + (response.data.message || 'XML wygenerowany pomyślnie') + '</div>');
                
                // Odśwież stronę po 3 sekundach żeby pokazać nowe statystyki
                setTimeout(function() {
                    location.reload();
                }, 3000);
            } else {
                var errorMessage = (response && response.data) ? response.data : 'Wystąpił błąd. Spróbuj ponownie.';
                $status.html('<div class="nai-notice nai-notice-error">❌ ' + errorMessage + '</div>');
            }
        })
        .fail(function(xhr, status, error) {
            $status.html('<div class="nai-notice nai-notice-error">❌ Błąd połączenia: ' + error + '</div>');
        })
        .always(function() {
            $button.prop('disabled', false).html(originalText);
        });
    }
    
    /**
     * Pokaż komunikat sukcesu
     */
    function showSuccess(message) {
        showNotice(message, 'success');
    }
    
    /**
     * Pokaż komunikat błędu
     */
    function showError(message) {
        showNotice(message, 'error');
    }
    
    /**
     * Pokaż komunikat
     */
    function showNotice(message, type) {
        type = type || 'info';
        
        console.log('Newsletter AI: Pokazywanie komunikatu:', type, message);
        
        var $notice = $('<div class="nai-notice nai-notice-' + type + '"><p>' + escapeHtml(message) + '</p></div>');
        
        // Znajdź miejsce na komunikat
        var $target = $('.nai-admin-page h1').first();
        if ($target.length) {
            $target.after($notice);
        } else if ($('.wrap h1').length) {
            $('.wrap h1').first().after($notice);
        } else {
            $('.wrap').prepend($notice);
        }
        
        // Animacja pojawiania się
        $notice.hide().slideDown(300);
        
        // Automatycznie ukryj po 5 sekundach
        setTimeout(function() {
            $notice.slideUp(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        if (typeof text !== 'string') {
            return text;
        }
        
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        
        return text.replace(/[&<>"']/g, function(m) { 
            return map[m]; 
        });
    }
    
    // Globalne funkcje dostępne z zewnątrz
    window.loadUsersTable = loadUsersTable;
    
    window.editUser = function(userId) {
        window.open('user-edit.php?user_id=' + userId, '_blank');
    };
    
    window.setConsentField = function(fieldName) {
        $('#nai_consent_field').val(fieldName);
        showSuccess('Pole zgody zostało ustawione na: ' + fieldName);
    };
    
    // Test funkcja dla debugowania
    window.testNewsletterAI = function() {
        console.log('Newsletter AI Test:');
        console.log('- jQuery:', typeof jQuery !== 'undefined' ? jQuery.fn.jquery : 'nie dostępne');
        console.log('- newsletterAI:', typeof newsletterAI !== 'undefined' ? newsletterAI : 'nie dostępne');
        console.log('- Kontener tabeli:', $('#nai-users-table-container').length > 0 ? 'istnieje' : 'nie istnieje');
        console.log('- Element .nai-users-consent:', $('.nai-users-consent').length > 0 ? 'istnieje' : 'nie istnieje');
        
        if (typeof newsletterAI !== 'undefined') {
            // Test AJAX
            console.log('Newsletter AI: Testowanie AJAX...');
            $.post(newsletterAI.ajax_url, {
                action: 'nai_get_users_table',
                nonce: newsletterAI.nonce,
                page: 1,
                search: ''
            })
            .done(function(response) {
                console.log('Newsletter AI Test AJAX: Success', response);
            })
            .fail(function(xhr, status, error) {
                console.log('Newsletter AI Test AJAX: Fail', status, error);
            });
        }
    };
}

/**
 * Fallback bez jQuery (vanilla JS)
 */
function initNewsletterAIVanilla() {
    console.log('Newsletter AI: Uruchamianie fallback vanilla JS');
    
    document.addEventListener('DOMContentLoaded', function() {
        // Podstawowa obsługa bez jQuery
        var bulkButton = document.getElementById('nai-bulk-create-fields');
        if (bulkButton) {
            bulkButton.addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Czy na pewno chcesz utworzyć pole zgody dla wszystkich użytkowników bez tego pola?')) {
                    // Vanilla JS AJAX
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', newsletterAI.ajax_url);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                alert(response.data.message);
                                location.reload();
                            } else {
                                alert('Błąd: ' + response.data);
                            }
                        }
                    };
                    xhr.send('action=nai_bulk_create_consent_field&nonce=' + newsletterAI.nonce);
                }
            });
        }
        
        // Funkcje globalne dla vanilla JS
        window.setConsentField = function(fieldName) {
            var field = document.getElementById('nai_consent_field');
            if (field) {
                field.value = fieldName;
                alert('Pole zgody zostało ustawione na: ' + fieldName);
            }
        };
        
        window.editUser = function(userId) {
            window.open('user-edit.php?user_id=' + userId, '_blank');
        };
        
        // Test bez jQuery
        window.testNewsletterAI = function() {
            console.log('Newsletter AI Test (vanilla JS):');
            console.log('- newsletterAI:', typeof newsletterAI !== 'undefined' ? newsletterAI : 'nie dostępne');
            console.log('- Kontener tabeli:', document.getElementById('nai-users-table-container') ? 'istnieje' : 'nie istnieje');
        };
    });
}