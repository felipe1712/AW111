<?php
namespace WpAutoWhats\Admin;

defined('ABSPATH') or die('No script kiddies please!');

use WpAutoWhats\Helpers\SettingHelper;

class Setting {

    public function __construct() {
        
    }

    public function wp_auto_whats_setting_page(){
        ?>
        <div class="wrap">
            <h1 style="margin-bottom: 20px;">Whatsapp Settings</h1>
            <p style="margin-bottom: 20px;">Here you can manage your Settings.</p>

            <form id="wpaw-settings-form" method="post" style="margin-bottom: 20px;">
                <?php
                    $api_url = get_option('wpaw_api_url');
                    $url_type = get_option('wpaw_url_type');
                    $api_sessions = get_option('wpaw_api_sessions');
                    ?>
                <label for="urlType">Select URL type </label>             
                <select name="url_type" id="urlType">
                    <option value="https" <?php selected($url_type, 'https'); ?>>HTTPS</option>
                    <option value="http" <?php selected($url_type, 'http'); ?>>HTTP</option>
                </select>
                <label for="apiUrl">Your api link</label>
                <input type="text" name="api_url" id="apiUrl" placeholder="example.com" value="<?php echo esc_attr($api_url); ?>">
                <button type="submit" class="button button-primary">Save</button>
                 <span id="wpaw-settings-msg"></span>
            </form>
            <br>
            <p>Setup sessions</p>
            <form id="wpaw-sessions-form" method="post" style="margin-bottom: 20px;">
                <label for="urlType">Your api sessions(E.g.: default ) </label>
                <input type="text" name="api_sessions" id="apiSessions" placeholder="default" value="<?php echo esc_attr($api_sessions); ?>">
                <button type="submit" class="button button-primary">Save</button>
                <span id="wpaw-sessions-msg"></span>
            </form>
            <br>
            <p>Session Management</p>
            <div id="wpaw-session-actions" style="margin-bottom: 20px;">
                <button type="button" class="button" id="wpaw-session-check">Check Connection</button>
                <button type="button" class="button" id="wpaw-session-create" style="display:none;">Create Session</button>
                <button type="button" class="button" id="wpaw-session-info" style="display:none;">Get Info</button>
                <button type="button" class="button" id="wpaw-session-update" style="display:none;">Update Session</button>
                <button type="button" class="button" id="wpaw-session-delete" style="display:none;">Delete Session</button>
                <button type="button" class="button" id="wpaw-session-me" style="display:none;">Get Me</button>
                <button type="button" class="button" id="wpaw-session-start" style="display:none;">Start</button>
                <button type="button" class="button" id="wpaw-session-stop" style="display:none;">Stop</button>
                <button type="button" class="button" id="wpaw-session-logout" style="display:none;">Logout</button>
                <button type="button" class="button" id="wpaw-session-restart" style="display:none;">Restart</button>
                <span id="wpaw-session-action-msg" style="margin-left:10px;"></span>
            </div>
            <div id="wpaw-connection-details"></div>
        </div>
        <script type="text/javascript">
        jQuery(document).ready(function($){
            // Save settings via AJAX
            $('#wpaw-settings-form').on('submit', function(e){
                e.preventDefault();
                var data = {
                    action: 'wpaw_save_settings',
                    api_url: $('#apiUrl').val(),
                    url_type: $('#urlType').val(),
                    _ajax_nonce: '<?php echo wp_create_nonce("wpaw_settings_nonce"); ?>'
                };
                $('#wpaw-settings-msg').text('Saving...');
                $.post(ajaxurl, data, function(response){
                    $('#wpaw-settings-msg').text(response.data ? response.data : response.message);
                });
            });
                        
            $('#wpaw-sessions-form').on('submit', function(e){
                e.preventDefault();
                var data = {
                    action: 'wpaw_save_sessions',
                    api_sessions: $('#apiSessions').val(),
                    _ajax_nonce: '<?php echo wp_create_nonce("wpaw_sessions_nonce"); ?>'
                };
                $('#wpaw-sessions-msg').text('Saving...');
                $.post(ajaxurl, data, function(response){
                    if (response.success) {
                        $('#wpaw-sessions-msg').text('Sessions saved successfully');
                        sessionActionInternal('wpaw_session_update');
                    } else {
                        $('#wpaw-sessions-msg').text('Error: ' + (response.data ? response.data : response.message));
                    }
                });
            });

            // Session management actions (internal AJAX)
            function sessionActionInternal(action, cb) {
                $('#wpaw-session-action-msg').text('Processing...');
                $('#wpaw-connection-details').html('');
                $.post(ajaxurl, {action: action, _ajax_nonce: '<?php echo wp_create_nonce("wpaw_connection_nonce"); ?>'}, function(resp) {
                    if(resp.success) {
                        $('#wpaw-session-action-msg').text('Success');
                        $('#wpaw-connection-details').html(resp.data.details ? resp.data.details : '');
                        console.log(resp.data);
                        if(cb) cb(resp);
                    } else {
                        var msg = resp.data && resp.data.error ? resp.data.error : (resp.data || 'Error');
                        if(action === 'wpaw_check_connection') {
                            updateSessionButtons('UNKNOWN');
                        }   
                        $('#wpaw-session-action-msg').text('Error: ' + msg);
                        if(cb) cb(false);
                    }
                });
            }
            $('#wpaw-session-check').on('click', function(){ sessionActionInternal('wpaw_check_connection'); });
            $('#wpaw-session-create').on('click', function(){ sessionActionInternal('wpaw_session_create'); });
            $('#wpaw-session-info').on('click', function(){ sessionActionInternal('wpaw_session_info'); });
            $('#wpaw-session-update').on('click', function(){ sessionActionInternal('wpaw_session_update'); });
            $('#wpaw-session-delete').on('click', function(){ sessionActionInternal('wpaw_session_delete'); });
            $('#wpaw-session-me').on('click', function(){ sessionActionInternal('wpaw_session_me'); });
            $('#wpaw-session-start').on('click', function(){ sessionActionInternal('wpaw_session_start'); });
            $('#wpaw-session-stop').on('click', function(){ sessionActionInternal('wpaw_session_stop'); });
            $('#wpaw-session-logout').on('click', function(){ sessionActionInternal('wpaw_session_logout'); });
            $('#wpaw-session-restart').on('click', function(){ sessionActionInternal('wpaw_session_restart'); });

            // Show/hide session buttons based on status
            function updateSessionButtons(status) {
                // Hide all first
                $('#wpaw-session-actions button').hide();
                if (status === 'WORKING') {
                    $('#wpaw-session-info,#wpaw-session-update,#wpaw-session-delete,#wpaw-session-me,#wpaw-session-stop,#wpaw-session-logout,#wpaw-session-restart').show();
                } else if (status === 'STOPPED') {
                    $('#wpaw-session-info,#wpaw-session-update,#wpaw-session-delete,#wpaw-session-start').show();
                } else if (status === 'STARTING') {
                    $('#wpaw-session-info,#wpaw-session-update,#wpaw-session-delete,#wpaw-session-stop').show();
                } else if(status === 'SCAN_QR_CODE'){
                    $('#wpaw-session-info').show();
                } else if (status === 'FAILED' || status === 'UNKNOWN') {
                    $('#wpaw-session-create').show();
                } else {
                    $('#wpaw-session-check').show();
                    $('#wpaw-session-create').show();
                }
            }
            // Fetch status from server (via AJAX to PHP helper)
            function fetchSessionStatus() {
                $.post(ajaxurl, {action:'wpaw_get_session_status'}, function(resp){
                    if(resp.success && resp.data) {
                        updateSessionButtons(resp.data.status);
                        if (resp.data.status === 'WORKING'|| resp.data.status === 'SCAN_QR_CODE') {
                            $html_data = sessionActionInternal('wpaw_check_connection');
                            if ($html_data && $html_data.data) {
                                $('#wpaw-connection-details').html($html_data.data.details);
                            }
                        }
                    } else {
                        updateSessionButtons('UNKNOWN');
                    }
                });
            }
            fetchSessionStatus();

            // After any session action, update status
            function afterSessionAction() {
                setTimeout(fetchSessionStatus, 1000);
            }
            $('#wpaw-session-actions button').on('click', afterSessionAction);

        });
        </script>
        <?php
    }
}