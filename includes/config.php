<?php
/**
 * WP Auto Whats - Archivo de Configuración
 * Configuraciones centralizadas del plugin
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase de configuración centralizada
 */
class WA_Config {
    
    /**
     * Configuraciones por defecto del plugin
     */
    const DEFAULT_SETTINGS = array(
        'wa_url_type' => 'https',
        'wa_api_link' => '',
        'wa_session_name' => 'default',
        'wa_debug_mode' => false,
        'wa_auto_refresh' => true,
        'wa_refresh_interval' => 30,
        'wa_qr_timeout' => 60,
        'wa_max_retries' => 3,
        'wa_api_timeout' => 30
    );
    
    /**
     * Endpoints de la API WAHA
     */
    const API_ENDPOINTS = array(
        'version' => '/api/version',
        'auth_qr' => '/api/{session}/auth/qr',
        'status' => '/api/{session}/status',
        'start' => '/api/{session}/start',
        'stop' => '/api/{session}/stop',
        'restart' => '/api/{session}/restart',
        'delete' => '/api/{session}',
        'chats' => '/api/{session}/chats',
        'contacts' => '/api/{session}/contacts',
        'send_text' => '/api/{session}/sendText',
        'send_file' => '/api/{session}/sendFile',
        'messages' => '/api/{session}/messages'
    );
    
    /**
     * Estados de sesión posibles
     */
    const SESSION_STATES = array(
        'STOPPED' => 'stopped',
        'STARTING' => 'starting',
        'SCAN_QR_CODE' => 'scan_qr_code',
        'WORKING' => 'working',
        'AUTHENTICATED' => 'authenticated',
        'FAILED' => 'failed'
    );
    
    /**
     * Códigos de estado HTTP esperados
     */
    const HTTP_STATUS_CODES = array(
        'OK' => 200,
        'CREATED' => 201,
        'BAD_REQUEST' => 400,
        'UNAUTHORIZED' => 401,
        'NOT_FOUND' => 404,
        'INTERNAL_ERROR' => 500
    );
    
    /**
     * Niveles de log
     */
    const LOG_LEVELS = array(
        'DEBUG' => 1,
        'INFO' => 2,
        'WARNING' => 3,
        'ERROR' => 4,
        'CRITICAL' => 5
    );
    
    /**
     * Mensajes de error estándar
     */
    const ERROR_MESSAGES = array(
        'api_not_configured' => 'API URL no está configurada. Ve a Configuración para establecer la URL de tu servidor WAHA.',
        'connection_failed' => 'No se pudo conectar con el servidor WAHA. Verifica que esté funcionando y accesible.',
        'invalid_response' => 'La respuesta del servidor no es válida. Verifica la configuración de la API.',
        'session_not_found' => 'La sesión especificada no existe en el servidor WAHA.',
        'qr_generation_failed' => 'No se pudo generar el código QR. Intenta nuevamente.',
        'qr_expired' => 'El código QR ha expirado. Solicita uno nuevo.',
        'session_start_failed' => 'No se pudo iniciar la sesión. Verifica la configuración.',
        'session_stop_failed' => 'No se pudo detener la sesión.',
        'message_send_failed' => 'No se pudo enviar el mensaje. Verifica que la sesión esté activa.',
        'unauthorized' => 'No tienes permisos para realizar esta acción.',
        'timeout' => 'La operación excedió el tiempo límite.',
        'invalid_phone' => 'El número de teléfono no es válido. Usa formato internacional sin +.',
        'empty_message' => 'El mensaje no puede estar vacío.',
        'file_too_large' => 'El archivo es demasiado grande. Tamaño máximo: 64MB.',
        'unsupported_file' => 'Tipo de archivo no soportado.'
    );
    
    /**
     * Configuraciones de validación
     */
    const VALIDATION_RULES = array(
        'phone_pattern' => '/^[1-9]\d{1,14}$/',
        'session_name_pattern' => '/^[a-zA-Z0-9_-]+$/',
        'max_message_length' => 4096,
        'max_file_size' => 67108864, // 64MB
        'allowed_file_types' => array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'mp3', 'mp4', 'wav'),
        'min_session_name_length' => 3,
        'max_session_name_length' => 50
    );
    
    /**
     * Configuraciones de UI
     */
    const UI_CONFIG = array(
        'qr_refresh_interval' => 1000, // milisegundos
        'status_check_interval' => 30000, // 30 segundos
        'notification_timeout' => 5000, // 5 segundos
        'loading_timeout' => 30000, // 30 segundos
        'max_chat_history' => 50,
        'max_log_lines' => 100
    );
    
    /**
     * Configuraciones de caché
     */
    const CACHE_CONFIG = array(
        'session_status_ttl' => 30, // segundos
        'chats_list_ttl' => 300, // 5 minutos
        'contacts_list_ttl' => 3600, // 1 hora
        'qr_code_ttl' => 60 // 1 minuto
    );
    
    /**
     * Obtener endpoint completo de la API
     * 
     * @param string $endpoint_key Clave del endpoint
     * @param string $session_name Nombre de la sesión
     * @param string $api_url URL base de la API
     * @return string URL completa del endpoint
     */
    public static function get_api_endpoint($endpoint_key, $session_name = '', $api_url = '') {
        if (!isset(self::API_ENDPOINTS[$endpoint_key])) {
            return '';
        }
        
        $endpoint = self::API_ENDPOINTS[$endpoint_key];
        
        // Reemplazar placeholder de sesión
        if ($session_name) {
            $endpoint = str_replace('{session}', $session_name, $endpoint);
        }
        
        // Agregar URL base si se proporciona
        if ($api_url) {
            return rtrim($api_url, '/') . $endpoint;
        }
        
        return $endpoint;
    }
    
    /**
     * Obtener mensaje de error localizado
     * 
     * @param string $error_key Clave del mensaje de error
     * @return string Mensaje de error localizado
     */
    public static function get_error_message($error_key) {
        if (isset(self::ERROR_MESSAGES[$error_key])) {
            return __(self::ERROR_MESSAGES[$error_key], 'wp-auto-whats');
        }
        
        return __('Error desconocido', 'wp-auto-whats');
    }
    
    /**
     * Validar número de teléfono
     * 
     * @param string $phone Número a validar
     * @return bool True si es válido
     */
    public static function validate_phone($phone) {
        $clean_phone = preg_replace('/[^\d]/', '', $phone);
        return preg_match(self::VALIDATION_RULES['phone_pattern'], $clean_phone);
    }
    
    /**
     * Validar nombre de sesión
     * 
     * @param string $session_name Nombre a validar
     * @return bool True si es válido
     */
    public static function validate_session_name($session_name) {
        $length = strlen($session_name);
        return ($length >= self::VALIDATION_RULES['min_session_name_length'] &&
                $length <= self::VALIDATION_RULES['max_session_name_length'] &&
                preg_match(self::VALIDATION_RULES['session_name_pattern'], $session_name));
    }
    
    /**
     * Formatear número de teléfono para WhatsApp
     * 
     * @param string $phone Número de teléfono
     * @return string Número formateado con @c.us
     */
    public static function format_whatsapp_id($phone) {
        $clean_phone = preg_replace('/[^\d]/', '', $phone);
        return $clean_phone . '@c.us';
    }
    
    /**
     * Verificar si un estado de sesión es válido
     * 
     * @param string $state Estado a verificar
     * @return bool True si es válido
     */
    public static function is_valid_session_state($state) {
        return in_array(strtolower($state), self::SESSION_STATES);
    }
    
    /**
     * Obtener configuraciones por defecto
     * 
     * @return array Configuraciones por defecto
     */
    public static function get_default_settings() {
        return self::DEFAULT_SETTINGS;
    }
    
    /**
     * Obtener configuración de validación
     * 
     * @param string $key Clave de configuración
     * @return mixed Valor de configuración o null
     */
    public static function get_validation_rule($key) {
        return isset(self::VALIDATION_RULES[$key]) ? self::VALIDATION_RULES[$key] : null;
    }
    
    /**
     * Verificar si un archivo es válido
     * 
     * @param string $filename Nombre del archivo
     * @param int $filesize Tamaño del archivo
     * @return array Array con 'valid' (bool) y 'error' (string)
     */
    public static function validate_file($filename, $filesize) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Verificar extensión
        if (!in_array($extension, self::VALIDATION_RULES['allowed_file_types'])) {
            return array(
                'valid' => false,
                'error' => 'unsupported_file'
            );
        }
        
        // Verificar tamaño
        if ($filesize > self::VALIDATION_RULES['max_file_size']) {
            return array(
                'valid' => false,
                'error' => 'file_too_large'
            );
        }
        
        return array(
            'valid' => true,
            'error' => null
        );
    }
    
    /**
     * Obtener configuración de caché
     * 
     * @param string $key Clave de configuración
     * @return int TTL en segundos
     */
    public static function get_cache_ttl($key) {
        return isset(self::CACHE_CONFIG[$key]) ? self::CACHE_CONFIG[$key] : 300;
    }
    
    /**
     * Determinar nivel de log mínimo según configuración
     * 
     * @param bool $debug_mode Si está en modo debug
     * @return int Nivel mínimo de log
     */
    public static function get_min_log_level($debug_mode = false) {
        return $debug_mode ? self::LOG_LEVELS['DEBUG'] : self::LOG_LEVELS['INFO'];
    }
    
    /**
     * Obtener configuraciones de UI para JavaScript
     * 
     * @return array Configuraciones para frontend
     */
    public static function get_ui_config() {
        return self::UI_CONFIG;
    }
}
?>