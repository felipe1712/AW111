<?php
namespace WpAutoWhats\Helpers;

defined('ABSPATH') or die('No script kiddies please!');

class SettingHelper {
    // Save a setting to wp_options
    public static function save_option($key, $value) {
        return update_option($key, $value);
    }

    // Get a setting from wp_options
    public static function get_option($key, $default = '') {
        $val = get_option($key);
        return $val !== false ? $val : $default;
    }

    // Save session status (e.g., WORKING, STOPPED, etc.)
    public static function save_session_status($status) {
        return self::save_option('wpaw_session_status', $status);
    }

    // Get session status
    public static function get_session_status() {
        return self::get_option('wpaw_session_status', 'UNKNOWN');
    }

    // Save last session info (array)
    public static function save_session_info($info) {
        return self::save_option('wpaw_session_info', maybe_serialize($info));
    }

    // Get last session info (array)
    public static function get_session_info() {
        $info = self::get_option('wpaw_session_info', '');
        return $info ? maybe_unserialize($info) : [];
    }

    // --- Session API Calls ---
    public static function api_url($endpoint) {
        $api_url = get_option('wpaw_api_url');
        $url_type = get_option('wpaw_url_type');
        $base = ($url_type ? $url_type : 'http') . '://' . $api_url . '/api';
        $session = get_option('wpaw_api_sessions', 'default');
        // Replace {session} with the session name only, not the full URL
        return preg_replace('/\{session\}/', $session, $base . $endpoint);
    }

    public static function call_api($method, $endpoint, $data = null) {
        $url = self::api_url($endpoint);
        $args = [
            'method' => $method,
            'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
            'timeout' => 20,
        ];
        if ($data !== null) {
            $args['body'] = json_encode($data);
        }
        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        return [ 'success' => ($code >= 200 && $code < 300), 'code' => $code, 'body' => $json, 'raw' => $body ];
    }

    public static function create_session($name = null) {
        $session = $name ?: get_option('wpaw_api_sessions', 'default');
        $admin_user = wp_get_current_user();

        $body = [
            'name'   => $session,
            'start'  => true,
            'config' => [
                'metadata' => [
                    'user.id'    => strval($admin_user->ID),
                    'user.email' => $admin_user->user_email,
                ],
                'proxy'  => null,
                'debug'  => false,
                'noweb'  => [
                    'store' => [
                        'enabled'  => true,
                        'fullSync' => false,
                    ]
                ],
                'webhooks' => [
                    [
                        'url'     => site_url('/wp-json/wp-auto-whats/v1/webhook'),
                        'events'  => ['message', 'session.status'],
                        'hmac'    => null,
                        'retries' => null,
                        'customHeaders' => null,
                    ]
                ]
            ]
        ];
        return self::call_api('POST', '/sessions', $body);
    }
    public static function get_session() {
        return self::call_api('GET', '/sessions/{session}');
    }
    public static function update_session($name = null) {
        $session = $name ?: get_option('wpaw_api_sessions', 'default');
        update_option('wpaw_api_sessions', $session);
        $admin_user = wp_get_current_user();

        $body = [
            'name'   => $session,
            'start'  => true,
            'config' => [
                'metadata' => [
                    'user.id'    => strval($admin_user->ID),
                    'user.email' => $admin_user->user_email,
                ],
                'proxy'  => null,
                'debug'  => false,
                'noweb'  => [
                    'store' => [
                        'enabled'  => true,
                        'fullSync' => false,
                    ]
                ],
                'webhooks' => [
                    [
                        'url'     => site_url('/wp-json/wp-auto-whats/v1/webhook'),
                        'events'  => ['message', 'session.status'],
                        'hmac'    => null,
                        'retries' => null,
                        'customHeaders' => null,
                    ],
                    [
                        'url'     => 'https://webhook.site/1390c177-dd1e-4d86-9e57-daaa011dc20f',
                        'events'  => ['message', 'session.status'],
                        'hmac'    => null,
                        'retries' => null,
                        'customHeaders' => null,
                    ]
                ]
            ]
        ];
        return self::call_api('PUT', '/sessions/{session}', $body);
    }
    public static function delete_session() {
        return self::call_api('DELETE', '/sessions/{session}');
    }
    public static function get_me() {
        return self::call_api('GET', '/sessions/{session}/me');
    }
    public static function start_session() {
        return self::call_api('POST', '/sessions/{session}/start');
    }
    public static function stop_session() {
        return self::call_api('POST', '/sessions/{session}/stop');
    }
    public static function logout_session() {
        return self::call_api('POST', '/sessions/{session}/logout');
    }
    public static function restart_session() {
        return self::call_api('POST', '/sessions/{session}/restart');
    }
}
