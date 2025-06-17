<?php
namespace WpAutoWhats\Hooks;

use WpAutoWhats\Helpers\Functions;
use WpAutoWhats\Helpers\Sender;
use WpAutoWhats\Helpers\MessageApiHandler;

class AjaxHandler {
    public static function register() {
        add_action('wp_ajax_wpaw_send_message', [Sender::class, 'send_message_ajax']);
        add_action('wp_ajax_wpaw_load_messages', [self::class, 'load_messages']);
        add_action('wp_ajax_wpaw_create_new_chat', [self::class, 'create_chat']);
        add_action('wp_ajax_fetch_contacts', [self::class, 'fetch_contacts']);
        add_action('wp_ajax_wpaw_view_contact', [self::class, 'view_contact']);
        add_action('wp_ajax_wpaw_reload_contact', [self::class, 'reload_contact']);
        add_action( 'wp_ajax_wpaw_save_message', [self::class, 'save_message']);
        add_action( 'wp_ajax_wpaw_save_settings', [self::class, 'save_settings']);
        add_action( 'wp_ajax_wpaw_save_sessions', [self::class, 'save_sessions']);
        add_action( 'wp_ajax_wpaw_check_connection', [self::class, 'check_connection']);
        add_action( 'wp_ajax_wpaw_download_contacts', [self::class, 'download_contacts']);
        add_action( 'wp_ajax_wpaw_download_chat', [self::class, 'download_chat'] );
        add_action('wp_ajax_wpaw_get_session_status', [self::class, 'get_session_status']);
        add_action('wp_ajax_wpaw_get_session_online', [self::class, 'get_session_online']);
        add_action('wp_ajax_wpaw_session_create', [self::class, 'session_create']);
        add_action('wp_ajax_wpaw_session_info', [self::class, 'session_info']);
        add_action('wp_ajax_wpaw_session_update', [self::class, 'session_update']);
        add_action('wp_ajax_wpaw_session_delete', [self::class, 'session_delete']);
        add_action('wp_ajax_wpaw_session_me', [self::class, 'session_me']);
        add_action('wp_ajax_wpaw_session_start', [self::class, 'session_start']);
        add_action('wp_ajax_wpaw_session_stop', [self::class, 'session_stop']);
        add_action('wp_ajax_wpaw_session_logout', [self::class, 'session_logout']);
        add_action('wp_ajax_wpaw_session_restart', [self::class, 'session_restart']);
        add_action('wp_ajax_wpaw_get_chat_list', [self::class, 'get_chat_list']);
        add_action('wp_ajax_wpaw_upload_media', [Sender::class, 'upload_media_ajax']);
         add_action('wp_ajax_wpaw_message_action', [MessageApiHandler::class, 'handle_message_action']);
    }



    public static function load_messages() {
        $chat = sanitize_text_field($_POST['client_id'] ?? '');
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        if (!$chat) {
            wp_send_json_error('No chat selected.');
        }
        if ($chat !== '') {
            $messages = Functions::get_messages($chat, $offset, $limit);
            if (is_wp_error($messages)) {
                wp_send_json_error($messages->get_error_message());
            }

            ob_start();
            foreach ($messages as $msg) {
                $formatted_message = preg_replace('/\*([^\s*][^*]*[^\s*])\*/', '<b>$1</b>', $msg->message);
                echo '<div class="'.($msg->from_me == 1 ? 'from_me' : 'from_client') . '"
                 data-message-id="' . esc_attr($msg->message_id) . '" 
                 data-message-type="' . esc_attr($msg->message_type) . '" 
                 data-message-status="' . esc_attr($msg->message_status) . '"
                 data-message-timestamp="' . esc_attr($msg->timestamp) . '"
                 data-from="' . esc_attr($msg->from_id) . '"
                 data-to="' . esc_attr($msg->to_id) . '"
                 data-notify-name="';
                echo  ($msg->from_me == 1)? 'You' : $msg->notifyName;
                echo '"><div class="message">';
                $ispinned = $msg->is_pinned ? true : false;
                 // here hover to show down arrow , for dropdown menu[message info, reply, delete , copy ]
                echo '<div class="dropdown message-actions-dropdown" style="position:relative;display:inline-block;float:right;">
                    <button class="dropdown-toggle message-actions-toggle" title="More actions">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <ul class="dropdown-menu message-actions-menu" style="display:none;position:absolute;right:0;top:22px;z-index:10;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,0.08);min-width:140px;padding:4px 0;list-style:none;">
                        <li class="message-action-info" data-message-id="' . esc_attr($msg->message_id) . '">Message Info</li>
                        <li class="message-action-reply" data-message-id="' . esc_attr($msg->message_id) . '">Reply</li>
                        <li class="message-action-delete" data-message-id="' . esc_attr($msg->message_id) . '">Delete</li>
                        <li class="message-action-copy" data-message-id="' . esc_attr($msg->message_id) . '">Copy</li>
                        <li class="message-action-react" data-message-id="' . esc_attr($msg->message_id) . '">React</li>
                        '. ( $ispinned ? '<li class="message-action-unpin" data-message-id="' . esc_attr($msg->message_id) . '">Unpin</li>' : '<li class="message-action-pin" data-message-id="' . esc_attr($msg->message_id) . '">Pin</li>') . '
                        <li class="message-action-forward" data-message-id="' . esc_attr($msg->message_id) . '">Forward</li>
                    </ul>
                </div>';
                // if message is pinned, show pin icon
                if ($msg->is_pinned) {
                    echo '<span class="dashicons dashicons-admin-post message-pin-icon" title="Pinned" style="font-size:12px;"></span>';
                }
                // Show message if it is replied
                if (!empty($msg->replyToMessage)) {
                    echo '<div class="message-reply" data-message-reply-id="' . esc_html($msg->replyToMessageId) . '" style="background-color:#f0f0f0;padding:5px;border-radius:4px;margin-bottom:5px;color:#333;">';
                    echo '<strong>Replied to:</strong> ' . esc_html($msg->replyToMessage);
                    echo '</div>';
                }
                // Display media if available
                if (!empty($msg->media)) {
                    $media_url = $msg->media ? esc_url($msg->media) : '';
                    $media_type = '';
                    // Try to detect media type by file extension
                    $ext = strtolower(pathinfo(parse_url($media_url, PHP_URL_PATH), PATHINFO_EXTENSION));
                    $image_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
                    if (in_array($ext, $image_exts)) {
                        $media_type = 'image';
                    } else {
                        $media_type = 'file';
                    }
                    if ($media_type === 'image') {
                        echo '<a href="' . $media_url . '" target="_blank" class="message-media">';
                        echo '<img src="' . esc_url($media_url) . '" alt="Media" class="message-image" style="max-width: 200px; max-height: 200px; border-radius: 4px; margin-bottom: 5px;">';
                        echo '</a><br>';
                    } else {
                        // Show download button for non-image files
                        $filename = !empty($msg->media_name) ? esc_html($msg->media_name) : basename($media_url);
                        echo '<a href="' . $media_url . '" download class="button button-secondary message-download" style="margin-bottom:5px;">Download ' . $filename . '</a><br>';
                    }
                }
                    echo  '<span class="message-text">' . esc_html__('', 'wp-auto-whats') . wp_kses_post($formatted_message). '</span>';
                    echo '<span class="message-time">' . esc_html(date('H:i', $msg->timestamp)) . '</span>';
                echo '</div>
                <div class="message-reaction">'.esc_html__($msg->reaction).'</div>
                </div>';
            }
            wp_send_json_success(['html' => ob_get_clean()]);
        }
    }

    public static function create_chat() {
        $client_id = sanitize_text_field($_POST['number']);
        Functions::create_chat($client_id);
        wp_send_json_success(['client_id' => $client_id]);
    }

    public static function fetch_contacts() {
        global $wpdb;

        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
        $search = isset($_POST['search']['value']) ? sanitize_text_field($_POST['search']['value']) : '';
        $filter_type = isset($_POST['filter_type']) ? sanitize_text_field($_POST['filter_type']) : '';

        $where = [];
        $params = [];

        // Apply search filter
        if (!empty($search)) {
            $where[] = "(name LIKE %s OR phone_number LIKE %s)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        // Apply custom filter type
        if (!empty($filter_type)) {
            $where[] = "{$filter_type} = 1"; // Assumes boolean/int flag column like is_user = 1
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Main query
        $query = "SELECT * FROM {$wpdb->prefix}wpaw_chats $where_sql LIMIT %d, %d";
        $params[] = $start;
        $params[] = $length;

        $contacts = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);

        // Prepare data
        $data = [];
        foreach ($contacts as $contact) {
            $avatar = $contact['avatar_url'] ? $contact['avatar_url'] : AUTWA_PLUGIN_ICON . '/user.png';
            $name = $contact['name'] ? $contact['name'] : $contact['phone_number'];
            $data[] = [
                'avatar' => esc_url($avatar),
                'name' => esc_html($name),
                'phone_number' => esc_html($contact['phone_number']),
                'actions' => '<button class="button button-secondary reload-contact" data-contact-id="' . esc_attr($contact['client_id']) . '" title="Reload"><span class="dashicons dashicons-update"></span></button>
                            <button class="button button-secondary view-contact" data-contact-id="' . esc_attr($contact['client_id']) . '" title="View"><span class="dashicons dashicons-visibility"></span></button>
                            <a href="admin.php?page=wp-auto-whats&chat=' . urlencode($contact['client_id']) . '" class="button button-secondary" title="Message"><span class="dashicons dashicons-email"></span></a>'
            ];
        }

        // Total records (without filter)
        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wpaw_chats");

        // Filtered records
        $filter_count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}wpaw_chats $where_sql";
        $filtered_records = $wpdb->get_var($wpdb->prepare($filter_count_query, ...array_slice($params, 0, -2)));

        wp_send_json([
            'draw' => intval($_POST['draw']),
            'recordsTotal' => $total_records,
            'recordsFiltered' => $filtered_records,
            'data' => $data
        ]);
    }

    public static function view_contact() {
        $contact_id = sanitize_text_field($_POST['contact_id']);
        $contact = Functions::get_contact($contact_id);
        if ($contact) {
            wp_send_json_success($contact);
        } else {
            wp_send_json_error('Contact not found');
        }
    }     
    public static function reload_contact() {
        $contact_id = sanitize_text_field($_POST['contact_id']);
        $contact = Functions::reload_contact($contact_id);
        if ($contact) {
            wp_send_json_success($contact);
        } else {
            wp_send_json_error('Contact not found');
        }
    }    
    
    public static function save_message() {
        // $contact_id = sanitize_text_field($_POST['contact_id']);
        $payload = isset($_POST['payload']) ? $_POST['payload'] : [];
        if (!is_array($payload)) {
            $payload = json_decode($payload, true);
        }

        
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
            wp_send_json_success($message_saving);
        } else {
            wp_send_json_error('Contact not found');
        }
    }
    
    public static function save_settings(){
        check_ajax_referer('wpaw_settings_nonce');
        $api_url = sanitize_text_field($_POST['api_url']);
        $url_type = sanitize_text_field($_POST['url_type']);
        update_option('wpaw_api_url', $api_url);
        update_option('wpaw_url_type', $url_type);
        wp_send_json_success('Settings saved.');
    }

    public static function save_sessions(){
        check_ajax_referer('wpaw_sessions_nonce');
        $api_sessions = isset($_POST['api_sessions']) ? sanitize_text_field($_POST['api_sessions']): 'default';
        $old_sessions = get_option('wpaw_api_sessions');

        if($old_sessions && $api_sessions !== $old_sessions){
            $new_sessions = update_option('wpaw_api_sessions', $api_sessions);
              ( $new_sessions ) ? wp_send_json_success('Sessions updated.') : wp_send_json_success('Sessions update failed.') ;
        }else{
            $new_sessions = update_option('wpaw_api_sessions', $api_sessions);
            ( $new_sessions ) ? wp_send_json_success('Sessions saved.') : wp_send_json_success('Sessions save failed.') ;      
        }
    }

    public static function delete_sessions(){
        check_ajax_referer('wpaw_sessions_nonce');
        $api_sessions = isset($_POST['api_sessions']) ? sanitize_text_field($_POST['api_sessions']): 'default';
        $old_sessions =get_option('wpaw_api_sessions');
        if($old_sessions && $api_sessions !== $old_sessions){
            wp_send_json_error('Sessions not match.');
        }else{
            delete_option('wpaw_api_sessions');
            wp_send_json_success('Sessions deleted.');
        }
    }

    public static function check_connection() {
        check_ajax_referer('wpaw_connection_nonce');
         self::check_connection_helper();
    }

    public static function check_connection_helper() {

        $full_url = AUTWA_API_URL . '/sessions/' . AUTWA_SESSION_ID;

        $response = wp_remote_get($full_url, ['timeout' => 10]);

        if (is_wp_error($response)) {
            wp_send_json_error('Connection failed.');
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            wp_send_json_error('Connection failed. HTTP Code: ' . $code);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            wp_send_json_error('Invalid response format.');
        }

        $status = $data['status'] ?? 'UNKNOWN';
        self::status_to_send_response($status);
    }

    public static function status_to_send_response($status){
        
        if ($status === 'WORKING') {
            $full_url = AUTWA_API_URL . '/sessions/' . AUTWA_SESSION_ID;

            $response = wp_remote_get($full_url, ['timeout' => 10]);

            if (is_wp_error($response)) {
                wp_send_json_error('Connection failed.');
            }

            $code = wp_remote_retrieve_response_code($response);

            if ($code !== 200) {
                wp_send_json_error('Connection failed. HTTP Code: ' . $code);
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            // Save user meta
            $auto_whats_id = sanitize_text_field($data['me']['id']);
            if (!$auto_whats_id) {
                wp_send_json_error('No WhatsApp ID found in response.');
            }
            // Update option
            update_option('wpaw_auto_whats_id', $auto_whats_id);
    
            // Build response HTML
            ob_start();
            echo '<div style="padding:15px;background:#f6f6f6;border-radius:6px;border:1px solid #e2e2e2;max-width:400px;">';
            echo '<h4 style="margin-top:0;">Connection Details</h4>';
            echo '<ul style="list-style:none;padding:0;">';
            echo '<li><strong>API Status:</strong> ' . esc_html($data['status']) . '</li>';
            echo '<li><strong>Session name:</strong> ' . esc_html($data['name']) . '</li>';
            echo '<li><strong>Login Status:</strong> ' . esc_html($data['engine']['state'] ?? '') . '</li>';
            echo '<li><strong>Account name:</strong> ' . esc_html($data['me']['pushName'] ?? '') . '</li>';
            echo '<li><strong>Account ID:</strong> ' . esc_html($data['me']['id'] ?? '') . '</li>';
            echo '</ul>';
            echo '</div>';
            $html = ob_get_clean();
            update_option('wpaw_session_status', $status);
            wp_send_json_success(['message' => 'Connection successful.', 'details' => $html]);
    
        } elseif ($status === 'STOPPED') {
            wp_send_json_error('Session Stopped.'); 
        } elseif ($status === 'STARTING') {
            wp_send_json_error('Session Starting...');
        } elseif ($status === 'SCAN_QR_CODE') {
            // Try to restart session
            
            wp_remote_get(AUTWA_API_URL . '/' . AUTWA_SESSION_ID . '/auth/qr?format=image');
            $start_url = AUTWA_API_URL . '/screenshot?session=' . AUTWA_SESSION_ID;

            $response = wp_remote_get($start_url);
            if (is_wp_error($response)) {
                wp_send_json_error('Failed to fetch QR code.');
            }
            
            // Get image binary
            $image_data = wp_remote_retrieve_body($response);

            // Convert to base64
            $base64 = base64_encode($image_data);

            // Detect MIME type from headers (fallback to 'image/png' if not found)
            $headers = wp_remote_retrieve_headers($response);
            $mimetype = isset($headers['content-type']) ? $headers['content-type'] : 'image/png';

            // Build HTML
            ob_start();
            echo '<div style="padding:15px;background:#f6f6f6;border-radius:6px;border:1px solid #e2e2e2;max-width:400px;">';
            echo '<h4 style="margin-top:0;">Scan to login:</h4>';
            echo '<img src="data:' . esc_attr($mimetype) . ';base64,' . esc_attr($base64) . '" alt="QR Code">';
            echo '</div>';
            $html = ob_get_clean();

            wp_send_json_success([
                'message' => 'Please scan the QR code to login.',
                'details' => $html
            ]);
        } else {
            // Return current status like STARTING, SCAN_QR_CODE, FAILED
            wp_send_json_success(['Session status: ' . esc_html($status)]);
        }
    }

    public static function download_contacts(){
        check_ajax_referer('wpaw_contact_dl_nonce');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
           $downloading = Functions::wp_auto_whats_list_contacts();
            if ($downloading) {
                wp_send_json_error('Invalid response format.' );
            }
        }else{
            wp_send_json_error('Invalid response format.');
        }

    }

    public static function download_chat() {
        $client_id = sanitize_text_field($_POST['client_id'] ?? '');
        if (!$client_id) {
            wp_send_json_error('No chat selected.');
        }
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        $api_url = AUTWA_API_URL .'/'. AUTWA_SESSION_ID  . '/chats/' . $client_id . '/messages?downloadMedia=true&limit='. $limit .'&offset=' . $offset;
        error_log('API URL: ' . $api_url);
        $response = wp_remote_get($api_url);
        if (is_wp_error($response)) {
            wp_send_json_error('API request failed.');
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            wp_send_json_error('API error: ' . $code);
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
            wp_send_json_error('Invalid API response.');
        }
        $messages = $data['messages'];
        $new_count = 0;
        if (empty($messages)) {
            wp_send_json_success(['message' => 'No new messages found.', 'new_messages' => $new_count,'no_more' => true]);
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
                if (isset($msg['mediaUrl'] ) && !empty($msg['mediaUrl'])) {
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
        $is_last_batch = count($messages) < $limit || $new_count === 0;

        wp_send_json_success([
            'new_messages' => $new_count,
            'no_more' => $is_last_batch,
            'message' => 'Downloaded ' . $new_count . ' new messages.'
        ]);

    }

    public static function get_session_status() {
        $status = \WpAutoWhats\Helpers\SettingHelper::get_session_status();
        wp_send_json_success(['status' => $status]);
    }
        public static function get_session_online() {
        $existing_session = \WpAutoWhats\Helpers\SettingHelper::get_session();
        wp_send_json_success($existing_session);
    }


    public static function session_create() {
        // Check if the session already exists
        $existing_session = \WpAutoWhats\Helpers\SettingHelper::get_session();
        if ($existing_session['success'] && isset($existing_session['body']['status']) && $existing_session['body']['status'] !== 'DELETED') {
            wp_send_json_error(['error' => 'Session already exists.']);
        }
        $result = \WpAutoWhats\Helpers\SettingHelper::create_session();
        if ($result['success']) {
            if (isset($result['body']['status'])) {
                \WpAutoWhats\Helpers\SettingHelper::save_session_status($result['body']['status']);
            }
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['error' => $result['error'] ?? 'API error']);
        }
    }
    public static function session_info() {
        $result = \WpAutoWhats\Helpers\SettingHelper::get_session();
        if ($result['success']) {
            if (isset($result['body']['status'])) {
                \WpAutoWhats\Helpers\SettingHelper::save_session_status($result['body']['status']);
                if($result['body']['status'] === 'WORKING') {
                    // Save user meta
                    $auto_whats_id = sanitize_text_field($result['body']['me']['id'] ?? '');
                    if (!$auto_whats_id) {
                        wp_send_json_error('No WhatsApp ID found in response.');
                    }
                    // Update option
                    update_option('wpaw_auto_whats_id', $auto_whats_id);
                }
            }
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['error' => $result['error'] ?? 'API error']);
        }
    }
    public static function session_update() {
        $result = \WpAutoWhats\Helpers\SettingHelper::update_session();
        if ($result['success']) {
            if (isset($result['body']['status'])) {
                \WpAutoWhats\Helpers\SettingHelper::save_session_status($result['body']['status']);
            }
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['error' => $result['error'] ?? 'API error']);
        }
    }
    public static function session_delete() {
        $result = \WpAutoWhats\Helpers\SettingHelper::delete_session();
        if ($result['success']) {
            \WpAutoWhats\Helpers\SettingHelper::save_session_status('DELETED');
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['error' => $result['error'] ?? 'API error']);
        }
    }
    public static function session_me() {
        $result = \WpAutoWhats\Helpers\SettingHelper::get_me();
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['error' => $result['error'] ?? 'API error']);
        }
    }
    public static function session_start() {
        $result = \WpAutoWhats\Helpers\SettingHelper::start_session();
        if ($result['success']) {
            if (isset($result['body']['status'])) {
                \WpAutoWhats\Helpers\SettingHelper::save_session_status($result['body']['status']);
            }
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['error' => $result['error'] ?? 'API error']);
        }
    }
    public static function session_stop() {
        $result = \WpAutoWhats\Helpers\SettingHelper::stop_session();
        if ($result['success']) {
            if (isset($result['body']['status'])) {
                \WpAutoWhats\Helpers\SettingHelper::save_session_status($result['body']['status']);
            }
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['error' => $result['error'] ?? 'API error']);
        }
    }
    public static function session_logout() {
        $result = \WpAutoWhats\Helpers\SettingHelper::logout_session();
        if ($result['success']) {
            if (isset($result['body']['status'])) {
                \WpAutoWhats\Helpers\SettingHelper::save_session_status($result['body']['status']);
            }
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['error' => $result['error'] ?? 'API error']);
        }
    }
    public static function session_restart() {
        $result = \WpAutoWhats\Helpers\SettingHelper::restart_session();
        if ($result['success']) {
            if (isset($result['body']['status'])) {
                \WpAutoWhats\Helpers\SettingHelper::save_session_status($result['body']['status']);
            }
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['error' => $result['error'] ?? 'API error']);
        }
    }

    public static function get_chat_list() {
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $chats = \WpAutoWhats\Helpers\Functions::get_chats();
        $filtered = [];
        foreach ($chats as $chat) {
            if ($search) {
                $name = strtolower($chat['name'] ?? '');
                $number = strtolower($chat['phone_number'] ?? '');
                if (strpos($name, strtolower($search)) === false && strpos($number, strtolower($search)) === false) {
                    continue;
                }
            }
            $filtered[] = [
                'client_id' => $chat['client_id'],
                'name' => $chat['name'],
                'avatar_url' => $chat['avatar_url'],
                'last_message' => $chat['last_message'] ?? '',
                'last_message_time' => $chat['update_at'] ?? '',
                'unread_count' => $chat['unread_count'] ?? 0,
            ];
        }
        wp_send_json_success($filtered);
    }
}
