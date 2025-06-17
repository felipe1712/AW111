<?php
namespace WpAutoWhats\Admin;

defined('ABSPATH') or die('No script kiddies please!');

class Assets {

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    public function enqueue_assets($hook) {
        // Only load on plugin admin page
        if ($hook !== 'toplevel_page_wp-auto-whats') {
            return;
        }

        wp_enqueue_script(
            'AUTWA-chat-js',
            AUTWA_PLUGIN_ASSETS . '/js/admin.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'AUTWA-admin-css',
            AUTWA_PLUGIN_ASSETS . '/css/admin.css',
            [],
            '1.0.0'
        );

        wp_localize_script('AUTWA-chat-js', 'wpaw_ajax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpaw_nonce'),
        ]);

    }
}