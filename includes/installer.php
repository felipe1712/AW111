<?php
/**
 * WP Auto Whats - Instalador y Actualizador
 * Maneja la instalación, actualización y desinstalación del plugin
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Incluir configuración
require_once WP_AUTO_WHATS_PLUGIN_DIR . 'includes/config.php';

/**
 * Clase instaladora del plugin
 */
class WA_Installer {
    
    /**
     * Versión actual de la base de datos
     */
    const DB_VERSION = '1.2.0';
    
    /**
     * Nombre de la opción de versión en la base de datos
     */
    const DB_VERSION_OPTION = 'wa_db_version';
    
    /**
     * Ejecutar durante la activación del plugin
     */
    public static function activate() {
        // Verificar requisitos del sistema
        self::check_requirements();
        
        // Crear tablas de base de datos
        self::create_tables();
        
        // Configurar opciones por defecto
        self::setup_default_options();
        
        // Configurar capacidades de usuario
        self::setup_capabilities();
        
        // Programar eventos cron
        self::schedule_cron_events();
        
        // Guardar versión de la base de datos
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        
        // Registrar instalación
        self::log_installation();
    }
    
    /**
     * Ejecutar durante la desactivación del plugin
     */
    public static function deactivate() {
        // Limpiar eventos cron
        self::clear_cron_events();
        
        // Limpiar cachés temporales
        self::clear_temporary_cache();
        
        // Registrar desactivación
        self::log_deactivation();
    }
    
    /**
     * Ejecutar durante la desinstalación del plugin
     */
    public static function uninstall() {
        // Verificar permisos
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // Verificar nonce
        check_admin_referer('bulk-plugins');
        
        // Limpiar opciones
        self::cleanup_options();
        
        // Eliminar tablas (opcional, comentado para seguridad)
        // self::drop_tables();
        
        // Limpiar archivos temporales
        self::cleanup_files();
        
        // Limpiar capacidades
        self::cleanup_capabilities();
        
        // Registrar desinstalación
        self::log_uninstallation();
    }
    
    /**
     * Verificar si necesita actualización
     */
    public static function maybe_upgrade() {
        $installed_version = get_option(self::DB_VERSION_OPTION, '0.0.0');
        
        if (version_compare($installed_version, self::DB_VERSION, '<')) {
            self::upgrade($installed_version);
        }
    }
    
    /**
     * Verificar requisitos del sistema
     */
    private static function check_requirements() {
        // Verificar versión de PHP
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            wp_die(__('WP Auto Whats requiere PHP 7.4 o superior. Versión actual: ', 'wp-auto-whats') . PHP_VERSION);
        }
        
        // Verificar versión de WordPress
        global $wp_version;
        if (version_compare($wp_version, '5.0', '<')) {
            wp_die(__('WP Auto Whats requiere WordPress 5.0 o superior. Versión actual: ', 'wp-auto-whats') . $wp_version);
        }
        
        // Verificar extensiones de PHP necesarias
        $required_extensions = array('curl', 'json', 'openssl');
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                wp_die(sprintf(__('WP Auto Whats requiere la extensión PHP %s', 'wp-auto-whats'), $extension));
            }
        }
        
        // Verificar permisos de directorio
        $upload_dir = wp_upload_dir();
        if (!is_writable($upload_dir['basedir'])) {
            wp_die(__('WP Auto Whats requiere permisos de escritura en el directorio de uploads', 'wp-auto-whats'));
        }
    }
    
    /**
     * Crear tablas de base de datos necesarias
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabla para logs de actividad
        $table_logs = $wpdb->prefix . 'wa_activity_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_name varchar(50) NOT NULL,
            level varchar(10) NOT NULL,
            message text NOT NULL,
            context longtext DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_name (session_name),
            KEY level (level),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        // Tabla para configuraciones de sesión
        $table_sessions = $wpdb->prefix . 'wa_sessions';
        $sql_sessions = "CREATE TABLE $table_sessions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_name varchar(50) NOT NULL UNIQUE,
            api_url varchar(255) NOT NULL,
            status varchar(20) DEFAULT 'stopped',
            last_qr_generated datetime DEFAULT NULL,
            last_status_check datetime DEFAULT NULL,
            config longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY session_name (session_name),
            KEY status (status),
            KEY updated_at (updated_at)
        ) $charset_collate;";
        
        // Tabla para caché temporal
        $table_cache = $wpdb->prefix . 'wa_cache';
        $sql_cache = "CREATE TABLE $table_cache (
            cache_key varchar(255) NOT NULL,
            cache_value longtext NOT NULL,
            expiration datetime NOT NULL,
            PRIMARY KEY (cache_key),
            KEY expiration (expiration)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_logs);
        dbDelta($sql_sessions);
        dbDelta($sql_cache);
    }
    
    /**
     * Configurar opciones por defecto
     */
    private static function setup_default_options() {
        $default_settings = WA_Config::get_default_settings();
        
        foreach ($default_settings as $option_name => $default_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $default_value);
            }
        }
        
        // Opciones adicionales
        add_option('wa_first_install', current_time('mysql'));
        add_option('wa_install_version', WP_AUTO_WHATS_VERSION);
    }
    
    /**
     * Configurar capacidades de usuario
     */
    private static function setup_capabilities() {
        // Capacidades para administradores
        $admin_caps = array(
            'wa_manage_settings',
            'wa_manage_sessions',
            'wa_send_messages',
            'wa_view_logs',
            'wa_import_contacts'
        );
        
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($admin_caps as $cap) {
                $admin_role->add_cap($cap);
            }
        }
        
        // Capacidades para editores (limitadas)
        $editor_caps = array(
            'wa_send_messages',
            'wa_view_logs'
        );
        
        $editor_role = get_role('editor');
        if ($editor_role) {
            foreach ($editor_caps as $cap) {
                $editor_role->add_cap($cap);
            }
        }
    }
    
    /**
     * Programar eventos cron
     */
    private static function schedule_cron_events() {
        // Limpiar logs antiguos (diario)
        if (!wp_next_scheduled('wa_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'wa_cleanup_logs');
        }
        
        // Limpiar caché expirado (cada hora)
        if (!wp_next_scheduled('wa_cleanup_cache')) {
            wp_schedule_event(time(), 'hourly', 'wa_cleanup_cache');
        }
        
        // Verificar estado de sesiones (cada 5 minutos)
        if (!wp_next_scheduled('wa_check_sessions')) {
            wp_schedule_event(time(), 'wa_five_minutes', 'wa_check_sessions');
        }
        
        // Agregar intervalo personalizado
        add_filter('cron_schedules', array(__CLASS__, 'add_cron_intervals'));
    }
    
    /**
     * Agregar intervalos personalizados de cron
     */
    public static function add_cron_intervals($schedules) {
        $schedules['wa_five_minutes'] = array(
            'interval' => 300,
            'display' => __('Cada 5 minutos', 'wp-auto-whats')
        );
        
        return $schedules;
    }
    
    /**
     * Limpiar eventos cron
     */
    private static function clear_cron_events() {
        wp_clear_scheduled_hook('wa_cleanup_logs');
        wp_clear_scheduled_hook('wa_cleanup_cache');
        wp_clear_scheduled_hook('wa_check_sessions');
    }
    
    /**
     * Proceso de actualización
     */
    private static function upgrade($from_version) {
        global $wpdb;
        
        // Actualización a 1.1.0
        if (version_compare($from_version, '1.1.0', '<')) {
            // Migrar configuraciones antiguas
            self::migrate_to_110();
        }
        
        // Actualización a 1.2.0
        if (version_compare($from_version, '1.2.0', '<')) {
            // Agregar nuevas tablas y campos
            self::migrate_to_120();
        }
        
        // Actualizar versión
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        
        // Registrar actualización
        self::log_upgrade($from_version, self::DB_VERSION);
    }
    
    /**
     * Migración a versión 1.1.0
     */
    private static function migrate_to_110() {
        // Migrar configuraciones del método screenshot al nuevo método QR
        $old_config = get_option('wa_use_screenshot', false);
        if ($old_config) {
            update_option('wa_use_qr_endpoint', true);
            delete_option('wa_use_screenshot');
        }
    }
    
    /**
     * Migración a versión 1.2.0
     */
    private static function migrate_to_120() {
        global $wpdb;
        
        // Agregar campos nuevos a tabla de sesiones si no existen
        $table_sessions = $wpdb->prefix . 'wa_sessions';
        
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_sessions");
        $column_names = wp_list_pluck($columns, 'Field');
        
        if (!in_array('last_qr_generated', $column_names)) {
            $wpdb->query("ALTER TABLE $table_sessions ADD COLUMN last_qr_generated datetime DEFAULT NULL");
        }
        
        if (!in_array('config', $column_names)) {
            $wpdb->query("ALTER TABLE $table_sessions ADD COLUMN config longtext DEFAULT NULL");
        }
        
        // Limpiar logs antiguos del método screenshot
        $wpdb->delete(
            $wpdb->prefix . 'wa_activity_logs',
            array('message' => array('LIKE' => '%screenshot%')),
            array('%s')
        );
    }
    
    /**
     * Limpiar opciones del plugin
     */
    private static function cleanup_options() {
        $options_to_delete = array(
            'wa_url_type',
            'wa_api_link',
            'wa_session_name',
            'wa_debug_mode',
            'wa_auto_refresh',
            'wa_refresh_interval',
            'wa_qr_timeout',
            'wa_max_retries',
            'wa_api_timeout',
            'wa_first_install',
            'wa_install_version',
            self::DB_VERSION_OPTION
        );
        
        foreach ($options_to_delete as $option) {
            delete_option($option);
        }
    }
    
    /**
     * Eliminar tablas (usar con precaución)
     */
    private static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'wa_activity_logs',
            $wpdb->prefix . 'wa_sessions',
            $wpdb->prefix . 'wa_cache'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Limpiar archivos temporales
     */
    private static function cleanup_files() {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/wa-debug.log';
        
        if (file_exists($log_file)) {
            unlink($log_file);
        }
        
        // Limpiar directorio de caché si existe
        $cache_dir = $upload_dir['basedir'] . '/wa-cache/';
        if (is_dir($cache_dir)) {
            self::recursive_rmdir($cache_dir);
        }
    }
    
    /**
     * Limpiar caché temporal
     */
    private static function clear_temporary_cache() {
        global $wpdb;
        
        // Limpiar caché de la base de datos
        $wpdb->query("DELETE FROM {$wpdb->prefix}wa_cache WHERE expiration < NOW()");
        
        // Limpiar transients de WordPress
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wa_%' OR option_name LIKE '_transient_timeout_wa_%'");
    }
    
    /**
     * Limpiar capacidades de usuario
     */
    private static function cleanup_capabilities() {
        $caps_to_remove = array(
            'wa_manage_settings',
            'wa_manage_sessions',
            'wa_send_messages',
            'wa_view_logs',
            'wa_import_contacts'
        );
        
        $roles = array('administrator', 'editor');
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($caps_to_remove as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
    
    /**
     * Registrar instalación
     */
    private static function log_installation() {
        self::add_activity_log('INFO', 'Plugin instalado correctamente', array(
            'version' => WP_AUTO_WHATS_VERSION,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version')
        ));
    }
    
    /**
     * Registrar desactivación
     */
    private static function log_deactivation() {
        self::add_activity_log('INFO', 'Plugin desactivado', array(
            'version' => WP_AUTO_WHATS_VERSION
        ));
    }
    
    /**
     * Registrar desinstalación
     */
    private static function log_uninstallation() {
        self::add_activity_log('INFO', 'Plugin desinstalado completamente', array(
            'version' => WP_AUTO_WHATS_VERSION
        ));
    }
    
    /**
     * Registrar actualización
     */
    private static function log_upgrade($from_version, $to_version) {
        self::add_activity_log('INFO', 'Plugin actualizado', array(
            'from_version' => $from_version,
            'to_version' => $to_version
        ));
    }
    
    /**
     * Agregar entrada al log de actividad
     */
    private static function add_activity_log($level, $message, $context = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wa_activity_logs';
        
        $wpdb->insert(
            $table,
            array(
                'session_name' => 'system',
                'level' => $level,
                'message' => $message,
                'context' => json_encode($context),
                'user_id' => get_current_user_id(),
                'ip_address' => self::get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Obtener IP del cliente
     */
    private static function get_client_ip() {
        $ip_fields = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_fields as $field) {
            if (!empty($_SERVER[$field]) && filter_var($_SERVER[$field], FILTER_VALIDATE_IP)) {
                return $_SERVER[$field];
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Eliminar directorio recursivamente
     */
    private static function recursive_rmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        self::recursive_rmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}

// Hooks para eventos de instalación
register_activation_hook(WP_AUTO_WHATS_PLUGIN_FILE, array('WA_Installer', 'activate'));
register_deactivation_hook(WP_AUTO_WHATS_PLUGIN_FILE, array('WA_Installer', 'deactivate'));

// Hook para verificar actualizaciones
add_action('plugins_loaded', array('WA_Installer', 'maybe_upgrade'));

// Hooks para eventos cron
add_action('wa_cleanup_logs', array('WA_Installer', 'cleanup_old_logs'));
add_action('wa_cleanup_cache', array('WA_Installer', 'cleanup_expired_cache'));
add_action('wa_check_sessions', array('WA_Installer', 'check_session_health'));
?>