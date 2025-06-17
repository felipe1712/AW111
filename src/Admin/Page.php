<?php
namespace WpAutoWhats\Admin;

use WpAutoWhats\Helpers\Functions;

defined('ABSPATH') or die('No script kiddies please!');

class Page {

    public function __construct() {
        
    }
    

    public function render_page() {
        $chat_list = Functions::get_chats(20);
        $current = isset($_GET['chat']) ? sanitize_text_field($_GET['chat']) : '';
        $recipients = Functions::get_recipient($current);
        if(empty($recipients)){
            $recipients=[
                'name' => 'This person is not available.',
                'phone_number' => '',
                'avatar_url' => AUTWA_PLUGIN_ICON .'/user.png',
            ];
        }
        if (!empty($recipients) && isset($_GET['chat']) && $current !== '') {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'wpaw_chats',
                ['unread_count' => 0],
                ['client_id' => $current],
                ['%s'],
                ['%s']
            );
        }
        ?>
        <style>
            #wpbody-content{padding-bottom:0;}
            div#wpwrap {
                background: #111B21;
                color: #ffffff;
            }
        div#wpfooter { display: none;}
        </style>
        <div id="wpaw-main-container" class="wrap" style="display: flex; height: 100%;">
            <div id="wpaw-sidebar" style="">
                <div id="wpaw-sidebar-top">
                    <h3 class="text-white">Chats</h3>
                    <input type="text" id="search-chat" placeholder="Search chats..." style="width: 100%;border: 0px;color: rgb(255, 255, 255);background: #2A3942;margin-bottom: 10px;">
                </div>
                <div id="wpaw-sidebar-chat-list" style="display: block;margin-top: 0px;height: -webkit-fill-available;">
                    <div style="height: 100%; overflow-y: scroll;">
                        <table id="chat-list" class="widefat striped" style="width: 100%; border-collapse: collapse;">
                            <tbody>
                                <!-- Chat list will be loaded here via AJAX -->
                            </tbody>
                        </table>
                    </div>
                    <script>
                    jQuery(document).ready(function($) {
                        function loadChatList(query = '') {
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'wpaw_get_chat_list',
                                    search: query
                                },
                                dataType: 'json',
                                success: function(response) {
                                    var $tbody = $('#chat-list tbody');
                                    $tbody.empty();
                                    if (response.success && response.data.length) {
                                        $.each(response.data, function(i, chat) {
                                            var inChat = '<?php echo esc_js($current); ?>' === chat.client_id ? 'in-chat' : '';
                                            var row = `<tr data-chat-id="${chat.client_id}" data-chat-name="${chat.name}" class="sidebar-chat-item ${inChat}" style="cursor: pointer;">
                                                <td style="padding: 5px; text-align: left;">
                                                    <a href="?page=wp-auto-whats&chat=${chat.client_id}" style="text-decoration: none; color: inherit;">
                                                        <div style="display:flex; align-items:center;">
                                                            <img src="${chat.avatar_url}" alt="" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
                                                            <div class="sidebar-chat-info" style="flex-grow: 1; display: flex; flex-direction: column; justify-content: center;">
                                                                <span class="sidebar-chat-title">${chat.name}</span>
                                                                <div class="sidebar-chat-name" style="display: flex; align-items: center; justify-content: space-between;">
                                                                    <span class="sidebar-last-message" style="margin-left: 10px; color: #888;">${chat.last_message}</span>  
                                                                    <div class="last-message-time" style="display: flex; align-items: center; justify-content: flex-end;">
                                                                    ${chat.unread_count > 0 ? `<span class="sidebar-chat-unread" style="background-color: #25D366; color: white; padding: 2px 5px; border-radius: 10px;"></span>` : ''}
                                                                    </div>
                                                                    <div class="sidebar-chat-status" style="display: flex; align-items: center; margin-bottom: 5px;">
                                                                    <span class="last-message-date">${chat.last_message_time}</span>                                                                        
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </td>
                                            </tr>`;
                                            $tbody.append(row);
                                        });
                                    } else {
                                        $tbody.append('<tr><td>No chats found.</td></tr>');
                                    }
                                }
                            });
                        }

                        // Initial load
                        window.loadChatList = loadChatList;
                        loadChatList();

                        // Search filter
                        $('#search-chat').on('input', function() {
                            loadChatList($(this).val());
                        });
                    });
                    </script>
                </div>
                <div id="wpaw-sidebar-bottom" style="display: flex; justify-content: space-between;">
                    <input type="text" name="new_number" id="new-chat-number" placeholder="New Number" >
                    <button id="new-chat-button" class="button button-primary">Start New Chat</button>
                </div>
            </div>
            <div id="wpaw-chat" style="">
                <div id="wpaw-chat-header">
                    <?php if ($current): ?>
                        <div id="chat-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <div id="toggle-sidebar" style="">
                                <button id="toggle-sidebar-button" class="button button-secondary" style="background: none; border: none; padding: 0; margin-right: 10px;">
                                    <span style="display: inline-block; width: 24px; height: 24px;">
                                        <!-- Hamburger icon SVG -->
                                        <svg viewBox="0 0 24 24" width="24" height="24" fill="#fff" xmlns="http://www.w3.org/2000/svg">
                                            <rect y="4" width="24" height="3" rx="1.5"/>
                                            <rect y="10.5" width="24" height="3" rx="1.5"/>
                                            <rect y="17" width="24" height="3" rx="1.5"/>
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        <h3 class="text-white">Chat with <?php echo esc_html($recipients['name']);?></h3> 
                        <div id="messageContainer"></div>
                        <div id="chat-header-profile" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <div class="dropdown view-contact-dropdown" style="position: relative; display: inline-block;">
                                <div class="view-contact" 
                                     style="display: flex; align-items: center; cursor: pointer;" 
                                     data-contact-id="<?php echo esc_attr($recipients['phone_number']); ?>">
                                    <?php //echo esc_html($recipients['phone_number']); ?>
                                    <img src="<?php echo esc_url($recipients['avatar_url']); ?>" alt="" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
                                    <span style="margin-left: 5px;">&#9662;</span>
                                </div>
                                <div class="dropdown-content" style="display: none; position: absolute; right: 0; background: #fff; color: #222; min-width: 220px; box-shadow: 0 8px 16px rgba(0,0,0,0.2); z-index: 100; border-radius: 8px; padding: 10px;">
                                    <div style="text-align: center;">
                                        <img src="<?php echo esc_url($recipients['avatar_url']); ?>" alt="" width="60" height="60" style="border-radius: 50%; margin-bottom: 10px;">
                                        <div style="font-weight: bold;"><?php echo esc_html($recipients['name']); ?></div>
                                        <div style="color: #888;"><?php echo esc_html($recipients['phone_number']); ?></div>
                                    </div>
                                    <hr style="margin: 10px 0;">
                                        <button id="wpaw-reload-btn" class="button button-secondary" title="Reload"><span class="dashicons dashicons-update"></span></button>
                                        <button id="wpaw-cloud-download-btn" class="button button-secondary" title="Cloud Download"><span class="dashicons dashicons-cloud-upload"></span></button>
                                </div>
                            </div>
                            <script>
                            jQuery(function($){
                                // Toggle dropdown
                                $(document).on('click', '.view-contact', function(e){
                                    e.stopPropagation();
                                    var $dropdown = $(this).closest('.dropdown');
                                    $('.dropdown-content').not($dropdown.find('.dropdown-content')).hide();
                                    $dropdown.find('.dropdown-content').toggle();
                                });

                                // Hide dropdown when clicking outside
                                $(document).on('click', function(){
                                    $('.dropdown-content').hide();
                                });
                            });
                            </script>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php endif; ?>
                </div>
                <div id="wpaw-chat-message" style=" background: url('<?php echo AUTWA_PLUGIN_ICON .'/logo-aw-wb.png';?>'),#0b141a2b;
                            border: 1px solid #0b141a;background-blend-mode: overlay;
                                background-position: center; background-repeat: no-repeat;overflow-y: scroll;">
                    <?php if ($current): ?>
                        <div id="chat-box" style="max-height: calc(100% - 20px);/*height: 600px; max-height: calc(100vh - 200px);*/ overflow-y: auto;padding: 10px;" >
                        <!-- Messages loaded via AJAX -->
                    </div>
                    <?php else: ?>
                        
                        <div class="chat-not-select">
                            <p class="text-white">Please select a chat to start messaging.</p>
                            <br>
                            <p class="text-white">Or start a new chat by entering a phone number in the "New Number" field on the left sidebar.</p>
                            <br>
                            <div id="messageContainer" class="text-white"></div>
                         </div>
                    <?php endif; ?>
                </div>
                <div id="wpaw-chat-form">
                    <?php if ($current): 
                        $auto_whats_id = get_option('wpaw_auto_whats_id', '');
                    ?>
                        <div id="media-preview" style="margin-top:5px;display:none;gap:5px;flex-wrap:wrap;z-index: 2;position: absolute;bottom: 60px;background: #202C33;padding: 10px;border-radius: 10px 10px 0 0;"></div>
                    <form id="AUTWA-send-form" methode="post" enctype="multipart/form-data" >
                        <input type="hidden" name="auto_whats_id" value="<?php echo esc_attr($auto_whats_id); ?>">
                        <input type="hidden" name="client_id" value="<?php echo esc_attr($current); ?>">
                        <div class="mediaUpload" style="position: relative; display: inline-block;">
                            <button type="button" id="media-upload-btn">+</button>
                            <div class="dropdown-media-type" style="display: none; position: absolute;z-index: 10;background: #202C33;color: #fff;bottom: 50px;border-radius: 10px;">
                                <ul style="list-style: none; margin: 0; padding: 5px;">
                                    <li class="media-type" data-type="image" style="cursor:pointer; padding: 5px;">Photos</li>
                                    <li class="media-type" data-type="file" style="cursor:pointer; padding: 5px;">Documents</li>
                                </ul>
                            </div>
                            <input type="hidden" name="reply_to" id="reply-to-message-id" value="">
                            <input type="file" id="media-image-input" name="media_images[]" accept="image/*" multiple style="display:none;">
                            <input type="file" id="media-file-input" name="media_files[]" multiple style="display:none;">
                        </div>
                        <textarea name="message" rows="2" style="width: 100%;" placeholder="Type message..."></textarea>
                        <button type="submit" class="button button-primary" style="background: #005C4B;border: 0;">Send</button>
                    </form>

                    <?php else: ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <script>
        var WPAW_SOCKET_URL = "<?php echo AUTWA_WS_URL .'/ws?session='. AUTWA_SESSION_ID .'&events=session.status&events=message' ; ?>";
        </script>
        <script src="<?php echo AUTWA_PLUGIN_ASSETS; ?>/js/chat.js?ver=<?php echo time(); ?>"></script>
        <script src="<?php echo AUTWA_PLUGIN_ASSETS; ?>/js/chat-ajax.js?ver=<?php echo time(); ?>"></script>
        <?php
    }

}
