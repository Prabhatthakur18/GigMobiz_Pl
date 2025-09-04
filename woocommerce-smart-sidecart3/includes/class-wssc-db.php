<?php
if (!defined('ABSPATH')) exit;

class WSSC_DB {
    public static function create_table() {
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'wssc_bulk_requests';
            $charset = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE IF NOT EXISTS $table (
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
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) $charset;";
            
            require_once ABSPATH.'wp-admin/includes/upgrade.php';
            dbDelta($sql);
            
            // Add new columns if they don't exist (for existing installations)
            // Use proper MySQL syntax for checking column existence
            $columns = $wpdb->get_col("DESCRIBE $table");
            
            if (!in_array('email', $columns)) {
                $wpdb->query("ALTER TABLE $table ADD COLUMN email VARCHAR(255) AFTER phone");
            }
            if (!in_array('status', $columns)) {
                $wpdb->query("ALTER TABLE $table ADD COLUMN status VARCHAR(20) DEFAULT 'pending' AFTER message");
            }
            if (!in_array('product_ids', $columns)) {
                $wpdb->query("ALTER TABLE $table ADD COLUMN product_ids TEXT AFTER product_id");
            }
            if (!in_array('mobile_brand', $columns)) {
                $wpdb->query("ALTER TABLE $table ADD COLUMN mobile_brand VARCHAR(255) AFTER product_ids");
            }
            if (!in_array('mobile_model', $columns)) {
                $wpdb->query("ALTER TABLE $table ADD COLUMN mobile_model VARCHAR(255) AFTER mobile_brand");
            }
        } catch (Exception $e) {
            error_log('WSSC DB Error creating bulk requests table: ' . $e->getMessage());
        }
    }
    
    public static function create_image_table() {
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'wssc_product_images';
            $charset = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE IF NOT EXISTS $table (
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
        } catch (Exception $e) {
            error_log('WSSC DB Error creating images table: ' . $e->getMessage());
        }
    }
}