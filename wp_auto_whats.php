<?php
/**
 * Plugin Name: WP Auto Whats
 * Plugin URI: https://github.com/felipe1712/AutoWAWP
 * Description: A WordPress plugin to connect your site with the WAHA API for seamless WhatsApp messaging, contact management, and session control from your WordPress dashboard.
 * Version: 1.2.1
 * Author: felipe1712
 * License: GPL v2 or later
 * Text Domain: wp-auto-whats
 * Domain Path: /languages
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('WP_AUTO_WHATS_VERSION', '1.2.1');
define('WP_AUTO_WHATS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_AUTO_WHATS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_AUTO_WHATS_PLUGIN_FILE', __FILE__);

/**
 * Clase principal del plugin WP Auto Whats
 */
class WP_Auto_Whats {
    
    private $api_url;
    private $session_name;
    private $debug_mode;
    private $api_version = '1.0';
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        
        // AJAX handlers - Mejorados para nueva autenticación
        add_action('wp_ajax_wa_get_qr_code', array($this, 'ajax_get_qr_code'));
        add_action('wp_ajax_wa_check_session', array($this, 'ajax_check_session'));
        add_action('wp_ajax_wa_start_session', array($this, 'ajax_start_session'));
        add_action('wp_ajax_wa_stop_session', array($this, 'ajax_stop_session'));
        add_action('wp_ajax_wa_restart_session', array($this, 'ajax_restart_session'));
        add_action('wp_ajax_wa_delete_session', array($this, 'ajax_delete_session'));
        add_action('wp_ajax_wa_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_wa_get_chats', array($this, 'ajax_get_chats'));
        add_action('wp_ajax_wa_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_wa_get_contacts', array($this, 'ajax_get_contacts'));
        
        // Cargar configuración
        $this->load_settings();
    }
    
    public function init() {
        // Cargar textdomain
        load_plugin_textdomain('wp-auto-whats', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    private function load_settings() {
        $url_type = get_option('wa_url_type', 'https');
        $api_link = get_option('wa_api_link', '');
        $this->session_name = get_option('wa_session_name', 'default');
        $this->debug_mode = get_option('wa_debug_mode', false);
        
        // CORREGIDO: Limpiar y construir URL correctamente
        if ($api_link) {
            // Limpiar URL de protocolos duplicados
            $api_link = preg_replace('/^https?:\/\//', '', $api_link);
            $api_link = rtrim($api_link, '/');
            
            // Construir URL base SIN /api al final (se agregará según el endpoint)
            $this->api_url = $url_type . '://' . $api_link;
            
            // Debug: Log de URL construida
            if ($this->debug_mode) {
                $this->log_debug("URL base construida: " . $this->api_url);
                $this->log_debug("Sesión configurada: " . $this->session_name);
            }
        }
    }
    
    public function admin_menu() {
        // Menú principal
        add_menu_page(
            __('WP Auto Whats', 'wp-auto-whats'),
            __('WP Auto Whats', 'wp-auto-whats'),
            'manage_options',
            'wp-auto-whats',
            array($this, 'admin_page'),
            'dashicons-whatsapp',
            30
        );
        
        // Submenús
        add_submenu_page(
            'wp-auto-whats',
            __('Configuración', 'wp-auto-whats'),
            __('Configuración', 'wp-auto-whats'),
            'manage_options',
            'wp-auto-whats-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'wp-auto-whats',
            __('Lista de Contactos', 'wp-auto-whats'),
            __('Lista de Contactos', 'wp-auto-whats'),
            'manage_options',
            'wp-auto-whats-contacts',
            array($this, 'contacts_page')
        );
    }
    
    public function admin_scripts($hook) {
        if (strpos($hook, 'wp-auto-whats') === false) {
            return;
        }
        
        wp_enqueue_script('wp-auto-whats-admin', WP_AUTO_WHATS_PLUGIN_URL . 'assets/admin.js', array('jquery'), WP_AUTO_WHATS_VERSION, true);
        wp_enqueue_style('wp-auto-whats-admin', WP_AUTO_WHATS_PLUGIN_URL . 'assets/admin.css', array(), WP_AUTO_WHATS_VERSION);
        
        // Localizar script
        wp_localize_script('wp-auto-whats-admin', 'waAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wa_nonce'),
            'debug' => $this->debug_mode,
            'strings' => array(
                'connecting' => __('Conectando...', 'wp-auto-whats'),
                'connected' => __('Conectado', 'wp-auto-whats'),
                'disconnected' => __('Desconectado', 'wp-auto-whats'),
                'error' => __('Error', 'wp-auto-whats'),
                'scan_qr' => __('Escanea el código QR con tu teléfono', 'wp-auto-whats'),
                'qr_expired' => __('Código QR expirado. Solicita uno nuevo.', 'wp-auto-whats'),
                'session_started' => __('Sesión iniciada correctamente', 'wp-auto-whats'),
                'session_stopped' => __('Sesión detenida', 'wp-auto-whats'),
                'loading' => __('Cargando...', 'wp-auto-whats')
            )
        ));
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('WP Auto Whats - Panel Principal', 'wp-auto-whats'); ?></h1>
            
            <!-- Navegación por pestañas -->
            <nav class="nav-tab-wrapper">
                <a href="#session-management" class="nav-tab nav-tab-active"><?php _e('Gestión de Sesión', 'wp-auto-whats'); ?></a>
                <a href="#chat-interface" class="nav-tab"><?php _e('Chat', 'wp-auto-whats'); ?></a>
                <a href="#debug-panel" class="nav-tab"><?php _e('Debug', 'wp-auto-whats'); ?></a>
            </nav>
            
            <!-- Contenido de pestañas -->
            <div id="session-management" class="tab-content active">
                <?php $this->render_session_management(); ?>
            </div>
            
            <div id="chat-interface" class="tab-content">
                <?php $this->render_chat_interface(); ?>
            </div>
            
            <div id="debug-panel" class="tab-content">
                <?php $this->render_debug_panel(); ?>
            </div>
        </div>
        <?php
    }
    
    private function render_session_management() {
        ?>
        <div class="wa-session-container">
            <h2><?php _e('Gestión de Sesión WhatsApp', 'wp-auto-whats'); ?></h2>
            
            <!-- Estado de la sesión -->
            <div class="wa-status-section">
                <h3><?php _e('Estado de la Sesión', 'wp-auto-whats'); ?></h3>
                <div class="status-indicator">
                    <span id="status-dot" class="status-dot"></span>
                    <span id="status-text"><?php _e('Verificando estado...', 'wp-auto-whats'); ?></span>
                    <button id="refresh-status" class="button"><?php _e('Actualizar', 'wp-auto-whats'); ?></button>
                </div>
                <div id="session-info" class="session-info"></div>
            </div>
            
            <!-- Código QR Mejorado -->
            <div class="wa-qr-section">
                <h3><?php _e('Autenticación WhatsApp', 'wp-auto-whats'); ?></h3>
                <p class="description"><?php _e('Obtén el código QR para vincular tu dispositivo WhatsApp. El nuevo método es más seguro y estable.', 'wp-auto-whats'); ?></p>
                
                <div id="qr-container" class="qr-container">
                    <div class="qr-placeholder">
                        <p><?php _e('Haz clic en "Obtener QR" para generar el código de autenticación', 'wp-auto-whats'); ?></p>
                    </div>
                </div>
                
                <div class="qr-controls">
                    <button id="get-qr-btn" class="button button-primary"><?php _e('Obtener Código QR', 'wp-auto-whats'); ?></button>
                    <button id="refresh-qr-btn" class="button" style="display:none;"><?php _e('Renovar QR', 'wp-auto-whats'); ?></button>
                </div>
                
                <div id="qr-timer" class="qr-timer" style="display:none;">
                    <p><?php _e('El código expira en:', 'wp-auto-whats'); ?> <span id="countdown">60</span> <?php _e('segundos', 'wp-auto-whats'); ?></p>
                </div>
            </div>
            
            <!-- Controles de sesión -->
            <div class="wa-controls-section">
                <h3><?php _e('Controles de Sesión', 'wp-auto-whats'); ?></h3>
                <div class="button-group">
                    <button id="start-session-btn" class="button button-primary"><?php _e('Iniciar Sesión', 'wp-auto-whats'); ?></button>
                    <button id="stop-session-btn" class="button button-secondary"><?php _e('Detener Sesión', 'wp-auto-whats'); ?></button>
                    <button id="restart-session-btn" class="button"><?php _e('Reiniciar Sesión', 'wp-auto-whats'); ?></button>
                    <button id="delete-session-btn" class="button button-link-delete"><?php _e('Eliminar Sesión', 'wp-auto-whats'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function render_chat_interface() {
        ?>
        <div class="wa-chat-container">
            <h2><?php _e('Interfaz de Chat', 'wp-auto-whats'); ?></h2>
            <div class="chat-layout">
                <div class="chat-sidebar">
                    <h3><?php _e('Chats', 'wp-auto-whats'); ?></h3>
                    <div id="chat-list" class="chat-list">
                        <p><?php _e('Cargando chats...', 'wp-auto-whats'); ?></p>
                    </div>
                    <button id="refresh-chats" class="button"><?php _e('Actualizar Chats', 'wp-auto-whats'); ?></button>
                </div>
                
                <div class="chat-main">
                    <div class="chat-header">
                        <h4 id="current-chat"><?php _e('Selecciona un chat', 'wp-auto-whats'); ?></h4>
                    </div>
                    
                    <div id="messages-container" class="messages-container">
                        <p><?php _e('Selecciona un chat para ver los mensajes', 'wp-auto-whats'); ?></p>
                    </div>
                    
                    <div class="message-input-container">
                        <input type="text" id="new-chat-number" placeholder="<?php _e('Número para nuevo chat (ej: 1234567890)', 'wp-auto-whats'); ?>" />
                        <button id="start-new-chat" class="button"><?php _e('Nuevo Chat', 'wp-auto-whats'); ?></button>
                        
                        <div class="message-compose">
                            <textarea id="message-input" placeholder="<?php _e('Escribe tu mensaje...', 'wp-auto-whats'); ?>" rows="3"></textarea>
                            <div class="message-actions">
                                <button id="send-message" class="button button-primary"><?php _e('Enviar', 'wp-auto-whats'); ?></button>
                                <button id="attach-file" class="button"><?php _e('📎 Adjuntar', 'wp-auto-whats'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function render_debug_panel() {
        ?>
        <div class="wa-debug-container">
            <h2><?php _e('Panel de Debug y Monitoreo', 'wp-auto-whats'); ?></h2>
            
            <div class="debug-section">
                <h3><?php _e('Información del Sistema', 'wp-auto-whats'); ?></h3>
                <div class="system-info">
                    <p><strong><?php _e('Plugin Version:', 'wp-auto-whats'); ?></strong> <?php echo WP_AUTO_WHATS_VERSION; ?></p>
                    <p><strong><?php _e('API URL:', 'wp-auto-whats'); ?></strong> <?php echo esc_html($this->api_url); ?></p>
                    <p><strong><?php _e('Sesión:', 'wp-auto-whats'); ?></strong> <?php echo esc_html($this->session_name); ?></p>
                    <p><strong><?php _e('Debug Mode:', 'wp-auto-whats'); ?></strong> <?php echo $this->debug_mode ? __('Activado', 'wp-auto-whats') : __('Desactivado', 'wp-auto-whats'); ?></p>
                </div>
            </div>
            
            <div class="debug-section">
                <h3><?php _e('Pruebas de Conectividad', 'wp-auto-whats'); ?></h3>
                <div class="connectivity-tests">
                    <button id="test-api-connection" class="button"><?php _e('Probar Conexión API', 'wp-auto-whats'); ?></button>
                    <button id="test-qr-endpoint" class="button"><?php _e('Probar Endpoint QR', 'wp-auto-whats'); ?></button>
                    <button id="test-session-endpoint" class="button"><?php _e('Probar Endpoint Sesión', 'wp-auto-whats'); ?></button>
                    <div id="test-results" class="test-results"></div>
                </div>
            </div>
            
            <div class="debug-section">
                <h3><?php _e('Logs de Actividad', 'wp-auto-whats'); ?></h3>
                <div id="activity-logs" class="activity-logs">
                    <?php echo $this->get_recent_logs(); ?>
                </div>
                <button id="clear-logs" class="button"><?php _e('Limpiar Logs', 'wp-auto-whats'); ?></button>
            </div>
        </div>
        <?php
    }
    
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $url_type = get_option('wa_url_type', 'https');
        $api_link = get_option('wa_api_link', '');
        $session_name = get_option('wa_session_name', 'default');
        $debug_mode = get_option('wa_debug_mode', false);
        ?>
        <div class="wrap">
            <h1><?php _e('Configuración WP Auto Whats', 'wp-auto-whats'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('wa_settings_save'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Tipo de URL', 'wp-auto-whats'); ?></th>
                        <td>
                            <select name="wa_url_type">
                                <option value="https" <?php selected($url_type, 'https'); ?>>HTTPS</option>
                                <option value="http" <?php selected($url_type, 'http'); ?>>HTTP</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Enlace de API', 'wp-auto-whats'); ?></th>
                        <td>
                            <input type="text" name="wa_api_link" value="<?php echo esc_attr($api_link); ?>" class="regular-text" />
                            <p class="description"><?php _e('Ejemplo: example.com:3000', 'wp-auto-whats'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Nombre de Sesión', 'wp-auto-whats'); ?></th>
                        <td>
                            <input type="text" name="wa_session_name" value="<?php echo esc_attr($session_name); ?>" class="regular-text" />
                            <p class="description"><?php _e('Nombre identificador para tu sesión (ej: default)', 'wp-auto-whats'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Modo Debug', 'wp-auto-whats'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wa_debug_mode" value="1" <?php checked($debug_mode, true); ?> />
                                <?php _e('Activar logs detallados para resolución de problemas', 'wp-auto-whats'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Guardar Configuración', 'wp-auto-whats')); ?>
            </form>
        </div>
        <?php
    }
    
    public function contacts_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Lista de Contactos', 'wp-auto-whats'); ?></h1>
            <div id="contacts-container" class="contacts-container">
                <p><?php _e('Cargando contactos...', 'wp-auto-whats'); ?></p>
            </div>
            <button id="import-contacts" class="button button-primary"><?php _e('Importar Contactos', 'wp-auto-whats'); ?></button>
        </div>
        <?php
    }
    
    private function save_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'wa_settings_save')) {
            return;
        }
        
        update_option('wa_url_type', sanitize_text_field($_POST['wa_url_type']));
        update_option('wa_api_link', sanitize_text_field($_POST['wa_api_link']));
        update_option('wa_session_name', sanitize_text_field($_POST['wa_session_name']));
        update_option('wa_debug_mode', isset($_POST['wa_debug_mode']));
        
        $this->load_settings();
        
        echo '<div class="notice notice-success"><p>' . __('Configuración guardada correctamente.', 'wp-auto-whats') . '</p></div>';
    }
    
    // ===== NUEVOS MÉTODOS AJAX CORREGIDOS =====
    
    /**
     * CORREGIDO: Obtener código QR usando URL correcta
     */
    public function ajax_get_qr_code() {
        check_ajax_referer('wa_nonce', 'nonce');
        
        if (!$this->api_url) {
            wp_send_json_error(array('message' => __('API URL no configurada', 'wp-auto-whats')));
        }
        
        try {
            $this->log_debug('Solicitando código QR para sesión: ' . $this->session_name);
            
            // CORREGIDO: Construir URL correcta del endpoint QR
            $qr_endpoint = $this->api_url . '/api/' . $this->session_name . '/auth/qr';
            
            $this->log_debug('URL QR construida: ' . $qr_endpoint);
            
            $response = $this->make_api_request($qr_endpoint, 'GET');
            
            if (is_wp_error($response)) {
                throw new Exception('Error en solicitud QR: ' . $response->get_error_message());
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            $this->log_debug("Respuesta QR - Código: {$status_code}");
            
            if ($status_code !== 200) {
                $this->log_debug("Cuerpo de respuesta de error: " . $body);
                throw new Exception("Error HTTP {$status_code} al obtener QR. Respuesta: " . $body);
            }
            
            $data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Respuesta JSON inválida del servidor');
            }
            
            // Procesar diferentes formatos de respuesta QR
            $qr_data = null;
            $expires_in = 60; // Default
            
            if (isset($data['qr'])) {
                $qr_data = $data['qr'];
            } elseif (isset($data['qrCode'])) {
                $qr_data = $data['qrCode'];
            } elseif (isset($data['base64'])) {
                $qr_data = $data['base64'];
            } elseif (isset($data['image'])) {
                $qr_data = $data['image'];
            }
            
            if (isset($data['expiresIn'])) {
                $expires_in = intval($data['expiresIn']);
            } elseif (isset($data['ttl'])) {
                $expires_in = intval($data['ttl']);
            }
            
            if (!$qr_data) {
                $this->log_debug("Datos de respuesta QR: " . print_r($data, true));
                throw new Exception('No se encontró código QR en la respuesta del servidor');
            }
            
            $this->log_debug('Código QR obtenido exitosamente');
            
            wp_send_json_success(array(
                'message' => __('Código QR generado correctamente', 'wp-auto-whats'),
                'qr_code' => $qr_data,
                'expires_in' => $expires_in,
                'session' => $this->session_name,
                'timestamp' => current_time('timestamp')
            ));
            
        } catch (Exception $e) {
            $this->log_error('Error al obtener código QR: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Error al obtener código QR: ', 'wp-auto-whats') . $e->getMessage()
            ));
        }
    }
    
    /**
     * CORREGIDO: Verificar estado de sesión usando URL correcta
     */
    public function ajax_check_session() {
        check_ajax_referer('wa_nonce', 'nonce');
        
        try {
            // CORREGIDO: Construir URL correcta del endpoint de estado
            $status_endpoint = $this->api_url . '/api/' . $this->session_name . '/status';
            
            $this->log_debug('URL estado construida: ' . $status_endpoint);
            
            $response = $this->make_api_request($status_endpoint, 'GET');
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            $this->log_debug("Respuesta estado - Código: {$status_code}");
            
            if ($status_code === 404) {
                // Sesión no encontrada - necesita ser creada
                wp_send_json_success(array(
                    'status' => 'disconnected',
                    'message' => __('Sesión no encontrada. Necesita ser iniciada.', 'wp-auto-whats'),
                    'details' => array('error' => 'Session not found', 'statusCode' => 404)
                ));
                return;
            }
            
            if ($status_code !== 200) {
                throw new Exception("Error HTTP {$status_code}: " . $body);
            }
            
            $data = json_decode($body, true);
            
            $status = 'disconnected';
            $message = __('Desconectado', 'wp-auto-whats');
            
            if (isset($data['status'])) {
                switch (strtolower($data['status'])) {
                    case 'working':
                    case 'authenticated':
                    case 'ready':
                        $status = 'connected';
                        $message = __('Conectado y listo', 'wp-auto-whats');
                        break;
                    case 'starting':
                    case 'initializing':
                        $status = 'connecting';
                        $message = __('Iniciando...', 'wp-auto-whats');
                        break;
                    case 'stopped':
                    case 'failed':
                        $status = 'disconnected';
                        $message = __('Desconectado', 'wp-auto-whats');
                        break;
                }
            }
            
            wp_send_json_success(array(
                'status' => $status,
                'message' => $message,
                'details' => $data
            ));
            
        } catch (Exception $e) {
            $this->log_error('Error al verificar sesión: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * CORREGIDO: Iniciar sesión usando URL correcta
     */
    public function ajax_start_session() {
        check_ajax_referer('wa_nonce', 'nonce');
        
        try {
            // CORREGIDO: Construir URL correcta del endpoint de inicio
            $start_endpoint = $this->api_url . '/api/' . $this->session_name . '/start';
            
            $this->log_debug('URL inicio construida: ' . $start_endpoint);
            
            $response = $this->make_api_request($start_endpoint, 'POST');
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code !== 200 && $status_code !== 201) {
                throw new Exception("Error HTTP {$status_code}: " . $body);
            }
            
            wp_send_json_success(array(
                'message' => __('Sesión iniciada correctamente', 'wp-auto-whats')
            ));
            
        } catch (Exception $e) {
            $this->log_error('Error al iniciar sesión: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function ajax_stop_session() {
        check_ajax_referer('wa_nonce', 'nonce');
        
        try {
            $stop_endpoint = $this->api_url . '/api/' . $this->session_name . '/stop';
            $response = $this->make_api_request($stop_endpoint, 'POST');
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            wp_send_json_success(array(
                'message' => __('Sesión detenida correctamente', 'wp-auto-whats')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function ajax_restart_session() {
        check_ajax_referer('wa_nonce', 'nonce');
        
        try {
            // Primero detener
            $stop_endpoint = $this->api_url . '/api/' . $this->session_name . '/stop';
            $this->make_api_request($stop_endpoint, 'POST');
            
            // Esperar un poco
            sleep(2);
            
            // Luego iniciar
            $start_endpoint = $this->api_url . '/api/' . $this->session_name . '/start';
            $response = $this->make_api_request($start_endpoint, 'POST');
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            wp_send_json_success(array(
                'message' => __('Sesión reiniciada correctamente', 'wp-auto-whats')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function ajax_delete_session() {
        check_ajax_referer('wa_nonce', 'nonce');
        
        try {
            $delete_endpoint = $this->api_url . '/api/' . $this->session_name;
            $response = $this->make_api_request($delete_endpoint, 'DELETE');
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            wp_send_json_success(array(
                'message' => __('Sesión eliminada correctamente', 'wp-auto-whats')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * CORREGIDO: Probar conexión usando URL correcta
     */
    public function ajax_test_connection() {
        check_ajax_referer('wa_nonce', 'nonce');
        
        try {
            if (!$this->api_url) {
                throw new Exception(__('URL de API no configurada', 'wp-auto-whats'));
            }
            
            // CORREGIDO: Usar endpoint de versión para probar conectividad
            $test_endpoint = $this->api_url . '/api/version';
            
            $this->log_debug('URL test construida: ' . $test_endpoint);
            
            $response = $this->make_api_request($test_endpoint, 'GET');
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code !== 200) {
                throw new Exception("Error HTTP {$status_code}: " . $body);
            }
            
            wp_send_json_success(array(
                'message' => __('Conexión exitosa con la API WAHA', 'wp-auto-whats'),
                'version' => $body
            ));
            
        } catch (Exception $e) {
            $this->log_error('Error al probar conexión: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function ajax_get_chats() {
        check_ajax_referer('wa_nonce', 'nonce');
        
        try {
            $chats_endpoint = $this->api_url . '/api/' . $this->session_name . '/chats';
            $response = $this->make_api_request($chats_endpoint, 'GET');
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $chats = json_decode($body, true);
            
            wp_send_json_success($chats);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function ajax_send_message() {
        check_ajax_referer('wa_nonce', 'nonce');
        
        try {
            $chat_id = sanitize_text_field($_POST['chat_id']);
            $message = sanitize_textarea_field($_POST['message']);
            
            if (!$chat_id || !$message) {
                throw new Exception(__('Chat ID y mensaje son requeridos', 'wp-auto-whats'));
            }
            
            $send_endpoint = $this->api_url . '/api/' . $this->session_name . '/sendText';
            
            $data = array(
                'chatId' => $chat_id,
                'text' => $message
            );
            
            $response = $this->make_api_request($send_endpoint, 'POST', $data);
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            wp_send_json_success(array(
                'message' => __('Mensaje enviado correctamente', 'wp-auto-whats')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function ajax_get_contacts() {
        check_ajax_referer('wa_nonce', 'nonce');
        
        try {
            $contacts_endpoint = $this->api_url . '/api/' . $this->session_name . '/contacts';
            $response = $this->make_api_request($contacts_endpoint, 'GET');
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $contacts = json_decode($body, true);
            
            wp_send_json_success($contacts);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    // ===== MÉTODOS DE UTILIDAD =====
    
    private function make_api_request($url, $method = 'GET', $data = null) {
        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'WP-Auto-Whats/' . WP_AUTO_WHATS_VERSION
            )
        );
        
        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = is_array($data) ? json_encode($data) : $data;
        }
        
        $this->log_debug("API Request: {$method} {$url}");
        if ($data) {
            $this->log_debug("Request data: " . print_r($data, true));
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_error("HTTP Error: " . $response->get_error_message());
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $this->log_debug("Response code: {$code}");
        }
        
        return $response;
    }
    
    private function log_debug($message) {
        if ($this->debug_mode) {
            $this->write_log('DEBUG', $message);
        }
    }
    
    private function log_error($message) {
        $this->write_log('ERROR', $message);
    }
    
    private function write_log($level, $message) {
        $log_entry = sprintf(
            "[%s] [%s] %s\n",
            current_time('Y-m-d H:i:s'),
            $level,
            $message
        );
        
        $uploads_dir = wp_upload_dir();
        $log_file = $uploads_dir['basedir'] . '/wa-debug.log';
        
        error_log($log_entry, 3, $log_file);
    }
    
    private function get_recent_logs() {
        $uploads_dir = wp_upload_dir();
        $log_file = $uploads_dir['basedir'] . '/wa-debug.log';
        
        if (!file_exists($log_file)) {
            return '<p>' . __('No hay logs disponibles.', 'wp-auto-whats') . '</p>';
        }
        
        $logs = file_get_contents($log_file);
        $lines = explode("\n", $logs);
        $recent_logs = array_slice($lines, -20); // Últimas 20 líneas
        
        return '<pre>' . esc_html(implode("\n", $recent_logs)) . '</pre>';
    }
}

// Instanciar plugin
new WP_Auto_Whats();

// Hook de activación
register_activation_hook(__FILE__, function() {
    add_option('wa_url_type', 'https');
    add_option('wa_api_link', '');
    add_option('wa_session_name', 'default');
    add_option('wa_debug_mode', false);
});

// Hook de desactivación
register_deactivation_hook(__FILE__, function() {
    $uploads_dir = wp_upload_dir();
    $log_file = $uploads_dir['basedir'] . '/wa-debug.log';
    if (file_exists($log_file)) {
        unlink($log_file);
    }
});
?>