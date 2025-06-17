/**
 * WP Auto Whats - Enhanced Admin JavaScript
 * Archivo: assets/js/enhanced-admin.js
 * Funcionalidad mejorada para método QR y gestión de sesiones
 */

(function($) {
    'use strict';
    
    // Variables globales
    let qrTimer = null;
    let statusCheckInterval = null;
    let qrAttempts = 0;
    const maxQrAttempts = 3;
    
    // Inicialización
    $(document).ready(function() {
        initEnhancedPlugin();
    });
    
    /**
     * Inicializar funcionalidades mejoradas
     */
    function initEnhancedPlugin() {
        setupQRHandlers();
        setupSessionHandlers();
        setupDebugHandlers();
        
        // Verificar estado inicial
        if (isPluginPage()) {
            checkSessionStatus();
            statusCheckInterval = setInterval(checkSessionStatus, 30000);
        }
        
        logDebug('WP Auto Whats Enhanced: Inicializado correctamente');
    }
    
    /**
     * Verificar si estamos en una página del plugin
     */
    function isPluginPage() {
        return $('.autwa-enhanced-container, #autwa-session-status, .wp-auto-whats').length > 0;
    }
    
    /**
     * Configurar handlers del QR mejorado
     */
    function setupQRHandlers() {
        // Obtener código QR (nuevo método)
        $(document).on('click', '#get-qr-btn, .autwa-get-qr, [data-action="get-qr"]', function(e) {
            e.preventDefault();
            const $btn = $(this);
            $btn.prop('disabled', true).addClass('loading');
            getEnhancedQRCode();
        });
        
        // Refrescar QR
        $(document).on('click', '#refresh-qr-btn, .autwa-refresh-qr, [data-action="refresh-qr"]', function(e) {
            e.preventDefault();
            refreshQRCode();
        });
        
        // Retry QR cuando hay error
        $(document).on('click', '.autwa-retry-qr', function(e) {
            e.preventDefault();
            qrAttempts = 0;
            getEnhancedQRCode();
        });
        
        // Obtener nuevo QR cuando expira
        $(document).on('click', '.autwa-get-new-qr', function(e) {
            e.preventDefault();
            qrAttempts = 0;
            getEnhancedQRCode();
        });
    }
    
    /**
     * Configurar handlers de sesión
     */
    function setupSessionHandlers() {
        $(document).on('click', '#check-status-btn, .autwa-check-status, [data-action="check-status"]', function(e) {
            e.preventDefault();
            checkSessionStatus();
        });
        
        $(document).on('click', '#test-connection-btn, .autwa-test-connection, [data-action="test-connection"]', function(e) {
            e.preventDefault();
            testAPIConnection();
        });
    }
    
    /**
     * Configurar handlers de debug
     */
    function setupDebugHandlers() {
        $(document).on('click', '.autwa-toggle-debug', function(e) {
            e.preventDefault();
            $('.autwa-debug-logs').slideToggle();
        });
        
        $(document).on('click', '.autwa-clear-logs', function(e) {
            e.preventDefault();
            if (confirm('¿Estás seguro de que quieres limpiar los logs?')) {
                $('.autwa-debug-logs').empty();
                showNotification('success', 'Logs limpiados');
            }
        });
    }
    
    /**
     * MÉTODO MEJORADO: Obtener código QR
     */
    function getEnhancedQRCode() {
        logDebug('Solicitando código QR con nuevo método...');
        
        clearQRTimer();
        qrAttempts++;
        
        // Mostrar loading
        const containers = $('#qr-container, .autwa-qr-container, .qr-container');
        showLoadingInContainer(containers, 'Generando código QR...');
        
        $.ajax({
            url: autwaAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'autwa_get_qr_code',
                nonce: autwaAjax.nonce
            },
            timeout: 30000,
            success: function(response) {
                logDebug('Respuesta QR:', response);
                
                if (response.success && response.data) {
                    displayQRCode(response.data);
                    showNotification('success', response.data.message || 'Código QR generado');
                    qrAttempts = 0;
                } else {
                    handleQRError(response.data ? response.data.message : 'Error desconocido');
                }
            },
            error: function(xhr, status, error) {
                logError('Error AJAX QR:', error);
                
                if (qrAttempts < maxQrAttempts) {
                    showNotification('warning', `Error de conexión. Reintentando... (${qrAttempts}/${maxQrAttempts})`);
                    setTimeout(getEnhancedQRCode, 2000);
                } else {
                    handleQRError('Error de conexión persistente. Verifica la configuración de API.');
                    qrAttempts = 0;
                }
            },
            complete: function() {
                $('.autwa-get-qr, #get-qr-btn, [data-action="get-qr"]')
                    .prop('disabled', false)
                    .removeClass('loading')
                    .text('Obtener Código QR');
            }
        });
    }
    
    /**
     * Mostrar código QR mejorado
     */
    function displayQRCode(data) {
        const qrCode = data.qr_code;
        const expiresIn = data.expires_in || 60;
        const containers = $('#qr-container, .autwa-qr-container, .qr-container');
        
        let qrHtml = '';
        
        if (qrCode.startsWith('data:image/') || qrCode.startsWith('iVBORw0KGgo')) {
            // Es imagen base64
            const imgSrc = qrCode.startsWith('data:') ? qrCode : 'data:image/png;base64,' + qrCode;
            qrHtml = `
                <div class="autwa-qr-image-container">
                    <img src="${imgSrc}" alt="Código QR WhatsApp" class="autwa-qr-image" />
                    <p class="autwa-qr-instructions">${autwaAjax.strings.scan_qr}</p>
                </div>
            `;
        } else if (qrCode.length > 50) {
            // Es texto largo
            qrHtml = `
                <div class="autwa-qr-text-container">
                    <h4>Código QR (formato texto)</h4>
                    <textarea readonly class="autwa-qr-text-area">${escapeHtml(qrCode)}</textarea>
                    <p class="autwa-qr-note">Usa una app generadora de QR para convertir a imagen</p>
                </div>
            `;
        } else {
            // Formato no reconocido
            qrHtml = `
                <div class="autwa-qr-error-container">
                    <p>⚠️ Formato de QR no reconocido</p>
                    <details>
                        <summary>Ver datos recibidos</summary>
                        <pre>${escapeHtml(qrCode)}</pre>
                    </details>
                </div>
            `;
        }
        
        containers.html(qrHtml);
        $('.autwa-refresh-qr, #refresh-qr-btn').show();
        
        // Iniciar timer
        startQRTimer(expiresIn);
        
        logDebug('QR mostrado correctamente');
    }
    
    /**
     * Manejar errores del QR
     */
    function handleQRError(message) {
        const containers = $('#qr-container, .autwa-qr-container, .qr-container');
        
        containers.html(`
            <div class="autwa-qr-error-container">
                <h4>❌ Error al obtener código QR</h4>
                <p class="autwa-error-message">${escapeHtml(message)}</p>
                <button class="button autwa-retry-qr">Reintentar</button>
            </div>
        `);
        
        showNotification('error', message);
    }
    
    /**
     * Timer para expiración de QR
     */
    function startQRTimer(seconds) {
        clearQRTimer();
        
        let timeLeft = seconds;
        const timers = $('#qr-timer, .autwa-qr-timer');
        
        timers.show();
        updateTimerDisplay(timeLeft);
        
        qrTimer = setInterval(function() {
            timeLeft--;
            updateTimerDisplay(timeLeft);
            
            if (timeLeft <= 0) {
                clearQRTimer();
                handleQRExpired();
            }
        }, 1000);
    }
    
    function updateTimerDisplay(seconds) {
        $('#countdown, .autwa-countdown').text(seconds);
        
        const timers = $('#qr-timer, .autwa-qr-timer');
        if (seconds <= 10) {
            timers.addClass('autwa-urgent');
        } else {
            timers.removeClass('autwa-urgent');
        }
    }
    
    function handleQRExpired() {
        const containers = $('#qr-container, .autwa-qr-container, .qr-container');
        
        containers.html(`
            <div class="autwa-qr-expired-container">
                <h4>⏰ Código QR Expirado</h4>
                <p>${autwaAjax.strings.qr_expired}</p>
                <button class="button button-primary autwa-get-new-qr">Obtener Nuevo QR</button>
            </div>
        `);
        
        $('#qr-timer, .autwa-qr-timer').hide();
        showNotification('warning', autwaAjax.strings.qr_expired);
    }
    
    function clearQRTimer() {
        if (qrTimer) {
            clearInterval(qrTimer);
            qrTimer = null;
        }
        $('#qr-timer, .autwa-qr-timer').hide().removeClass('autwa-urgent');
    }
    
    function refreshQRCode() {
        qrAttempts = 0;
        getEnhancedQRCode();
    }
    
    /**
     * Verificar estado de sesión
     */
    function checkSessionStatus() {
        $.ajax({
            url: autwaAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'autwa_check_session_status',
                nonce: autwaAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateStatusIndicator(response.data.status, response.data.message);
                    updateSessionInfo(response.data.details);
                } else {
                    updateStatusIndicator('error', response.data.message || 'Error al verificar estado');
                }
            },
            error: function() {
                updateStatusIndicator('error', 'Error de conexión');
            }
        });
    }
    
    function updateStatusIndicator(status, message) {
        const indicators = $('#autwa-session-status, .autwa-session-status');
        const dots = $('.autwa-status-dot, .status-dot');
        const texts = $('.autwa-status-text, .status-text');
        
        // Limpiar clases previas
        dots.removeClass('connected connecting disconnected error');
        indicators.removeClass('connected connecting disconnected error');
        
        // Aplicar nueva clase
        dots.addClass(status);
        indicators.addClass(status);
        texts.text(message);
        
        logDebug('Estado actualizado:', status, message);
    }
    
    function updateSessionInfo(details) {
        if (details && typeof details === 'object') {
            const formattedInfo = JSON.stringify(details, null, 2);
            $('#session-info, .autwa-session-info').html(`<pre>${escapeHtml(formattedInfo)}</pre>`);
        }
    }
    
    /**
     * Probar conexión API
     */
    function testAPIConnection() {
        const btns = $('#test-connection-btn, .autwa-test-connection, [data-action="test-connection"]');
        const originalText = btns.first().text();
        
        btns.prop('disabled', true).addClass('loading').text('Probando...');
        
        $.ajax({
            url: autwaAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'autwa_test_connection',
                nonce: autwaAjax.nonce
            },
            success: function(response) {
                let resultHtml = '';
                
                if (response.success) {
                    resultHtml = `
                        <div class="autwa-test-success">
                            <h4>✅ Conexión Exitosa</h4>
                            <p>${escapeHtml(response.data.message)}</p>
                            <small>Versión: ${escapeHtml(response.data.version)}</small>
                        </div>
                    `;
                    showNotification('success', 'Conexión exitosa');
                } else {
                    resultHtml = `
                        <div class="autwa-test-error">
                            <h4>❌ Error de Conexión</h4>
                            <p>${escapeHtml(response.data.message)}</p>
                        </div>
                    `;
                    showNotification('error', response.data.message);
                }
                
                $('#test-results, .autwa-test-results').html(resultHtml);
            },
            error: function(xhr, status, error) {
                const errorHtml = `
                    <div class="autwa-test-error">
                        <h4>❌ Error de Conexión</h4>
                        <p>No se pudo conectar con la API: ${escapeHtml(error)}</p>
                    </div>
                `;
                $('#test-results, .autwa-test-results').html(errorHtml);
                showNotification('error', 'Error de conexión');
            },
            complete: function() {
                btns.prop('disabled', false).removeClass('loading').text(originalText);
            }
        });
    }
    
    /**
     * Utilidades
     */
    function showLoadingInContainer(containers, message) {
        const loadingHtml = `
            <div class="autwa-loading-container">
                <div class="autwa-loading-spinner"></div>
                <p>${message || 'Cargando...'}</p>
            </div>
        `;
        containers.html(loadingHtml);
    }
    
    function showNotification(type, message) {
        // Remover notificaciones previas
        $('.autwa-notification').remove();
        
        const $notification = $(`
            <div class="notice notice-${type} is-dismissible autwa-notification">
                <p><strong>WP Auto Whats:</strong> ${escapeHtml(message)}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Cerrar</span>
                </button>
            </div>
        `);
        
        $('.wrap').first().prepend($notification);
        
        // Auto-remover
        setTimeout(function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Botón cerrar
        $notification.find('.notice-dismiss').on('click', function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        });
    }
    
    function escapeHtml(text) {
        if (typeof text !== 'string') {
            return text;
        }
        
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function logDebug() {
        if (autwaAjax && autwaAjax.debug) {
            console.log('AUTWA Debug:', ...arguments);
        }
    }
    
    function logError() {
        console.error('AUTWA Error:', ...arguments);
    }
    
    // Limpiar al salir
    $(window).on('beforeunload', function() {
        if (qrTimer) {
            clearInterval(qrTimer);
        }
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
        }
    });
    
    // API pública
    window.autwaEnhanced = {
        getQRCode: getEnhancedQRCode,
        checkStatus: checkSessionStatus,
        testConnection: testAPIConnection,
        showNotification: showNotification,
        refreshQR: refreshQRCode
    };
    
})(jQuery);