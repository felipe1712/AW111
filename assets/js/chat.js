jQuery(document).ready(function($){
    // Sidebar toggle for emobil
    $('#toggle-sidebar-button').on('click', function(e) {
        e.preventDefault();
        $('#wpaw-sidebar').toggleClass('wpaw-sidebar-open');
        if ($('#wpaw-sidebar').hasClass('wpaw-sidebar-open')) {
            $('body').append('<div id="wpaw-sidebar-backdrop" style="position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:999;background:rgba(0,0,0,0.2);"></div>');
            $('#wpaw-sidebar-backdrop').on('click', function() {
                $('#wpaw-sidebar').removeClass('wpaw-sidebar-open');
                $(this).remove();
            });
        } else {
            $('#wpaw-sidebar-backdrop').remove();
        }
    });

    $('#chat-list').on('click', 'tr', function(e) {
        e.preventDefault();
        var chatId = $(this).data('chat-id');
        var redirectUrl = '?page=wp-auto-whats&chat=' + chatId;
        window.location.href = redirectUrl;
    });

    // WebSocket connection
    if (typeof WPAW_SOCKET_URL !== 'undefined') {
        const socket = new WebSocket(WPAW_SOCKET_URL);
        const messageContainer = $('#messageContainer');
        socket.addEventListener('open', function(event) {
            socket.send('Hello Server!');
            $.post(ajaxurl, {action:'wpaw_get_session_status'}, function(resp){
                if(resp.success && resp.data) {
                    if (resp.data.status === 'WORKING' ) {
                        console.log(resp.data);
                        const connectedDiv = document.createElement('div');
                        connectedDiv.textContent = 'Connected';
                        messageContainer.html(connectedDiv);
                    }else if( resp.data.status === 'SCAN_QR_CODE') {
                        const qrCodeDiv = document.createElement('div');
                        qrCodeDiv.textContent = 'Scan QR Code';
                        messageContainer.html(qrCodeDiv);
                    } else {
                        const disconnectedDiv = document.createElement('div');
                        disconnectedDiv.textContent = 'Disconnected';
                        messageContainer.html(disconnectedDiv);
                    }
                }
            });
        });
        socket.addEventListener('message', function(event) {
            const messageDiv = document.createElement('div');
            if(event.data){
                try {
                    const dataObj = JSON.parse(event.data);
                    if(dataObj.event === 'message'){
                        messageDiv.textContent = 'New message';
                        // save by ajax action wpaw_save_message and pass dataObj.payload
                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'wpaw_save_message',
                                payload: dataObj.payload
                            },
                            success: function(response) {
                                window.AUTWALoadNewMessages && window.AUTWALoadNewMessages();
                                if (typeof window.loadChatList === 'function') {
                                    window.loadChatList();
                                }
                                console.log('Message saved:', response);
                            },
                            error: function(xhr, status, error) {
                                console.error('Error saving message:', error);
                                window.AUTWALoadNewMessages && window.AUTWALoadNewMessages();
                            }
                        });
                    } else if(dataObj.event === 'session.status') {
                        if (dataObj.payload && dataObj.payload.status === 'WORKING') {
                            messageDiv.textContent = 'Connected';
                        } else if (dataObj.payload && dataObj.payload.status === 'SCAN_QR_CODE') {
                            messageDiv.textContent = 'Scan QR Code';
                            alert('Please scan the QR code to connect.');
                        } else if (dataObj.payload && dataObj.payload.status === 'DISCONNECTED') {
                            messageDiv.textContent = 'Disconnected';
                            alert('Disconnected from WhatsApp. Please check your connection.');
                        } else {
                            messageDiv.textContent = 'Unknown status: ' + (dataObj.payload ? dataObj.payload.status : 'No payload');
                        }
                    }
                    
                    else {
                        messageDiv.textContent = '';
                    }
                } catch (e) {
                    messageDiv.textContent = 'Invalid message format';
                }
            }
            messageContainer.html(messageDiv);
        });
        socket.addEventListener('error', function(event) {
            console.error('WebSocket error observed:', event);
            const errorDiv = document.createElement('div');
            errorDiv.textContent = 'connection failed';
            messageContainer.append(errorDiv);
        });
        // // Listen for connection close
        // socket.addEventListener('close', function(event) {
        //     const closeDiv = document.createElement('div');
        //     closeDiv.textContent = 'Connection closed';
        //     messageContainer.append(closeDiv);
        // });
    }

    // Download Chat button handler
    var downloadOffset = 0;
    var downloadLimit = 5;
    $('#wpaw-cloud-download-btn').on('click', function(e) {
        e.preventDefault();
        var chatId = $('input[name="client_id"]').val();
        if (!chatId) {
            alert('No chat selected.');
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true).text('Downloading...');
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'wpaw_download_chat',
                client_id: chatId,
                offset: downloadOffset
            },
            success: function(response) {
                if (response.success) {
                    // alert('Chat downloaded and updated!');
                    window.AUTWALoadNewMessages && window.AUTWALoadNewMessages();
                    downloadOffset += downloadLimit;
                } else {
                    alert('Failed to download chat: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                alert('AJAX error: ' + error);
            },
            complete: function() {
                $btn.prop('disabled', false).text('Download Chat');
            }
        });
    });


    // Toggle dropdown menu for message actions
    $(document).on('click', '.message-actions-toggle', function(e) {
        e.preventDefault();
        var $toggle = $(this);
        var $menu = $toggle.siblings('.message-actions-menu');
        // Hide any other open menus
        $('.message-actions-menu').not($menu).hide();
        // Reset menu position
        $menu.css({top: '', bottom: '', left: '', right: ''});
        // Calculate available space below and above
        var toggleOffset = $toggle.offset();
        var toggleHeight = $toggle.outerHeight();
        var menuWidth = $menu.outerWidth();
        var menuHeight = $menu.outerHeight();
        var windowScrollTop = $(window).scrollTop();
        var windowHeight = $(window).height();
        var windowWidth = $(window).width();
        var spaceBelow = windowHeight - (toggleOffset.top - windowScrollTop + toggleHeight);
        var spaceAbove = toggleOffset.top - windowScrollTop;
        var spaceRight = windowWidth - (toggleOffset.left + $toggle.outerWidth());
        var spaceLeft = toggleOffset.left;
        // Default: show below, right-aligned
        var css = {right: 0, left: 'auto'};
        if ($toggle.closest('.from_client').length) {
            // If message is on the left, align left
            css.left = 0;
            css.right = 'auto';
        }
        if (spaceBelow < menuHeight && spaceAbove > menuHeight) {
            // Show above
            css.top = 'auto';
            css.bottom = toggleHeight + 'px';
        } else {
            // Show below
            css.top = toggleHeight + 'px';
            css.bottom = 'auto';
        }
        $menu.css(css).show();
        e.stopPropagation();
    });

    // Hide dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.message-actions-dropdown').length) {
            $('.message-actions-menu').hide();
        }
    });

    // Optional: Hide dropdown on scroll (for chat box)
    $('#chat-box').on('scroll', function() {
        $('.message-actions-menu').hide();
    });

    // Example: Action handlers (to be implemented)
    $(document).on('click', '.message-actions-menu a', function(e) {
        e.preventDefault();
        var $a = $(this);
        var action = $a.attr('class').replace('message-action-', '');
        var messageId = $a.data('message-id');
        // You can add switch/case here for each action
        // Example:
        // if (action === 'delete') { ... }
        // if (action === 'info') { ... }
        // etc.
        // For now, just close the menu
        $a.closest('.message-actions-menu').hide();
    });
});
