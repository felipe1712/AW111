jQuery(document).ready(function($) {
    // Message actions AJAX handlers (li version)
    $(document).on('click', '.message-actions-menu li', function(e) {
        e.preventDefault();
        var $li = $(this);
        var classes = $li.attr('class') || '';
        var action = '';
        // Find the first class that starts with message-action-
        classes.split(' ').forEach(function(cls) {
            if(cls.indexOf('message-action-') === 0) action = cls.replace('message-action-', '');
        });
        var messageId = $li.data('message-id');
        var $msgDiv = $li.closest('.from_me, .from_client');
        var chatId = $msgDiv.data('from') || $('input[name="client_id"]').val();
        var session = typeof AUTWA_SESSION_ID !== 'undefined' ? AUTWA_SESSION_ID : 'default';
        // Close menu
        $li.closest('.message-actions-menu').hide();
        // Action switch
        switch(action) {
            case 'delete':
                if(confirm('Delete this message?')) {
                    $.post(ajaxurl, {
                        action: 'wpaw_message_action',
                        action_type: 'delete',
                        message_id: messageId,
                        chat_id: chatId
                    }, function(resp) {
                        if(resp.success) {
                            $msgDiv.fadeOut(300, function(){ $(this).remove(); });
                        } else {
                            alert('Failed to delete message.');
                        }
                    }).fail(function(){ alert('Failed to delete message.'); });
                }
                break;
            case 'info':
                $.post(ajaxurl, {
                    action: 'wpaw_message_action',
                    action_type: 'info',
                    message_id: messageId,
                    chat_id: chatId
                }, function(resp) {
                    if(resp.success) {
                        $data = JSON.stringify(resp.data);
                        var info = 'Message Info:\n' +
                                   'Status: ' + resp.data.ackName + '\n' +
                                   'From: ' + resp.data._data.from.user + '\n' +
                                   'To: ' + resp.data._data.to.user + '\n' +
                                   'Timestamp: ' + new Date(resp.data.timestamp * 1000).toLocaleString() + '\n';
                        alert(info);
                    } else {
                        alert('Failed to fetch message info.');
                    }
                }).fail(function(){ alert('Failed to fetch message info.'); });
                break;
            case 'pin':
                $.post(ajaxurl, {
                    action: 'wpaw_message_action',
                    action_type: 'pin',
                    message_id: messageId,
                    chat_id: chatId
                }, function(resp) {
                    if(resp.success) {
                         window.AUTWALoadNewMessages && window.AUTWALoadNewMessages();
                        alert('Message pinned!');
                    } else {
                        alert('Failed to pin message.');
                    }
                }).fail(function(){ alert('Failed to pin message.'); });
                break;
            case 'unpin':
                $.post(ajaxurl, {
                    action: 'wpaw_message_action',
                    action_type: 'unpin',
                    message_id: messageId,
                    chat_id: chatId
                }, function(resp) {
                    if(resp.success) {
                        window.AUTWALoadNewMessages && window.AUTWALoadNewMessages();
                        alert('Message unpinned!');
                    } else {
                        alert('Failed to unpin message.');
                    }
                }).fail(function(){ alert('Failed to unpin message.'); });
                break;
            case 'react':
                // Show emoji picker near the message, attached to the message div
                var emojis = ['👍', '😂', '❤️', '😮', '😢', '🙏'];
                // Remove any existing picker for this message only
                $msgDiv.find('.emoji-picker').remove();
                var $picker = $('<div class="emoji-picker" style="position:absolute; z-index:9999; background:#fff; border:1px solid #ccc; padding:5px; border-radius:5px;"></div>');
                emojis.forEach(function(e) {
                    var $btn = $('<span style="font-size:22px; cursor:pointer; margin:2px;">'+e+'</span>');
                    $btn.on('click', function(ev) {
                        ev.stopPropagation();
                        $picker.remove();
                        handleEmoji(e);
                    });
                    $picker.append($btn);
                });
                // Allow custom emoji input
                var $input = $('<input type="text" maxlength="2" placeholder="Custom" style="width:40px; margin-left:5px;">');
                $input.on('keydown', function(ev) {
                    if(ev.key === 'Enter' && $input.val().trim()) {
                        $picker.remove();
                        handleEmoji($input.val().trim());
                    }
                });
                // $picker.append($input);
                // Position picker relative to the message div
                $msgDiv.css('position', 'relative');
                $picker.css({
                    top: $msgDiv.outerHeight() + 5,
                    left: 0
                });
                // Append picker to the message div
                $msgDiv.append($picker);
                // Remove picker on outside click
                setTimeout(function(){
                    $(document).one('click', function(e) {
                        if (!$(e.target).closest('.emoji-picker').length) {
                            $picker.remove();
                        }
                    });
                }, 10);
                // Handler function
                function handleEmoji(emoji) {
                    if(emoji) {
                        $.post(ajaxurl, {
                            action: 'wpaw_message_action',
                            action_type: 'react',
                            message_id: messageId,
                            chat_id: chatId,
                            reaction: emoji
                        }, function(resp) {
                            console.log(resp);
                            if(resp.success) {
                                // Remove existing reactions and add new one
                                $msgDiv.find('.message-reaction').remove();
                                $msgDiv.append('<span class="message-reaction">' + emoji + '</span>');
                            } else {
                                                              // Remove existing reactions and add new one
                                $msgDiv.find('.message-reaction').remove();
                                // $msgDiv.append('<span class="message-reaction">' + emoji + '</span>');
                            }
                        }).fail(function(){ alert('Failed to react.'); });
                    }
                }
                break;
            case 'forward':
                // Show popup with chat list to select target chat for forwarding
                var $popup = $('<div class="chat-forward-popup" style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.3);z-index:10000;display:flex;align-items:center;justify-content:center;"></div>');
                var $content = $('<div style="background:#fff;padding:20px;border-radius:8px;max-width:350px;width:100%;max-height:80vh;overflow:auto;box-shadow:0 2px 16px rgba(0,0,0,0.2);"></div>');
                $content.append('<div style="font-weight:bold;margin-bottom:10px;">Select chat to forward</div>');
                var $list = $('<div class="chat-list"></div>');
                $content.append($list);
                $popup.append($content);
                $('body').append($popup);

                // Close popup on outside click
                $popup.on('click', function(e){
                    if(e.target === this) $popup.remove();
                });

                // Show loading
                $list.html('<div>Loading...</div>');
                $.post(ajaxurl, { action: 'wpaw_get_chat_list' }, function(resp){
                    $list.empty();
                    if(resp.success && Array.isArray(resp.data) && resp.data.length) {
                        resp.data.forEach(function(chat){
                            var $item = $('<div style="display:flex;align-items:center;cursor:pointer;padding:7px 0;border-bottom:1px solid #eee;"></div>');
                            $item.append('<img src="'+chat.avatar_url+'" style="width:32px;height:32px;border-radius:50%;object-fit:cover;margin-right:10px;">');
                            $item.append('<div><div style="font-weight:500;">'+chat.name+'</div><div style="font-size:12px;color:#888;">'+(chat.last_message || '')+'</div></div>');
                            $item.on('click', function(){
                                $popup.remove();
                                targetChat = chat.client_id;
                                // Continue with forwarding
                                $.post(ajaxurl, {
                                    action: 'wpaw_message_action',
                                    action_type: 'forward',
                                    message_id: messageId,
                                    chat_id: chatId,
                                    target_chat_id: targetChat
                                }, function(resp) {
                                    if(resp.success) {
                                        window.AUTWADownloadMessages && window.AUTWADownloadMessages(0,5);
                                        window.AUTWALoadNewMessages && window.AUTWALoadNewMessages();
                                    } else {
                                        alert('Failed to forward.');
                                    }
                                }).fail(function(){ alert('Failed to forward.'); });
                            });
                            $list.append($item);
                        });
                    } else {
                        $list.html('<div>No chats found.</div>');
                    }
                }).fail(function(){
                    $list.html('<div>Failed to load chat list.</div>');
                });
                // var targetChat = null;
                // if(targetChat) {
                //     $.post(ajaxurl, {
                //         action: 'wpaw_message_action',
                //         action_type: 'forward',
                //         message_id: messageId,
                //         chat_id: chatId,
                //         target_chat_id: targetChat
                //     }, function(resp) {
                //         if(resp.success) {
                //             window.AUTWADownloadMessages && window.AUTWADownloadMessages(0,5);
                //              window.AUTWALoadNewMessages && window.AUTWALoadNewMessages();
                //             // alert('Message forwarded!');
                //         } else {
                //             alert('Failed to forward.');
                //         }
                //     }).fail(function(){ alert('Failed to forward.'); });
                // }
                break;
            case 'reply':
                // Get message text (try to find .message-text inside $msgDiv)
                var $msgText = $msgDiv.find('.message-text');
                var messageText = $msgText.length ? $msgText.text() : '';
                // Set reply_to input in send form
                var $replyInput = $('#reply-to-message-id');
                $replyInput.val(messageId);
                // Show reply preview in #media-preview
                var $mediaPreview = $('#media-preview');
                // Remove any existing reply-to preview
                $mediaPreview.find('.reply-to-preview').remove();
                // Create reply preview element
                var replyHtml = '<div class="reply-to-preview" style="background:#eee;color:#222;padding:6px 10px;border-radius:6px 6px 0 0;display:flex;align-items:center;gap:8px;margin-bottom:4px;position:relative;">'+
                    '<span style="font-weight:bold;">Replying to:</span>'+
                    '<span class="reply-to-text" style="flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;">'+$('<div>').text(messageText).html()+'</span>'+
                    '<button type="button" class="unselect-reply-btn" style="background:#fff;border:1px solid #ccc;border-radius:50%;width:18px;height:18px;line-height:16px;padding:0;font-size:13px;cursor:pointer;">&times;</button>'+
                    '</div>';
                $mediaPreview.prepend(replyHtml);
                $mediaPreview.css('display', 'flex');
                // Scroll to bottom of chat box (optional)
                var $chatBox = $('#chat-box');
                $chatBox.scrollTop($chatBox[0].scrollHeight);
                break;
            case 'copy':
                var text = $msgDiv.find('.message-text').text();
                if(navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(function(){
                        alert('Copied!');
                    }, function(){
                        alert('Failed to copy.');
                    });
                } else {
                    alert('Clipboard API not supported.');
                }
                break;
            default:
                alert('Action not implemented: ' + action);
        }
    });
    // Add handler for unselect reply button
    $(document).on('click', '.unselect-reply-btn', function(){
        $('#reply-to-message-id').val('');
        $(this).closest('.reply-to-preview').remove();
    });
});
