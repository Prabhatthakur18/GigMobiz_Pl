<?php
if (!defined('ABSPATH')) exit;

class WSSC_DB {
    public static function create_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'wssc_bulk_requests';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT NOT NULL,
            product_ids TEXT,
            mobile_brand VARCHAR(255),
            mobile_model VARCHAR(255),
            name VARCHAR(255),
            phone VARCHAR(50),
            email VARCHAR(255),
            quantity INT,
            message TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            created_at DATETIME
        ) $charset;";
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        // Add new columns if they don't exist (for existing installations)
        $wpdb->query("ALTER TABLE $table ADD COLUMN IF NOT EXISTS email VARCHAR(255) AFTER phone");
        $wpdb->query("ALTER TABLE $table ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'pending' AFTER message");
        $wpdb->query("ALTER TABLE $table ADD COLUMN IF NOT EXISTS product_ids TEXT AFTER product_id");
        $wpdb->query("ALTER TABLE $table ADD COLUMN IF NOT EXISTS mobile_brand VARCHAR(255) AFTER product_ids");
        $wpdb->query("ALTER TABLE $table ADD COLUMN IF NOT EXISTS mobile_model VARCHAR(255) AFTER mobile_brand");
    }
    
    public static function create_image_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'wssc_product_images';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT NOT NULL,
            order_id BIGINT,
            mobile_brand VARCHAR(255),
            mobile_model VARCHAR(255),
            image_path VARCHAR(500) NOT NULL,
            image_name VARCHAR(255) NOT NULL,
            image_size INT,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_product_id (product_id),
            INDEX idx_order_id (order_id)
        ) $charset;";
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}