<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 *
 * @package    WP_Auto_Whats
 * @subpackage WP_Auto_Whats/admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for
 * admin-facing functionality.
 *
 * @package    WP_Auto_Whats
 * @subpackage WP_Auto_Whats/admin
 * @author     felipe1712
 */
class WP_Auto_Whats_Admin {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . '../css/admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . '../js/admin.js',
            array('jquery'),
            $this->version,
            false
        );

        // Localize script for AJAX
        wp_localize_script(
            $this->plugin_name,
            'wpAutoWhatsAdmin',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wp_auto_whats_nonce')
            )
        );
    }

    /**
     * Add plugin admin menu
     *
     * @since    1.0.0
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('WP Auto Whats', 'wp-auto-whats'),
            __('WP Auto Whats', 'wp-auto-whats'),
            'manage_options',
            'wp-auto-whats',
            array($this, 'display_chat_page'),
            'dashicons-phone',
            30
        );

        // Chat page (same as main)
        add_submenu_page(
            'wp-auto-whats',
            __('Chat', 'wp-auto-whats'),
            __('Chat', 'wp-auto-whats'),
            'manage_options',
            'wp-auto-whats',
            array($this, 'display_chat_page')
        );

        // Settings page
        add_submenu_page(
            'wp-auto-whats',
            __('Configuración', 'wp-auto-whats'),
            __('Configuración', 'wp-auto-whats'),
            'manage_options',
            'wp-auto-whats-settings',
            array($this, 'display_settings_page')
        );

        // Contact list page
        add_submenu_page(
            'wp-auto-whats',
            __('Contactos', 'wp-auto-whats'),
            __('Contactos', 'wp-auto-whats'),
            'manage_options',
            'wp-auto-whats-contacts',
            array($this, 'display_contacts_page')
        );

        // Session management page
        add_submenu_page(
            'wp-auto-whats',
            __('Sesiones', 'wp-auto-whats'),
            __('Sesiones', 'wp-auto-whats'),
            'manage_options',
            'wp-auto-whats-sessions',
            array($this, 'display_sessions_page')
        );
    }

    /**
     * Initialize admin settings
     *
     * @since    1.0.0
     */
    public function admin_init() {
        // Register settings
        register_setting('wp_auto_whats_settings', 'wp_auto_whats_api_url_type');
        register_setting('wp_auto_whats_settings', 'wp_auto_whats_api_link');
        register_setting('wp_auto_whats_settings', 'wp_auto_whats_api_session');
        register_setting('wp_auto_whats_settings', 'wp_auto_whats_webhook_url');
        register_setting('wp_auto_whats_settings', 'wp_auto_whats_auto_start');
        register_setting('wp_auto_whats_settings', 'wp_auto_whats_debug_mode');

        // Add AJAX handlers
        add_action('wp_ajax_wp_auto_whats_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_wp_auto_whats_session_action', array($this, 'ajax_session_action'));
    }

    /**
     * Display chat page
     *
     * @since    1.0.0
     */
    public function display_chat_page() {
        include_once plugin_dir_path(__FILE__) . 'partials/wp-auto-whats-chat-display.php';
    }

    /**
     * Display settings page
     *
     * @since    1.0.0
     */
    public function display_settings_page() {
        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['wp_auto_whats_nonce'], 'wp_auto_whats_save_settings')) {
            $this->save_settings();
        }

        include_once plugin_dir_path(__FILE__) . 'partials/wp-auto-whats-admin-display.php';
    }

    /**
     * Display contacts page
     *
     * @since    1.0.0
     */
    public function display_contacts_page() {
        include_once plugin_dir_path(__FILE__) . 'partials/wp-auto-whats-contact-display.php';
    }

    /**
     * Display sessions page
     *
     * @since    1.0.0
     */
    public function display_sessions_page() {
        include_once plugin_dir_path(__FILE__) . 'partials/wp-auto-whats-session-display.php';
    }

    /**
     * Save plugin settings
     *
     * @since    1.0.0
     */
    private function save_settings() {
        // Sanitize and save API URL Type
        $api_url_type = sanitize_text_field($_POST['wp_auto_whats_api_url_type']);
        if (in_array($api_url_type, array('http', 'https'))) {
            update_option('wp_auto_whats_api_url_type', $api_url_type);
        }

        // Sanitize and save API Link
        $api_link = sanitize_text_field($_POST['wp_auto_whats_api_link']);
        // Remove protocol if user included it
        $api_link = str_replace(array('http://', 'https://'), '', $api_link);
        $api_link = trim($api_link, '/');
        update_option('wp_auto_whats_api_link', $api_link);

        // Sanitize and save API Session
        $api_session = sanitize_text_field($_POST['wp_auto_whats_api_session']);
        if (empty($api_session)) {
            $api_session = 'default';
        }
        update_option('wp_auto_whats_api_session', $api_session);

        // Sanitize and save Webhook URL
        $webhook_url = esc_url_raw($_POST['wp_auto_whats_webhook_url']);
        update_option('wp_auto_whats_webhook_url', $webhook_url);

        // Save Auto Start setting
        $auto_start = isset($_POST['wp_auto_whats_auto_start']) ? true : false;
        update_option('wp_auto_whats_auto_start', $auto_start);

        // Save Debug Mode setting
        $debug_mode = isset($_POST['wp_auto_whats_debug_mode']) ? true : false;
        update_option('wp_auto_whats_debug_mode', $debug_mode);

        // Show success message
        add_settings_error(
            'wp_auto_whats_messages',
            'wp_auto_whats_message',
            __('Configuración guardada exitosamente.', 'wp-auto-whats'),
            'updated'
        );
    }

    /**
     * AJAX handler for testing API connection
     *
     * @since    1.0.0
     */
    public function ajax_test_connection() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wp_auto_whats_nonce')) {
            wp_die(__('Nonce verification failed', 'wp-auto-whats'));
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-auto-whats'));
        }

        // Get temporary settings from POST data
        $temp_protocol = sanitize_text_field($_POST['api_url_type']);
        $temp_domain = sanitize_text_field($_POST['api_link']);
        $temp_session = sanitize_text_field($_POST['api_session']);

        // Clean domain
        $temp_domain = str_replace(array('http://', 'https://'), '', $temp_domain);
        $temp_domain = trim($temp_domain, '/');

        // Validate input
        if (empty($temp_protocol) || empty($temp_domain)) {
            wp_send_json_error(array(
                'message' => __('Protocolo y dominio son requeridos', 'wp-auto-whats'),
                'debug_info' => array(
                    'protocol' => $temp_protocol,
                    'domain' => $temp_domain
                )
            ));
        }

        // Temporarily update options for testing
        $original_protocol = get_option('wp_auto_whats_api_url_type');
        $original_domain = get_option('wp_auto_whats_api_link');
        $original_session = get_option('wp_auto_whats_api_session');

        update_option('wp_auto_whats_api_url_type', $temp_protocol);
        update_option('wp_auto_whats_api_link', $temp_domain);
        update_option('wp_auto_whats_api_session', $temp_session ?: 'default');

        // Test connection
        if (!class_exists('WP_Auto_Whats_API')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wp-auto-whats-api.php';
        }

        $api = new WP_Auto_Whats_API();
        $validation = $api->validate_config();

        if (!$validation['valid']) {
            // Restore original settings
            update_option('wp_auto_whats_api_url_type', $original_protocol);
            update_option('wp_auto_whats_api_link', $original_domain);
            update_option('wp_auto_whats_api_session', $original_session);

            wp_send_json_error(array(
                'message' => implode(', ', $validation['errors']),
                'debug_info' => array(
                    'validation_errors' => $validation['errors'],
                    'validation_warnings' => $validation['warnings'],
                    'config' => $validation['config']
                )
            ));
        }

        $result = $api->test_connection();

        // Restore original settings
        update_option('wp_auto_whats_api_url_type', $original_protocol);
        update_option('wp_auto_whats_api_link', $original_domain);
        update_option('wp_auto_whats_api_session', $original_session);

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'api_data' => $result['data'] ?? null,
                'config' => $validation['config']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message'],
                'debug_info' => $result['debug_info'] ?? null
            ));
        }
    }

    /**
     * AJAX handler for session actions
     *
     * @since    1.0.0
     */
    public function ajax_session_action() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wp_auto_whats_nonce')) {
            wp_die(__('Nonce verification failed', 'wp-auto-whats'));
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-auto-whats'));
        }

        $action = sanitize_text_field($_POST['session_action']);

        if (!class_exists('WP_Auto_Whats_API')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wp-auto-whats-api.php';
        }

        $api = new WP_Auto_Whats_API();

        switch ($action) {
            case 'start':
                $result = $api->start_session();
                break;
            case 'stop':
                $result = $api->stop_session();
                break;
            case 'status':
                $result = $api->get_session_status();
                break;
            case 'qr':
                $result = $api->get_qr_code();
                break;
            default:
                wp_send_json_error(array('message' => __('Acción no válida', 'wp-auto-whats')));
                return;
        }

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Acción ejecutada exitosamente', 'wp-auto-whats'),
                'data' => $result['data'] ?? null
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message'],
                'debug_info' => $result['debug_info'] ?? null
            ));
        }
    }
}