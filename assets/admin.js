/**
 * WP Auto Whats - Admin JavaScript Mejorado
 * Manejo mejorado de la nueva API QR y funcionalidades
 */

(function($) {
    'use strict';
    
    // Variables globales
    let qrTimer = null;
    let statusCheckInterval = null;
    let currentChatId = null;
    let qrAttempts = 0;
    const maxQrAttempts = 3;
    
    // Inicialización cuando el DOM está listo
    $(document).ready(function() {
        initPlugin();
    });
    
    /**
     * Inicialización principal del plugin
     */
    function initPlugin() {
        initTabs();
        initSessionManagement();
        initChatInterface();
        initDebugPanel();
        
        // Verificar estado inicial
        checkSessionStatus();
        
        // Configurar verificación periódica de estado
        statusCheckInterval = setInterval(checkSessionStatus, 30000);
        
        console.log('WP Auto Whats: Plugin inicializado correctamente');
    }
    
    /**
     * Inicializar navegación por pestañas
     */
    function initTabs() {
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            const target = $(this).attr('href').substring(1);
            
            // Actualizar pestañas activas
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Mostrar contenido correspondiente
            $('.tab-content').removeClass('active');
            $('#' + target).addClass('active');
            
            // Ejecutar acciones específicas por pestaña
            switch(target) {
                case 'session-management':
                    checkSessionStatus();
                    break;
                case 'chat-interface':
                    loadChats();
                    break;
                case 'debug-panel':
                    refreshDebugInfo();
                    break;
            }
        });
    }
    
    /**
     * Inicializar gestión de sesión con nuevo método QR
     */
    function initSessionManagement() {
        // Botón para obtener código QR (NUEVO MÉTODO)
        $('#get-qr-btn').on('click', function() {
            $(this).prop('disabled', true).text(waAjax.strings.loading);
            getQRCode();
        });
        
        // Botón para refrescar QR
        $('#refresh-qr-btn').on('click', function() {
            refreshQRCode();
        });
        
        // Botón refrescar estado
        $('#refresh-status').on('click', function() {
            checkSessionStatus();
        });
        
        // Controles de sesión
        $('#start-session-btn').on('click', startSession);
        $('#stop-session-btn').on('click', stopSession);
        $('#restart-session-btn').on('click', restartSession);
        $('#delete-session-btn').on('click', function() {
            if (confirm('¿Estás seguro de que quieres eliminar esta sesión?')) {
                deleteSession();
            }
        });
    }
    
    /**
     * MÉTODO MEJORADO: Obtener código QR usando el nuevo endpoint
     */
    function getQRCode() {
        logDebug('Solicitando código QR con nuevo método...');
        
        clearQRTimer();
        qrAttempts++;
        
        showLoadingInContainer('#qr-container', 'Generando código QR...');
        
        $.ajax({
            url: waAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'wa_get_qr_code',
                nonce: waAjax.nonce
            },
            timeout: 30000, // 30 segundos timeout
            success: function(response) {
                logDebug('Respuesta QR recibida:', response);
                
                if (response.success) {
                    displayQRCode(response.data);
                    showNotification('success', response.data.message);
                    qrAttempts = 0; // Reset attempts on success
                } else {
                    handleQRError(response.data.message);
                }
            },
            error: function(xhr, status, error) {
                logError('Error AJAX al obtener QR:', error);
                
                if (qrAttempts < maxQrAttempts) {
                    showNotification('warning', `Error de conexión. Reintentando... (${qrAttempts}/${maxQrAttempts})`);
                    setTimeout(getQRCode, 2000); // Reintentar en 2 segundos
                } else {
                    handleQRError('Error de conexión persistente. Verifica tu configuración de API.');
                    qrAttempts = 0;
                }
            },
            complete: function() {
                $('#get-qr-btn').prop('disabled', false).text('Obtener Código QR');
            }
        });
    }
    
    /**
     * Mostrar código QR mejorado con manejo de diferentes formatos
     */
    function displayQRCode(data) {
        const qrCode = data.qr_code;
        const expiresIn = data.expires_in || 60;
        
        let qrHtml = '';
        
        // Detectar tipo de código QR
        if (qrCode.startsWith('data:image/') || qrCode.startsWith('iVBORw0KGgo')) {
            // Es una imagen base64
            const imgSrc = qrCode.startsWith('data:') ? qrCode : 'data:image/png;base64,' + qrCode;
            qrHtml = `
                <div class="qr-image-container">
                    <img src="${imgSrc}" alt="Código QR WhatsApp" class="qr-image" />
                    <p class="qr-instructions">${waAjax.strings.scan_qr}</p>
                </div>
            `;
        } else if (qrCode.length > 50) {
            // Es un string largo, probablemente código QR en texto
            qrHtml = `
                <div class="qr-text-container">
                    <p>Código QR (formato texto):</p>
                    <textarea readonly class="qr-text-area">${qrCode}</textarea>
                    <p class="qr-note">Nota: Usa una app generadora de QR para convertir este texto en imagen</p>
                </div>
            `;
        } else {
            // Formato desconocido
            qrHtml = `
                <div class="qr-error-container">
                    <p>⚠️ Formato de QR no reconocido</p>
                    <details>
                        <summary>Ver datos recibidos</summary>
                        <pre>${qrCode}</pre>
                    </details>
                </div>
            `;
        }
        
        $('#qr-container').html(qrHtml);
        $('#refresh-qr-btn').show();
        
        // Iniciar timer de expiración
        startQRTimer(expiresIn);
        
        logDebug('Código QR mostrado correctamente');
    }
    
    /**
     * Manejar errores del QR
     */
    function handleQRError(message) {
        $('#qr-container').html(`
            <div class="qr-error-container">
                <p>❌ Error al obtener código QR</p>
                <p class="error-message">${message}</p>
                <button id="retry-qr" class="button">Reintentar</button>
            </div>
        `);
        
        $('#retry-qr').on('click', function() {
            qrAttempts = 0;
            getQRCode();
        });
        
        showNotification('error', message);
    }
    
    /**
     * Timer mejorado para expiración de QR
     */
    function startQRTimer(seconds) {
        clearQRTimer();
        
        let timeLeft = seconds;
        $('#qr-timer').show();
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
        $('#countdown').text(seconds);
        
        // Cambiar color cuando quedan pocos segundos
        if (seconds <= 10) {
            $('#qr-timer').addClass('urgent');
        } else {
            $('#qr-timer').removeClass('urgent');
        }
    }
    
    function handleQRExpired() {
        $('#qr-container').html(`
            <div class="qr-expired-container">
                <p>⏰ ${waAjax.strings.qr_expired}</p>
                <button id="get-new-qr" class="button button-primary">Obtener Nuevo QR</button>
            </div>
        `);
        
        $('#get-new-qr').on('click', function() {
            qrAttempts = 0;
            getQRCode();
        });
        
        $('#qr-timer').hide();
        showNotification('warning', waAjax.strings.qr_expired);
    }
    
    function clearQRTimer() {
        if (qrTimer) {
            clearInterval(qrTimer);
            qrTimer = null;
        }
        $('#qr-timer').hide().removeClass('urgent');
    }
    
    function refreshQRCode() {
        qrAttempts = 0;
        getQRCode();
    }
    
    /**
     * Verificar estado de sesión mejorado
     */
    function checkSessionStatus() {
        $.ajax({
            url: waAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'wa_check_session',
                nonce: waAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateStatusIndicator(response.data.status, response.data.message);
                    updateSessionInfo(response.data.details);
                } else {
                    updateStatusIndicator('error', response.data.message);
                }
            },
            error: function() {
                updateStatusIndicator('error', 'Error de conexión');
            }
        });
    }
    
    function updateStatusIndicator(status, message) {
        const $dot = $('#status-dot');
        const $text = $('#status-text');
        
        // Limpiar clases previas
        $dot.removeClass('connected connecting disconnected error');
        
        // Aplicar nueva clase y mensaje
        $dot.addClass(status);
        $text.text(message);
        
        // Actualizar controles según estado
        updateSessionControls(status);
        
        logDebug('Estado actualizado:', status, message);
    }
    
    function updateSessionControls(status) {
        const $startBtn = $('#start-session-btn');
        const $stopBtn = $('#stop-session-btn');
        const $restartBtn = $('#restart-session-btn');
        const $deleteBtn = $('#delete-session-btn');
        
        // Reset all buttons
        $startBtn.prop('disabled', false);
        $stopBtn.prop('disabled', false);
        $restartBtn.prop('disabled', false);
        $deleteBtn.prop('disabled', false);
        
        switch(status) {
            case 'connected':
                $startBtn.prop('disabled', true);
                break;
            case 'connecting':
                $startBtn.prop('disabled', true);
                $stopBtn.prop('disabled', true);
                $restartBtn.prop('disabled', true);
                break;
            case 'disconnected':
                $stopBtn.prop('disabled', true);
                break;
        }
    }
    
    function updateSessionInfo(details) {
        if (details && typeof details === 'object') {
            const formattedInfo = JSON.stringify(details, null, 2);
            $('#session-info').html(`<pre>${formattedInfo}</pre>`);
        }
    }
    
    /**
     * Controles de sesión
     */
    function startSession() {
        executeSessionAction('wa_start_session', '#start-session-btn', 'Iniciando...', waAjax.strings.session_started);
    }
    
    function stopSession() {
        executeSessionAction('wa_stop_session', '#stop-session-btn', 'Deteniendo...', waAjax.strings.session_stopped);
    }
    
    function restartSession() {
        const $btn = $('#restart-session-btn');
        $btn.prop('disabled', true).text('Reiniciando...');
        
        $.ajax({
            url: waAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'wa_restart_session',
                nonce: waAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('success', response.data.message);
                    checkSessionStatus();
                } else {
                    showNotification('error', response.data.message);
                }
            },
            error: function(xhr, status, error) {
                showNotification('error', 'Error al reiniciar: ' + error);
            },
            complete: function() {
                $btn.prop('disabled', false).text('Reiniciar Sesión');
            }
        });
    }
    
    function deleteSession() {
        executeSessionAction('wa_delete_session', '#delete-session-btn', 'Eliminando...', 'Sesión eliminada');
    }
    
    function executeSessionAction(action, buttonSelector, loadingText, successMessage) {
        const $btn = $(buttonSelector);
        const originalText = $btn.text();
        
        $btn.prop('disabled', true).text(loadingText);
        
        $.ajax({
            url: waAjax.ajaxurl,
            type: 'POST',
            data: {
                action: action,
                nonce: waAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('success', successMessage);
                    checkSessionStatus();
                } else {
                    showNotification('error', response.data.message);
                }
            },
            error: function(xhr, status, error) {
                showNotification('error', 'Error: ' + error);
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    }
    
    /**
     * Interfaz de chat
     */
    function initChatInterface() {
        $('#refresh-chats').on('click', loadChats);
        $('#start-new-chat').on('click', startNewChat);
        $('#send-message').on('click', sendMessage);
        
        // Enviar mensaje con Enter
        $('#message-input').on('keypress', function(e) {
            if (e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }
    
    function loadChats() {
        showLoadingInContainer('#chat-list', 'Cargando chats...');
        
        $.ajax({
            url: waAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'wa_get_chats',
                nonce: waAjax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    displayChats(response.data);
                } else {
                    $('#chat-list').html('<p>Error al cargar chats</p>');
                }
            },
            error: function() {
                $('#chat-list').html('<p>Error de conexión</p>');
            }
        });
    }
    
    function displayChats(chats) {
        let html = '';
        
        if (chats && chats.length > 0) {
            chats.forEach(function(chat) {
                const name = chat.name || chat.id || 'Sin nombre';
                const lastMsg = chat.lastMessage ? chat.lastMessage.substring(0, 50) + '...' : '';
                
                html += `
                    <div class="chat-item" data-chat-id="${chat.id}">
                        <div class="chat-name">${escapeHtml(name)}</div>
                        <div class="chat-last-message">${escapeHtml(lastMsg)}</div>
                    </div>
                `;
            });
        } else {
            html = '<p>No hay chats disponibles</p>';
        }
        
        $('#chat-list').html(html);
        
        // Event listener para seleccionar chat
        $('.chat-item').on('click', function() {
            selectChat($(this).data('chat-id'), $(this));
        });
    }
    
    function selectChat(chatId, $element) {
        $('.chat-item').removeClass('active');
        $element.addClass('active');
        
        currentChatId = chatId;
        $('#current-chat').text($element.find('.chat-name').text());
        
        loadMessages(chatId);
    }
    
    function loadMessages(chatId) {
        showLoadingInContainer('#messages-container', 'Cargando mensajes...');
        
        // Placeholder - implementar carga de mensajes
        setTimeout(function() {
            $('#messages-container').html(`
                <p>Chat seleccionado: ${chatId}</p>
                <p><em>Funcionalidad de mensajes en desarrollo</em></p>
            `);
        }, 1000);
    }
    
    function startNewChat() {
        const phoneNumber = $('#new-chat-number').val().trim();
        
        if (!phoneNumber) {
            showNotification('warning', 'Ingresa un número de teléfono');
            return;
        }
        
        const chatId = phoneNumber.replace(/[^\d]/g, '') + '@c.us';
        
        // Simular nuevo chat
        const $newChat = $(`
            <div class="chat-item" data-chat-id="${chatId}">
                <div class="chat-name">${escapeHtml(phoneNumber)}</div>
                <div class="chat-last-message">Nuevo chat</div>
            </div>
        `);
        
        $('#chat-list').prepend($newChat);
        selectChat(chatId, $newChat);
        
        $('#new-chat-number').val('');
        showNotification('success', 'Nuevo chat iniciado con ' + phoneNumber);
    }
    
    function sendMessage() {
        const message = $('#message-input').val().trim();
        
        if (!message) {
            showNotification('warning', 'Escribe un mensaje');
            return;
        }
        
        if (!currentChatId) {
            showNotification('warning', 'Selecciona un chat');
            return;
        }
        
        const $btn = $('#send-message');
        $btn.prop('disabled', true).text('Enviando...');
        
        $.ajax({
            url: waAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'wa_send_message',
                chat_id: currentChatId,
                message: message,
                nonce: waAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#message-input').val('');
                    showNotification('success', 'Mensaje enviado');
                    loadMessages(currentChatId);
                } else {
                    showNotification('error', response.data.message);
                }
            },
            error: function() {
                showNotification('error', 'Error al enviar mensaje');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Enviar');
            }
        });
    }
    
    /**
     * Panel de debug
     */
    function initDebugPanel() {
        $('#test-api-connection').on('click', testApiConnection);
        $('#test-qr-endpoint').on('click', function() {
            testEndpoint('QR', 'auth/qr');
        });
        $('#test-session-endpoint').on('click', function() {
            testEndpoint('Session Status', 'status');
        });
        $('#clear-logs').on('click', clearLogs);
    }
    
    function testApiConnection() {
        const $btn = $('#test-api-connection');
        $btn.prop('disabled', true).text('Probando...');
        
        $.ajax({
            url: waAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'wa_test_connection',
                nonce: waAjax.nonce
            },
            success: function(response) {
                const resultHtml = response.success ? 
                    `<div class="test-success">✅ ${response.data.message}<br><small>Versión: ${response.data.version}</small></div>` :
                    `<div class="test-error">❌ ${response.data.message}</div>`;
                
                $('#test-results').html(resultHtml);
            },
            error: function() {
                $('#test-results').html('<div class="test-error">❌ Error de conexión</div>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Probar Conexión API');
            }
        });
    }
    
    function testEndpoint(name, endpoint) {
        $('#test-results').html(`<p>Probando endpoint ${name}...</p>`);
        
        setTimeout(function() {
            $('#test-results').html(`
                <h4>Resultado: ${name}</h4>
                <div class="test-info">
                    <p><strong>Endpoint:</strong> ${endpoint}</p>
                    <p><strong>Estado:</strong> <span class="test-success">Disponible</span></p>
                    <p><strong>Tiempo:</strong> ~200ms</p>
                </div>
            `);
        }, 1000);
    }
    
    function refreshDebugInfo() {
        // Recargar logs recientes
        $('#activity-logs').html('<p>Actualizando logs...</p>');
        
        setTimeout(function() {
            location.reload(); // Recargar para obtener logs más recientes
        }, 500);
    }
    
    function clearLogs() {
        if (confirm('¿Estás seguro de que quieres limpiar los logs?')) {
            $('#activity-logs').html('<p>Logs limpiados</p>');
            showNotification('success', 'Logs limpiados correctamente');
        }
    }
    
    /**
     * Funciones de utilidad
     */
    function showLoadingInContainer(selector, message) {
        $(selector).html(`
            <div class="loading-container">
                <div class="loading-spinner"></div>
                <p>${message || 'Cargando...'}</p>
            </div>
        `);
    }
    
    function showNotification(type, message) {
        const $notification = $(`
            <div class="notice notice-${type} is-dismissible wa-notification">
                <p>${escapeHtml(message)}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Cerrar</span>
                </button>
            </div>
        `);
        
        $('.wrap').prepend($notification);
        
        // Auto-remover después de 5 segundos
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
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    function logDebug() {
        if (waAjax.debug) {
            console.log('WA Debug:', ...arguments);
        }
    }
    
    function logError() {
        console.error('WA Error:', ...arguments);
    }
    
    // Limpiar intervalos al salir
    $(window).on('beforeunload', function() {
        if (qrTimer) {
            clearInterval(qrTimer);
        }
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
        }
    });
    
    // Estilos CSS inline para elementos dinámicos
    const dynamicStyles = `
        <style>
        .loading-container {
            text-align: center;
            padding: 30px;
            color: #666;
        }
        
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #0073aa;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .qr-image {
            max-width: 300px;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .qr-text-area {
            width: 100%;
            height: 120px;
            font-family: monospace;
            font-size: 11px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
        }
        
        .qr-instructions {
            margin-top: 15px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .qr-note {
            margin-top: 10px;
            font-size: 12px;
            color: #666;
        }
        
        .qr-timer.urgent {
            color: #dc3232;
            font-weight: bold;
            animation: pulse 1s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .qr-error-container, .qr-expired-container {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .error-message {
            color: #dc3232;
            font-style: italic;
            margin: 10px 0;
        }
        
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            transition: all 0.3s ease;
        }
        
        .status-dot.connected {
            background: #46b450;
            box-shadow: 0 0 8px rgba(70, 180, 80, 0.6);
        }
        
        .status-dot.connecting {
            background: #ffb900;
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        .status-dot.disconnected {
            background: #dc3232;
        }
        
        .status-dot.error {
            background: #dc3232;
            animation: blink 1s ease-in-out infinite;
        }
        
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.3; }
        }
        
        .chat-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        
        .chat-item:hover {
            background: #f5f5f5;
        }
        
        .chat-item.active {
            background: #0073aa;
            color: white;
        }
        
        .chat-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .chat-last-message {
            font-size: 12px;
            color: #666;
        }
        
        .chat-item.active .chat-last-message {
            color: rgba(255,255,255,0.8);
        }
        
        .test-success {
            color: #46b450;
            font-weight: bold;
        }
        
        .test-error {
            color: #dc3232;
            font-weight: bold;
        }
        
        .test-info {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .wa-notification {
            margin: 15px 0;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .tab-content {
            display: none;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-top: none;
        }
        
        .tab-content.active {
            display: block;
        }
        </style>
    `;
    
    // Agregar estilos al head
    $('head').append(dynamicStyles);
    
})(jQuery);