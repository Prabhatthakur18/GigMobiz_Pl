<?php
if (!defined('ABSPATH')) exit;

class WSSC_Mobile_Selector {
    public function __construct() {
        // Frontend functionality
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_shortcode('mobile_selector', [$this, 'render_selector']);
        add_shortcode('mobile_selector_with_image', [$this, 'render_selector_with_image']);
        
        // AJAX handlers
        add_action('wp_ajax_wssc_get_models', [$this, 'get_models']);
        add_action('wp_ajax_nopriv_wssc_get_models', [$this, 'get_models']);
        add_action('wp_ajax_wssc_upload_image', [$this, 'upload_image']);
        add_action('wp_ajax_nopriv_wssc_upload_image', [$this, 'upload_image']);
        
        // Cart integration - FIXED: Better priority and more hooks
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 5, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_order_item_meta'], 10, 4);
        add_action('woocommerce_new_order', [$this, 'link_images_to_order'], 10, 1);
        add_action('woocommerce_checkout_order_processed', [$this, 'link_images_to_order'], 20, 1);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_add_to_cart'], 10, 3);
        
        // Ensure image ID is attached to cart form
        add_action('woocommerce_before_add_to_cart_button', [$this, 'add_hidden_image_input']);
        
        // Display in orders
        add_action('woocommerce_before_order_itemmeta', [$this, 'display_order_item_meta'], 10, 3);
        add_action('woocommerce_order_item_meta_end', [$this, 'display_order_item_meta_frontend'], 10, 4);
        
        // Ensure upload directory exists on init
        add_action('init', [$this, 'ensure_upload_directory']);
    }
    
    // FIXED: Enhanced method to link images when order is created
    public function link_images_to_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        global $wpdb;
        $table = $wpdb->prefix . 'wssc_product_images';
        
        foreach ($order->get_items() as $item_id => $item) {
            $image_id = $item->get_meta('WSSC Image ID');
            if ($image_id) {
                // Update image record with order ID
                $result = $wpdb->update(
                    $table,
                    ['order_id' => $order_id],
                    ['id' => $image_id],
                    ['%d'],
                    ['%d']
                );
                
                error_log("WSSC: Linking image $image_id to order $order_id - Result: " . ($result !== false ? 'Success' : 'Failed'));
            }
        }
    }
    
    public function ensure_upload_directory() {
        // Only run this check occasionally to avoid performance impact
        $last_check = get_option('wssc_upload_dir_last_check', 0);
        if (time() - $last_check > 3600) { // Check once per hour
            $this->create_upload_directory();
            update_option('wssc_upload_dir_last_check', time());
        }
    }
    
    public function check_upload_directory_status() {
        $upload_dir = wp_upload_dir();
        $wssc_dir = $upload_dir['basedir'] . '/wssc-images';
        
        $status = [
            'directory_exists' => file_exists($wssc_dir),
            'directory_writable' => is_writable($wssc_dir),
            'htaccess_exists' => file_exists($wssc_dir . '/.htaccess'),
            'index_exists' => file_exists($wssc_dir . '/index.php'),
            'full_path' => $wssc_dir,
            'url_path' => $upload_dir['baseurl'] . '/wssc-images'
        ];
        
        return $status;
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $brand_table = $wpdb->prefix . 'wssc_mobile_brands';
        $model_table = $wpdb->prefix . 'wssc_mobile_models';

        $sql = "
        CREATE TABLE $brand_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brand_name VARCHAR(255) NOT NULL UNIQUE
        ) $charset_collate;

        CREATE TABLE $model_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brand_id INT NOT NULL,
            model_name VARCHAR(255) NOT NULL,
            INDEX idx_brand_id (brand_id),
            FOREIGN KEY (brand_id) REFERENCES $brand_table(id) ON DELETE CASCADE
        ) $charset_collate;
        ";

        dbDelta($sql);
        
        // Create image table
        WSSC_DB::create_image_table();
    }
    
    public function create_upload_directory() {
        $upload_dir = wp_upload_dir();
        $wssc_dir = $upload_dir['basedir'] . '/wssc-images';
        
        // Create directory if it doesn't exist
        if (!file_exists($wssc_dir)) {
            $created = wp_mkdir_p($wssc_dir);
            if (!$created) {
                error_log('WSSC Error: Failed to create upload directory: ' . $wssc_dir);
                return false;
            }
        }
        
        // Set proper permissions (755 for directories)
        if (file_exists($wssc_dir)) {
            chmod($wssc_dir, 0755);
        }
        
        // Create .htaccess to allow access but prevent directory listing
        $htaccess_file = $wssc_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "Order allow,deny\n";
            $htaccess_content .= "Allow from all\n";
            @file_put_contents($htaccess_file, $htaccess_content);
        }
        
        // Create index.php to prevent directory listing
        $index_file = $wssc_dir . '/index.php';
        if (!file_exists($index_file)) {
            @file_put_contents($index_file, '<?php // Silence is golden');
        }
        
        // Test if directory is writable
        if (!is_writable($wssc_dir)) {
            error_log('WSSC Error: Upload directory is not writable: ' . $wssc_dir);
            return false;
        }
        
        return true;
    }
    
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
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Verify file was actually created
            if (!file_exists($file_path)) {
                wp_send_json_error('File was not created after move_uploaded_file');
            }
            
            // Save to database
            global $wpdb;
            $table = $wpdb->prefix . 'wssc_product_images';
            
            $result = $wpdb->insert($table, [
                'product_id' => $product_id,
                'mobile_brand' => $mobile_brand,
                'mobile_model' => $mobile_model,
                'image_path' => $file_path,
                'image_name' => $unique_filename,
                'image_size' => $file['size']
            ]);
            
            if ($result !== false) {
                $image_id = $wpdb->insert_id;
                
                wp_send_json_success([
                    'message' => 'Image uploaded successfully!',
                    'id' => $image_id,
                    'image_path' => $file_path,
                    'image_name' => $unique_filename,
                    'image_url' => $upload_dir['baseurl'] . '/wssc-images/' . $unique_filename
                ]);
            } else {
                wp_send_json_error('Failed to save image information to database: ' . $wpdb->last_error);
            }
        } else {
            wp_send_json_error('Failed to move uploaded file');
        }
    }

    public function enqueue_scripts() {
        if (is_woocommerce() || is_cart() || is_checkout() || is_shop() || is_product_category() || is_product_tag() || is_product()) {
            wp_enqueue_script('wssc-mobile-selector', WSSC_PLUGIN_URL . 'assets/js/wssc-mobile-selector.js', ['jquery'], '1.0.0', true);
            wp_localize_script('wssc-mobile-selector', 'wsscMobile', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wssc_mobile_nonce')
            ]);
        }
    }

    public function render_selector($atts = []) {
        global $wpdb;
        
        $atts = shortcode_atts([
            'required' => 'true',
            'class' => 'wssc-mobile-selector'
        ], $atts);

        // Get all brands
        $brands = $wpdb->get_results("SELECT brand_name FROM {$wpdb->prefix}wssc_mobile_brands ORDER BY brand_name");
        
        // Get current product ID if available
        global $product;
        $product_id = $product ? $product->get_id() : 0;

        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>">
            <div class="wssc-mobile-field">
                <label for="mobile_brand">📱 Select Mobile Brand <?php echo $atts['required'] === 'true' ? '*' : ''; ?></label>
                <select id="mobile_brand" name="mobile_brand" <?php echo $atts['required'] === 'true' ? 'required' : ''; ?>>
                    <option value="">Choose Brand</option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?php echo esc_attr($brand->brand_name); ?>">
                            <?php echo esc_html($brand->brand_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="wssc-mobile-field">
                <label for="mobile_model">📱 Select Mobile Model <?php echo $atts['required'] === 'true' ? '*' : ''; ?></label>
                <select id="mobile_model" name="mobile_model" <?php echo $atts['required'] === 'true' ? 'required' : ''; ?> disabled>
                    <option value="">First select brand</option>
                </select>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function render_selector_with_image($atts = []) {
        global $wpdb;
        
        $atts = shortcode_atts([
            'required' => 'true',
            'class' => 'wssc-mobile-selector-with-image'
        ], $atts);

        // Get current product ID if available
        global $product;
        $product_id = $product ? $product->get_id() : 0;

        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>">
            <div class="wssc-mobile-field">
                <label for="wssc_image_upload_<?php echo $product_id; ?>">📸 Upload Image (Max 2MB) <?php echo $atts['required'] === 'true' ? '*' : ''; ?></label>
                <div class="wssc-image-upload-container">
                    <input type="file" id="wssc_image_upload_<?php echo $product_id; ?>" name="wssc_image_upload" accept="image/*" <?php echo $atts['required'] === 'true' ? 'required' : ''; ?>>
                    <input type="hidden" id="wssc_image_id_<?php echo $product_id; ?>" name="wssc_image_id" value="">
                    <div class="wssc-image-preview" style="display: none;">
                        <img id="wssc_image_preview_<?php echo $product_id; ?>" src="" alt="Image Preview" style="max-width: 150px; height: auto; border-radius: 4px; margin-top: 10px;">
                        <button type="button" id="wssc_remove_image_<?php echo $product_id; ?>" class="button" style="margin-top: 5px;">Remove Image</button>
                    </div>
                    <div class="wssc-upload-status"></div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var productId = <?php echo $product_id; ?>;
            var imageUpload = $('#wssc_image_upload_' + productId);
            var imagePreview = $('#wssc_image_preview_' + productId);
            var imageId = $('#wssc_image_id_' + productId);
            var removeBtn = $('#wssc_remove_image_' + productId);
            var previewContainer = $('.wssc-image-preview');
            var statusDiv = $('.wssc-upload-status');
            
            // Handle image upload
            imageUpload.on('change', function() {
                var file = this.files[0];
                if (file) {
                    // Validate file size (2MB)
                    if (file.size > 2 * 1024 * 1024) {
                        alert('Image size must be 2MB or less');
                        this.value = '';
                        return;
                    }
                    
                    // Validate file type
                    if (!file.type.match('image.*')) {
                        alert('Please select an image file');
                        this.value = '';
                        return;
                    }
                    
                    // Show preview
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.attr('src', e.target.result);
                        previewContainer.show();
                    };
                    reader.readAsDataURL(file);
                    
                    // Upload image
                    var formData = new FormData();
                    formData.append('action', 'wssc_upload_image');
                    formData.append('image', file);
                    formData.append('product_id', productId);
                    formData.append('mobile_brand', '');
                    formData.append('mobile_model', '');
                    formData.append('nonce', wsscMobile.nonce);
                    
                    statusDiv.html('<span style="color: #0073aa;">⏳ Uploading...</span>');
                    
                    $.ajax({
                        url: wsscMobile.ajax_url,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                imageId.val(response.data.id);
                                statusDiv.html('<span style="color: #00a32a;">✅ ' + response.data.message + '</span>');
                            } else {
                                statusDiv.html('<span style="color: #d63638;">❌ ' + response.data + '</span>');
                            }
                        },
                        error: function() {
                            statusDiv.html('<span style="color: #d63638;">❌ Upload failed</span>');
                        }
                    });
                }
            });
            
            // Handle image removal
            removeBtn.on('click', function() {
                imageUpload.val('');
                imageId.val('');
                previewContainer.hide();
                statusDiv.html('');
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function get_models() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wssc_mobile_nonce')) {
            wp_die('Security check failed');
        }

        global $wpdb;
        $brand_name = sanitize_text_field($_POST['brand_name']);
        
        // Get brand ID
        $brand_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}wssc_mobile_brands WHERE brand_name = %s", 
            $brand_name
        ));

        if (!$brand_id) {
            wp_send_json([]);
        }
        
        // Get models for this brand
        $models = $wpdb->get_results($wpdb->prepare(
            "SELECT model_name FROM {$wpdb->prefix}wssc_mobile_models WHERE brand_id = %d ORDER BY model_name", 
            $brand_id
        ));
        
        wp_send_json($models);
    }

    public function add_hidden_image_input() {
        global $product;
        if (!$product) return;
        
        $product_id = $product->get_id();
        
        // Check if there's an image ID for this product
        $image_id = '';
        if (isset($_POST['wssc_image_id'])) {
            $image_id = sanitize_text_field($_POST['wssc_image_id']);
        }
        
        // Add hidden input for image ID
        echo '<input type="hidden" name="wssc_image_id" value="' . esc_attr($image_id) . '">';
    }

    // FIXED: Better cart item data handling
    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        // Check for image ID first
        if (isset($_POST['wssc_image_id']) && !empty($_POST['wssc_image_id'])) {
            $cart_item_data['wssc_image_id'] = sanitize_text_field($_POST['wssc_image_id']);
        }
        
        // Check for mobile data
        if (isset($_POST['mobile_brand']) && !empty($_POST['mobile_brand']) && 
            isset($_POST['mobile_model']) && !empty($_POST['mobile_model'])) {
            
            $cart_item_data['mobile_brand'] = sanitize_text_field($_POST['mobile_brand']);
            $cart_item_data['mobile_model'] = sanitize_text_field($_POST['mobile_model']);
        }
        
        // Make each cart item unique when any of our data exists
        if (!empty($cart_item_data['wssc_image_id']) || !empty($cart_item_data['mobile_brand'])) {
            $cart_item_data['unique_key'] = md5(microtime().rand());
        }
        
        return $cart_item_data;
    }

    public function display_cart_item_data($item_data, $cart_item) {
        if (!empty($cart_item['mobile_brand'])) {
            $item_data[] = [
                'key'     => __('Mobile Brand', 'wssc'),
                'value'   => wc_clean($cart_item['mobile_brand']),
                'display' => '',
            ];
        }
        
        if (!empty($cart_item['mobile_model'])) {
            $item_data[] = [
                'key'     => __('Mobile Model', 'wssc'),
                'value'   => wc_clean($cart_item['mobile_model']),
                'display' => '',
            ];
        }
        
        // Display image if present
        if (!empty($cart_item['wssc_image_id'])) {
            global $wpdb;
            $table = $wpdb->prefix . 'wssc_product_images';
            $image = $wpdb->get_row($wpdb->prepare(
                "SELECT image_name, image_path FROM $table WHERE id = %d",
                $cart_item['wssc_image_id']
            ));
            
            if ($image) {
                $upload_dir = wp_upload_dir();
                $image_url = $upload_dir['baseurl'] . '/wssc-images/' . $image->image_name;
                
                $item_data[] = [
                    'key'     => __('Uploaded Image', 'wssc'),
                    'value'   => '<img src="' . esc_url($image_url) . '" style="max-width: 100px; height: auto; border-radius: 4px;" alt="Uploaded Image">',
                    'display' => '',
                ];
            }
        }
        
        return $item_data;
    }

    public function save_order_item_meta($item, $cart_item_key, $values, $order) {
        if (!empty($values['mobile_brand'])) {
            $item->add_meta_data(__('Mobile Brand', 'wssc'), $values['mobile_brand']);
        }
        
        if (!empty($values['mobile_model'])) {
            $item->add_meta_data(__('Mobile Model', 'wssc'), $values['mobile_model']);
        }
        
        // Save image ID to order meta
        if (!empty($values['wssc_image_id'])) {
            $item->add_meta_data(__('WSSC Image ID', 'wssc'), $values['wssc_image_id']);
        }
    }

    public function validate_add_to_cart($passed, $product_id, $quantity) {
        // Only validate mobile fields if they are present in the form
        if (isset($_POST['mobile_brand']) && isset($_POST['mobile_model'])) {
            if (empty($_POST['mobile_brand']) || empty($_POST['mobile_model'])) {
                wc_add_notice(__('Please select both mobile brand and model.', 'wssc'), 'error');
                $passed = false;
            }
        }
        
        // If image upload is required but no image, show error
        if (isset($_POST['wssc_image_upload']) && empty($_POST['wssc_image_id'])) {
            wc_add_notice(__('Please upload an image before adding to cart.', 'wssc'), 'error');
            $passed = false;
        }
        
        return $passed;
    }

    public function display_order_item_meta($item_id, $item, $order) {
        $brand = $item->get_meta('Mobile Brand');
        $model = $item->get_meta('Mobile Model');
        $image_id = $item->get_meta('WSSC Image ID');
        
        if ($brand) {
            echo '<div><strong>' . __('Mobile Brand', 'wssc') . ':</strong> ' . esc_html($brand) . '</div>';
        }
        
        if ($model) {
            echo '<div><strong>' . __('Mobile Model', 'wssc') . ':</strong> ' . esc_html($model) . '</div>';
        }
        
        // Display image if present
        if ($image_id) {
            global $wpdb;
            $table = $wpdb->prefix . 'wssc_product_images';
            $image = $wpdb->get_row($wpdb->prepare(
                "SELECT image_name, image_path FROM $table WHERE id = %d",
                $image_id
            ));
            
            if ($image) {
                $upload_dir = wp_upload_dir();
                $image_url = $upload_dir['baseurl'] . '/wssc-images/' . $image->image_name;
                
                echo '<div><strong>' . __('Uploaded Image', 'wssc') . ':</strong><br>';
                echo '<img src="' . esc_url($image_url) . '" style="max-width: 150px; height: auto; border-radius: 4px; margin-top: 5px;" alt="Uploaded Image">';
                echo '</div>';
            }
        }
    }

    public function display_order_item_meta_frontend($item_id, $item, $order, $plain_text) {
        $brand = $item->get_meta('Mobile Brand');
        $model = $item->get_meta('Mobile Model');
        $image_id = $item->get_meta('WSSC Image ID');
        
        if ($brand || $model || $image_id) {
            if ($plain_text) {
                echo "\n";
                if ($brand) echo 'Mobile Brand: ' . $brand . "\n";
                if ($model) echo 'Mobile Model: ' . $model . "\n";
                if ($image_id) echo 'Uploaded Image: Yes' . "\n";
            } else {
                echo '<div class="wssc-mobile-info" style="margin-top: 5px; font-size: 0.9em; color: #666;">';
                if ($brand) echo '<div><strong>Mobile Brand:</strong> ' . esc_html($brand) . '</div>';
                if ($model) echo '<div><strong>Mobile Model:</strong> ' . esc_html($model) . '</div>';
                
                // Display image if present
                if ($image_id) {
                    global $wpdb;
                    $table = $wpdb->prefix . 'wssc_product_images';
                    $image = $wpdb->get_row($wpdb->prepare(
                        "SELECT image_name, image_path FROM $table WHERE id = %d",
                        $image_id
                    ));
                    
                    if ($image) {
                        $upload_dir = wp_upload_dir();
                        $image_url = $upload_dir['baseurl'] . '/wssc-images/' . $image->image_name;
                        
                        echo '<div><strong>Uploaded Image:</strong><br>';
                        echo '<img src="' . esc_url($image_url) . '" style="max-width: 120px; height: auto; border-radius: 4px; margin-top: 5px;" alt="Uploaded Image">';
                        echo '</div>';
                    }
                }
                echo '</div>';
            }
        }
    }

    // Get brands for admin
    public function get_all_brands() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wssc_mobile_brands ORDER BY brand_name");
    }

    // Get models for admin
    public function get_models_by_brand($brand_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wssc_mobile_models WHERE brand_id = %d ORDER BY model_name", 
            $brand_id
        ));
    }

    // Add brand
    public function add_brand($brand_name) {
        global $wpdb;
        return $wpdb->insert(
            $wpdb->prefix . 'wssc_mobile_brands',
            ['brand_name' => sanitize_text_field($brand_name)]
        );
    }

    // Add model
    public function add_model($brand_id, $model_name) {
        global $wpdb;
        return $wpdb->insert(
            $wpdb->prefix . 'wssc_mobile_models',
            [
                'brand_id' => intval($brand_id),
                'model_name' => sanitize_text_field($model_name)
            ]
        );
    }

    // Delete brand (and its models)
    public function delete_brand($brand_id) {
        global $wpdb;
        return $wpdb->delete($wpdb->prefix . 'wssc_mobile_brands', ['id' => intval($brand_id)]);
    }

    // Delete model
    public function delete_model($model_id) {
        global $wpdb;
        return $wpdb->delete($wpdb->prefix . 'wssc_mobile_models', ['id' => intval($model_id)]);
    }
}