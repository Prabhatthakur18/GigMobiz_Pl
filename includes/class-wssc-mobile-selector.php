@@ .. @@
     public function upload_image() {
         // Verify nonce
         if (!wp_verify_nonce($_POST['nonce'], 'wssc_mobile_nonce')) {
             wp_send_json_error('Invalid nonce');
         }
         
         // Check if file was uploaded
         if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
             wp_send_json_error('No image uploaded or upload error: ' . $_FILES['image']['error']);
         }
         
         $file = $_FILES['image'];
         $product_id = intval($_POST['product_id']);
         $mobile_brand = sanitize_text_field($_POST['mobile_brand']);
         $mobile_model = sanitize_text_field($_POST['mobile_model']);
         
         // Validate file size (2MB = 2 * 1024 * 1024 bytes)
         if ($file['size'] > 2 * 1024 * 1024) {
             wp_send_json_error('Image size must be 2MB or less');
         }
         
         // Validate file type
         $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
         if (!in_array($file['type'], $allowed_types)) {
             wp_send_json_error('Only JPG, PNG, and GIF images are allowed');
         }
         
         // Ensure upload directory exists
         $this->create_upload_directory();
         
         // Create unique filename
         $upload_dir = wp_upload_dir();
         $wssc_dir = $upload_dir['basedir'] . '/wssc-images';
         $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
         $unique_filename = 'wssc_' . $product_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
         $file_path = $wssc_dir . '/' . $unique_filename;
         
         // Debug logging
         error_log('WSSC Upload Debug - Directory: ' . $wssc_dir);
         error_log('WSSC Upload Debug - File path: ' . $file_path);
         error_log('WSSC Upload Debug - File exists: ' . (file_exists($wssc_dir) ? 'Yes' : 'No'));
         error_log('WSSC Upload Debug - Directory writable: ' . (is_writable($wssc_dir) ? 'Yes' : 'No'));
         
         // Move uploaded file
         if (move_uploaded_file($file['tmp_name'], $file_path)) {
             // Verify file was actually created
             if (!file_exists($file_path)) {
                 wp_send_json_error('File was not created after move_uploaded_file');
             }
             
            // Update image record with order ID and mark as confirmed
             global $wpdb;
             $table = $wpdb->prefix . 'wssc_product_images';
             
             $result = $wpdb->insert($table, [
                 'product_id' => $product_id,
                 'mobile_brand' => $mobile_brand,
                 'mobile_model' => $mobile_model,
                'image_path' => $file_path,
                 'image_name' => $unique_filename,
                'image_size' => $file['size'],
                'status' => 'pending' // Mark as pending until order is placed
             ]);
             
             if ($result !== false) {
                 $image_id = $wpdb->insert_id;
                 
                    [
                        'order_id' => $order->get_id(),
                        'status' => 'confirmed'
                    ],
                 $check_record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $image_id));
                    ['%d', '%s'],
                    error_log('WSSC Order Debug - Successfully updated image with order ID and confirmed status');
                 }
                 
                 wp_send_json_success([
                    'message' => 'Image uploaded successfully! It will appear in admin after order confirmation.',
                     'id' => $image_id,
                     'image_path' => $file_path,
                     'image_name' => $unique_filename,
                    error_log('WSSC Order Debug - Failed to update image order_id and status. DB Error: ' . $wpdb->last_error);
                 ]);
             } else {
                 wp_send_json_error('Failed to save image information to database: ' . $wpdb->last_error);
             }
         } else {
             wp_send_json_error('Failed to move uploaded file. PHP Error: ' . error_get_last()['message']);
         }
     }