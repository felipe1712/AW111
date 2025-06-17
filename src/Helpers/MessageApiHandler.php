<?php
namespace WpAutoWhats\Helpers;

use WP_REST_Request;

class MessageApiHandler {
    public static function register() {
        add_action('wp_ajax_wpaw_message_action', [self::class, 'handle_message_action']);
    }

    public static function handle_message_action() {
        $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
        $messageId = isset($_POST['message_id']) ? sanitize_text_field($_POST['message_id']) : '';
        $chatId = isset($_POST['chat_id']) ? sanitize_text_field($_POST['chat_id']) : '';
        $session = defined('AUTWA_SESSION_ID') ? AUTWA_SESSION_ID : 'default';
        $api_url = defined('AUTWA_API_URL') ? AUTWA_API_URL : '';
        if (!$api_url) {
            wp_send_json_error('API URL not set.');
        }
        $endpoint = '';
        $method = 'GET';
        $body = null;
        switch ($action) {
            case 'delete':
                $endpoint = "/$session/chats/$chatId/messages/$messageId";
                $method = 'DELETE';
                break;
            case 'info':
                $endpoint = "/$session/chats/$chatId/messages/$messageId";
                $method = 'GET';
                break;
            case 'pin':
                $endpoint = "/$session/chats/$chatId/messages/$messageId/pin";
                $method = 'POST';
                $body = json_encode([ "duration" => 86400]);
                break;
            case 'unpin':
                $endpoint = "/$session/chats/$chatId/messages/$messageId/unpin";
                $method = 'POST';
                break;
            case 'react':
                $endpoint = "/reaction";
                $method = 'PUT';
                $body = json_encode([
                    'messageId' => $messageId,
                    'reaction' => isset($_POST['reaction']) ? sanitize_text_field($_POST['reaction']) : '',
                    'session' => $session
                ]);
                break;
            case 'forward':
                $endpoint = "/forwardMessage";
                $method = 'POST';
                $body = json_encode([
                    'chatId' => isset($_POST['target_chat_id']) ? sanitize_text_field($_POST['target_chat_id']) : '',
                    'messageId' => $messageId,
                    'session' => $session
                ]);
                error_log("Forwarding message with ID: $messageId to chat ID: " . (isset($_POST['target_chat_id']) ? sanitize_text_field($_POST['target_chat_id']) : ''));
                break;
            default:
                wp_send_json_error('Unknown action');
        }
        $args = [
            'method' => $method,
            'headers' => ['Content-Type' => 'application/json'],
        ];
        if ($body) $args['body'] = $body;
        $response = wp_remote_request(rtrim($api_url, '/') . $endpoint, $args);
        // error_log("Message API Response: " . ($response ? json_encode($response) : 'No response received'));
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        // Database update for relevant actions
        if ($code >= 200 && $code < 300) {
            global $wpdb;
            $messages_table = $wpdb->prefix . 'wpaw_messages';
            switch ($action) {
                case 'delete':
                    // Mark as deleted or remove from DB
                    $wpdb->delete($messages_table, ['message_id' => $messageId]);
                    break;
                case 'pin':
                    $wpdb->update($messages_table, ['is_pinned' => 1], ['message_id' => $messageId]);
                    break;
                case 'unpin':
                    $wpdb->update($messages_table, ['is_pinned' => 0], ['message_id' => $messageId]);
                    break;
                case 'react':
                    if (isset($_POST['reaction'])) {
                        $wpdb->update($messages_table, ['reaction' => sanitize_text_field($_POST['reaction'])], ['message_id' => $messageId]);
                    }
                    break;
                case 'forward':
                    // Optionally log the forward event (not duplicating message)
                    // Forward API does not return message data, so copy the original message and insert as new in DB
                    $target_chat_id = isset($_POST['target_chat_id']) ? sanitize_text_field($_POST['target_chat_id']) : '';
                    if ($target_chat_id) {
                        
                    }
                    break;
                // 'info' is read-only
            }
            wp_send_json_success($data);
        } else {
            wp_send_json_error($body);
        }
    }
}
