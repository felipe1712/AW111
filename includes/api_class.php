<?php
/**
 * WP Auto Whats API Class
 *
 * @since      1.0.0
 * @package    WP_Auto_Whats
 * @subpackage WP_Auto_Whats/includes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WP Auto Whats API Class
 *
 * Handles communication with WAHA API
 *
 * @since      1.0.0
 * @package    WP_Auto_Whats
 * @subpackage WP_Auto_Whats/includes
 * @author     felipe1712
 */
class WP_Auto_Whats_API {

    /**
     * API Base URL
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $api_url    The base URL for the WAHA API.
     */
    private $api_url;

    /**
     * API Session
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $session    The session name for API calls.
     */
    private $session;

    /**
     * Debug mode
     *
     * @since    1.0.0
     * @access   private
     * @var      bool    $debug    Whether debug mode is enabled.
     */
    private $debug;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->build_api_url();
        $this->session = get_option('wp_auto_whats_api_session', 'default');
        $this->debug = get_option('wp_auto_whats_debug_mode', false);
    }

    /**
     * Build the API URL from settings
     *
     * @since    1.0.0
     * @access   private
     */
    private function build_api_url() {
        $protocol = get_option('wp_auto_whats_api_url_type', 'https');
        $domain = get_option('wp_auto_whats_api_link', '');

        // Limpiar el dominio de protocolos existentes
        $domain = str_replace(array('http://', 'https://'), '', $domain);
        $domain = trim($domain, '/');

        // Construir URL completa
        if (!empty($domain)) {
            $this->api_url = $protocol . '://' . $domain;
        } else {
            $this->api_url = '';
        }

        if ($this->debug) {
            error_log('WP Auto Whats: API URL construida: ' . $this->api_url);
        }
    }

    /**
     * Test API connection
     *
     * @since    1.0.0
     * @return   array    Response from API test
     */
    public function test_connection() {
        if (empty($this->api_url)) {
            return array(
                'success' => false,
                'message' => __('API URL no configurada', 'wp-auto-whats')
            );
        }

        $endpoint = '/api/sessions';
        $response = $this->make_request('GET', $endpoint);

        if ($response['success']) {
            return array(
                'success' => true,
                'message' => __('Conexión exitosa con la API', 'wp-auto-whats'),
                'data' => $response['data']
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Error al conectar con la API: ', 'wp-auto-whats') . $response['message'],
                'debug_info' => $response['debug_info'] ?? null
            );
        }
    }

    /**
     * Make HTTP request to WAHA API
     *
     * @since    1.0.0
     * @param    string    $method      HTTP method (GET, POST, PUT, DELETE)
     * @param    string    $endpoint    API endpoint
     * @param    array     $data        Data to send
     * @return   array     Response array
     */
    private function make_request($method = 'GET', $endpoint = '', $data = array()) {
        if (empty($this->api_url)) {
            return array(
                'success' => false,
                'message' => __('API URL no configurada', 'wp-auto-whats'),
                'debug_info' => array(
                    'protocol' => get_option('wp_auto_whats_api_url_type', 'https'),
                    'domain' => get_option('wp_auto_whats_api_link', ''),
                    'constructed_url' => $this->api_url
                )
            );
        }

        // Construir URL completa
        $url = rtrim($this->api_url, '/') . '/' . ltrim($endpoint, '/');

        // Agregar session si está definida y no está en los datos
        if (!empty($this->session) && strpos($endpoint, 'session') === false) {
            $data['session'] = $this->session;
        }

        if ($this->debug) {
            error_log('WP Auto Whats: Haciendo petición a: ' . $url);
            error_log('WP Auto Whats: Método: ' . $method);
            error_log('WP Auto Whats: Datos: ' . json_encode($data));
        }

        // Configurar cURL
        $curl_options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'WP Auto Whats/' . WP_AUTO_WHATS_VERSION,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json'
            )
        );

        // Configurar método HTTP
        switch (strtoupper($method)) {
            case 'POST':
                $curl_options[CURLOPT_POST] = true;
                if (!empty($data)) {
                    $curl_options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;
            case 'PUT':
                $curl_options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                if (!empty($data)) {
                    $curl_options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;
            case 'DELETE':
                $curl_options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
            case 'GET':
            default:
                if (!empty($data)) {
                    $url .= '?' . http_build_query($data);
                    $curl_options[CURLOPT_URL] = $url;
                }
                break;
        }

        // Ejecutar petición
        $curl = curl_init();
        curl_setopt_array($curl, $curl_options);
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        $curl_errno = curl_errno($curl);
        curl_close($curl);

        if ($this->debug) {
            error_log('WP Auto Whats: Código HTTP: ' . $http_code);
            error_log('WP Auto Whats: Respuesta: ' . $response);
            if ($curl_error) {
                error_log('WP Auto Whats: Error cURL: ' . $curl_error);
            }
        }

        // Verificar errores de cURL
        if ($curl_errno !== 0) {
            return array(
                'success' => false,
                'message' => 'cURL Error ' . $curl_errno . ': ' . $curl_error,
                'debug_info' => array(
                    'url' => $url,
                    'method' => $method,
                    'curl_errno' => $curl_errno,
                    'curl_error' => $curl_error,
                    'api_url_components' => array(
                        'protocol' => get_option('wp_auto_whats_api_url_type', 'https'),
                        'domain' => get_option('wp_auto_whats_api_link', ''),
                        'constructed_url' => $this->api_url
                    )
                )
            );
        }

        // Verificar código de respuesta HTTP
        if ($http_code < 200 || $http_code >= 300) {
            return array(
                'success' => false,
                'message' => 'HTTP Error ' . $http_code,
                'debug_info' => array(
                    'url' => $url,
                    'method' => $method,
                    'http_code' => $http_code,
                    'response' => $response
                )
            );
        }

        // Decodificar respuesta JSON
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => 'Invalid JSON response: ' . json_last_error_msg(),
                'debug_info' => array(
                    'url' => $url,
                    'response' => $response
                )
            );
        }

        return array(
            'success' => true,
            'data' => $decoded,
            'http_code' => $http_code
        );
    }

    /**
     * Start session
     *
     * @since    1.0.0
     * @param    string    $session_name    Session name
     * @return   array     Response from API
     */
    public function start_session($session_name = null) {
        if (!$session_name) {
            $session_name = $this->session;
        }

        $endpoint = '/api/sessions';
        $data = array(
            'name' => $session_name
        );

        return $this->make_request('POST', $endpoint, $data);
    }

    /**
     * Stop session
     *
     * @since    1.0.0
     * @param    string    $session_name    Session name
     * @return   array     Response from API
     */
    public function stop_session($session_name = null) {
        if (!$session_name) {
            $session_name = $this->session;
        }

        $endpoint = '/api/sessions/' . $session_name . '/stop';
        return $this->make_request('POST', $endpoint);
    }

    /**
     * Get session status
     *
     * @since    1.0.0
     * @param    string    $session_name    Session name
     * @return   array     Response from API
     */
    public function get_session_status($session_name = null) {
        if (!$session_name) {
            $session_name = $this->session;
        }

        $endpoint = '/api/sessions/' . $session_name;
        return $this->make_request('GET', $endpoint);
    }

    /**
     * Get QR code
     *
     * @since    1.0.0
     * @param    string    $session_name    Session name
     * @return   array     Response from API
     */
    public function get_qr_code($session_name = null) {
        if (!$session_name) {
            $session_name = $this->session;
        }

        $endpoint = '/api/screenshot';
        $data = array('session' => $session_name);
        return $this->make_request('GET', $endpoint, $data);
    }

    /**
     * Send text message
     *
     * @since    1.0.0
     * @param    string    $chat_id    Chat ID
     * @param    string    $text       Message text
     * @param    string    $session_name    Session name
     * @return   array     Response from API
     */
    public function send_text_message($chat_id, $text, $session_name = null) {
        if (!$session_name) {
            $session_name = $this->session;
        }

        $endpoint = '/api/sendText';
        $data = array(
            'chatId' => $chat_id,
            'text' => $text,
            'session' => $session_name
        );

        return $this->make_request('POST', $endpoint, $data);
    }

    /**
     * Get chats
     *
     * @since    1.0.0
     * @param    string    $session_name    Session name
     * @return   array     Response from API
     */
    public function get_chats($session_name = null) {
        if (!$session_name) {
            $session_name = $this->session;
        }

        $endpoint = '/api/chats';
        $data = array('session' => $session_name);
        return $this->make_request('GET', $endpoint, $data);
    }

    /**
     * Get messages
     *
     * @since    1.0.0
     * @param    string    $chat_id       Chat ID
     * @param    int       $limit         Number of messages
     * @param    string    $session_name  Session name
     * @return   array     Response from API
     */
    public function get_messages($chat_id, $limit = 50, $session_name = null) {
        if (!$session_name) {
            $session_name = $this->session;
        }

        $endpoint = '/api/chats/' . $chat_id . '/messages';
        $data = array(
            'limit' => $limit,
            'session' => $session_name
        );

        return $this->make_request('GET', $endpoint, $data);
    }

    /**
     * Get contacts
     *
     * @since    1.0.0
     * @param    string    $session_name    Session name
     * @return   array     Response from API
     */
    public function get_contacts($session_name = null) {
        if (!$session_name) {
            $session_name = $this->session;
        }

        $endpoint = '/api/contacts';
        $data = array('session' => $session_name);
        return $this->make_request('GET', $endpoint, $data);
    }

    /**
     * Validate API configuration
     *
     * @since    1.0.0
     * @return   array     Validation results
     */
    public function validate_config() {
        $errors = array();
        $warnings = array();

        $protocol = get_option('wp_auto_whats_api_url_type', '');
        $domain = get_option('wp_auto_whats_api_link', '');
        $session = get_option('wp_auto_whats_api_session', '');

        if (empty($protocol)) {
            $errors[] = __('Protocolo no seleccionado', 'wp-auto-whats');
        }

        if (empty($domain)) {
            $errors[] = __('Dominio de la API no configurado', 'wp-auto-whats');
        } else {
            // Verificar que el dominio no incluya protocolo
            if (strpos($domain, 'http://') !== false || strpos($domain, 'https://') !== false) {
                $warnings[] = __('El dominio no debe incluir http:// o https://', 'wp-auto-whats');
            }
        }

        if (empty($session)) {
            $warnings[] = __('Nombre de sesión no configurado, se usará "default"', 'wp-auto-whats');
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'config' => array(
                'protocol' => $protocol,
                'domain' => $domain,
                'session' => $session,
                'constructed_url' => $this->api_url
            )
        );
    }

    /**
     * Get API URL for debugging
     *
     * @since    1.0.0
     * @return   string    The constructed API URL
     */
    public function get_api_url() {
        return $this->api_url;
    }

    /**
     * Get session name
     *
     * @since    1.0.0
     * @return   string    The session name
     */
    public function get_session() {
        return $this->session;
    }
}