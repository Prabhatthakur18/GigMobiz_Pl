<?php
if (!defined('ABSPATH')) exit;

class WSSC_Debug {
    public function __construct() {
        // Add debug hooks
        add_action('wp_ajax_wssc_debug_images', [$this, 'debug_images']);
        add_action('wp_ajax_wssc_fix_image_orders', [$this, 'fix_image_orders']);
        
        // Add admin debug page with higher priority to ensure it loads after main menu
        add_action('admin_menu', [$this, 'add_debug_menu'], 20);
        
        // Enqueue admin scripts for debug page
        add_action('admin_enqueue_scripts', [$this, 'enqueue_debug_scripts']);
    }
    
    public function enqueue_debug_scripts($hook) {
        // Only load on debug page
        if (strpos($hook, 'wssc-debug') !== false) {
            wp_enqueue_script('jquery');
            wp_localize_script('jquery', 'ajaxurl', admin_url('admin-ajax.php'));
        }
    }
    
    public function add_debug_menu() {
        // Check if parent menu exists first
        global $menu, $submenu;
        
        // Ensure the parent menu exists before adding submenu
        if (!isset($submenu['wssc-settings'])) {
            // If parent doesn't exist, create it first
            add_menu_page(
                'Side Cart Settings', 
                'Side Cart', 
                'manage_options', 
                'wssc-settings', 
                '__return_false', 
                'dashicons-cart', 
                56
            );
        }
        
        add_submenu_page(
            'wssc-settings',
            'Debug Images',
            'Debug Images',
            'manage_options',
            'wssc-debug',
            [$this, 'debug_page']
        );
    }
    
    public function debug_page() {
        global $wpdb;
        $images_table = $wpdb->prefix . 'wssc_product_images';
        
        // Get all images
        $all_images = $wpdb->get_results("SELECT * FROM $images_table ORDER BY uploaded_at DESC");
        
        // Get images without order_id
        $pending_images = $wpdb->get_results("SELECT * FROM $images_table WHERE order_id IS NULL OR order_id = 0 ORDER BY uploaded_at DESC");
        
        // Get recent orders with meta - FIXED: Better query to find order item meta
        $recent_orders = $wpdb->get_results("
            SELECT DISTINCT p.ID as order_id, p.post_date, 
                   oim.meta_value as image_meta_id,
                   pm.meta_value as order_image_id
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->prefix}woocommerce_order_items oi ON oi.order_id = p.ID
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oim.order_item_id = oi.order_item_id AND oim.meta_key = 'WSSC Image ID'
            LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wssc_image_id'
            WHERE p.post_type = 'shop_order' 
            AND p.post_status IN ('wc-processing', 'wc-completed', 'wc-on-hold', 'wc-pending')
            ORDER BY p.post_date DESC 
            LIMIT 15
        ");
        
        ?>
        <div class="wrap">
            <h1>🐛 WSSC Debug Information</h1>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4;">
                <h2>Image Statistics</h2>
                <p><strong>Total Images:</strong> <?php echo count($all_images); ?></p>
                <p><strong>Pending Images (No Order):</strong> <?php echo count($pending_images); ?></p>
                <p><strong>Confirmed Images:</strong> <?php echo count($all_images) - count($pending_images); ?></p>
            </div>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4;">
                <h2>Pending Images (No Order ID)</h2>
                <?php if (empty($pending_images)): ?>
                    <p>No pending images found.</p>
                <?php else: ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Image ID</th>
                                <th>Product ID</th>
                                <th>Image Name</th>
                                <th>Upload Date</th>
                                <th>Mobile Info</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_images as $image): ?>
                                <tr>
                                    <td><?php echo $image->id; ?></td>
                                    <td><?php echo $image->product_id; ?></td>
                                    <td><?php echo $image->image_name; ?></td>
                                    <td><?php echo $image->uploaded_at; ?></td>
                                    <td>
                                        <?php if ($image->mobile_brand || $image->mobile_model): ?>
                                            <?php echo esc_html($image->mobile_brand . ' ' . $image->mobile_model); ?>
                                        <?php else: ?>
                                            <em>No mobile info</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="button" onclick="fixImageOrder(<?php echo $image->id; ?>)">
                                            Try Auto-Fix
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p style="margin-top: 15px;">
                        <button class="button button-primary" onclick="fixAllImageOrders()">
                            🔧 Try to Fix All Pending Images
                        </button>
                    </p>
                <?php endif; ?>
            </div>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4;">
                <h2>Recent Orders</h2>
                <?php if (empty($recent_orders)): ?>
                    <p>No recent orders found.</p>
                <?php else: ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Image Meta (Item)</th>
                                <th>Image Meta (Order)</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo admin_url('post.php?post=' . $order->order_id . '&action=edit'); ?>">
                                            #<?php echo $order->order_id; ?>
                                        </a>
                                    </td>
                                    <td><?php echo $order->post_date; ?></td>
                                    <td><?php echo $order->image_meta_id ?: 'No item meta'; ?></td>
                                    <td><?php echo $order->order_image_id ?: 'No order meta'; ?></td>
                                    <td>
                                        <button class="button button-secondary" onclick="linkOrderImages(<?php echo $order->order_id; ?>)">
                                            Force Link
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Test Section -->
            <div style="background: #f0f6fc; padding: 20px; margin: 20px 0; border: 1px solid #0073aa;">
                <h2>🧪 Debug Test</h2>
                <p><strong>Page Status:</strong> ✅ Debug page is now working correctly!</p>
                <p><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></p>
                <p><strong>Plugin Path:</strong> <?php echo WSSC_PLUGIN_PATH; ?></p>
                <p><strong>Current User Can Manage:</strong> <?php echo current_user_can('manage_options') ? 'Yes' : 'No'; ?></p>
                <p><strong>Database Tables:</strong></p>
                <ul>
                    <li>Images Table: <?php echo $wpdb->get_var("SHOW TABLES LIKE '$images_table'") ? '✅ Exists' : '❌ Missing'; ?></li>
                    <li>Bulk Requests Table: <?php echo $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wssc_bulk_requests'") ? '✅ Exists' : '❌ Missing'; ?></li>
                </ul>
            </div>
        </div>
        
        <script>
        function fixImageOrder(imageId) {
            if (confirm('Try to automatically link this image to a recent order?')) {
                jQuery.post(ajaxurl, {
                    action: 'wssc_fix_image_orders',
                    image_id: imageId,
                    nonce: '<?php echo wp_create_nonce('wssc_debug_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('✅ ' + response.data.message);
                        location.reload();
                    } else {
                        alert('❌ ' + response.data);
                    }
                });
            }
        }
        
        function fixAllImageOrders() {
            if (confirm('Try to automatically link all pending images to recent orders?')) {
                jQuery.post(ajaxurl, {
                    action: 'wssc_fix_image_orders',
                    fix_all: true,
                    nonce: '<?php echo wp_create_nonce('wssc_debug_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('✅ ' + response.data.message);
                        location.reload();
                    } else {
                        alert('❌ ' + response.data);
                    }
                });
            }
        }
        
        function linkOrderImages(orderId) {
            if (confirm('Force link images to order #' + orderId + '?')) {
                jQuery.post(ajaxurl, {
                    action: 'wssc_fix_image_orders',
                    force_order_id: orderId,
                    nonce: '<?php echo wp_create_nonce('wssc_debug_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('✅ ' + response.data.message);
                        location.reload();
                    } else {
                        alert('❌ ' + response.data);
                    }
                });
            }
        }
        </script>
        <?php
    }
    
    public function fix_image_orders() {
        // Check permissions and nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'wssc_debug_nonce')) {
            wp_send_json_error('Unauthorized');
        }
        
        global $wpdb;
        $images_table = $wpdb->prefix . 'wssc_product_images';
        
        if (isset($_POST['force_order_id'])) {
            // Force link images to specific order
            $order_id = intval($_POST['force_order_id']);
            $order = wc_get_order($order_id);
            
            if (!$order) {
                wp_send_json_error('Order not found');
            }
            
            $linked_count = 0;
            $order_date = $order->get_date_created();
            
            // Get order items and try to match with pending images
            foreach ($order->get_items() as $item_id => $item) {
                $product_id = $item->get_product_id();
                
                // Find pending images for this product around order time
                $time_window_start = $order_date->date('Y-m-d H:i:s');
                $time_window_before = $order_date->modify('-3 hours')->date('Y-m-d H:i:s');
                $time_window_after = $order_date->modify('+6 hours')->date('Y-m-d H:i:s');
                
                $pending_images = $wpdb->get_results($wpdb->prepare("
                    SELECT id 
                    FROM $images_table 
                    WHERE product_id = %d 
                    AND (order_id IS NULL OR order_id = 0)
                    AND uploaded_at BETWEEN %s AND %s
                    ORDER BY uploaded_at DESC
                    LIMIT 1
                ", $product_id, $time_window_before, $time_window_after));
                
                if (!empty($pending_images)) {
                    $image = $pending_images[0];
                    $result = $wpdb->update(
                        $images_table,
                        ['order_id' => $order_id],
                        ['id' => $image->id],
                        ['%d'],
                        ['%d']
                    );
                    
                    if ($result !== false) {
                        $linked_count++;
                    }
                }
            }
            
            wp_send_json_success(['message' => "Force linked $linked_count images to order #$order_id"]);
            
        } elseif (isset($_POST['fix_all'])) {
            // Fix all pending images
            $pending_images = $wpdb->get_results("SELECT * FROM $images_table WHERE order_id IS NULL OR order_id = 0 ORDER BY uploaded_at DESC");
            $fixed_count = 0;
            
            foreach ($pending_images as $image) {
                $order_id = $this->find_matching_order($image);
                if ($order_id) {
                    $wpdb->update(
                        $images_table,
                        ['order_id' => $order_id],
                        ['id' => $image->id],
                        ['%d'],
                        ['%d']
                    );
                    $fixed_count++;
                }
            }
            
            wp_send_json_success(['message' => "Fixed $fixed_count images"]);
            
        } else {
            // Fix single image
            $image_id = intval($_POST['image_id']);
            $image = $wpdb->get_row($wpdb->prepare("SELECT * FROM $images_table WHERE id = %d", $image_id));
            
            if (!$image) {
                wp_send_json_error('Image not found');
            }
            
            $order_id = $this->find_matching_order($image);
            if ($order_id) {
                $wpdb->update(
                    $images_table,
                    ['order_id' => $order_id],
                    ['id' => $image_id],
                    ['%d'],
                    ['%d']
                );
                wp_send_json_success(['message' => "Linked image to order #$order_id"]);
            } else {
                wp_send_json_error('No matching order found');
            }
        }
    }
    
    private function find_matching_order($image) {
        global $wpdb;
        
        // IMPROVED: Look for orders with the same product and around the same time
        $time_window = date('Y-m-d H:i:s', strtotime($image->uploaded_at . ' +4 hours'));
        $time_start = date('Y-m-d H:i:s', strtotime($image->uploaded_at . ' -2 hours'));
        
        // Find orders with matching product in the time window
        $order_id = $wpdb->get_var($wpdb->prepare("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON oi.order_id = p.ID
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oim.order_item_id = oi.order_item_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-processing', 'wc-completed', 'wc-on-hold', 'wc-pending')
            AND p.post_date BETWEEN %s AND %s
            AND oim.meta_key = '_product_id'
            AND oim.meta_value = %d
            ORDER BY p.post_date DESC
            LIMIT 1
        ", $time_start, $time_window, $image->product_id));
        
        return $order_id;
    }
}

// Initialize debug class
new WSSC_Debug();