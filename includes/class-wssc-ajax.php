@@ .. @@
     public function delete_image() {
         // Check user permissions
         if (!current_user_can('manage_options')) {
             wp_send_json_error('Unauthorized');
         }
 
         // Verify nonce
         // Accept either _ajax_nonce (from wp_localize_script) or explicit nonce
         $nonce = isset($_POST['_ajax_nonce']) ? $_POST['_ajax_nonce'] : (isset($_POST['nonce']) ? $_POST['nonce'] : '');
         if (!wp_verify_nonce($nonce, 'wssc_admin_nonce')) {
             wp_send_json_error('Invalid nonce');
         }
 
         global $wpdb;
         $table = $wpdb->prefix . 'wssc_product_images';
         $id = intval($_POST['id']);
 
         // Get image info before deletion
         $image = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
         
         if (!$image) {
             wp_send_json_error('Image not found');
         }
+        
+        // Only allow deletion of confirmed images (those with orders)
+        if ($image->status !== 'confirmed' || empty($image->order_id)) {
+            wp_send_json_error('Can only delete confirmed images with valid orders');
+        }
 
         // Delete from database
         $result = $wpdb->delete($table, ['id' => $id], ['%d']);
 
         if ($result !== false) {
             // Delete physical file
             if (file_exists($image->image_path)) {
                 unlink($image->image_path);
             }
             
             wp_send_json_success(['message' => 'Image deleted successfully']);
         } else {
             wp_send_json_error('Failed to delete image');
         }
     }