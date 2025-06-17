<?php
namespace WpAutoWhats\Admin;

defined('ABSPATH') or die('No script kiddies please!');

class Menu {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        $page_class = New Page();
        $contacts_class = New Contacts();
        $setting_class = New Setting();
        add_menu_page(
            'AutoWhats Chat',
            'AutoWhats Chat',
            'manage_options',
            'wp-auto-whats',
            [$page_class, 'render_page'],
            'dashicons-format-chat',
            26
        );
        
        // Add the "Contact List" submenu
        add_submenu_page(
            'wp-auto-whats', // Parent slug
            'Contact List',  // Page title
            'Contact List',  // Menu title
            'manage_options', // Capability
            'wp-auto-whats-contact-list', // Menu slug
            [$contacts_class,'wp_auto_whats_contact_list_page'] // Callback function
        );
                // Add the "Contact List" submenu
        add_submenu_page(
            'wp-auto-whats', // Parent slug
            'Setting',  // Page title
            'Setting',  // Menu title
            'manage_options', // Capability
            'wp-auto-whats-setting', // Menu slug
            [$setting_class,'wp_auto_whats_setting_page'] // Callback function
        );
    }
}