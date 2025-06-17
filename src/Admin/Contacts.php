<?php
namespace WpAutoWhats\Admin;

defined('ABSPATH') or die('No script kiddies please!');

use  WpAutoWhats\Helpers\Functions;

class Contacts {

    public function __construct() {
        
    }
    
    // Callback function for the "Contact List" submenu page
    function wp_auto_whats_contact_list_page() {

        ?>
        <div class="wrap">
            <form method="post" style="margin-bottom: 20px;">
                <label for="reload_contacts">Contacts download from Whatsapp : </label>
                <button type="submit" name="reload_contacts" id="reload_contacts" class="button button-primary">Input Contacts</button>
            </form>
            <h1 style="margin-bottom: 20px;">Contact List</h1>
            <p style="margin-bottom: 20px;">Here you can manage your contact list.</p>
            <style>.wp-list-table-top { display: flex;justify-content: space-between;}</style>
            <table id="contacts-table" class="widefat fixed striped display">
                <thead>
                    <tr>
                        <th style="padding: 10px; text-align: left;">Avatar</th>
                        <th style="padding: 10px; text-align: left;">Name</th>
                        <th style="padding: 10px; text-align: left;">Phone Number</th>
                        <th style="padding: 10px; text-align: left;">Actions</th>
                    </tr>
                </thead>
            </table>
            <div id="contact-modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 0, 0, 0.4);">
                <div style="background-color: #fff; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 50%; border-radius: 8px; position: relative;">
                    <p>Pleace wait...</p>
                </div>
            </div>
            <script src="<?php echo AUTWA_PLUGIN_ASSETS . '/js/datatables.min.js'; ?>"></script>
            <script>
                jQuery(document).ready(function($) {
                    // First define your filter dropdown HTML
                    var filterDropdown = `
                        <div id="filter-wrapper" style="margin-bottom: 10px;">
                            <label for="filter-type">Filter By:</label>
                            <select id="filter-type" class="postform" style="margin-left: 5px;">
                                <option value="">All</option>
                                <option value="is_me">Me</option>
                                <option value="is_user">User</option>
                                <option value="is_group">Group</option>
                                <option value="is_wpaw_contact">WA Contact</option>
                                <option value="is_my_contact">My Contact</option>
                                <option value="is_blocked">Blocked</option>
                                <option value="is_business">Business</option>
                            </select>
                        </div>
                    `;

                    // Initialize DataTable
                    var table = $('#contacts-table').DataTable({
                        processing: true,
                        serverSide: true,
                        ajax: {
                            url: ajaxurl,
                            type: "POST",
                            data: function(d) {
                                d.action = "fetch_contacts";
                                d.filter_type = $('#filter-type').val(); // send selected filter
                            }
                        },
                        columns: [
                            { data: "avatar", render: function(data) {
                                return '<img src="' + data + '" alt="Avatar" width="50" height="50" style="border-radius: 50%;">';
                            }},
                            { data: "name" },
                            { data: "phone_number" },
                            { data: "actions", orderable: false, searchable: false }
                        ],
                        language: {
                            emptyTable: "No contacts available",
                            loadingRecords: "Loading...",
                            processing: "Processing...",
                            paginate: {
                                first: "«",
                                last: "»",
                                next: "›",
                                previous: "‹"
                            }
                        },
                        dom: '<"wp-list-table-top"lf>rt<"wp-list-table-bottom"ip>',
                        order: [[1, "asc"]]
                    });

                    // Inject the filter dropdown above the table after initialization
                    $('#contacts-table_wrapper .wp-list-table-top').prepend(filterDropdown);

                    // Reload table on filter change
                    $(document).on('change', '#filter-type', function() {
                        table.ajax.reload();
                    });

                    $(document).on('click', '.view-contact', function() {
                        const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
                        var contactId = $(this).data('contact-id');
                        // Show the modal
                        $('#contact-modal').fadeIn();
                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'wpaw_view_contact',
                                contact_id: contactId
                            },
                            success: function(response) {
                                var contact = response.data;
                                console.log(contact);
                                var modalHtml = `
                                <div style="background-color: #fff; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 50%; border-radius: 8px; position: relative;">
                                    <span id="contact-modal-close" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                                    <h2 style="margin-top: 0;">Contact Details</h2>
                                    ${contact.image_link ? `<img src="${contact.image_link}" alt="Avatar" width="100" height="100" style="border-radius: 50%;">` : ''}
                                    <p><strong>Name:</strong> ${contact.name}</p>
                                    <p><strong>Phone Number:</strong> ${contact.number}</p>
                                    ${contact.isBusiness ? `<p><strong>Business:</strong> Yes</p>` : ''}
                                    ${contact.businessProfile?.address ? `<p><strong>Address:</strong> ${contact.businessProfile.address}</p>` : ''}
                                    ${contact.businessProfile?.email ? `<p><strong>Email:</strong> ${contact.businessProfile.email}</p>` : ''}
                                    ${contact.businessProfile?.website?.[0]?.url ? `<p><strong>Website:</strong> ${contact.businessProfile.website[0].url}</p>` : ''}
                                    <a href="<?php echo admin_url('admin.php');?>?page=wp-auto-whats&chat=${contact.id}" target="_blank" style="display: inline-block; margin-top: 10px; padding: 10px 20px; background-color: #0073aa; color: #fff; text-decoration: none; border-radius: 4px;">Send Message</a>
                                </div>
                                `;
                                $('#contact-modal').html(modalHtml);



                                // Close the modal on clicking the close button
                                $('#contact-modal-close').on('click', function() {
                                    $('#contact-modal').fadeOut(function() {
                                        $(this).html('<div style="background-color: #fff; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 50%; border-radius: 8px; position: relative;"><p>Pleace wait...</p></div>');
                                    });
                                });

                                // Close the modal on clicking outside the modal content
                                $(window).on('click', function(event) {
                                    if ($(event.target).is('#contact-modal')) {
                                        $('#contact-modal').fadeOut(function() {
                                            $(this).html('<div style="background-color: #fff; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 50%; border-radius: 8px; position: relative;"><p>Pleace wait...</p></div>');
                                        });
                                    }
                                });
                            },
                            error: function() {
                                alert('Failed to load contact details.');
                            }
                        });
                    }); 
                                       
                    $(document).on('click', '.reload-contact', function() {
                        const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
                        var contactId = $(this).data('contact-id');
                        // Show the modal
                        $('#contact-modal').fadeIn();
                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'wpaw_reload_contact',
                                contact_id: contactId
                            },
                            success: function(response) {
                                var contact = response.data;
                                table.ajax.reload();
                                var modalHtml = `
                                <div style="background-color: #fff; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 50%; border-radius: 8px; position: relative;">
                                    <span id="contact-modal-close" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                                    <h2 style="margin-top: 0;">Contact Details</h2>
                                    ${contact.image_link ? `<img src="${contact.image_link}" alt="Avatar" width="100" height="100" style="border-radius: 50%;">` : ''}
                                    <p><strong>Name:</strong> ${contact.name}</p>
                                    <p><strong>Phone Number:</strong> ${contact.number}</p>
                                    ${contact.isBusiness ? `<p><strong>Business:</strong> Yes</p>` : ''}
                                    ${contact.businessProfile?.address ? `<p><strong>Address:</strong> ${contact.businessProfile.address}</p>` : ''}
                                    ${contact.businessProfile?.email ? `<p><strong>Email:</strong> ${contact.businessProfile.email}</p>` : ''}
                                    ${contact.businessProfile?.website?.[0]?.url ? `<p><strong>Website:</strong> ${contact.businessProfile.website[0].url}</p>` : ''}
                                    <a href="<?php echo admin_url('admin.php');?>?page=wp-auto-whats&chat=${contact.id}" target="_blank" style="display: inline-block; margin-top: 10px; padding: 10px 20px; background-color: #0073aa; color: #fff; text-decoration: none; border-radius: 4px;">Send Message</a>
                                </div>
                                `;
                                $('#contact-modal').html(modalHtml);



                                // Close the modal on clicking the close button
                                $('#contact-modal-close').on('click', function() {
                                    $('#contact-modal').fadeOut(function() {
                                        $(this).html('<div style="background-color: #fff; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 50%; border-radius: 8px; position: relative;"><p>Pleace wait...</p></div>');
                                    });
                                });

                                // Close the modal on clicking outside the modal content
                                $(window).on('click', function(event) {
                                    if ($(event.target).is('#contact-modal')) {
                                        $('#contact-modal').fadeOut(function() {
                                            $(this).html('<div style="background-color: #fff; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 50%; border-radius: 8px; position: relative;"><p>Pleace wait...</p></div>');
                                        });
                                    }
                                });
                            },
                            error: function() {
                                alert('Failed to load contact details.');
                            }
                        });
                    });

                    $(document).on('click', '#reload_contacts', function(event) {
                        event.preventDefault();
                        const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
                        // Show the modal
                        $('#contact-modal').fadeIn(function() {
                            $(this).html('<div style="background-color: #fff; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 50%; border-radius: 8px; position: relative;"><p>Pleace wait..., Contact Downloading...</p></div>');
                        });
                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'wpaw_download_contacts',
                                _ajax_nonce: '<?php echo wp_create_nonce("wpaw_contact_dl_nonce"); ?>'
                            },
                            success: function(response) {
                                var contact = response.data;
                                table.ajax.reload();
                                $('#contact-modal').fadeOut(function() {
                                    $(this).html('<div style="background-color: #fff; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 50%; border-radius: 8px; position: relative;"><p>Pleace wait...</p></div>');
                                });
                            },
                            error: function() {
                               // alert('Failed to load contact details.');
                                // $('#contact-modal').html('<div style="background-color: #fff; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 50%; border-radius: 8px; position: relative;"><p>Failed to load contact details.</p></div>');

                            }
                        });
                        
                        // Close the modal on clicking outside the modal content
                        $(window).on('click', function(event) {
                            if ($(event.target).is('#contact-modal')) {
                                $('#contact-modal').fadeOut(function() {
                                    $(this).html('<div style="background-color: #fff; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 50%; border-radius: 8px; position: relative;"><p>Pleace wait...</p></div>');
                                });
                            }
                        });
                    });

                });
            </script>
        </div>
        <?php
    }




}