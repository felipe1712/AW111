<?php
namespace WpAutoWhats\API;

use WP_REST_Request;
use WP_REST_Response;
use WpAutoWhats\Helpers\Functions;

defined('ABSPATH') or die('No script kiddies please!');

class Event {

    /**
     * Register REST route for webhook
     */
    public static function register_routes() {
        register_rest_route('wp-auto-whats/v1', '/webhook', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'webhook_handler'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Handle incoming webhook POST request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function webhook_handler(WP_REST_Request $request): WP_REST_Response {
        $data = $request->get_json_params();

        if (empty($data) || !isset($data['event'])) {
            return new WP_REST_Response(['message' => 'Invalid payload: missing event'], 400);
        }

        // Validate session ID
        if (!isset($data['session']) || $data['session'] !== AUTWA_SESSION_ID) {
            error_log('Webhook: Invalid session ID');
            return new WP_REST_Response(['message' => 'Invalid session ID'], 403);
        }

        switch ($data['event']) {
            case 'message':
                return self::handle_message_event($data);

            case 'session.status':
                return self::handle_session_status_event($data);

            default:
                error_log('Webhook: Unhandled event type: ' . $data['event']);
                return new WP_REST_Response(['message' => 'Unhandled event type'], 400);
        }
    }

    /**
     * Process 'message' event
     *
     * @param array $data
     * @return WP_REST_Response
     */
    protected static function handle_message_event(array $data): WP_REST_Response {
        // Extract all relevant fields from payload
        $payload     = $data['payload'];
        $from_id     = isset($payload['from']) ? sanitize_text_field($payload['from']) : '';
        $to_id       = isset($payload['to']) ? sanitize_text_field($payload['to']) : '';
        $message_id  = isset($payload['id']) ? sanitize_text_field($payload['id']) : '';
        $fromMe      = $payload['fromMe'];
        $has_media   = $payload['hasMedia'] ?? false;
        $message     = isset($payload['body']) ?$payload['body']: '';
        $media       = isset($payload['mediaUrl']) ? $payload['mediaUrl'] : '';
        $media_name  = isset($payload['media']['filename']) ? sanitize_text_field($payload['media']['filename']) : '';
        $medial = '';
        if (isset($payload['mediaUrl'] ) && !empty($payload['mediaUrl'])) {
            $medial = $payload['mediaUrl'];
            // Replace http://localhost:3000/api with WP_AUTO_WHATS_API_URL and force https
            if (!empty($medial)) {
            $medial = preg_replace(
                '#^https?://localhost:3000/api#',
                rtrim(AUTWA_API_URL, '/'),
                $medial
            );
            // Ensure https
            $medial = preg_replace('#^http://#', 'https://', $medial);
            }
        }

        // check if media URL is valid and not empty, then check file size if file size is greater than 5MB, then return old media URL else wp_auto_whats_get_file_url($medial); to get the new media URL
        if (!empty($medial)) {
            $file_size = Functions::get_file_size($medial);
            global $wpdb;
            $table = $wpdb->prefix . 'wpaw_documents';
            $document_type = isset($payload['_data']['type']) ? sanitize_text_field($payload['_data']['type']) : '';
            $document_name = isset($payload['media']['filename']) ? sanitize_text_field($payload['media']['filename']) : $document_type;
            $document_url = $medial;
            $document_size = intval($file_size);
            $document_mime = isset($payload['_data']['mimetype']) ? sanitize_text_field($payload['_data']['mimetype']) : '';
            $document_caption = isset($payload['body']) ? sanitize_text_field($payload['body']) : '';
            $created_at = current_time('mysql');
            $update_at = $created_at;

            // Default values
            $downloaded_url = $medial;
            $download_status = 0;

            if ($file_size > 5242880) { // 5MB
                // File too large, do not download, just log
                error_log('Websocket: Media file size exceeds 5MB, returning old media URL');
            } else {
                $medial_new = Functions::wp_auto_whats_get_file_url($medial);
                if (is_wp_error($medial_new)) {
                    error_log('Websocket: Error fetching media URL: ' . $medial_new->get_error_message());
                } else {
                    $medial = $medial_new;
                    error_log('Websocket: Media URL fetched before 1 : ' . $downloaded_url);
                    $downloaded_url = $medial;
                    $download_status = 1;
                    error_log('Websocket: Media URL fetched successfully 2: ' . $medial_new);
                    error_log('Websocket: Media URL fetched successfully 3: ' . $medial_new);
                }
            }

            $wpdb->insert(
                $table,
                [
                    'client_id'        => $from_id,
                    'message_id'       => $message_id,
                    'document_type'    => $document_type,
                    'document_name'    => $document_name,
                    'document_url'     => $document_url,
                    'document_size'    => $document_size,
                    'document_mime'    => $document_mime,
                    'document_caption' => $document_caption,
                    'downloaded_url'   => $downloaded_url,
                    'download_status'  => $download_status,
                    'created_at'       => $created_at,
                    'update_at'        => $created_at,
                ]
            );
        }
        error_log('Websocket: Media URL processed: ' . $medial);
        $timestamp   = isset($payload['timestamp']) ? intval($payload['timestamp']) : 0;
        $source      = isset($payload['source']) ? sanitize_text_field($payload['source']) : '';
        $ack         = isset($payload['ack']) ? intval($payload['ack']) : 0;
        $ackName     = isset($payload['ackName']) ? sanitize_text_field($payload['ackName']) : '';
        $vCards      = isset($payload['vCards']) ? maybe_serialize($payload['vCards']) : '';
        $message_type   = isset($payload['type']) ? sanitize_text_field($payload['type']) : 'chat';
        $message_status = isset($payload['messageStatus']) ? sanitize_text_field($payload['messageStatus']) : 'sent';
        $notifyName = isset($payload['_data']['notifyName']) ? sanitize_text_field($payload['_data']['notifyName']) : '';
        $ispinned = isset($payload['_data']['isPinned']) ? intval($payload['_data']['isPinned']) : 0;
        
        //if has quotedMsg then save into replyToMessageId, replyToMessage, replyTo
        $replyToMessageId = '';
        $replyToMessage = '';
        $replyTo = '';

        // Check for reply/quoted message in payload
        if (isset($payload['_data']['quotedMsg']) && !empty($payload['_data']['quotedMsg'])) {
            // WhatsApp Websocket style (quotedMsg, quotedStanzaID, quotedParticipant)
            $replyToMessage = isset($payload['_data']['quotedMsg']['body']) ? sanitize_text_field($payload['_data']['quotedMsg']['body']) : '';
            $replyTo = isset($payload['_data']['quotedParticipant']['_serialized']) ? sanitize_text_field($payload['_data']['quotedParticipant']['_serialized']) : '';
            if (isset($payload['_data']['quotedStanzaID']) && isset($payload['_data']['quotedParticipant']['_serialized'])) {
                $replyToMessageId = ($payload['_data']['quotedParticipant']['_serialized'] === $payload['from'])
                    ? 'true_' . $payload['_data']['quotedParticipant']['_serialized'] . '_' . $payload['_data']['quotedStanzaID']
                    : 'false_' . $payload['_data']['quotedParticipant']['_serialized'] . '_' . $payload['_data']['quotedStanzaID'];
            } else {
                $replyToMessageId = '';
            }
        } elseif (isset($payload['replyTo']) && is_array($payload['replyTo'])) {
            // Some APIs use replyTo object
            $replyToMessage = isset($payload['replyTo']['body']) ? sanitize_text_field($payload['replyTo']['body']) : '';
            $replyTo = isset($payload['replyTo']['from']) ? sanitize_text_field($payload['replyTo']['from']) : '';
            if (isset($payload['replyTo']['id']) && isset($payload['replyTo']['from'])) {
                $replyToMessageId = ($payload['replyTo']['from'] === $payload['from'])
                    ? 'true_' . $payload['replyTo']['from'] . '_' . $payload['replyTo']['id']
                    : 'false_' . $payload['replyTo']['from'] . '_' . $payload['replyTo']['id'];
            } else {
                $replyToMessageId = '';
            }
        }

        error_log($payload['fromMe']);
        // Validate required fields
        if (empty($from_id) || empty($to_id) || empty($message_id)) {
            return new WP_REST_Response(['message' => 'Missing from_id, to_id or message_id'], 400);
        }

        // Log the message
        $message_saving = Functions::wp_auto_whats_log_message(
            $message_id,
            $from_id,
            $to_id,
            $message,
            $timestamp,
            $fromMe,
            $has_media,
            $medial,
            $media_name,
            $source,
            $ack,
            $ackName,
            $vCards,
            $message_type,
            $message_status,
            $notifyName,
            $replyToMessageId,
            $replyToMessage,
            $replyTo,
            $ispinned
        );
        if ($message_saving) {
            return new WP_REST_Response(['message' => $message_saving], 200);
        } else {
            error_log("Function wp_auto_whats_log_message() not found.");
            return new WP_REST_Response(['message' => 'Logging function missing.'], 500);
        }
    }


    /**
     * Process 'session.status' event
     *
     * @param array $data
     * @return WP_REST_Response
     */
    protected static function handle_session_status_event(array $data): WP_REST_Response {
        error_log('Webhook: Session status event received: ' . print_r($data, true));
        // Add your custom session status handling here, e.g., update DB, notify admin, etc.

        return new WP_REST_Response(['message' => 'Session status received'], 200);
    }
}
