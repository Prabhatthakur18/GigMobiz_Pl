<?php
if (!defined('ABSPATH')) exit;

class WSSC_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_wssc_upload_csv', [$this, 'handle_csv']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'wssc') !== false) {
            wp_enqueue_style('wssc-admin-css', WSSC_PLUGIN_URL . 'assets/css/wssc-admin.css');
            wp_enqueue_script('wssc-admin-js', WSSC_PLUGIN_URL . 'assets/js/wssc-admin.js', ['jquery'], null, true);
            wp_localize_script('wssc-admin-js', 'wsscAdmin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wssc_admin_nonce')
            ]);
        }
    }

    public function add_menu() {
        add_menu_page('Side Cart Settings', 'Side Cart', 'manage_options', 'wssc-settings', [$this, 'settings_page'], 'dashicons-cart', 56);
        add_submenu_page('wssc-settings', 'Product Relations', 'Product Relations', 'manage_options', 'wssc-settings', [$this, 'settings_page']);
        add_submenu_page('wssc-settings', 'Bulk Requests', 'Bulk Requests', 'manage_options', 'wssc-bulk-requests', [$this, 'requests_page']);
        add_submenu_page('wssc-settings', 'Image Selected', 'Image Selected', 'manage_options', 'wssc-image-selected', [$this, 'image_selected_page']);
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>📦 Product Relations Management</h1>
            <p class="description">Manage product recommendations that appear in the side cart ("Ye Bhi Jaruri he" and "Hume bhi dekh lo" sections).</p>

            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                <div class="wssc-admin-toast success">
                    ✅ CSV uploaded successfully!
                </div>
            <?php elseif (isset($_GET['error']) && $_GET['error'] == 1): ?>
                <div class="wssc-admin-toast error">
                    ❌ Error uploading CSV. Please try again.
                </div>
            <?php endif; ?>

            <div class="wssc-upload-section">
                <h3>CSV Format Instructions</h3>
                <p>Your CSV should have 3 columns:</p>
                <ol>
                    <li><strong>Product ID</strong> - The main product ID</li>
                    <li><strong>Recommended Products</strong> - Comma-separated product IDs for "Ye Bhi Jaruri he" section</li>
                    <li><strong>Interested Products</strong> - Comma-separated product IDs for "Hume bhi dekh lo" section</li>
                </ol>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" class="wssc-upload-form">
                    <input type="hidden" name="action" value="wssc_upload_csv">
                    <?php wp_nonce_field('wssc_csv', 'wssc_nonce'); ?>
                    
                    <div class="upload-area">
                        <input type="file" name="wssc_csv" id="wssc_csv" required accept=".csv" class="file-input">
                        <label for="wssc_csv" class="file-label">
                            <span class="file-icon">📁</span>
                            <span class="file-text">Choose CSV File</span>
                        </label>
                    </div>
                    
                    <button type="submit" class="button button-primary button-large">
                        <span class="upload-icon">⬆️</span>
                        Upload CSV
                    </button>
                </form>
            </div>
        </div>
        <?php
    }

    public function handle_csv() {
        if (!isset($_POST['wssc_nonce']) || !wp_verify_nonce($_POST['wssc_nonce'], 'wssc_csv')) {
            wp_die('Invalid nonce');
        }

        $success = false;

        if (!empty($_FILES['wssc_csv']['tmp_name'])) {
            $file = fopen($_FILES['wssc_csv']['tmp_name'], 'r');

            if ($file) {
                $row_count = 0;
                while (($row = fgetcsv($file)) !== false) {
                    // Skip header row if exists
                    if ($row_count === 0 && !is_numeric($row[0])) {
                        $row_count++;
                        continue;
                    }

                    $product_id = intval($row[0]);
                    $recommended = isset($row[1]) ? sanitize_text_field($row[1]) : '';
                    $interested = isset($row[2]) ? sanitize_text_field($row[2]) : '';

                    if ($product_id > 0) {
                        update_post_meta($product_id, '_wssc_recommended', $recommended);
                        update_post_meta($product_id, '_wssc_interested', $interested);
                    }
                    $row_count++;
                }

                fclose($file);
                $success = true;
            }
        }

        // Redirect with success or error flag
        $redirect_url = admin_url('admin.php?page=wssc-settings');
        if ($success) {
            $redirect_url .= '&success=1';
        } else {
            $redirect_url .= '&error=1';
        }

        wp_redirect($redirect_url);
        exit;
    }

public function requests_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'wssc_bulk_requests';
    $results = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    ?>
    <div class="wrap">
        <h1>📋 Bulk Purchase Requests</h1>
        <p class="description">Manage bulk purchase requests submitted by customers through the side cart.</p>
        
        <?php if (empty($results)): ?>
            <div class="no-requests">
                <p>No bulk requests found.</p>
            </div>
        <?php else: ?>
            <table class="widefat fixed striped wssc-requests-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Products</th>
                        <th>Mobile Info</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th style="width: 80px;">Qty</th>
                        <th>Message</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 120px;">Date</th>
                        <th style="width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row):
                        $status_class = 'status-' . $row->status;
                        
                        // Get products information
                        $products_info = '';
                        if (!empty($row->product_ids)) {
                            $product_ids = json_decode($row->product_ids, true);
                            if (is_array($product_ids)) {
                                $product_names = [];
                                foreach ($product_ids as $pid) {
                                    $product = wc_get_product($pid);
                                    if ($product) {
                                        $product_names[] = $product->get_name();
                                    } else {
                                        $product_names[] = "Product ID: $pid (Deleted)";
                                    }
                                }
                                $products_info = implode('<br>', $product_names);
                            }
                        } else {
                            // Fallback to single product
                            $product = wc_get_product($row->product_id);
                            $products_info = $product ? $product->get_name() : "Product ID: {$row->product_id} (Deleted)";
                        }
                        
                        // Mobile info
                        $mobile_info = '';
                        if (!empty($row->mobile_brand) || !empty($row->mobile_model)) {
                            $mobile_info = '';
                            if (!empty($row->mobile_brand)) {
                                $mobile_info .= '<strong>Brand:</strong> ' . esc_html($row->mobile_brand) . '<br>';
                            }
                            if (!empty($row->mobile_model)) {
                                $mobile_info .= '<strong>Model:</strong> ' . esc_html($row->mobile_model);
                            }
                        } else {
                            $mobile_info = '<em>No mobile selected</em>';
                        }
                    ?>
                        <tr data-id="<?php echo $row->id; ?>">
                            <td><?php echo esc_html($row->id); ?></td>
                            <td><?php echo $products_info; ?></td>
                            <td><?php echo $mobile_info; ?></td>
                            <td><?php echo esc_html($row->name); ?></td>
                            <td><?php echo esc_html($row->phone); ?></td>
                            <td><?php echo esc_html($row->email ?: 'Not provided'); ?></td>
                            <td><?php echo esc_html($row->quantity); ?></td>
                            <td><?php echo esc_html($row->message ?: 'No message'); ?></td>
                            <td>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst(esc_html($row->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(date('M j, Y g:i A', strtotime($row->created_at))); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="edit-status-btn" data-id="<?php echo $row->id; ?>" title="Edit Status">
                                        ✏️
                                    </button>
                                    <button class="delete-request-btn" data-id="<?php echo $row->id; ?>" title="Delete Request">
                                        🗑️
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Status Edit Modal -->
    <div id="status-edit-modal" class="wssc-modal" style="display: none;">
        <div class="wssc-box">
            <h3>Update Status</h3>
            <form id="status-edit-form">
                <input type="hidden" id="edit-request-id" name="request_id">
                <label for="status-select">Select Status:</label>
                <select id="status-select" name="status" required>
                    <option value="pending">Pending</option>
                    <option value="done">Done</option>
                </select>
                <div style="margin-top: 15px;">
                    <button type="submit" class="button button-primary">Update Status</button>
                    <button type="button" class="button cancel-edit">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

    public function image_selected_page() {
        // Handle repair action
        if (isset($_GET['action']) && $_GET['action'] === 'repair_upload_dir') {
            $this->repair_upload_directory();
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'wssc_product_images';
        
        // FIXED: Only show images that have an associated order_id (confirmed orders)
        $results = $wpdb->get_results("SELECT * FROM $table WHERE order_id IS NOT NULL AND order_id > 0 ORDER BY uploaded_at DESC");
        
        // Get count of pending images (uploaded but no order yet)
        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE order_id IS NULL OR order_id = 0");
        ?>
        <div class="wrap">
            <h1>📸 Image Selected Management</h1>
            <p class="description">Manage images uploaded by customers for mobile selector products. Only images from confirmed orders are shown here.</p>
            
            <?php if ($pending_count > 0): ?>
                <div class="notice notice-info">
                    <p><strong>ℹ️ Info:</strong> There are <?php echo $pending_count; ?> uploaded images waiting for order confirmation. They will appear here once the customer places an order.</p>
                </div>
            <?php endif; ?>
            
            <!-- Debug Information -->
            <div class="wssc-debug-info" style="background: #f0f6fc; padding: 15px; border-left: 4px solid #0073aa; margin-bottom: 20px; border-radius: 4px;">
                <h3 style="margin-top: 0;">🔧 Upload Directory Status</h3>
                <?php
                global $wpdb;
                $mobile_selector = new WSSC_Mobile_Selector();
                $upload_status = $mobile_selector->check_upload_directory_status();
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
                    <p>No confirmed order images found.</p>
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
                                        <?php if (!empty($row->order_id)): ?>
                                            <a href="<?php echo admin_url('post.php?post=' . $row->order_id . '&action=edit'); ?>" target="_blank">
                                                Order #<?php echo esc_html($row->order_id); ?>
                                            </a>
                                        <?php else: ?>
                                            <em>No order yet</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html(date('M j, Y g:i A', strtotime($row->uploaded_at))); ?></td>
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
    
    public function repair_upload_directory() {
        $mobile_selector = new WSSC_Mobile_Selector();
        $result = $mobile_selector->create_upload_directory();
        
        if ($result) {
            echo '<div class="notice notice-success"><p>✅ Upload directory repaired successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>❌ Failed to repair upload directory. Check server permissions.</p></div>';
        }
        
        // Redirect back to the main page
        echo '<script>setTimeout(function() { window.location.href = "' . admin_url('admin.php?page=wssc-image-selected') . '"; }, 2000);</script>';
    }
}