<?php
/**
 * Provide an admin area view for the plugin settings
 *
 * @since      1.0.0
 *
 * @package    WP_Auto_Whats
 * @subpackage WP_Auto_Whats/admin/partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Display settings errors/messages
settings_errors('wp_auto_whats_messages');
?>

<div class="wrap wp-auto-whats-container">
    <div class="wp-auto-whats-header">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p><?php _e('Configura la conexión con tu servidor WAHA para comenzar a usar WhatsApp desde WordPress.', 'wp-auto-whats'); ?></p>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('wp_auto_whats_save_settings', 'wp_auto_whats_nonce'); ?>
        
        <div class="wp-auto-whats-card">
            <div class="wp-auto-whats-card-header">
                <h2><?php _e('Configuración de la API WAHA', 'wp-auto-whats'); ?></h2>
            </div>
            <div class="wp-auto-whats-card-body">
                
                <!-- URL Type -->
                <div class="wp-auto-whats-form-group">
                    <label for="wp_auto_whats_api_url_type">
                        <?php _e('Protocolo', 'wp-auto-whats'); ?>
                        <span style="color: red;">*</span>
                    </label>
                    <select name="wp_auto_whats_api_url_type" id="wp_auto_whats_api_url_type" required>
                        <option value=""><?php _e('Seleccionar protocolo', 'wp-auto-whats'); ?></option>
                        <option value="http" <?php selected(get_option('wp_auto_whats_api_url_type'), 'http'); ?>>HTTP</option>
                        <option value="https" <?php selected(get_option('wp_auto_whats_api_url_type'), 'https'); ?>>HTTPS</option>
                    </select>
                    <p class="description">
                        <?php _e('Selecciona HTTP o HTTPS según tu configuración del servidor WAHA.', 'wp-auto-whats'); ?>
                    </p>
                </div>

                <!-- API Link -->
                <div class="wp-auto-whats-form-group">
                    <label for="wp_auto_whats_api_link">
                        <?php _e('Dominio de la API', 'wp-auto-whats'); ?>
                        <span style="color: red;">*</span>
                    </label>
                    <input type="text" 
                           name="wp_auto_whats_api_link" 
                           id="wp_auto_whats_api_link" 
                           value="<?php echo esc_attr(get_option('wp_auto_whats_api_link')); ?>"
                           placeholder="ejemplo.com:3000"
                           required>
                    <p class="description">
                        <?php _e('Ingresa solo el dominio y puerto (ej: ejemplo.com:3000). NO incluyas http:// o https://', 'wp-auto-whats'); ?>
                    </p>
                </div>

                <!-- Session Name -->
                <div class="wp-auto-whats-form-group">
                    <label for="wp_auto_whats_api_session">
                        <?php _e('Nombre de Sesión', 'wp-auto-whats'); ?>
                    </label>
                    <input type="text" 
                           name="wp_auto_whats_api_session" 
                           id="wp_auto_whats_api_session" 
                           value="<?php echo esc_attr(get_option('wp_auto_whats_api_session', 'default')); ?>"
                           placeholder="default">
                    <p class="description">
                        <?php _e('Nombre de la sesión de WhatsApp (por defecto: "default").', 'wp-auto-whats'); ?>
                    </p>
                </div>

                <!-- URL Preview -->
                <div class="wp-auto-whats-form-group">
                    <div id="url-preview" style="background: #f0f0f1; padding: 10px; border-radius: 4px; border-left: 4px solid #00a32a;">
                        <em><?php _e('Configura el protocolo y dominio para ver la URL completa', 'wp-auto-whats'); ?></em>
                    </div>
                </div>

                <!-- Test Connection Button -->
                <div class="wp-auto-whats-form-group">
                    <button type="button" id="test-connection" class="wp-auto-whats-btn wp-auto-whats-btn-secondary">
                        <span class="dashicons dashicons-admin-plugins" style="margin-right: 5px;"></span>
                        <?php _e('Probar Conexión', 'wp-auto-whats'); ?>
                    </button>
                    <p class="description">
                        <?php _e('Prueba la conexión con tu servidor WAHA antes de guardar.', 'wp-auto-whats'); ?>
                    </p>
                </div>

            </div>
        </div>

        <!-- Advanced Settings -->
        <div class="wp-auto-whats-card">
            <div class="wp-auto-whats-card-header">
                <h2>
                    <a href="#" id="toggle-advanced-options" style="text-decoration: none; color: inherit;">
                        <span class="dashicons dashicons-arrow-down" style="margin-right: 5px;"></span>
                        <?php _e('Configuración Avanzada', 'wp-auto-whats'); ?>
                    </a>
                </h2>
            </div>
            <div class="wp-auto-whats-card-body advanced-options" style="display: none;">
                
                <!-- Webhook URL -->
                <div class="wp-auto-whats-form-group">
                    <label for="wp_auto_whats_webhook_url">
                        <?php _e('URL del Webhook', 'wp-auto-whats'); ?>
                    </label>
                    <input type="url" 
                           name="wp_auto_whats_webhook_url" 
                           id="wp_auto_whats_webhook_url" 
                           value="<?php echo esc_attr(get_option('wp_auto_whats_webhook_url')); ?>"
                           placeholder="<?php echo esc_attr(home_url('/wp-admin/admin-ajax.php?action=wp_auto_whats_webhook')); ?>">
                    <p class="description">
                        <?php _e('URL para recibir notificaciones de WhatsApp. Dejar vacío para usar la URL automática.', 'wp-auto-whats'); ?>
                        <br>
                        <strong><?php _e('URL sugerida:', 'wp-auto-whats'); ?></strong> 
                        <code><?php echo esc_html(home_url('/wp-admin/admin-ajax.php?action=wp_auto_whats_webhook')); ?></code>
                    </p>
                </div>

                <!-- Auto Start Session -->
                <div class="wp-auto-whats-form-group">
                    <label>
                        <input type="checkbox" 
                               name="wp_auto_whats_auto_start" 
                               value="1" 
                               <?php checked(get_option('wp_auto_whats_auto_start'), true); ?>>
                        <?php _e('Iniciar sesión automáticamente', 'wp-auto-whats'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Inicia la sesión de WhatsApp automáticamente cuando se active el plugin.', 'wp-auto-whats'); ?>
                    </p>
                </div>

                <!-- Debug Mode -->
                <div class="wp-auto-whats-form-group">
                    <label>
                        <input type="checkbox" 
                               name="wp_auto_whats_debug_mode" 
                               value="1" 
                               <?php checked(get_option('wp_auto_whats_debug_mode'), true); ?>>
                        <?php _e('Modo Debug', 'wp-auto-whats'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Habilita el registro detallado de errores y depuración.', 'wp-auto-whats'); ?>
                    </p>
                </div>

            </div>
        </div>

        <!-- Submit Button -->
        <div class="wp-auto-whats-card">
            <div class="wp-auto-whats-card-body">
                <button type="submit" name="submit" class="wp-auto-whats-btn wp-auto-whats-btn-primary">
                    <span class="dashicons dashicons-yes" style="margin-right: 5px;"></span>
                    <?php _e('Guardar Configuración', 'wp-auto-whats'); ?>
                </button>
                
                <a href="<?php echo admin_url('admin.php?page=wp-auto-whats-sessions'); ?>" 
                   class="wp-auto-whats-btn wp-auto-whats-btn-secondary">
                    <span class="dashicons dashicons-controls-play" style="margin-right: 5px;"></span>
                    <?php _e('Gestionar Sesiones', 'wp-auto-whats'); ?>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=wp-auto-whats'); ?>" 
                   class="wp-auto-whats-btn wp-auto-whats-btn-secondary">
                    <span class="dashicons dashicons-format-chat" style="margin-right: 5px;"></span>
                    <?php _e('Ir al Chat', 'wp-auto-whats'); ?>
                </a>
            </div>
        </div>

    </form>

    <!-- Help Section -->
    <div class="wp-auto-whats-card">
        <div class="wp-auto-whats-card-header">
            <h2><?php _e('Ayuda y Documentación', 'wp-auto-whats'); ?></h2>
        </div>
        <div class="wp-auto-whats-card-body">
            <h3><?php _e('Configuración de WAHA', 'wp-auto-whats'); ?></h3>
            <p><?php _e('WAHA (WhatsApp HTTP API) debe estar ejecutándose en tu servidor antes de configurar este plugin.', 'wp-auto-whats'); ?></p>
            
            <h4><?php _e('Pasos para configurar:', 'wp-auto-whats'); ?></h4>
            <ol>
                <li><?php _e('Instala y ejecuta WAHA en tu servidor', 'wp-auto-whats'); ?></li>
                <li><?php _e('Selecciona el protocolo (HTTP/HTTPS) según tu configuración', 'wp-auto-whats'); ?></li>
                <li><?php _e('Ingresa el dominio y puerto donde está ejecutándose WAHA', 'wp-auto-whats'); ?></li>
                <li><?php _e('Configura el nombre de sesión (opcional)', 'wp-auto-whats'); ?></li>
                <li><?php _e('Prueba la conexión antes de guardar', 'wp-auto-whats'); ?></li>
                <li><?php _e('Guarda la configuración', 'wp-auto-whats'); ?></li>
            </ol>

            <h4><?php _e('Ejemplos de configuración:', 'wp-auto-whats'); ?></h4>
            <ul>
                <li><strong><?php _e('Servidor local:', 'wp-auto-whats'); ?></strong> localhost:3000</li>
                <li><strong><?php _e('Servidor remoto:', 'wp-auto-whats'); ?></strong> whatsapp.midominio.com:3000</li>
                <li><strong><?php _e('Con dominio personalizado:', 'wp-auto-whats'); ?></strong> api.miempresa.com</li>
            </ul>

            <h4><?php _e('Solución de problemas:', 'wp-auto-whats'); ?></h4>
            <ul>
                <li><?php _e('Error "Could not resolve host": Verifica que el dominio sea correcto y no incluya http:// o https://', 'wp-auto-whats'); ?></li>
                <li><?php _e('Error de conexión: Asegúrate de que WAHA esté ejecutándose y accesible', 'wp-auto-whats'); ?></li>
                <li><?php _e('Error 404: Verifica que la URL de la API sea correcta', 'wp-auto-whats'); ?></li>
                <li><?php _e('Habilita el modo debug para obtener más información sobre errores', 'wp-auto-whats'); ?></li>
            </ul>

            <p>
                <strong><?php _e('Documentación de WAHA:', 'wp-auto-whats'); ?></strong> 
                <a href="https://waha.devlike.pro/" target="_blank">https://waha.devlike.pro/</a>
            </p>
        </div>
    </div>

</div>

<script type="text/javascript">
// Compatibility check for older browsers
if (typeof wpAutoWhatsAdmin === 'undefined') {
    console.warn('WP Auto Whats: JavaScript variables not loaded properly');
}
</script>