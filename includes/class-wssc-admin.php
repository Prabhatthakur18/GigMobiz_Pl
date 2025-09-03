@@ .. @@
     public function image_selected_page() {
         // Handle repair action
         if (isset($_GET['action']) && $_GET['action'] === 'repair_upload_dir') {
             $this->repair_upload_directory();
         }
         
         global $wpdb;
         $table = $wpdb->prefix . 'wssc_product_images';
-        $results = $wpdb->get_results("SELECT * FROM $table ORDER BY uploaded_at DESC");
+        // Only show confirmed images (images from completed orders)
+        $results = $wpdb->get_results("SELECT * FROM $table WHERE status = 'confirmed' AND order_id IS NOT NULL ORDER BY uploaded_at DESC");
         ?>
         <div class="wrap">
             <h1>📸 Image Selected Management</h1>
-            <p class="description">Manage images uploaded by customers for mobile selector products.</p>
+            <p class="description">Manage images uploaded by customers for mobile selector products. Images appear here only after order confirmation.</p>
             
             <!-- Debug Information -->
             <div class="wssc-debug-info" style="background: #f0f6fc; padding: 15px; border-left: 4px solid #0073aa; margin-bottom: 20px; border-radius: 4px;">
                 <h3 style="margin-top: 0;">🔧 Upload Directory Status</h3>
                 <?php
                 global $wpdb;
                 $mobile_selector = new WSSC_Mobile_Selector();
                 $upload_status = $mobile_selector->check_upload_directory_status();
+                
+                // Get statistics
+                $pending_images = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'");
+                $confirmed_images = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'confirmed'");
                 ?>
                 <table style="width: 100%; border-collapse: collapse;">
                     <tr>
                         <td style="padding: 5px; font-weight: bold;">Directory Path:</td>
                         <td style="padding: 5px;"><?php echo esc_html($upload_status['full_path']); ?></td>
                     </tr>
                     <tr>
                         <td style="padding: 5px; font-weight: bold;">Directory Exists:</td>
                         <td style="padding: 5px;"><?php echo $upload_status['directory_exists'] ? '✅ Yes' : '❌ No'; ?></td>
                     </tr>
                     <tr>
                         <td style="padding: 5px; font-weight: bold;">Directory Writable:</td>
                         <td style="padding: 5px;"><?php echo $upload_status['directory_writable'] ? '✅ Yes' : '❌ No'; ?></td>
                     </tr>
                     <tr>
                         <td style="padding: 5px; font-weight: bold;">.htaccess File:</td>
                         <td style="padding: 5px;"><?php echo $upload_status['htaccess_exists'] ? '✅ Yes' : '❌ No'; ?></td>
                     </tr>
                     <tr>
                         <td style="padding: 5px; font-weight: bold;">Index File:</td>
                         <td style="padding: 5px;"><?php echo $upload_status['index_exists'] ? '✅ Yes' : '❌ No'; ?></td>
                     </tr>
                     <tr>
                         <td style="padding: 5px; font-weight: bold;">URL Path:</td>
                         <td style="padding: 5px;"><?php echo esc_html($upload_status['url_path']); ?></td>
                     </tr>
+                    <tr>
+                        <td style="padding: 5px; font-weight: bold;">Pending Images:</td>
+                        <td style="padding: 5px;"><?php echo esc_html($pending_images); ?> (waiting for order confirmation)</td>
+                    </tr>
+                    <tr>
+                        <td style="padding: 5px; font-weight: bold;">Confirmed Images:</td>
+                        <td style="padding: 5px;"><?php echo esc_html($confirmed_images); ?> (shown below)</td>
+                    </tr>
                 </table>
                 
                 <?php if (!$upload_status['directory_exists'] || !$upload_status['directory_writable']): ?>
                     <p style="color: #d63638; margin-top: 10px;">
                         <strong>⚠️ Warning:</strong> Upload directory issues detected. 
                         <a href="<?php echo admin_url('admin.php?page=wssc-image-selected&action=repair_upload_dir'); ?>" class="button button-secondary">Repair Upload Directory</a>
                     </p>
                 <?php endif; ?>
             </div>
             
             <?php if (empty($results)): ?>
                 <div class="no-requests">
-                    <p>No uploaded images found.</p>
+                    <p>No confirmed images found. Images will appear here after customers place their orders.</p>
                 </div>
             <?php else: ?>
                 <div class="wssc-image-table">
                     <table class="widefat fixed striped">
                         <thead>
                             <tr>
                                 <th style="width: 50px;">ID</th>
                                 <th>Product</th>
                                 <th>Mobile Info</th>
                                 <th>Image</th>
                                 <th>Order ID</th>
                                 <th style="width: 120px;">Upload Date</th>
+                                <th style="width: 120px;">Order Date</th>
                                 <th style="width: 120px;">Actions</th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php foreach ($results as $row):
                                 $product = wc_get_product($row->product_id);
                                 $product_name = $product ? $product->get_name() : "Product ID: {$row->product_id} (Deleted)";
                                 
                                 // Generate proper image URL for display and download
                                 $upload_dir = wp_upload_dir();
                                 $image_url = $upload_dir['baseurl'] . '/wssc-images/' . $row->image_name;
                                 
                                 // For download, use the stored file path if available, otherwise generate URL
                                 $download_url = $image_url;
                                 if (!empty($row->image_path) && file_exists($row->image_path)) {
                                     $download_url = $image_url; // Use URL for download
                                 }
+                                
+                                // Get order information
+                                $order = null;
+                                $order_date = 'N/A';
+                                if (!empty($row->order_id)) {
+                                    $order = wc_get_order($row->order_id);
+                                    if ($order) {
+                                        $order_date = $order->get_date_created()->format('M j, Y g:i A');
+                                    }
+                                }
                             ?>
                                 <tr data-id="<?php echo $row->id; ?>">
                                     <td><?php echo esc_html($row->id); ?></td>
                                     <td class="product-cell">
                                         <strong><?php echo esc_html($product_name); ?></strong><br>
                                         <em>ID: <?php echo esc_html($row->product_id); ?></em>
                                     </td>
                                     <td class="mobile-info">
                                         <?php if (!empty($row->mobile_brand) || !empty($row->mobile_model)): ?>
                                             <?php if (!empty($row->mobile_brand)): ?>
                                                 <strong>Brand:</strong> <?php echo esc_html($row->mobile_brand); ?><br>
                                             <?php endif; ?>
                                             <?php if (!empty($row->mobile_model)): ?>
                                                 <strong>Model:</strong> <?php echo esc_html($row->mobile_model); ?>
                                             <?php endif; ?>
                                         <?php else: ?>
                                             <em>No mobile data</em>
                                         <?php endif; ?>
                                     </td>
                                     <td class="image-cell">
                                         <img src="<?php echo esc_url($image_url); ?>" 
                                              alt="Uploaded Image">
                                         <small><?php echo esc_html($row->image_name); ?></small>
                                         <small><?php echo size_format($row->image_size); ?></small>
                                     </td>
                                     <td class="order-cell">
-                                        <?php if (!empty($row->order_id)): ?>
+                                        <?php if (!empty($row->order_id) && $order): ?>
                                             <a href="<?php echo admin_url('post.php?post=' . $row->order_id . '&action=edit'); ?>" target="_blank">
-                                                Order #<?php echo esc_html($row->order_id); ?>
+                                                <strong>Order #<?php echo esc_html($row->order_id); ?></strong>
                                             </a>
+                                            <br><em><?php echo esc_html($order->get_status()); ?></em>
                                         <?php else: ?>
-                                            <em>No order yet</em>
+                                            <em>Order not found</em>
                                         <?php endif; ?>
                                     </td>
                                     <td><?php echo esc_html(date('M j, Y g:i A', strtotime($row->uploaded_at))); ?></td>
+                                    <td><?php echo esc_html($order_date); ?></td>
                                     <td>
                                         <div class="action-buttons">
                                             <a href="<?php echo esc_url($download_url); ?>" download="<?php echo esc_attr($row->image_name); ?>" class="button" title="Download Image">
                                                 💾 Download
                                             </a>
                                             <button class="delete-image-btn" data-id="<?php echo $row->id; ?>" title="Delete Image">
                                                 🗑️ Delete
                                             </button>
                                         </div>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 </div>
             <?php endif; ?>
         </div>
         <?php
     }