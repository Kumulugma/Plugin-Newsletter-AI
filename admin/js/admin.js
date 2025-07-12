/**
 * Newsletter AI - Admin JavaScript
 * Bezpieczne ładowanie bez konfliktów jQuery
 */

// Sprawdź czy jQuery jest dostępne globalnie
if (typeof jQuery !== 'undefined') {
    // Użyj jQuery bezpośrednio
    initNewsletterAI(jQuery);
} else if (typeof $ !== 'undefined') {
    // Użyj $ jeśli dostępne
    initNewsletterAI($);
} else {
    // Czekaj na jQuery
    document.addEventListener('DOMContentLoaded', function() {
        // Spróbuj ponownie po załadowaniu DOM
        if (typeof jQuery !== 'undefined') {
            initNewsletterAI(jQuery);
        } else {
            console.error('Newsletter AI: jQuery nie jest dostępne po załadowaniu DOM');
            // Spróbuj załadować bez jQuery
            initNewsletterAIVanilla();
        }
    });
}

function initNewsletterAI($) {
    'use strict';
    
    console.log('Newsletter AI: Inicjalizacja z jQuery', $.fn.jquery);
    
    // Główna funkcja inicjalizująca
    $(document).ready(function() {
        console.log('Newsletter AI: DOM ready');
        initializeNewsletterAI();
    });
    
    function initializeNewsletterAI() {
        // Sprawdź czy obiekt newsletterAI istnieje
        if (typeof newsletterAI === 'undefined') {
            console.error('Newsletter AI: Brak obiektu newsletterAI - sprawdź wp_localize_script');
            return;
        }
        
        console.log('Newsletter AI: Obiekt newsletterAI dostępny', newsletterAI);
        
        // Inicjalizuj komponenty w zależności od strony
        if ($('.nai-users-consent').length) {
            console.log('Newsletter AI: Inicjalizacja strony użytkowników');
            initializeUsersPage();
        }
        
        if ($('.nai-xml-settings').length) {
            console.log('Newsletter AI: Inicjalizacja strony XML');
            initializeXMLPage();
        }
        
        // Globalne funkcje
        initializeGlobalComponents();
    }
    
    /**
     * Inicjalizacja strony użytkowników
     */
    function initializeUsersPage() {
        console.log('Newsletter AI: Konfiguracja strony użytkowników');
        
        // Załaduj tabelę użytkowników
        loadUsersTable(1);
        
        // Obsługa wyszukiwania z debounce
        var searchTimeout;
        $('#nai-user-search').on('keyup', function() {
            var search = $(this).val();
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                loadUsersTable(1, search);
            }, 500);
        });
        
        // Obsługa zmiany zgody - USUNIĘTE
        // Już nie potrzebujemy obsługi przełączników zgody
        
        // Obsługa zbiorczego tworzenia pól - POPRAWKA
        $('#nai-bulk-create-fields').off('click').on('click', function(e) {
            e.preventDefault();
            console.log('Newsletter AI: Kliknięto przycisk bulk create');
            
            // Sprawdź czy newsletterAI.strings istnieje
            var confirmMessage = 'Czy na pewno chcesz utworzyć pole zgody dla wszystkich użytkowników bez tego pola?';
            if (typeof newsletterAI !== 'undefined' && newsletterAI.strings && newsletterAI.strings.confirm_bulk_create) {
                confirmMessage = newsletterAI.strings.confirm_bulk_create;
            }
            
            if (confirm(confirmMessage)) {
                console.log('Newsletter AI: Potwierdzono bulk create');
                bulkCreateConsentFields();
            }
        });
    }
    
    /**
     * Inicjalizacja strony XML
     */
    function initializeXMLPage() {
        console.log('Newsletter AI: Konfiguracja strony XML');
        
        // Obsługa generowania XML
        $('#nai-generate-xml').off('click').on('click', function(e) {
            e.preventDefault();
            console.log('Newsletter AI: Kliknięto przycisk generowania XML');
            generateXML();
        });
        
        // Sprawdź status pliku XML co 30 sekund
        setInterval(checkXMLFileStatus, 30000);
    }
    
    /**
     * Inicjalizacja globalnych komponentów
     */
    function initializeGlobalComponents() {
        console.log('Newsletter AI: Konfiguracja komponentów globalnych');
        
        // Tooltips
        if ($.fn.tooltip) {
            $('[data-tooltip]').tooltip();
        }
        
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
        
        console.log('Newsletter AI: Ładowanie tabeli użytkowników', page, search);
        
        var $container = $('#nai-users-table-container');
        $container.html('<div class="nai-loading"><span class="spinner is-active"></span> Ładowanie...</div>');
        
        // Sprawdź czy mamy dane AJAX
        if (typeof newsletterAI === 'undefined' || !newsletterAI.ajax_url || !newsletterAI.nonce) {
            console.error('Newsletter AI: Brak danych AJAX dla loadUsersTable', newsletterAI);
            showError('Błąd konfiguracji AJAX');
            return;
        }
        
        console.log('Newsletter AI: Wysyłanie AJAX request do:', newsletterAI.ajax_url);
        
        $.post(newsletterAI.ajax_url, {
            action: 'nai_get_users_table',
            nonce: newsletterAI.nonce,
            page: page,
            search: search
        })
        .done(function(response) {
            console.log('Newsletter AI: Odpowiedź tabeli użytkowników', response);
            if (response.success) {
                $container.html(buildUsersTable(response.data));
            } else {
                showError('Błąd podczas ładowania użytkowników: ' + (response.data || 'Nieznany błąd'));
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Newsletter AI: Błąd AJAX tabeli użytkowników', xhr, status, error);
            showError('Błąd połączenia podczas ładowania użytkowników: ' + error);
        });
    }
    
    /**
     * Zbuduj HTML tabeli użytkowników
     */
    function buildUsersTable(data) {
        console.log('Newsletter AI: Budowanie tabeli użytkowników', data);
        
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
        if (data.users && data.users.length > 0) {
            $.each(data.users, function(i, user) {
                html += '<tr>';
                html += '<td>' + escapeHtml(user.ID) + '</td>';
                html += '<td><strong>' + escapeHtml(user.user_login) + '</strong></td>';
                html += '<td>' + escapeHtml(user.user_email) + '</td>';
                html += '<td>' + escapeHtml(user.display_name || '-') + '</td>';
                html += '<td>';
                if (user.consent_field_exists) {
                    html += '<span class="dashicons dashicons-yes-alt" style="color: green;" title="Pole istnieje"></span>';
                } else {
                    html += '<span class="dashicons dashicons-dismiss" style="color: red;" title="Pole nie istnieje"></span>';
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
                    html += '<span style="color: #666;">-</span>';
                }
                html += '</td>';
                html += '<td>';
                html += '<div class="nai-table-actions">';
                html += '<button type="button" class="button button-small nai-edit-btn" onclick="editUser(' + user.ID + ')" title="Edytuj użytkownika">';
                html += '<span class="dashicons dashicons-edit"></span>';
                html += '</button>';
                html += '<a href="user-edit.php?user_id=' + user.ID + '" class="button button-small nai-view-btn" title="Otwórz profil użytkownika" target="_blank">';
                html += '<span class="dashicons dashicons-admin-users"></span>';
                html += '</a>';
                html += '</div>';
                html += '</td>';
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="7" style="text-align: center; padding: 20px;">Brak użytkowników do wyświetlenia</td></tr>';
        }
        html += '</tbody></table>';
        
        // Paginacja
        if (data.pages > 1) {
            html += buildPagination(data);
        }
        
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
     * Aktualizuj zgodę użytkownika
     */
    function updateUserConsent(userId, consentValue, callback) {
        console.log('Newsletter AI: Aktualizacja zgody użytkownika', userId, consentValue);
        
        // Sprawdź czy mamy dane AJAX
        if (typeof newsletterAI === 'undefined' || !newsletterAI.ajax_url || !newsletterAI.nonce) {
            console.error('Newsletter AI: Brak danych AJAX dla updateUserConsent', newsletterAI);
            showError('Błąd konfiguracji AJAX - nie można zaktualizować zgody');
            if (callback) callback(false);
            return;
        }
        
        console.log('Newsletter AI: Wysyłanie AJAX request updateUserConsent do:', newsletterAI.ajax_url);
        
        $.post(newsletterAI.ajax_url, {
            action: 'nai_update_user_consent',
            nonce: newsletterAI.nonce,
            user_id: userId,
            consent_value: consentValue
        })
        .done(function(response) {
            console.log('Newsletter AI: Odpowiedź aktualizacji zgody', response);
            if (response.success) {
                showSuccess('Zgoda użytkownika została zaktualizowana');
                if (callback) callback(true);
            } else {
                showError('Błąd: ' + (response.data || 'Nieznany błąd'));
                if (callback) callback(false);
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Newsletter AI: Błąd AJAX aktualizacji zgody', xhr, status, error);
            showError('Błąd połączenia podczas aktualizacji zgody: ' + error);
            if (callback) callback(false);
        });
    }
    
    /**
     * Zbiorczego tworzenia pól zgody
     */
    function bulkCreateConsentFields() {
        console.log('Newsletter AI: Rozpoczęcie bulk create consent fields');
        
        var $button = $('#nai-bulk-create-fields');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Tworzenie pól...');
        
        $.post(newsletterAI.ajax_url, {
            action: 'nai_bulk_create_consent_field',
            nonce: newsletterAI.nonce
        })
        .done(function(response) {
            console.log('Newsletter AI: Odpowiedź bulk create', response);
            if (response.success) {
                showSuccess(response.data.message);
                // Odśwież statystyki i tabelę
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showError('Błąd: ' + (response.data || 'Nieznany błąd'));
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Newsletter AI: Błąd AJAX bulk create', xhr, status, error);
            showError('Błąd połączenia podczas tworzenia pól: ' + error);
        })
        .always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    }
    
    /**
     * Generuj XML
     */
    function generateXML() {
        console.log('Newsletter AI: Rozpoczęcie generowania XML');
        
        var $button = $('#nai-generate-xml');
        var $status = $('#nai-xml-generation-status');
        var originalText = $button.text();
        
        var generatingText = 'Generowanie XML...';
        if (typeof newsletterAI !== 'undefined' && newsletterAI.strings && newsletterAI.strings.generating_xml) {
            generatingText = newsletterAI.strings.generating_xml;
        }
        
        $button.prop('disabled', true).text(generatingText);
        $status.html('<span class="spinner is-active" style="float: none; margin: 0 5px;"></span>');
        
        $.post(newsletterAI.ajax_url, {
            action: 'nai_generate_xml',
            nonce: newsletterAI.nonce
        })
        .done(function(response) {
            console.log('Newsletter AI: Odpowiedź generowania XML', response);
            if (response.success) {
                $status.html('<span style="color: green; font-weight: bold;">✓ ' + response.data.message + '</span>');
                
                // Odśwież stronę po 3 sekundach żeby pokazać nowe statystyki
                setTimeout(function() {
                    location.reload();
                }, 3000);
            } else {
                var errorMessage = response.data || 'Wystąpił błąd. Spróbuj ponownie.';
                $status.html('<span style="color: red; font-weight: bold;">✗ ' + errorMessage + '</span>');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Newsletter AI: Błąd AJAX generowania XML', xhr, status, error);
            $status.html('<span style="color: red; font-weight: bold;">✗ Błąd połączenia: ' + error + '</span>');
        })
        .always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    }
    
    /**
     * Sprawdź status pliku XML
     */
    function checkXMLFileStatus() {
        // Ta funkcja może być rozszerzona o automatyczne sprawdzanie statusu pliku
        // Na razie pozostaje pusta
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
        
        console.log('Newsletter AI: Pokazywanie komunikatu', type, message);
        
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + escapeHtml(message) + '</p></div>');
        
        // Znajdź miejsce na komunikat
        var $target = $('.wrap h1').first();
        if ($target.length) {
            $target.after($notice);
        } else {
            $('body').prepend($notice);
        }
        
        // Automatycznie ukryj po 5 sekundach
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
        
        // Obsługa przycisku zamknięcia
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut();
        });
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
}

/**
 * Fallback bez jQuery (vanilla JS)
 */
function initNewsletterAIVanilla() {
    console.log('Newsletter AI: Inicjalizacja bez jQuery (vanilla JS)');
    
    // Podstawowe funkcje bez jQuery
    document.addEventListener('DOMContentLoaded', function() {
        // Obsługa przycisków
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
    });
}/**
 * Newsletter AI - Admin JavaScript
 */

(function($) {
    'use strict';
    
    // Sprawdź czy jQuery jest dostępne
    if (typeof $ === 'undefined') {
        console.error('Newsletter AI: jQuery nie jest dostępne');
        return;
    }
    
    // Główna funkcja inicjalizująca
    $(document).ready(function() {
        console.log('Newsletter AI: Inicjalizacja JavaScript');
        initializeNewsletterAI();
    });
    
    function initializeNewsletterAI() {
        // Sprawdź czy obiekt newsletterAI istnieje
        if (typeof newsletterAI === 'undefined') {
            console.error('Newsletter AI: Brak obiektu newsletterAI - sprawdź wp_localize_script');
            return;
        }
        
        console.log('Newsletter AI: Obiekt newsletterAI dostępny', newsletterAI);
        
        // Inicjalizuj komponenty w zależności od strony
        if ($('.nai-users-consent').length) {
            console.log('Newsletter AI: Inicjalizacja strony użytkowników');
            initializeUsersPage();
        }
        
        if ($('.nai-xml-settings').length) {
            console.log('Newsletter AI: Inicjalizacja strony XML');
            initializeXMLPage();
        }
        
        // Globalne funkcje
        initializeGlobalComponents();
    }
    
    /**
     * Inicjalizacja strony użytkowników
     */
    function initializeUsersPage() {
        console.log('Newsletter AI: Konfiguracja strony użytkowników');
        
        // Załaduj tabelę użytkowników
        loadUsersTable(1);
        
        // Obsługa wyszukiwania z debounce
        var searchTimeout;
        $('#nai-user-search').on('keyup', function() {
            var search = $(this).val();
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                loadUsersTable(1, search);
            }, 500);
        });
        
        // Obsługa zmiany zgody
        $(document).on('change', '.nai-consent-toggle', function() {
            var $toggle = $(this);
            var userId = $toggle.data('user-id');
            var consentValue = $toggle.is(':checked') ? 'yes' : 'no';
            
            console.log('Newsletter AI: Zmiana zgody dla użytkownika', userId, consentValue);
            
            // Wyłącz toggle podczas aktualizacji
            $toggle.prop('disabled', true);
            
            updateUserConsent(userId, consentValue, function(success) {
                if (!success) {
                    // Przywróć poprzedni stan w przypadku błędu
                    $toggle.prop('checked', !$toggle.is(':checked'));
                }
                $toggle.prop('disabled', false);
            });
        });
        
        // Obsługa zbiorczego tworzenia pól - POPRAWKA
        $('#nai-bulk-create-fields').off('click').on('click', function(e) {
            e.preventDefault();
            console.log('Newsletter AI: Kliknięto przycisk bulk create');
            
            // Sprawdź czy newsletterAI.strings istnieje
            var confirmMessage = 'Czy na pewno chcesz utworzyć pole zgody dla wszystkich użytkowników bez tego pola?';
            if (typeof newsletterAI !== 'undefined' && newsletterAI.strings && newsletterAI.strings.confirm_bulk_create) {
                confirmMessage = newsletterAI.strings.confirm_bulk_create;
            }
            
            if (confirm(confirmMessage)) {
                console.log('Newsletter AI: Potwierdzono bulk create');
                bulkCreateConsentFields();
            }
        });
    }
    
    /**
     * Inicjalizacja strony XML
     */
    function initializeXMLPage() {
        console.log('Newsletter AI: Konfiguracja strony XML');
        
        // Obsługa generowania XML
        $('#nai-generate-xml').off('click').on('click', function(e) {
            e.preventDefault();
            console.log('Newsletter AI: Kliknięto przycisk generowania XML');
            generateXML();
        });
        
        // Sprawdź status pliku XML co 30 sekund
        setInterval(checkXMLFileStatus, 30000);
    }
    
    /**
     * Inicjalizacja globalnych komponentów
     */
    function initializeGlobalComponents() {
        console.log('Newsletter AI: Konfiguracja komponentów globalnych');
        
        // Tooltips
        if ($.fn.tooltip) {
            $('[data-tooltip]').tooltip();
        }
        
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
        
        console.log('Newsletter AI: Ładowanie tabeli użytkowników', page, search);
        
        var $container = $('#nai-users-table-container');
        $container.html('<div class="nai-loading"><span class="spinner is-active"></span> Ładowanie...</div>');
        
        // Sprawdź czy mamy dane AJAX
        if (typeof newsletterAI === 'undefined' || !newsletterAI.ajax_url || !newsletterAI.nonce) {
            console.error('Newsletter AI: Brak danych AJAX', newsletterAI);
            showError('Błąd konfiguracji AJAX');
            return;
        }
        
        $.post(newsletterAI.ajax_url, {
            action: 'nai_get_users_table',
            nonce: newsletterAI.nonce,
            page: page,
            search: search
        })
        .done(function(response) {
            console.log('Newsletter AI: Odpowiedź tabeli użytkowników', response);
            if (response.success) {
                $container.html(buildUsersTable(response.data));
            } else {
                showError('Błąd podczas ładowania użytkowników: ' + (response.data || 'Nieznany błąd'));
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Newsletter AI: Błąd AJAX tabeli użytkowników', xhr, status, error);
            showError('Błąd połączenia podczas ładowania użytkowników: ' + error);
        });
    }
    
    /**
     * Zbuduj HTML tabeli użytkowników
     */
    function buildUsersTable(data) {
        console.log('Newsletter AI: Budowanie tabeli użytkowników', data);
        
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
        if (data.users && data.users.length > 0) {
            $.each(data.users, function(i, user) {
                html += '<tr>';
                html += '<td>' + escapeHtml(user.ID) + '</td>';
                html += '<td><strong>' + escapeHtml(user.user_login) + '</strong></td>';
                html += '<td>' + escapeHtml(user.user_email) + '</td>';
                html += '<td>' + escapeHtml(user.display_name || '-') + '</td>';
                html += '<td>';
                if (user.consent_field_exists) {
                    html += '<span class="dashicons dashicons-yes-alt" style="color: green;" title="Pole istnieje"></span>';
                } else {
                    html += '<span class="dashicons dashicons-dismiss" style="color: red;" title="Pole nie istnieje"></span>';
                }
                html += '</td>';
                html += '<td>';
                if (user.consent_field_exists) {
                    html += '<label class="nai-switch">';
                    html += '<input type="checkbox" class="nai-consent-toggle" data-user-id="' + user.ID + '"' + (user.has_consent ? ' checked' : '') + '>';
                    html += '<span class="nai-slider"></span>';
                    html += '</label>';
                    html += '<div class="nai-consent-value">(' + escapeHtml(user.consent_value || 'brak') + ')</div>';
                } else {
                    html += '<span style="color: #666;">-</span>';
                }
                html += '</td>';
                html += '<td>';
                html += '<button type="button" class="button button-small nai-edit-btn" onclick="editUser(' + user.ID + ')" title="Edytuj użytkownika">';
                html += '<span class="dashicons dashicons-edit"></span>';
                html += '</button>';
                html += '</td>';
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="7" style="text-align: center; padding: 20px;">Brak użytkowników do wyświetlenia</td></tr>';
        }
        html += '</tbody></table>';
        
        // Paginacja
        if (data.pages > 1) {
            html += buildPagination(data);
        }
        
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
     * Aktualizuj zgodę użytkownika
     */
    function updateUserConsent(userId, consentValue, callback) {
        console.log('Newsletter AI: Aktualizacja zgody użytkownika', userId, consentValue);
        
        // Sprawdź czy mamy dane AJAX
        if (typeof newsletterAI === 'undefined' || !newsletterAI.ajax_url || !newsletterAI.nonce) {
            console.error('Newsletter AI: Brak danych AJAX dla updateUserConsent', newsletterAI);
            showError('Błąd konfiguracji AJAX - nie można zaktualizować zgody');
            if (callback) callback(false);
            return;
        }
        
        console.log('Newsletter AI: Wysyłanie AJAX request updateUserConsent');
        
        $.post(newsletterAI.ajax_url, {
            action: 'nai_update_user_consent',
            nonce: newsletterAI.nonce,
            user_id: userId,
            consent_value: consentValue
        })
        .done(function(response) {
            console.log('Newsletter AI: Odpowiedź aktualizacji zgody', response);
            if (response.success) {
                showSuccess('Zgoda użytkownika została zaktualizowana');
                if (callback) callback(true);
            } else {
                showError('Błąd: ' + (response.data || 'Nieznany błąd'));
                if (callback) callback(false);
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Newsletter AI: Błąd AJAX aktualizacji zgody', xhr, status, error);
            showError('Błąd połączenia podczas aktualizacji zgody: ' + error);
            if (callback) callback(false);
        });
    }
    
    /**
     * Zbiorczego tworzenia pól zgody
     */
    function bulkCreateConsentFields() {
        console.log('Newsletter AI: Rozpoczęcie bulk create consent fields');
        
        var $button = $('#nai-bulk-create-fields');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Tworzenie pól...');
        
        $.post(newsletterAI.ajax_url, {
            action: 'nai_bulk_create_consent_field',
            nonce: newsletterAI.nonce
        })
        .done(function(response) {
            console.log('Newsletter AI: Odpowiedź bulk create', response);
            if (response.success) {
                showSuccess(response.data.message);
                // Odśwież statystyki i tabelę
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showError('Błąd: ' + (response.data || 'Nieznany błąd'));
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Newsletter AI: Błąd AJAX bulk create', xhr, status, error);
            showError('Błąd połączenia podczas tworzenia pól: ' + error);
        })
        .always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    }
    
    /**
     * Generuj XML
     */
    function generateXML() {
        console.log('Newsletter AI: Rozpoczęcie generowania XML');
        
        var $button = $('#nai-generate-xml');
        var $status = $('#nai-xml-generation-status');
        var originalText = $button.text();
        
        var generatingText = 'Generowanie XML...';
        if (typeof newsletterAI !== 'undefined' && newsletterAI.strings && newsletterAI.strings.generating_xml) {
            generatingText = newsletterAI.strings.generating_xml;
        }
        
        $button.prop('disabled', true).text(generatingText);
        $status.html('<span class="spinner is-active" style="float: none; margin: 0 5px;"></span>');
        
        $.post(newsletterAI.ajax_url, {
            action: 'nai_generate_xml',
            nonce: newsletterAI.nonce
        })
        .done(function(response) {
            console.log('Newsletter AI: Odpowiedź generowania XML', response);
            if (response.success) {
                $status.html('<span style="color: green; font-weight: bold;">✓ ' + response.data.message + '</span>');
                
                // Odśwież stronę po 3 sekundach żeby pokazać nowe statystyki
                setTimeout(function() {
                    location.reload();
                }, 3000);
            } else {
                var errorMessage = response.data || 'Wystąpił błąd. Spróbuj ponownie.';
                $status.html('<span style="color: red; font-weight: bold;">✗ ' + errorMessage + '</span>');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Newsletter AI: Błąd AJAX generowania XML', xhr, status, error);
            $status.html('<span style="color: red; font-weight: bold;">✗ Błąd połączenia: ' + error + '</span>');
        })
        .always(function() {
            $button.prop('disabled', false).text(originalText);
        });
    }
    
    /**
     * Sprawdź status pliku XML
     */
    function checkXMLFileStatus() {
        // Ta funkcja może być rozszerzona o automatyczne sprawdzanie statusu pliku
        // Na razie pozostaje pusta
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
        
        console.log('Newsletter AI: Pokazywanie komunikatu', type, message);
        
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + escapeHtml(message) + '</p></div>');
        
        // Znajdź miejsce na komunikat
        var $target = $('.wrap h1').first();
        if ($target.length) {
            $target.after($notice);
        } else {
            $('body').prepend($notice);
        }
        
        // Automatycznie ukryj po 5 sekundach
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
        
        // Obsługa przycisku zamknięcia
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut();
        });
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
    
})(jQuery);