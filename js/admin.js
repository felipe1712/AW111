(function($) {
    'use strict';

    /**
     * All of the code for your admin-facing JavaScript source
     */
    $(document).ready(function() {
        
        // Test API Connection
        $('#test-connection').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            // Show loading state
            $button.text('Probando conexión...').prop('disabled', true);
            
            // Clear previous results
            $('#connection-result').remove();
            
            // Get current form values
            var apiData = {
                action: 'wp_auto_whats_test_connection',
                nonce: wpAutoWhatsAdmin.nonce,
                api_url_type: $('select[name="wp_auto_whats_api_url_type"]').val(),
                api_link: $('input[name="wp_auto_whats_api_link"]').val(),
                api_session: $('input[name="wp_auto_whats_api_session"]').val()
            };
            
            $.ajax({
                url: wpAutoWhatsAdmin.ajax_url,
                type: 'POST',
                data: apiData,
                dataType: 'json',
                success: function(response) {
                    displayConnectionResult(response, $button);
                },
                error: function(xhr, status, error) {
                    var errorResponse = {
                        success: false,
                        data: {
                            message: 'Error de AJAX: ' + error,
                            debug_info: {
                                status: status,
                                xhr_status: xhr.status,
                                response: xhr.responseText
                            }
                        }
                    };
                    displayConnectionResult(errorResponse, $button);
                },
                complete: function() {
                    // Restore button
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });
        
        // Display connection test results
        function displayConnectionResult(response, $button) {
            var resultHtml = '<div id="connection-result" class="notice" style="margin-top: 15px;">';
            
            if (response.success) {
                resultHtml += '<div class="notice-success" style="padding: 10px;">';
                resultHtml += '<h4 style="color: #008a20; margin: 0 0 10px 0;">✓ Conexión Exitosa</h4>';
                resultHtml += '<p><strong>Mensaje:</strong> ' + escapeHtml(response.data.message) + '</p>';
                
                if (response.data.config) {
                    resultHtml += '<p><strong>URL construida:</strong> <code>' + escapeHtml(response.data.config.constructed_url) + '</code></p>';
                }
                
                if (response.data.api_data && response.data.api_data.length > 0) {
                    resultHtml += '<p><strong>Sesiones encontradas:</strong> ' + response.data.api_data.length + '</p>';
                }
                
                resultHtml += '</div>';
            } else {
                resultHtml += '<div class="notice-error" style="padding: 10px;">';
                resultHtml += '<h4 style="color: #d63638; margin: 0 0 10px 0;">✗ Error de Conexión</h4>';
                resultHtml += '<p><strong>Error:</strong> ' + escapeHtml(response.data.message || 'Error desconocido') + '</p>';
                
                if (response.data.debug_info) {
                    resultHtml += '<details style="margin-top: 10px;">';
                    resultHtml += '<summary style="cursor: pointer; font-weight: bold;">Información de Debug</summary>';
                    resultHtml += '<div style="background: #f1f1f1; padding: 10px; margin-top: 5px; border-radius: 3px;">';
                    
                    if (response.data.debug_info.api_url_components) {
                        var components = response.data.debug_info.api_url_components;
                        resultHtml += '<p><strong>Configuración:</strong></p>';
                        resultHtml += '<ul>';
                        resultHtml += '<li>Protocolo: <code>' + escapeHtml(components.protocol || 'No definido') + '</code></li>';
                        resultHtml += '<li>Dominio: <code>' + escapeHtml(components.domain || 'No definido') + '</code></li>';
                        resultHtml += '<li>URL construida: <code>' + escapeHtml(components.constructed_url || 'No definida') + '</code></li>';
                        resultHtml += '</ul>';
                    }
                    
                    if (response.data.debug_info.url) {
                        resultHtml += '<p><strong>URL intentada:</strong> <code>' + escapeHtml(response.data.debug_info.url) + '</code></p>';
                    }
                    
                    if (response.data.debug_info.curl_error) {
                        resultHtml += '<p><strong>Error cURL:</strong> <code>' + escapeHtml(response.data.debug_info.curl_error) + '</code></p>';
                    }
                    
                    if (response.data.debug_info.http_code) {
                        resultHtml += '<p><strong>Código HTTP:</strong> <code>' + response.data.debug_info.http_code + '</code></p>';
                    }
                    
                    resultHtml += '</div></details>';
                    
                    // Sugerencias específicas para errores comunes
                    if (response.data.message.includes('Could not resolve host: https')) {
                        resultHtml += '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin-top: 10px; border-radius: 3px;">';
                        resultHtml += '<h5 style="margin: 0 0 5px 0; color: #856404;">💡 Posible Solución:</h5>';
                        resultHtml += '<p style="margin: 0;">El error "Could not resolve host: https" indica que la URL se está construyendo incorrectamente. ';
                        resultHtml += 'Verifica que:</p>';
                        resultHtml += '<ul style="margin: 5px 0 0 20px;">';
                        resultHtml += '<li>El campo "API Link" contenga solo el dominio (ej: <code>example.com</code>)</li>';
                        resultHtml += '<li>NO incluyas <code>http://</code> o <code>https://</code> en el campo "API Link"</li>';
                        resultHtml += '<li>Selecciones el protocolo correcto en "URL Type"</li>';
                        resultHtml += '</ul>';
                        resultHtml += '</div>';
                    }
                }
                
                resultHtml += '</div>';
            }
            
            resultHtml += '</div>';
            
            // Insert result after the button
            $button.parent().after(resultHtml);
            
            // Scroll to result
            $('html, body').animate({
                scrollTop: $("#connection-result").offset().top - 100
            }, 500);
        }
        
        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            if (typeof text !== 'string') return text;
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        // Auto-format API Link field
        $('input[name="wp_auto_whats_api_link"]').on('blur', function() {
            var value = $(this).val();
            if (value) {
                // Remove protocol if present
                value = value.replace(/^https?:\/\//, '');
                // Remove trailing slash
                value = value.replace(/\/$/, '');
                $(this).val(value);
            }
        });
        
        // Show/hide advanced options
        $('#toggle-advanced-options').on('click', function(e) {
            e.preventDefault();
            $('.advanced-options').slideToggle();
            var $icon = $(this).find('.dashicons');
            $icon.toggleClass('dashicons-arrow-down dashicons-arrow-up');
        });
        
        // Session management
        $('.session-action').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var action = $button.data('action');
            var originalText = $button.text();
            
            $button.text('Procesando...').prop('disabled', true);
            
            $.ajax({
                url: wpAutoWhatsAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wp_auto_whats_session_action',
                    nonce: wpAutoWhatsAdmin.nonce,
                    session_action: action
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message);
                        // Refresh session info if available
                        if (typeof refreshSessionInfo === 'function') {
                            refreshSessionInfo();
                        }
                    } else {
                        showNotice('error', response.data.message || 'Error desconocido');
                    }
                },
                error: function(xhr, status, error) {
                    showNotice('error', 'Error de AJAX: ' + error);
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });
        
        // Show admin notices
        function showNotice(type, message) {
            var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            var noticeHtml = '<div class="notice ' + noticeClass + ' is-dismissible" style="margin: 15px 0;">';
            noticeHtml += '<p>' + escapeHtml(message) + '</p>';
            noticeHtml += '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>';
            noticeHtml += '</div>';
            
            $('.wp-auto-whats-container').prepend(noticeHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $('.notice.is-dismissible').fadeOut();
            }, 5000);
        }
        
        // Handle notice dismiss
        $(document).on('click', '.notice-dismiss', function() {
            $(this).parent().fadeOut();
        });
        
        // Form validation
        $('form').on('submit', function(e) {
            var apiLink = $('input[name="wp_auto_whats_api_link"]').val();
            
            if (apiLink && (apiLink.indexOf('http://') === 0 || apiLink.indexOf('https://') === 0)) {
                e.preventDefault();
                showNotice('error', 'El campo "API Link" no debe contener http:// o https://. Solo ingresa el dominio.');
                $('input[name="wp_auto_whats_api_link"]').focus();
                return false;
            }
        });
        
        // Real-time URL preview
        function updateUrlPreview() {
            var protocol = $('select[name="wp_auto_whats_api_url_type"]').val();
            var domain = $('input[name="wp_auto_whats_api_link"]').val();
            
            if (protocol && domain) {
                var cleanDomain = domain.replace(/^https?:\/\//, '').replace(/\/$/, '');
                var fullUrl = protocol + '://' + cleanDomain;
                $('#url-preview').html('<strong>URL completa:</strong> <code>' + escapeHtml(fullUrl) + '</code>');
            } else {
                $('#url-preview').html('<em>Configura el protocolo y dominio para ver la URL completa</em>');
            }
        }
        
        // Update preview on field changes
        $('select[name="wp_auto_whats_api_url_type"], input[name="wp_auto_whats_api_link"]').on('change keyup', updateUrlPreview);
        
        // Initial preview update
        updateUrlPreview();
    });

})(jQuery);