@@ .. @@
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
+            status VARCHAR(20) DEFAULT 'pending',
             uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
             INDEX idx_product_id (product_id),
-            INDEX idx_order_id (order_id)
+            INDEX idx_order_id (order_id),
+            INDEX idx_status (status)
         ) $charset;";
         require_once ABSPATH.'wp-admin/includes/upgrade.php';
         dbDelta($sql);
+        
+        // Add status column to existing tables if it doesn't exist
+        $wpdb->query("ALTER TABLE $table ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'pending' AFTER image_size");
     }