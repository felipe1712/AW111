<?php
namespace WpAutoWhats\Admin;

defined('ABSPATH') or die('No script kiddies please!');

class Help_Tab {
        public function __construct() {
        add_action('admin_head', [$this, 'add_screen_help_tab']);
        add_action('admin_head', [$this, 'contact_list_help_tab']);
    }
            
    public static function add_screen_help_tab() {
        $screen = get_current_screen();
        
        // Make sure we're on the right page
        if ( $screen->id !== 'autowhats-chat_page_wp-auto-whats-setting' ) {
            return;
        }

        $screen->add_help_tab([
            'id'      => 'wp_auto_whats_help',
            'title'   => __('How to Use', 'wp-auto-whats'),
            'content' => '<p>' . __('This plugin connects your site to the AUTWA API and allows you to manage WhatsApp messages, contacts, and sessions.', 'wp-auto-whats') . '</p>'
            . '<p>' . __('Fist, set your API domain.', 'wp-auto-whats') . '</p>'
            . '<p>' . __('Second, check API sessions.', 'wp-auto-whats') . '</p>'
            . '<p>' . __('Third, check API connection.', 'wp-auto-whats') . '</p>'
            . '<p>' . __('Forth, If you are connected. Now you goto Contact List page.', 'wp-auto-whats') . '</p>'
        ]);
        
        $screen->set_help_sidebar(
            '<p><strong>' . __('More Information', 'wp-auto-whats') . '</strong></p>' .
            '<p><a href="https://asraful.com.bd" target="_blank">' . __('Plugin Documentation', 'wp-auto-whats') . '</a></p>'
        );
    }    
    public static function contact_list_help_tab() {
        $screen = get_current_screen();
        
        // Make sure we're on the right page
        if ( $screen->id !== 'AUTWA-chat_page_wp-auto-whats-contact-list' ) {
            return;
        }

        $screen->add_help_tab([
            'id'      => 'wp_auto_whats_help',
            'title'   => __('How to Use', 'wp-auto-whats'),
            'content' => '<p>' . __('This plugin connects your site to the AUTWA API and allows you to manage WhatsApp messages, contacts, and sessions.', 'wp-auto-whats') . '</p>'
            . '<p>' . __('Fist, If You was setup in setting. If empty contact then inport contact from whatsapp', 'wp-auto-whats') . '</p>'
        ]);
        
        $screen->set_help_sidebar(
            '<p><strong>' . __('More Information', 'wp-auto-whats') . '</strong></p>' .
            '<p><a href="https://asraful.com.bd" target="_blank">' . __('Plugin Documentation', 'wp-auto-whats') . '</a></p>'
        );
    }
}