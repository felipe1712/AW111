<?php
namespace WpAutoWhats\Helpers;

defined('ABSPATH') or die('No script kiddies please!');

class Sender {

    /**
     * Send a WhatsApp message via the AUTWA API.
     *
     * @param string $chatId
     * @param string $message
     * @param string $media Optional media URL or data.
     * @return bool True on success, false on failure.
     */
    public static function send_message_ajax() {
        $chat = isset($_POST['client_id']) ? sanitize_text_field($_POST['client_id']) : '';
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        $chatType = isset($_POST['message_type']) ? sanitize_text_field($_POST['message_type']) : '';
        $replyTo = isset($_POST['reply_to']) ? sanitize_text_field($_POST['reply_to']) : '';
        $results = [];

        // Handle images
        if (!empty($_FILES['aw_media_images']['name'][0])) {
            $captions = isset($_POST['aw_media_image_captions']) ? $_POST['aw_media_image_captions'] : [];
            foreach ($_FILES['aw_media_images']['tmp_name'] as $i => $tmpName) {
                $fileName = $_FILES['aw_media_images']['name'][$i];
                $caption = $captions[$i] ?? '';
                $mimeType = $_FILES['aw_media_images']['type'][$i];
                $uploadDir = wp_upload_dir();
                $customDir = $uploadDir['basedir'] . '/wp-auto-whats/';
                if (!file_exists($customDir)) {
                    wp_mkdir_p($customDir);
                }
                $finalFileName = $fileName;
                $counter = 1;
                while (file_exists($customDir . $finalFileName)) {
                    $fileInfo = pathinfo($fileName);
                    $name = $fileInfo['filename'];
                    $ext = isset($fileInfo['extension']) ? '.' . $fileInfo['extension'] : '';
                    $finalFileName = $name . '-' . $counter . $ext;
                    $counter++;
                }
                $targetPath = $customDir . $finalFileName;
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $url = $uploadDir['baseurl'] . '/wp-auto-whats/' . $finalFileName;
                    $results[] = self::send_image_message($chat, $replyTo, $caption, $url, $mimeType);
                }
            }
        }
        // Handle files
        if (!empty($_FILES['aw_media_files']['name'][0])) {
            $captions = isset($_POST['aw_media_file_captions']) ? $_POST['aw_media_file_captions'] : [];
            foreach ($_FILES['aw_media_files']['tmp_name'] as $i => $tmpName) {
                $fileName = $_FILES['aw_media_files']['name'][$i];
                $caption = $captions[$i] ?? '';
                $mimeType = $_FILES['aw_media_files']['type'][$i];
                $uploadDir = wp_upload_dir();
                $customDir = $uploadDir['basedir'] . '/wp-auto-whats/';
                if (!file_exists($customDir)) {
                    wp_mkdir_p($customDir);
                }
                $finalFileName = $fileName;
                $counter = 1;
                while (file_exists($customDir . $finalFileName)) {
                    $fileInfo = pathinfo($fileName);
                    $name = $fileInfo['filename'];
                    $ext = isset($fileInfo['extension']) ? '.' . $fileInfo['extension'] : '';
                    $finalFileName = $name . '-' . $counter . $ext;
                    $counter++;
                }
                $targetPath = $customDir . $finalFileName;
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $url = $uploadDir['baseurl'] . '/wp-auto-whats/' . $finalFileName;
                    $results[] = self::send_file_message($chat, $replyTo, $caption, $url, $mimeType);
                }
            }
        }
        // Handle pre-uploaded image URLs (from AJAX, not file upload)
        if (empty($_FILES['aw_media_images']['name'][0]) && !empty($_POST['aw_media_images'])) {
            $images = is_array($_POST['aw_media_images']) ? $_POST['aw_media_images'] : [$_POST['aw_media_images']];
            $captions = isset($_POST['aw_media_image_captions']) ? $_POST['aw_media_image_captions'] : [];
            foreach ($images as $i => $url) {
                $caption = $captions[$i] ?? '';
                // Try to get mime type from URL extension
                $mimeType = wp_check_filetype($url)['type'] ?? 'image/jpeg';
                $results[] = self::send_image_message($chat, $replyTo, $caption, $url, $mimeType);
            }
        }
        // Handle pre-uploaded file URLs (from AJAX, not file upload)
        if (empty($_FILES['aw_media_files']['name'][0]) && !empty($_POST['aw_media_files'])) {
            $files = is_array($_POST['aw_media_files']) ? $_POST['aw_media_files'] : [$_POST['aw_media_files']];
            $captions = isset($_POST['aw_media_file_captions']) ? $_POST['aw_media_file_captions'] : [];
            foreach ($files as $i => $url) {
                $caption = $captions[$i] ?? '';
                $mimeType = wp_check_filetype($url)['type'] ?? 'application/octet-stream';
                $results[] = self::send_file_message($chat, $replyTo, $caption, $url, $mimeType);
            }
        }
        // If has text, send text
        if (!empty($message)) {
            $results[] = self::send_text_message($chat, $replyTo, $message);
        }
        wp_send_json_success(['sent' => $results]);
    }

    /**
     * Send a text message to a WhatsApp chat.
     *
     * @param string $chatId The ID of the chat to send the message to.
     * @param string $replyTo The ID of the message to reply to (optional).
     * @param string $message The text message to send.
     * @return bool True on success, false on failure.
     */

    private static function send_text_message(string $chatId, string $replyTo, string $message): bool {
        $body = [
            'chatId' => $chatId,
            'reply_to' => $replyTo,
            'text' => $message,
            'linkPreview' => true,
            'linkPreviewHighQuality' => false,
            'session' => AUTWA_SESSION_ID
        ];

        $replyToData = self::get_message_by_id($replyTo);
        if (!empty($replyToData)) {
            $body['reply_to'] = $replyToData['message_id'];
            $body['replyToMessageId'] = $replyToData['message_id'];
            $body['replyToMessage'] = $replyToData['message'];
            $body['notifyName'] = $replyToData['notifyName'];
        } else {
            $body['reply_to'] = '';
            $body['replyToMessageId'] = '';
            $body['replyToMessage'] = '';
            $body['notifyName'] = '';
        }

        $response = wp_remote_post(AUTWA_API_URL . '/sendText/', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($body),
            'timeout' => 15,
        ]);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 201) {
            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);
            $message = $data['message'] ?? [];
            self::log_message([
                'client_id'      => $chatId,
                'message'        => $data['body'] ?? $message,
                'from_me'        => 1,
                'media'          => '',
                'message_id'     => $data['id']['_serialized'] ?? '',
                'message_type'   => 'text',
                'message_status' => 'SEND',
                'from_id'        => $data['from'] ?? '',
                'to_id'          => $data['to'] ?? '',
                'has_media'      => 0,
                'timestamp'      => $data['timestamp'] ?? time(),
                'source'         => 'sendText',
                'ack'            => $data['ack'] ?? 0,
                'ack_name'       => self::get_ack_name($data['ack'] ?? 0),
                'vcards'         => '',
                'replyTo'        => $body['reply_to'] ?? '',
                'replyToMessageId' => $body['replyToMessageId'] ?? '',
                'replyToMessage' => $body['replyToMessage'] ?? '',
                'notifyName'     => $body['notifyName'] ?? '',
            ]);
            return true;
        }
        return false;
    }

    /**
     * Send an image message to a WhatsApp chat.
     *
     * @param string $chatId The ID of the chat to send the image to.
     * @param string $replyTo The ID of the message to reply to (optional).
     * @param string $caption The caption for the image.
     * @param string $mediaUrl The URL of the image to send.
     * @param string $mimeType The MIME type of the image.
     * @return bool True on success, false on failure.
     */
    private static function send_image_message(string $chatId, string $replyTo, string $caption, string $mediaUrl, string $mimeType): bool {
        $body = [
            'chatId' => $chatId,
            'file' => [
                'mimetype' => $mimeType,
                'filename' => basename($mediaUrl),
                'url' => $mediaUrl
            ],
            'reply_to' =>  $replyTo,
            'caption' => $caption,
            'session' => AUTWA_SESSION_ID
        ];

        $replyToData = self::get_message_by_id($replyTo);
        if (!empty($replyToData)) {
            $body['reply_to'] = $replyToData['message_id'];
            $body['replyToMessageId'] = $replyToData['message_id'];
            $body['replyToMessage'] = $replyToData['message'];
            $body['notifyName'] = $replyToData['notifyName'];
        } else {
            $body['reply_to'] = '';
            $body['replyToMessageId'] = '';
            $body['replyToMessage'] = '';
            $body['notifyName'] = '';
        }
        // Send the request to the AUTWA API
        $response = wp_remote_post(AUTWA_API_URL . '/sendImage/', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($body),
            'timeout' => 15,
        ]);

        if (!is_wp_error($response) ) {
            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);
            // Try to get the _data field if present (AUTWA API v3+)
            $msgData = $data['_data'] ?? $data['message'] ?? [];
            $message_id = $msgData['id']['_serialized'] ?? ($msgData['id'] ?? ($data['id']['_serialized'] ?? ''));
            $from_id = $msgData['from']['_serialized'] ?? ($msgData['from'] ?? ($data['from'] ?? ''));
            $to_id = $msgData['to']['_serialized'] ?? ($msgData['to'] ?? ($data['to'] ?? ''));
            $caption = $msgData['caption'] ?? ($caption ?? '');
            $timestamp = $msgData['t'] ?? ($msgData['timestamp'] ?? time());
            $ack = $msgData['ack'] ?? ($data['ack'] ?? 0);
            $message_type = $msgData['type'] ?? 'image';
            $message_status = 'SEND';
            $has_media = 1;
            $media_url = $mediaUrl;
            self::log_message([
                'client_id'      => $chatId,
                'message'        => $caption,
                'from_me'        => 1,
                'media'          => $media_url,
                'message_id'     => $message_id,
                'message_type'   => $message_type,
                'message_status' => $message_status,
                'from_id'        => $from_id,
                'to_id'          => $to_id,
                'has_media'      => $has_media,
                'timestamp'      => $timestamp,
                'source'         => 'sendImage',
                'ack'            => $ack,
                'ack_name'       => self::get_ack_name($ack),
                'vcards'         => '',
                'replyTo'        => $body['reply_to'] ?? '',
                'replyToMessageId' => $body['replyToMessageId'] ?? '',
                'replyToMessage' => $body['replyToMessage'] ?? '',
                'notifyName'     => $body['notifyName'] ?? '',
            ]);
            return true;
        }
        return false;
    }

    /**
     * Send a file message to a WhatsApp chat.
     *
     * @param string $chatId The ID of the chat to send the file to.
     * @param string $replyTo The ID of the message to reply to (optional).
     * @param string $caption The caption for the file.
     * @param string $mediaUrl The URL of the file to send.
     * @param string $mimeType The MIME type of the file.
     * @return bool True on success, false on failure.
     */
    private static function send_file_message(string $chatId, string $replyTo, string $caption, string $mediaUrl, string $mimeType): bool {
        $body = [
            'chatId' => $chatId,
            'file' => [
                'mimetype' => $mimeType,
                'filename' => basename($mediaUrl),
                'url' => $mediaUrl
            ],
            'reply_to' =>  $replyTo,
            'caption' => $caption,
            'session' => AUTWA_SESSION_ID
        ];

        $replyToData = self::get_message_by_id($replyTo);

        if (!empty($replyToData)) {
            $body['reply_to'] = $replyToData['message_id'];
            $body['replyToMessageId'] = $replyToData['message_id'];
            $body['replyToMessage'] = $replyToData['message'];
            $body['notifyName'] = $replyToData['notifyName'];
        } else {
            $body['reply_to'] = '';
            $body['replyToMessageId'] = '';
            $body['replyToMessage'] = '';
            $body['notifyName'] = '';
        }
        // Send the request to the AUTWA API
        $response = wp_remote_post(AUTWA_API_URL . '/sendFile/', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($body),
            'timeout' => 15,
        ]);

        if (!is_wp_error($response)) {
            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);
            // Try to get the _data field if present (AUTWA API v3+)
            $msgData = $data['_data'] ?? $data['message'] ?? [];
            $message_id = $msgData['id']['_serialized'] ?? ($msgData['id'] ?? ($data['id']['_serialized'] ?? ''));
            $from_id = $msgData['from']['_serialized'] ?? ($msgData['from'] ?? ($data['from'] ?? ''));
            $to_id = $msgData['to']['_serialized'] ?? ($msgData['to'] ?? ($data['to'] ?? ''));
            $caption_logged = $msgData['caption'] ?? ($caption ?? '');
            $timestamp = $msgData['t'] ?? ($msgData['timestamp'] ?? ($data['timestamp'] ?? time()));
            $ack = $msgData['ack'] ?? ($data['ack'] ?? 0);
            $message_type = $msgData['type'] ?? 'file';
            $message_status = 'SEND';
            $has_media = 1;
            $media_url = $mediaUrl;
            self::log_message([
                'client_id'      => $chatId,
                'message'        => $caption_logged,
                'from_me'        => 1,
                'media'          => $media_url,
                'message_id'     => $message_id,
                'message_type'   => $message_type,
                'message_status' => $message_status,
                'from_id'        => $from_id,
                'to_id'          => $to_id,
                'has_media'      => $has_media,
                'timestamp'      => $timestamp,
                'source'         => 'sendFile',
                'ack'            => $ack,
                'ack_name'       => self::get_ack_name($ack),
                'vcards'         => '',
                'replyTo'        => $body['reply_to'] ?? '',
                'replyToMessageId' => $body['replyToMessageId'] ?? '',
                'replyToMessage' => $body['replyToMessage'] ?? '',
                'notifyName'     => $body['notifyName'] ?? '',
            ]);
            return true;
        }
        return false;
    }
    
    /**
     * Get the name of the acknowledgment status.
     *
     * @param int $ack The acknowledgment status code.
     * @return string The name of the acknowledgment status.
     */
    public static function get_ack_name(int $ack): string {
        return match ($ack) {
            0 => 'Pending',
            1 => 'Server Received',
            2 => 'Delivered',
            3 => 'Read',
            4 => 'Played',
            default => 'Unknown',
        };
    }

    public static function get_message_by_id(string $messageId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'wpaw_messages';
        $query = $wpdb->prepare("SELECT * FROM {$table} WHERE message_id = %s", $messageId);
        return $wpdb->get_row($query, ARRAY_A) ?: [];
    }

    /**
     * Logs a message in the local database.
     *
     * @param string $chatId
     * @param string $message
     * @param bool   $fromMe
     * @param string $media
     */
    public static function log_message(array $data): void {
        global $wpdb;

        $table = $wpdb->prefix . 'wpaw_messages';

        $wpdb->insert(
            $table,
            [
                'client_id'      => $data['client_id'] ?? '',
                'message'        => $data['message'] ?? '',
                'from_me'        => $data['from_me'] ?? 0,
                'media'          => $data['media'] ?? '',
                'media_name'     => isset($data['media']) ? basename($data['media']) : '',
                'notifyName'     => $data['notifyName'] ?? '',
                'created_at'     => current_time('mysql'),
                'update_at'      => current_time('mysql'),
                'message_id'     => $data['message_id'] ?? '',
                'message_type'   => $data['message_type'] ?? '',
                'message_status' => $data['message_status'] ?? '',
                'from_id'        => $data['from_id'] ?? '',
                'to_id'          => $data['to_id'] ?? '',
                'has_media'      => $data['has_media'] ?? 0,
                'timestamp'      => $data['timestamp'] ?? 0,
                'source'         => $data['source'] ?? 'api',
                'ack'            => $data['ack'] ?? 0,
                'ack_name'       => $data['ack_name'] ?? '',
                'vcards'         => $data['vcards'] ?? '',
                'replyTo'        => $data['replyTo'] ?? '',
                'replyToMessageId' => $data['replyToMessageId'] ?? '',
                'replyToMessage' => $data['replyToMessage'] ?? '',
                'reaction'       => $data['reaction'] ?? '',
                'is_pinned'      => $data['is_pinned'] ?? 0,
                'is_deleted'     => $data['is_deleted'] ?? 0,
                'is_forwarded'   => $data['is_forwarded'] ?? 0,
                'forwarded_from' => $data['forwarded_from'] ?? '',
                'forwarded_to'   => $data['forwarded_to'] ?? '',
                'forwarded_message_id' => $data['forwarded_message_id'] ?? '',
                'forwarded_timestamp' => $data['forwarded_timestamp'] ?? 0,
                'forwarded_message_type' => $data['forwarded_message_type'] ?? '',
                'forwarded_message_status' => $data['forwarded_message_status'] ?? '',
                'forwarded_message_source' => $data['forwarded_message_source'] ?? '',
            ],
            [
                '%s', // client_id
                '%s', // message
                '%d', // from_me
                '%s', // media
                '%s', // media_name
                '%s', // notifyName
                '%s', // created_at
                '%s', // update_at
                '%s', // message_id
                '%s', // message_type
                '%s', // message_status
                '%s', // from_id
                '%s', // to_id
                '%d', // has_media
                '%d', // timestamp
                '%s', // source
                '%d', // ack
                '%s', // ack_name
                '%s',  // vcards
                '%s', // replyTo
                '%s', // replyToMessageId
                '%s', // replyToMessage
                '%s', // reaction
                '%d', // is_pinned
                '%d', // is_deleted
                '%d', // is_forwarded
                '%s', // forwarded_from
                '%s', // forwarded_to
                '%s', // forwarded_message_id
                '%d', // forwarded_timestamp
                '%s', // forwarded_message_type
                '%s', // forwarded_message_status
                '%s', // forwarded_message_source
            ]   
        );
        // last message update
        $wpdb->update(
            $wpdb->prefix . 'wpaw_chats',
            ['last_message' => $data['message'], 'last_message_time' => current_time('mysql')],
            ['client_id' => $data['client_id']],
            ['%s', '%s'],
            ['%s']
        );
    }

    /**
     * AJAX handler to upload a single media file (image or file) and return its URL.
     * Used for progress bar uploads before sending the message.
     *
     * Expects:
     * - $_FILES['media_file']
     * - $_POST['media_type'] ('image' or 'file')
     * - $_POST['media_caption'] (optional)
     *
     * Returns: { success: true, data: { url, mime, name } }
     */
    public static function upload_media_ajax() {
        if (empty($_FILES['media_file']['tmp_name'])) {
            wp_send_json_error(['message' => 'No file uploaded.']);
        }
        $file = $_FILES['media_file'];
        $type = isset($_POST['media_type']) ? sanitize_text_field($_POST['media_type']) : '';
        $caption = isset($_POST['media_caption']) ? sanitize_text_field($_POST['media_caption']) : '';
        $fileName = $file['name'];
        $mimeType = $file['type'];
        $uploadDir = wp_upload_dir();
        $customDir = $uploadDir['basedir'] . '/wp-auto-whats/';
        if (!file_exists($customDir)) {
            wp_mkdir_p($customDir);
        }
        $finalFileName = $fileName;
        $counter = 1;
        while (file_exists($customDir . $finalFileName)) {
            $fileInfo = pathinfo($fileName);
            $name = $fileInfo['filename'];
            $ext = isset($fileInfo['extension']) ? '.' . $fileInfo['extension'] : '';
            $finalFileName = $name . '-' . $counter . $ext;
            $counter++;
        }
        $targetPath = $customDir . $finalFileName;
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $url = $uploadDir['baseurl'] . '/wp-auto-whats/' . $finalFileName;
            wp_send_json_success([
                'url' => $url,
                'mime' => $mimeType,
                'name' => $finalFileName,
                'caption' => $caption
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to save file.']);
        }
    }
}