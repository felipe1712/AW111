<?php
namespace WpAutoWhats\Helpers;

defined('ABSPATH') or die('No script kiddies please!');

class DB {
     
    function __construct() {
        $this->create_tables();
    }
    
    /**
     * Create or update database tables for the plugin.
     *
     * @return void
     */

    public function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci';

        // Tables to create or update
        $tables = [

            $wpdb->prefix . 'wpaw_chats'  => [
                'columns' => [
                'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                'client_id' => 'varchar(30) DEFAULT NULL',
                'wpaw_id' => 'varchar(30) DEFAULT NULL',
                'name' => 'varchar(100) DEFAULT NULL',
                'phone_number' => 'varchar(20) DEFAULT NULL',
                'avatar_url' => 'varchar(255) DEFAULT NULL',
                'created_at' => 'datetime DEFAULT current_timestamp()',
                'short_name' => 'varchar(100) DEFAULT NULL',
                'type' => 'varchar(20) DEFAULT NULL',
                'is_me' => 'tinyint(1) DEFAULT NULL',
                'is_user' => 'tinyint(1) DEFAULT NULL',
                'is_group' => 'tinyint(1) DEFAULT NULL',
                'is_wpaw_contact' => 'tinyint(1) DEFAULT NULL',
                'is_my_contact' => 'tinyint(1) DEFAULT NULL',
                'is_blocked' => 'tinyint(1) DEFAULT NULL',
                'is_business' => 'tinyint(1) DEFAULT NULL',
                'last_message' => 'varchar(255) DEFAULT NULL',
                'unread_count' => 'int(11) DEFAULT 0',
                'last_message_time' => 'bigint(20) DEFAULT NULL',
                'update_at' => 'datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()',
                ],
                'primary_key' => 'id',
            ],
    
            $wpdb->prefix . 'wpaw_messages' => [
                'columns' => [
                    'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'client_id' => 'varchar(30) DEFAULT NULL',
                    'message_id' => 'varchar(60) DEFAULT NULL',
                    'message_type' => 'varchar(20) DEFAULT NULL',
                    'message_status' => 'varchar(20) DEFAULT NULL',
                    'from_id' => 'varchar(30) DEFAULT NULL',
                    'to_id' => 'varchar(30) DEFAULT NULL',
                    'message' => 'text DEFAULT NULL',
                    'from_me' => 'tinyint(1) DEFAULT NULL',
                    'created_at' => 'datetime DEFAULT current_timestamp()',
                    'has_media' => 'tinyint(1) DEFAULT NULL',
                    'media' => 'text DEFAULT NULL',
                    'media_name' => 'varchar(255) DEFAULT NULL',
                    'update_at' => 'datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()',
                    'timestamp' => 'bigint(20) DEFAULT NULL',
                    'source' => 'varchar(50) DEFAULT NULL',
                    'ack' => 'tinyint(1) DEFAULT NULL',
                    'ack_name' => 'varchar(50) DEFAULT NULL',
                    'vcards' => 'text DEFAULT NULL',
                    'replyTo' => 'varchar(60) DEFAULT NULL',
                    'replyToMessageId' => 'varchar(60) DEFAULT NULL',
                    'replyToMessage' => 'text DEFAULT NULL',
                    'notifyName' => 'varchar(200) DEFAULT NULL',
                    'reaction' => 'varchar(50) DEFAULT NULL',
                    'is_pinned' => 'tinyint(1) DEFAULT NULL',
                    'is_deleted' => 'tinyint(1) DEFAULT NULL',
                    'is_forwarded' => 'tinyint(1) DEFAULT NULL',
                    'forwarded_from' => 'varchar(60) DEFAULT NULL',
                    'forwarded_to' => 'varchar(60) DEFAULT NULL',
                    'forwarded_message_id' => 'varchar(60) DEFAULT NULL',
                    'forwarded_timestamp' => 'bigint(20) DEFAULT NULL',
                    'forwarded_message_type' => 'varchar(20) DEFAULT NULL',
                    'forwarded_message_status' => 'varchar(20) DEFAULT NULL',
                    'forwarded_message_source' => 'varchar(50) DEFAULT NULL',
                ],
                'primary_key' => 'id',
            ],
            $wpdb->prefix . 'wpaw_documents' => [
                'columns' => [
                    'id' => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'client_id' => 'varchar(30) DEFAULT NULL',
                    'message_id' => 'varchar(60) DEFAULT NULL',
                    'document_type' => 'varchar(50) DEFAULT NULL',
                    'document_name' => 'varchar(255) DEFAULT NULL',
                    'document_url' => 'text DEFAULT NULL',
                    'document_size' => 'bigint(20) DEFAULT NULL',
                    'document_mime' => 'varchar(100) DEFAULT NULL',
                    'document_caption' => 'text DEFAULT NULL',
                    'downloaded_url' => 'text DEFAULT NULL',
                    'download_status' => 'tinyint(1) DEFAULT NULL',
                    'created_at' => 'datetime DEFAULT current_timestamp()',
                    'update_at' => 'datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()',
                ],
                'primary_key' => 'id',
            ],
        ];

        foreach ($tables as $table_name => $table_schema) {
            $this->maybe_create_or_update_table($table_name, $table_schema, $charset_collate);
            error_log("Table $table_name created or updated.");
        }
    }
    
    private function maybe_create_or_update_table($table_name, $table_schema, $charset_collate) {
        global $wpdb;

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;

        if ($table_exists) {
            // Check existing columns
            $existing_columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name", ARRAY_A);
            $existing_columns = array_column($existing_columns, 'Field');

            // Alter table if columns are missing
            foreach ($table_schema['columns'] as $column_name => $column_definition) {
                if (!in_array($column_name, $existing_columns)) {
                    $wpdb->query("ALTER TABLE $table_name ADD COLUMN $column_name $column_definition");
                }
            }
        } else {
            // Create table if not exists
            $columns_sql = [];
            foreach ($table_schema['columns'] as $column_name => $column_definition) {
                $columns_sql[] = "$column_name $column_definition";
            }
            $columns_sql = implode(", ", $columns_sql);
            $primary_key = $table_schema['primary_key'];

            $sql = "CREATE TABLE $table_name (
                $columns_sql,
                PRIMARY KEY ($primary_key)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
      
    }
}
