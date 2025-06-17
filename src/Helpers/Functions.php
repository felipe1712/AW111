<?php
namespace WpAutoWhats\Helpers;

defined('ABSPATH') or die('No script kiddies please!');

class Functions {

    /**
     * Retrieve all messages for a specific chat ID.
     *
     * @param string $chatId
     * @return array|object[] List of message objects.
     */
    public static function get_messages(string $chatId, int $offset = 0, int $limit = 10): array {
        global $wpdb;
        $auto_whats_id = get_option('wpaw_auto_whats_id', false);
        $table = $wpdb->prefix . 'wpaw_messages';
        if (!$auto_whats_id) {
            return [];
        }
        // Select last 20 messages, using offset, ordered by timestamp DESC, then reverse for ASC
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE ((to_id = %s AND from_id = %s)
                    OR (from_id = %s AND to_id = %s))
                 ORDER BY timestamp DESC
                 LIMIT $limit OFFSET %d",
                $chatId, $auto_whats_id, $chatId, $auto_whats_id, $offset
            )
        );
        // Reverse to get ASC order
        return array_reverse($results);
    }

    /**
     * Creates a new chat record in the database.
     *
     * @param string $chatId Raw or formatted chat ID.
     * @return void
     */
    public static function create_chat(string $chatId): void {
        global $wpdb;

        $table = $wpdb->prefix . 'wpaw_chats';

        // Format chatId like 880123456789@c.us if only digits
        $chatIdFormatted = preg_match('/^\d+$/', $chatId) ? $chatId . '@c.us' : $chatId;

        $wpdb->insert(
            $table,
            [
                'client_id'     => $chatIdFormatted,
                'name'        => $chatId,
                'phone_number'=> $chatId,
                'avatar_url'  => '',
                'created_at'  => current_time('mysql'),
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );
    }

    /**
     * Returns a list of chats, optionally limited.
     *
     * @param int|string $limit Number of chats to retrieve.
     * @return array List of chats as associative arrays.
     */
    public static function get_chats($limit = ''): array {
        global $wpdb;

        $table = $wpdb->prefix . 'wpaw_chats';

        $limitQuery = $limit ? 'LIMIT ' . intval($limit) : '';

        $results = $wpdb->get_results(
            "SELECT DISTINCT c.* 
             FROM {$table} c
             INNER JOIN {$wpdb->prefix}wpaw_messages m ON c.client_id = m.client_id
             ORDER BY m.update_at DESC $limitQuery",
            ARRAY_A
        );

        foreach ($results as &$chat) {
            $chat['avatar_url'] = $chat['avatar_url'] ?:AUTWA_PLUGIN_ICON . '/user.png';
            $chat['name'] = $chat['name'] ?: preg_replace('/@c\.us$/', '', $chat['phone_number']);
        }

        return $results;
    }

    /**
     * Fetches contacts from the database with optional search and pagination.
     *
     * @param int $start Starting index for pagination.
     * @param int $length Number of records to fetch.
     * @param string $search Search term for filtering contacts.
     * @return array List of contacts as associative arrays.
     */
    public static function get_contact($contactId): array {  
        global $wpdb;
        // Filter contact IDs to only include those ending with '@c.us'
        if (!preg_match('/^\d+@c\.us$/', $contactId)) {
           return ['error'=>'client id not valid'];
        }
        global $wpdb;

        $table = $wpdb->prefix . 'wpaw_chats';

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE client_id = %s",
                $contactId
            ),
            ARRAY_A
        );

        if ($result) {
            $result['image_link'] = $result['avatar_url'] ?: AUTWA_PLUGIN_ICON . '/user.png';
            $result['number'] = $result['phone_number'] ?: '';
            $result['name'] = $result['name'] ?: $result['number'];
            return $result;
        }

        return [];
    }
    /**
     * Fetches contacts from the database with optional search and pagination.
     *
     * @param int $start Starting index for pagination.
     * @param int $length Number of records to fetch.
     * @param string $search Search term for filtering contacts.
     * @return array List of contacts as associative arrays.
     */
    public static function reload_contact($contactId): array {  
        $response = wp_remote_get(AUTWA_API_URL . '/contacts?contactId=' . $contactId . '&session=' . AUTWA_SESSION_ID, [
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 15,
        ]);
        $image = self::wp_auto_whats_get_profile_pic($contactId);
         self::wp_auto_whats_update_contact($contactId);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['id'])) {
            $data['image_link'] = $image;
            }
            return $data;
        }

        return [];
    }

    /**
     * Fetches contacts from the database with optional search and pagination.
     *
     * @param int $start Starting index for pagination.
     * @param int $length Number of records to fetch.
     * @param string $search Search term for filtering contacts.
     * @return array List of contacts as associative arrays.
     */
    public static function wp_auto_whats_get_profile_pic($client_id) {
        $url = AUTWA_API_URL . '/contacts/profile-picture?contactId='.$client_id.'&refresh=false&session=' . AUTWA_SESSION_ID;
        $response = wp_remote_get($url, [
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        if (is_wp_error($response)) {
            error_log('Error fetching profile picture for contact: ' . $contact['id']);
            return null;
        }
        $body = wp_remote_retrieve_body($response);
        $profile_pic = json_decode($body, true);
        if (isset($profile_pic['profilePictureURL'])) {
            return $profile_pic['profilePictureURL'];
        } else {
            return '';
        }
    }

    public static function get_recipient($client_id): array {
        global $wpdb;

        $table = $wpdb->prefix . 'wpaw_chats';

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE client_id = %s",
                $client_id
            ),
            ARRAY_A
        );

        if ($result) {
            $result['avatar_url'] = $result['avatar_url'] ?: AUTWA_PLUGIN_ICON . '/user.png';
            return $result;
        }

        return [];
    }

    // Function to save a message to the database
    public static function wp_auto_whats_log_message(
        $message_id,
        $from_id,
        $to_id,
        $message,
        $timestamp,
        $fromMe,
        $has_media,
        $media,
        $media_name,
        $source,
        $ack,
        $ackName,
        $vCards,
        $message_type ,
        $message_status,
        $notifyName,
        $replyToMessageId,
        $replyToMessage,
        $replyTo,
        $ispinned
    ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wpaw_messages';

        // Check if message_id already exists
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE message_id = %s",
                $message_id
            )
        );

        if (!$exists) {
            $unslas_message = wp_unslash($message);
            $wpdb->insert($table, [
                'client_id'      => $from_id,
                'message_id'     => $message_id,
                'message'        => $unslas_message,
                'from_me'        => $fromMe ? $fromMe : 0,
                'created_at'     => current_time('mysql'),
                'update_at'      => current_time('mysql'),
                'media'          => $media,
                'media_name'     => $media_name,
                'message_type'   => $message_type,
                'message_status' => $message_status,
                'from_id'        => $from_id,
                'to_id'          => $to_id,
                'has_media'      => $has_media ? $has_media : 0,
                'timestamp'      => $timestamp,
                'source'         => $source,
                'ack'            => $ack,
                'ack_name'       => $ackName,
                'vcards'         => $vCards,
                'notifyName'     => $notifyName,
                'replyToMessageId' => $replyToMessageId,
                'replyToMessage' => $replyToMessage,
                'replyTo'        => $replyTo,
                'is_pinned'      => $ispinned ? $ispinned : 0,
            ]);
            // last message update
            $wpdb->update(
                $wpdb->prefix . 'wpaw_chats',
                [
                    'last_message'    => $unslas_message,
                    'last_message_time' => current_time('mysql'),
                    'unread_count' => 1,
                ],
                ['client_id' => $from_id],
                ['%s', '%s'],
                ['%s']
            );
            return 'message saved';
        } else {
            return 'message already saved';
        }
    }

    // Function to update the contact list in the database api endpoint /{session}/chats
    public static function wp_auto_whats_list_contacts() {
        global $wpdb;
        $contacts = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpaw_chats", ARRAY_A);
        // get the list of contacts from api endpoint /{session}/chats/overview?limit=20, it response [{"id":"8801519607646@c.us","name":"SakileGp","picture":"https://pps.whatsapp.net/v/t61.24694-24/367989333_831997688459638_6617877833831928414_n.jpg?ccb=11-4&oh=01_Q5Aa1QFKuZeZVRm5Ly5ajBfIRg2rnRzQ2mJNTCG0DR3Y8xb3DQ&oe=6826AC56&_nc_sid=5e03e0&_nc_cat=109","lastMessage":{"id":"false_8801519607646@c.us_3EB0AF547F3E4DB6582556","timestamp":1746524318,"from":"8801519607646@c.us","fromMe":false,"source":"app","to":"8801907847558@c.us","body":"wel","hasMedia":false,"media":null,"ack":1,"ackName":"SERVER","vCards":[],"_data":{"id":{"fromMe":false,"remote":"8801519607646@c.us","id":"3EB0AF547F3E4DB6582556","_serialized":"false_8801519607646@c.us_3EB0AF547F3E4DB6582556"},"viewed":false,"body":"wel","type":"chat","t":1746524318,"notifyName":"MD ASRAFUL ISLAM","from":{"server":"c.us","user":"8801519607646","_serialized":"8801519607646@c.us"},"to":{"server":"c.us","user":"8801907847558","_serialized":"8801907847558@c.us"},"ack":1,"invis":false,"isNewMsg":true,"star":false,"kicNotified":false,"recvFresh":true,"isFromTemplate":false,"pollInvalidated":false,"isSentCagPollCreation":false,"latestEditMsgKey":null,"latestEditSenderTimestampMs":null,"mentionedJidList":[],"groupMentions":[],"isEventCanceled":false,"eventInvalidated":false,"isVcardOverMmsDocument":false,"isForwarded":false,"hasReaction":false,"viewMode":"VISIBLE","messageSecret":{"0":96,"1":0,"2":140,"3":58,"4":140,"5":74,"6":38,"7":155,"8":59,"9":218,"10":44,"11":181,"12":215,"13":188,"14":211,"15":0,"16":231,"17":43,"18":159,"19":120,"20":115,"21":200,"22":211,"23":54,"24":46,"25":109,"26":253,"27":215,"28":132,"29":55,"30":131,"31":5},"productHeaderImageRejected":false,"lastPlaybackProgress":0,"isDynamicReplyButtonsMsg":false,"isCarouselCard":false,"parentMsgId":null,"callSilenceReason":null,"isVideoCall":false,"callDuration":null,"callParticipants":null,"isMdHistoryMsg":false,"stickerSentTs":0,"isAvatar":false,"lastUpdateFromServerTs":0,"invokedBotWid":null,"bizBotType":null,"botResponseTargetId":null,"botPluginType":null,"botPluginReferenceIndex":null,"botPluginSearchProvider":null,"botPluginSearchUrl":null,"botPluginSearchQuery":null,"botPluginMaybeParent":false,"botReelPluginThumbnailCdnUrl":null,"botMessageDisclaimerText":null,"botMsgBodyType":null,"reportingTokenInfo":{"reportingToken":{"0":171,"1":63,"2":117,"3":10,"4":117,"5":103,"6":45,"7":76,"8":3,"9":196,"10":13,"11":67,"12":39,"13":22,"14":23,"15":211},"version":1,"reportingTag":{"0":1,"1":6,"2":238,"3":150,"4":235,"5":186,"6":17,"7":121,"8":19,"9":163,"10":141,"11":91,"12":114,"13":51,"14":241,"15":189,"16":55,"17":189,"18":217,"19":166}},"requiresDirectConnection":false,"bizContentPlaceholderType":null,"hostedBizEncStateMismatch":false,"senderOrRecipientAccountTypeHosted":false,"placeholderCreatedWhenAccountIsHosted":false,"links":[]}},"_chat":{"id":{"server":"c.us","user":"8801519607646","_serialized":"8801519607646@c.us"},"name":"SakileGp","isGroup":false,"unreadCount":5,"timestamp":1746524318,"pinned":false,"isMuted":false,"muteExpiration":0,"lastMessage":{"_data":{"id":{"fromMe":false,"remote":"8801519607646@c.us","id":"3EB0AF547F3E4DB6582556","_serialized":"false_8801519607646@c.us_3EB0AF547F3E4DB6582556"},"viewed":false,"body":"wel","type":"chat","t":1746524318,"notifyName":"MD ASRAFUL ISLAM","from":{"server":"c.us","user":"8801519607646","_serialized":"8801519607646@c.us"},"to":{"server":"c.us","user":"8801907847558","_serialized":"8801907847558@c.us"},"ack":1,"invis":false,"isNewMsg":true,"star":false,"kicNotified":false,"recvFresh":true,"isFromTemplate":false,"pollInvalidated":false,"isSentCagPollCreation":false,"latestEditMsgKey":null,"latestEditSenderTimestampMs":null,"mentionedJidList":[],"groupMentions":[],"isEventCanceled":false,"eventInvalidated":false,"isVcardOverMmsDocument":false,"isForwarded":false,"hasReaction":false,"viewMode":"VISIBLE","messageSecret":{"0":96,"1":0,"2":140,"3":58,"4":140,"5":74,"6":38,"7":155,"8":59,"9":218,"10":44,"11":181,"12":215,"13":188,"14":211,"15":0,"16":231,"17":43,"18":159,"19":120,"20":115,"21":200,"22":211,"23":54,"24":46,"25":109,"26":253,"27":215,"28":132,"29":55,"30":131,"31":5},"productHeaderImageRejected":false,"lastPlaybackProgress":0,"isDynamicReplyButtonsMsg":false,"isCarouselCard":false,"parentMsgId":null,"callSilenceReason":null,"isVideoCall":false,"callDuration":null,"callParticipants":null,"isMdHistoryMsg":false,"stickerSentTs":0,"isAvatar":false,"lastUpdateFromServerTs":0,"invokedBotWid":null,"bizBotType":null,"botResponseTargetId":null,"botPluginType":null,"botPluginReferenceIndex":null,"botPluginSearchProvider":null,"botPluginSearchUrl":null,"botPluginSearchQuery":null,"botPluginMaybeParent":false,"botReelPluginThumbnailCdnUrl":null,"botMessageDisclaimerText":null,"botMsgBodyType":null,"reportingTokenInfo":{"reportingToken":{"0":171,"1":63,"2":117,"3":10,"4":117,"5":103,"6":45,"7":76,"8":3,"9":196,"10":13,"11":67,"12":39,"13":22,"14":23,"15":211},"version":1,"reportingTag":{"0":1,"1":6,"2":238,"3":150,"4":235,"5":186,"6":17,"7":121,"8":19,"9":163,"10":141,"11":91,"12":114,"13":51,"14":241,"15":189,"16":55,"17":189,"18":217,"19":166}},"requiresDirectConnection":false,"bizContentPlaceholderType":null,"hostedBizEncStateMismatch":false,"senderOrRecipientAccountTypeHosted":false,"placeholderCreatedWhenAccountIsHosted":false,"links":[]},"id":{"fromMe":false,"remote":"8801519607646@c.us","id":"3EB0AF547F3E4DB6582556","_serialized":"false_8801519607646@c.us_3EB0AF547F3E4DB6582556"},"ack":1,"hasMedia":false,"body":"wel","type":"chat","timestamp":1746524318,"from":"8801519607646@c.us","to":"8801907847558@c.us","deviceType":"android","isForwarded":false,"forwardingScore":0,"isStatus":false,"isStarred":false,"fromMe":false,"hasQuotedMsg":false,"hasReaction":false,"vCards":[],"mentionedIds":[],"groupMentions":[],"isGif":false,"links":[]}}}]
        $url = AUTWA_API_URL .'/contacts/all?session='. AUTWA_SESSION_ID;   

        $response = wp_remote_get($url, [
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        if (is_wp_error($response)) {
            error_log('Error fetching contacts');
        }
        $body = wp_remote_retrieve_body($response);
        $contacts = json_decode($body, true);
        //print_r($contacts);
        self::wp_auto_whats_update_contact_list($contacts);

        return true;
    }

    // function for contuct list update
    public static function wp_auto_whats_update_contact_list($contacts) {
        global $wpdb;

        foreach ($contacts as $contact) {
            // Filter contact IDs to only include those ending with '@c.us'
            if (!preg_match('/^\d+@c\.us$/', $contact['id'])) {
                continue;
            }
            self::wp_auto_whats_update_contact($contact['id']);
        }
        return true;
    }

    public static function wp_auto_whats_update_contact($contactId) {
        global $wpdb;
        // Filter contact IDs to only include those ending with '@c.us'
        if (!preg_match('/^\d+@c\.us$/', $contactId)) {
           return 'client id not valid';
        }
        $url = AUTWA_API_URL .'/contacts?contactId='. $contactId .'&session='. AUTWA_SESSION_ID;   
        $response = wp_remote_get($url, [
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        if (is_wp_error($response)) {
            error_log('Error fetching contacts');
        }
        $body = wp_remote_retrieve_body($response);
        $contact = json_decode($body, true);

        // Check if the contact already exists in the database
        $existing_contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wpaw_chats WHERE client_id = %s",
            $contactId
        ));

        if ($existing_contact) {
            // Update existing contact
            $wpdb->update(
                $wpdb->prefix . 'wpaw_chats',
                [
                    'name' => $contact['name'] ?? '',
                    'phone_number' => $contact['number']?? '',
                    'short_name' => $contact['shortName'] ?? '',
                    'type' => $contact['type'],
                    'is_me' => $contact['isMe'] ? 1 : 0,
                    'is_user' => $contact['isUser'] ? 1 : 0,
                    'is_group' => $contact['isGroup'] ? 1 : 0,
                    'is_wpaw_contact' => $contact['isWAContact'] ? 1 : 0,
                    'is_my_contact' => $contact['isMyContact'] ? 1 : 0,
                    'is_blocked' => $contact['isBlocked'] ? 1 : 0,
                    'is_business' => $contact['isBusiness'] ? 1 : 0,
                    'avatar_url' =>   self::wp_auto_whats_get_profile_pic($contact['id']),

                ],
                ['client_id' => $contact['id']]
            );
            return 'contact update success';
        } else {
            // Insert new contact
            $wpdb->insert(
                $wpdb->prefix . 'wpaw_chats',
                [
                    'client_id' => $contact['id'],
                    'name' => $contact['name'],
                    'phone_number' => $contact['number'],
                    'short_name' => $contact['shortName'],
                    'type' => $contact['type'],
                    'is_me' => $contact['isMe'] ? 1 : 0,
                    'is_user' => $contact['isUser'] ? 1 : 0,
                    'is_group' => $contact['isGroup'] ? 1 : 0,
                    'is_wpaw_contact' => $contact['isWAContact'] ? 1 : 0,
                    'is_my_contact' => $contact['isMyContact'] ? 1 : 0,
                    'is_blocked' => $contact['isBlocked'] ? 1 : 0,
                    'is_business' => $contact['isBusiness'] ? 1 : 0,
                    'avatar_url' =>   self::wp_auto_whats_get_profile_pic($contact['id']),
                ]
            );
            return 'contact insert success';
        }

    }

    // url to download file in folder and return the file URL 
    // Get the public URL for a file in the uploads/wp-auto-whats folder, given an absolute or relative path
    public static function wp_auto_whats_get_file_url($fileurl) {
        $uploads = wp_upload_dir();
        $upload_dir = $uploads['basedir'] . '/wp-auto-whats';
        $upload_url = $uploads['baseurl'] . '/wp-auto-whats';

        // Ensure the upload directory exists
        if (!file_exists($upload_dir)) {
            wp_mkdir_p($upload_dir);
        }
        // If the file is not a URL, return it directly
        if (!filter_var($fileurl, FILTER_VALIDATE_URL)) {
            return $fileurl;
        }

        // If the file is a local path, check if it exists
        if (file_exists($upload_dir . '/' . $fileurl)) {
            return $upload_url . '/' . $fileurl;
        }

        // If the file does not exist, download the file from the URL
        if (filter_var($fileurl, FILTER_VALIDATE_URL)) {
            if ( ! function_exists( 'download_url' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            $tmp = download_url($fileurl);
            if (is_wp_error($tmp)) {
                return false; // Download failed
            }
            // Get the file name from the URL
            $filename = basename(parse_url($fileurl, PHP_URL_PATH));
            // Move the downloaded file to the uploads directory
            $file_path = $upload_dir . '/' . $filename;
            rename($tmp, $file_path);
            return $upload_url . '/' . $filename;
        }

    }

    //get_file_size
    public static function get_file_size($fileurl) {
        $uploads = wp_upload_dir();
        $upload_dir = $uploads['basedir'] . '/wp-auto-whats';

        // If the file is a URL, get the size from the headers
        if (filter_var($fileurl, FILTER_VALIDATE_URL)) {
            $headers = wp_remote_head($fileurl);
            if (is_wp_error($headers)) {
                return false; // Error fetching headers
            }
            error_log(print_r(wp_remote_retrieve_header($headers, 'content-length'), true));
            return wp_remote_retrieve_header($headers, 'content-length');
        }

        // If the file is a local path, check if it exists
        if (file_exists($upload_dir . '/' . $fileurl)) {
            return filesize($upload_dir . '/' . $fileurl);
        }

        return false; // File does not exist
    }

    /**
     * Generate a downloadable chat file or media zip for a chat and return the file URL.
     * If $format is 'auto', will detect file type from the first media URL and export only that type.
     *
     * @param string $chatId
     * @param string $format 'json', 'csv', 'txt', 'zip', 'auto', or media type ('images', 'videos', 'audio', 'pdf', 'doc', 'excel')
     * @return string|false URL to the generated file or false on failure
     */
    public static function generate_chat_download_file($chatId, $format = 'json') {
        global $wpdb;
        $uploads = wp_upload_dir();
        $upload_dir = $uploads['basedir'] . '/wp-auto-whats';
        $upload_url = $uploads['baseurl'] . '/wp-auto-whats';

        if (!file_exists($upload_dir)) {
            wp_mkdir_p($upload_dir);
        }

        // Fetch all messages for this chat
        $table = $wpdb->prefix . 'wpaw_messages';
        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE (from_id = %s OR to_id = %s) ORDER BY timestamp ASC",
                $chatId, $chatId
            ),
            ARRAY_A
        );

        if (!$messages) {
            return false;
        }

        $timestamp = date('Ymd_His');
        $base_filename = 'chat_' . sanitize_file_name($chatId) . '_' . $timestamp;

        // Helper: get media file extensions by type
        $media_types = [
            'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'videos' => ['mp4', 'mov', 'avi', 'mkv', 'webm'],
            'audio'  => ['mp3', 'ogg', 'wav', 'm4a'],
            'pdf'    => ['pdf'],
            'doc'    => ['doc', 'docx'],
            'excel'  => ['xls', 'xlsx', 'csv'],
        ];

        // Collect media files referenced in messages
        $media_files = [];
        foreach ($messages as $msg) {
            if (!empty($msg['media'])) {
                $media_path = $msg['media'];
                // Accept both URLs and local paths
                if (filter_var($media_path, FILTER_VALIDATE_URL)) {
                    $media_files[] = $media_path;
                } elseif (file_exists($media_path)) {
                    $media_files[] = $media_path;
                } else {
                    $try_path = $upload_dir . '/' . basename($media_path);
                    if (file_exists($try_path)) {
                        $media_files[] = $try_path;
                    }
                }
            }
        }

        // If auto-detect mode: detect type from first media file
        if ($format === 'auto' && !empty($media_files)) {
            $first = $media_files[0];
            $ext = strtolower(pathinfo(parse_url($first, PHP_URL_PATH), PATHINFO_EXTENSION));
            foreach ($media_types as $type => $exts) {
                if (in_array($ext, $exts)) {
                    $format = $type;
                    break;
                }
            }
        }

        // If exporting only a specific media type (including auto-detected)
        if (array_key_exists($format, $media_types)) {
            $exts = $media_types[$format];
            $filtered = array_filter($media_files, function($f) use ($exts) {
                $ext = strtolower(pathinfo(parse_url($f, PHP_URL_PATH), PATHINFO_EXTENSION));
                return in_array($ext, $exts);
            });
            if (empty($filtered)) return false;
            $zipname = $base_filename . '_' . $format . '.zip';
            $zippath = $upload_dir . '/' . $zipname;
            $zip = new \ZipArchive();
            if ($zip->open($zippath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
                foreach ($filtered as $file) {
                    if (filter_var($file, FILTER_VALIDATE_URL)) {
                        // Download to temp, add, then delete
                        if ( ! function_exists( 'download_url' ) ) {
                            require_once ABSPATH . 'wp-admin/includes/file.php';
                        }
                        $tmp = download_url($file);
                        if (!is_wp_error($tmp)) {
                            $zip->addFile($tmp, basename(parse_url($file, PHP_URL_PATH)));
                            @unlink($tmp);
                        }
                    } else {
                        $zip->addFile($file, basename($file));
                    }
                }
                $zip->close();
                return $upload_url . '/' . $zipname;
            } else {
                return false;
            }
        }

        // If exporting everything as a zip (chat + all media)
        if ($format === 'zip') {
            $zipname = $base_filename . '.zip';
            $zippath = $upload_dir . '/' . $zipname;
            $zip = new \ZipArchive();
            if ($zip->open($zippath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
                // Add chat as JSON
                $jsonfile = $base_filename . '.json';
                $zip->addFromString($jsonfile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                // Add all media
                foreach ($media_files as $file) {
                    if (filter_var($file, FILTER_VALIDATE_URL)) {
                        if ( ! function_exists( 'download_url' ) ) {
                            require_once ABSPATH . 'wp-admin/includes/file.php';
                        }
                        $tmp = download_url($file);
                        if (!is_wp_error($tmp)) {
                            $zip->addFile($tmp, 'media/' . basename(parse_url($file, PHP_URL_PATH)));
                            @unlink($tmp);
                        }
                    } else {
                        $zip->addFile($file, 'media/' . basename($file));
                    }
                }
                $zip->close();
                return $upload_url . '/' . $zipname;
            } else {
                return false;
            }
        }

        // Standard export: json, csv, txt
        $filename = $base_filename . '.' . $format;
        $filepath = $upload_dir . '/' . $filename;
        $fileurl = $upload_url . '/' . $filename;

        if ($format === 'json') {
            file_put_contents($filepath, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } elseif ($format === 'csv') {
            $fp = fopen($filepath, 'w');
            if ($fp) {
                fputcsv($fp, array_keys($messages[0]));
                foreach ($messages as $row) {
                    fputcsv($fp, $row);
                }
                fclose($fp);
            } else {
                return false;
            }
        } elseif ($format === 'txt') {
            $lines = [];
            foreach ($messages as $msg) {
                $lines[] = '[' . date('Y-m-d H:i:s', $msg['timestamp']) . '] ' . ($msg['from_me'] ? 'Me' : $msg['from_id']) . ': ' . $msg['message'];
            }
            file_put_contents($filepath, implode("\n", $lines));
        } else {
            return false;
        }

        return $fileurl;
    }

}
