<?php
namespace WpAutoWhats\Helpers;

defined('ABSPATH') or die('No script kiddies please!');

use WpAutoWhats\Helpers\Functions;

class Scheduler {
     
    function __construct() {
        add_action('init', [$this, 'schedule_hourly_event']);
        add_action('wp_auto_whats_hourly_event', [$this, 'do_hourly_task']);
        add_action('init', [$this, 'schedule_six_hourly_event']);
        add_action('wp_auto_whats_six_hourly_event', [$this, 'do_six_hourly_task']);
        add_filter( 'plugin_row_meta', [$this , 'plugin_row_meta'], 10, 2 );
    }

    public function schedule_hourly_event() {
        if (!wp_next_scheduled('wp_auto_whats_hourly_event')) {
            wp_schedule_event(time(), 'hourly', 'wp_auto_whats_hourly_event');
        }

    }

    public function do_hourly_task() {
        error_log('Scheduler: Running hourly task'. ' at ' . date('Y-m-d H:i:s'));
        global $wpdb;
        // Only process files that are not downloaded
        $table_documents = $wpdb->prefix . 'wpaw_documents';
        $table_messages = $wpdb->prefix . 'wpaw_messages';
        $undownloaded_files = $wpdb->get_results("SELECT id, message_id, client_id FROM $table_documents WHERE download_status = 0", ARRAY_A);
        if (empty($undownloaded_files)) {
            return;
        }
        foreach ($undownloaded_files as $file) {
            $message_id = $file['message_id'] ?? '';
            $client_id = $file['client_id'] ?? '';
            if (empty($message_id) || empty($client_id)) {
                continue;
            }
            // Check if the message exists
            $message_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_messages WHERE message_id = %s",
                $message_id
            ));
            if (!$message_exists) {
                error_log('Scheduler: Message ID ' . $message_id . ' does not exist in wpaw_messages, skipping download.');
                continue;
            }
            // Build API URL securely
            if (!defined('AUTWA_API_URL') || !defined('AUTWA_SESSION_ID')) {
                error_log('Scheduler: AUTWA_API_URL or AUTWA_SESSION_ID not defined.');
                continue;
            }
            $api_url = rtrim(AUTWA_API_URL, '/') . '/' . urlencode(AUTWA_SESSION_ID) . '/chats/' . urlencode($client_id) . '/messages/' . urlencode($message_id) . '/?downloadMedia=true';
            $response = wp_remote_get($api_url);
            if (is_wp_error($response)) {
                error_log('Scheduler: Error fetching message: ' . $response->get_error_message());
                continue;
            }
            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                error_log('Scheduler: API error for message ID ' . $message_id . ': ' . $code);
                continue;
            }
            $body = wp_remote_retrieve_body($response);
            $payload = json_decode($body, true);
            if (!is_array($payload) || empty($payload['mediaUrl'])) {
                error_log('Scheduler: No mediaUrl found for message ID ' . $message_id);
                continue;
            }
            $media_url = $payload['mediaUrl'];
            // Always re-fetch the media URL from the API, as saved links may expire
            $media_url = preg_replace('#^https?://localhost:3000/api#', rtrim(AUTWA_API_URL, '/'), $media_url);
            $media_url = preg_replace('#^http://#', 'https://', $media_url);
            // Always call the API to get a fresh file, do not use cached/saved link
            $downloaded_url = Functions::wp_auto_whats_get_file_url($media_url, true); // Pass a flag to force re-download if your helper supports it
            if (is_wp_error($downloaded_url)) {
                error_log('Scheduler: Error fetching media URL: ' . $downloaded_url->get_error_message());
                continue;
            }
            // Update document and message tables securely
            $wpdb->update(
                $table_documents,
                ['downloaded_url' => $downloaded_url, 'download_status' => 1],
                ['id' => $file['id']],
                ['%s', '%d'],
                ['%d']
            );
            $wpdb->update(
                $table_messages,
                ['mediaUrl' => $downloaded_url],
                ['message_id' => $message_id],
                ['%s'],
                ['%s']
            );
        }
    }


    // every  6 hours chat auto download
    public function schedule_six_hourly_event() {
        if (!wp_next_scheduled('wp_auto_whats_six_hourly_event')) {
            wp_schedule_event(time(), 'twicedaily', 'wp_auto_whats_six_hourly_event');
        }
    }
    public function do_six_hourly_task() {
        // Example: Auto-download chats for all active contacts every 6 hours
        global $wpdb;
        $contacts = $wpdb->get_col("SELECT client_id FROM {$wpdb->prefix}wpaw_chats");
        foreach ($contacts as $client_id) {
            // Simulate chat download 
            self::download_chat($client_id);
        }
    }

    public static function plugin_row_meta( $links, $file ) {
        if ( AUTWA_PLUGIN_BASENAME !== $file ) {
			return $links;
		}

		$author_url = apply_filters( 'wpaw_author_url', 'https://asraful.com.bd/' );

		$row_meta = array(
			'docs'    => '<a href="' . esc_url( $author_url ) . '" aria-label="' . esc_attr__( 'Developed by MD. ASRAFUL ISLAM', 'wp-auto-whats' ) . '">' . esc_html__( 'DEVELOPER', 'wp-auto-whats' ) . '</a>',
		);

		return array_merge( $links, $row_meta );
	}


    public static function download_chat($client_id) {
        // Ensure AUTWA_API_URL and AUTWA_SESSION_ID are defined
        if (!defined('AUTWA_API_URL') || !defined('AUTWA_SESSION_ID')) {
            error_log('AUTWA_API_URL or AUTWA_SESSION_ID is not defined.');
            return;
        }
        if (!$client_id) {
            error_log('Client ID is not provided.');
            return;
        }

        $offset = 0;
        $limit = 10;
        $total_new = 0;

        do {
            $api_url = AUTWA_API_URL . '/' . AUTWA_SESSION_ID . '/chats/' . $client_id . '/messages?downloadMedia=true&limit=' . $limit . '&offset=' . $offset;
            error_log('API URL: ' . $api_url);
            $response = wp_remote_get($api_url);
            if (is_wp_error($response)) {
                error_log('API request failed.');
                break;
            }
            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                error_log('API error: ' . $code);
                break;
            }
            $body = wp_remote_retrieve_body($response);

            // If the response is a numerically indexed array, wrap it as ['messages' => $body]
            $data_preview = json_decode($body, true);
            if (is_array($data_preview) && isset($data_preview[0]) && !isset($data_preview['messages'])) {
                // The API returned a plain array of messages, wrap it
                $body = json_encode(['messages' => $data_preview]);
            }
            $data = json_decode($body, true);
            if (!is_array($data) || !isset($data['messages'])) {
                error_log('Invalid API response.');
                break;
            }
            $messages = $data['messages'];
            $new_count = 0;
            if (empty($messages)) {
                break;
            }
            foreach ($messages as $msg) {
                $message_id = $msg['id'] ?? '';
                if (!$message_id) continue;
                global $wpdb;
                $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}wpaw_messages WHERE message_id = %s", $message_id));
                if (!$exists) {
                    // Save message using Functions::wp_auto_whats_log_message
                    $from_id = $msg['from'] ?? '';
                    $to_id = $msg['to'] ?? '';
                    $message = $msg['body'] ?? '';
                    $timestamp = $msg['timestamp'] ?? 0;
                    $fromMe = $msg['fromMe'] ?? false;
                    $has_media = $msg['hasMedia'] ?? false;
                    $media = $msg['mediaUrl'] ?? '';
                    $media_name = isset($msg['media']['filename']) ? sanitize_text_field($msg['media']['filename']) : '';
                    $medial = '';

                    // Ensure media URL is valid
                    if (isset($msg['mediaUrl']) && !empty($msg['mediaUrl'])) {
                        $medial = $msg['mediaUrl'];
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
                    if (!empty($medial)) {
                        $file_size = Functions::get_file_size($medial);
                        global $wpdb;
                        $table = $wpdb->prefix . 'wpaw_documents';
                        $document_type = isset($msg['_data']['type']) ? sanitize_text_field($msg['_data']['type']) : '';
                        $document_name = isset($msg['media']['filename']) ? sanitize_text_field($msg['media']['filename']) : $document_type;
                        $document_url = $medial;
                        $document_size = intval($file_size);
                        $document_mime = isset($msg['_data']['mimetype']) ? sanitize_text_field($msg['_data']['mimetype']) : '';
                        $document_caption = isset($msg['body']) ? sanitize_text_field($msg['body']) : '';
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
                                $downloaded_url = $medial;
                                $download_status = 1;
                                error_log('Websocket: Media URL fetched successfully: ' . $medial);
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
                    // Additional fields

                    $source = $msg['source'] ?? '';
                    $ack = $msg['ack'] ?? 0;
                    $ackName = $msg['ackName'] ?? '';
                    $vCards = $msg['vCards'] ?? '';
                    $message_type = $msg['type'] ?? 'chat';
                    $message_status = $msg['messageStatus'] ?? 'sent';
                    $notifyName = $msg['_data']['notifyName'] ?? '';
                    $ispinned = isset($msg['_data']['isPinned']) ? (bool)$msg['_data']['isPinned'] : false;
                    $replyToMessageId = '';
                    $replyToMessage = '';
                    $replyTo = '';
                    // Check for reply/quoted message in payload
                    if (isset($msg['_data']['quotedMsg']) && !empty($msg['_data']['quotedMsg'])) {
                        // WhatsApp Websocket style (quotedMsg, quotedStanzaID, quotedParticipant)
                        $replyToMessage = isset($msg['_data']['quotedMsg']['body']) ? sanitize_text_field($msg['_data']['quotedMsg']['body']) : '';
                        $replyTo = isset($msg['_data']['quotedParticipant']['_serialized']) ? sanitize_text_field($msg['_data']['quotedParticipant']['_serialized']) : '';
                        if (isset($msg['_data']['quotedStanzaID']) && isset($msg['_data']['quotedParticipant']['_serialized'])) {
                            $replyToMessageId = ($msg['_data']['quotedParticipant']['_serialized'] === $from_id)
                                ? 'true_' . $msg['_data']['quotedParticipant']['_serialized'] . '_' . $msg['_data']['quotedStanzaID']
                                : 'false_' . $msg['_data']['quotedParticipant']['_serialized'] . '_' . $msg['_data']['quotedStanzaID'];
                        } else {
                            $replyToMessageId = '';
                        }
                    } elseif (isset($msg['replyTo']) && is_array($msg['replyTo'])) {
                        // Some APIs use replyTo object
                        $replyToMessage = isset($msg['replyTo']['body']) ? sanitize_text_field($msg['replyTo']['body']) : '';
                        $replyTo = isset($msg['replyTo']['from']) ? sanitize_text_field($msg['replyTo']['from']) : '';
                        if (isset($msg['replyTo']['id']) && isset($msg['replyTo']['from'])) {
                            $replyToMessageId = ($msg['replyTo']['from'] === $from_id)
                                ? 'true_' . $msg['replyTo']['from'] . '_' . $msg['replyTo']['id']
                                : 'false_' . $msg['replyTo']['from'] . '_' . $msg['replyTo']['id'];
                        } else {
                            $replyToMessageId = '';
                        }
                    }

                    Functions::wp_auto_whats_log_message(
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
                    $new_count++;
                }
            }
            $total_new += $new_count;
            $offset += $limit;
        } while ($new_count > 0);

        return $total_new;
    }
}