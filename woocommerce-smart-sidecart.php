@@ .. @@
// Initialize the plugin
new WSSC_Plugin();

+// Add additional hooks for order processing
+add_action('woocommerce_checkout_order_processed', function($order_id, $posted_data, $order) {
+    error_log('WSSC Order Processed Hook - Order ID: ' . $order_id);
+    
+    // Get all cart items and check for image IDs
+    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
+        if (!empty($cart_item['wssc_image_id'])) {
+            error_log('WSSC Order Processed - Found image ID in cart: ' . $cart_item['wssc_image_id']);
+            
+            // Update image record
+            global $wpdb;
+            $table = $wpdb->prefix . 'wssc_product_images';
+            
+            $result = $wpdb->update(
+                $table,
+                [
+                    'order_id' => $order_id,
+                    'status' => 'confirmed'
+                ],
+                ['id' => $cart_item['wssc_image_id']],
+                ['%d', '%s'],
+                ['%d']
+            );
+            
+            if ($result !== false) {
+                error_log('WSSC Order Processed - Successfully updated image ID ' . $cart_item['wssc_image_id'] . ' with order ID ' . $order_id);
+            } else {
+                error_log('WSSC Order Processed - Failed to update image: ' . $wpdb->last_error);
+            }
+        }
+    }
+}, 10, 3);
+
+// Additional hook for when order status changes to processing/completed
+add_action('woocommerce_order_status_changed', function($order_id, $old_status, $new_status) {
+    if (in_array($new_status, ['processing', 'completed'])) {
+        error_log('WSSC Order Status Changed - Order ID: ' . $order_id . ' Status: ' . $new_status);
+        
+        // Ensure images are confirmed for this order
+        global $wpdb;
+        $table = $wpdb->prefix . 'wssc_product_images';
+        
+        $result = $wpdb->update(
+            $table,
+            ['status' => 'confirmed'],
+            ['order_id' => $order_id],
+            ['%s'],
+            ['%d']
+        );
+        
+        if ($result !== false) {
+            error_log('WSSC Order Status Changed - Confirmed ' . $result . ' images for order ' . $order_id);
+        }
+    }
+}, 10, 3);
+
// Hook into the mobile selector plugin compatibility
add_action('init', function() {
    // Check if the mobile selector functions exist
    if (function_exists('mms_add_cart_item_data')) {
        // Ensure our plugin uses the same cart item data structure
        add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id, $variation_id) {
            // This ensures compatibility with the mobile selector plugin
            if (isset($_POST['mobile_brand']) && isset($_POST['mobile_model'])) {
                $cart_item_data['mobile_brand'] = sanitize_text_field($_POST['mobile_brand']);
                $cart_item_data['mobile_model'] = sanitize_text_field($_POST['mobile_model']);
                $cart_item_data['unique_key'] = md5(microtime().rand());
            }
+            
+            // Also handle image ID
+            if (isset($_POST['wssc_image_id']) && !empty($_POST['wssc_image_id'])) {
+                $cart_item_data['wssc_image_id'] = sanitize_text_field($_POST['wssc_image_id']);
+                error_log('WSSC Compatibility - Added image ID: ' . $_POST['wssc_image_id']);
+            }
+            
            return $cart_item_data;
        }, 5, 3); // Lower priority to run before other plugins
    }
});