<?php
if (!defined('ABSPATH')) exit;

class WSSC_SideCart {
    public function __construct() {
        add_action('woocommerce_widget_shopping_cart_after_buttons', [$this, 'render_bulk_button']);
        add_action('woocommerce_widget_shopping_cart_after_buttons', [$this, 'render_sections']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_footer', [$this, 'add_bulk_modal']);
    }

    public function enqueue_assets() {
        if (is_woocommerce() || is_cart() || is_checkout() || is_shop() || is_product_category() || is_product_tag() || is_product()) {
            wp_enqueue_script('wssc-js', WSSC_PLUGIN_URL . 'assets/js/wssc.js', ['jquery'], '1.0.3', true);
            wp_localize_script('wssc-js', 'wsscAjax', [
                'url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wssc_nonce')
            ]);

            wp_enqueue_style('wssc-css', WSSC_PLUGIN_URL . 'assets/css/wssc.css', [], '1.0.3');
        }
    }

    public function render_sections() {
        // Prevent double rendering
        static $has_run = false;
        if ($has_run) return;
        $has_run = true;

        if (WC()->cart->is_empty()) return;

        // First, render cart items with mobile data
        $this->render_cart_items_with_mobile_data();

        $all_recommended = [];
        $all_interested = [];
        $cart_product_ids = [];

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $cart_product_ids[] = $product_id;

            $recommended = get_post_meta($product_id, '_wssc_recommended', true);
            $interested = get_post_meta($product_id, '_wssc_interested', true);

            if ($recommended) {
                $recommended_ids = array_map('trim', explode(',', $recommended));
                $all_recommended = array_merge($all_recommended, $recommended_ids);
            }

            if ($interested) {
                $interested_ids = array_map('trim', explode(',', $interested));
                $all_interested = array_merge($all_interested, $interested_ids);
            }
        }

        $all_recommended = array_unique(array_filter($all_recommended, function ($id) use ($cart_product_ids) {
            return !empty($id) && is_numeric($id) && !in_array((int)$id, $cart_product_ids);
        }));

        $all_interested = array_unique(array_filter($all_interested, function ($id) use ($cart_product_ids) {
            return !empty($id) && is_numeric($id) && !in_array((int)$id, $cart_product_ids);
        }));

        if (!empty($all_recommended)) {
            echo '<div class="wssc-section">';
            echo '<h4 class="wssc-section-title">Ye Bhi Jaruri he 🛒</h4>';
            echo '<div class="wssc-products-grid">';
            foreach ($all_recommended as $id) {
                $product = wc_get_product((int)$id);
                if ($product && $product->is_purchasable() && $product->is_in_stock()) {
                    $this->render_product_card($product, (int)$id);
                }
            }
            echo '</div></div>';
        }

        if (!empty($all_interested)) {
            echo '<div class="wssc-section">';
            echo '<h4 class="wssc-section-title">Hume bhi dekh lo! 👀</h4>';
            echo '<div class="wssc-products-grid">';
            foreach ($all_interested as $id) {
                $product = wc_get_product((int)$id);
                if ($product && $product->is_purchasable() && $product->is_in_stock()) {
                    $this->render_product_card($product, (int)$id);
                }
            }
            echo '</div></div>';
        }
    }

    public function render_bulk_button() {
        if (WC()->cart->is_empty()) return;

        $cart_product_ids = array_map(function($item) {
            return $item['product_id'];
        }, WC()->cart->get_cart());

        $main_product_id = $cart_product_ids[0] ?? 0;

        echo '<div class="wssc-bulk-btn-wrapper" style="margin-top: 10px; margin-bottom: 0; padding: 0;">';
        echo '<a href="#" class="button wssc-bulk-btn" data-product="' . esc_attr($main_product_id) . '">BUY BULK</a>';
        echo '</div>';
    }

    private function render_product_card($product, $product_id) {
        $qty = $this->get_cart_quantity($product_id);
        $price = $product->get_price_html();
        $image = $product->get_image('woocommerce_gallery_thumbnail');
        $name = $product->get_name();

        echo '<div class="wssc-product-card" data-id="' . esc_attr($product_id) . '">';
        echo '<div class="wssc-product-image">' . $image . '</div>';
        echo '<div class="wssc-product-info">';
        echo '<h5 class="wssc-product-name">' . esc_html($name) . '</h5>';
        echo '<div class="wssc-product-price">' . $price . '</div>';
        echo '</div>';
        echo '<div class="wssc-product-actions">';
        echo '<button type="button" class="wssc-add-btn" data-product-id="' . esc_attr($product_id) . '">+ ADD</button>';
        echo '</div>';

        if ($qty > 0) {
            echo '<span class="wssc-qty-badge">' . esc_html($qty) . '</span>';
        }

        echo '</div>';
    }

    // ADD THIS NEW METHOD - This is where the mobile data will be displayed
    public function render_cart_items_with_mobile_data() {
        if (WC()->cart->is_empty()) return;

        echo '<div class="wssc-section wssc-cart-section">';
        echo '<h4 class="wssc-section-title">Your Cart Items 🛒</h4>';
        echo '<div class="wssc-cart-items">';
        
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            
            // Get mobile brand and model data - check multiple possible keys
            $mobile_brand = '';
            $mobile_model = '';
            
            // Check various possible keys where mobile data might be stored
            if (isset($cart_item['mobile_brand'])) {
                $mobile_brand = $cart_item['mobile_brand'];
            }
            if (isset($cart_item['mobile_model'])) {
                $mobile_model = $cart_item['mobile_model'];
            }
            
            // Also check if it's stored in a different format
            if (empty($mobile_brand) && isset($cart_item['Mobile Brand'])) {
                $mobile_brand = $cart_item['Mobile Brand'];
            }
            if (empty($mobile_model) && isset($cart_item['Mobile Model'])) {
                $mobile_model = $cart_item['Mobile Model'];
            }
            
            echo '<div class="wssc-cart-item" data-key="' . esc_attr($cart_item_key) . '">';
            echo '<div class="wssc-product-image">' . $product->get_image('woocommerce_gallery_thumbnail') . '</div>';
            echo '<div class="wssc-cart-item-info">';
            echo '<h5 class="wssc-product-name">' . esc_html($product->get_name()) . '</h5>';
            echo '<div class="wssc-product-price">' . $product->get_price_html() . '</div>';
            echo '<div class="wssc-quantity">Qty: ' . esc_html($quantity) . '</div>';
            
            // Display mobile brand and model if available
            if (!empty($mobile_brand) || !empty($mobile_model)) {
                echo '<div class="wssc-mobile-info">';
                echo '<div class="wssc-mobile-title">📱 Mobile Info:</div>';
                if (!empty($mobile_brand)) {
                    echo '<div class="wssc-mobile-brand"><strong>Brand:</strong> ' . esc_html($mobile_brand) . '</div>';
                }
                if (!empty($mobile_model)) {
                    echo '<div class="wssc-mobile-model"><strong>Model:</strong> ' . esc_html($mobile_model) . '</div>';
                }
                echo '</div>';
            } else {
                echo '<div class="wssc-mobile-info wssc-no-mobile">';
                echo '<div class="wssc-mobile-debug">No mobile data found for this item</div>';
                echo '</div>';
            }
            
            // Display uploaded image if available
            if (isset($cart_item['wssc_image_id']) && !empty($cart_item['wssc_image_id'])) {
                global $wpdb;
                $table = $wpdb->prefix . 'wssc_product_images';
                $image = $wpdb->get_row($wpdb->prepare(
                    "SELECT image_name, image_path FROM $table WHERE id = %d",
                    $cart_item['wssc_image_id']
                ));
                
                if ($image) {
                    $upload_dir = wp_upload_dir();
                    $image_url = $upload_dir['baseurl'] . '/wssc-images/' . $image->image_name;
                    
                    echo '<div class="wssc-uploaded-image">';
                    echo '<div class="wssc-image-title">📸 Uploaded Image:</div>';
                    echo '<img src="' . esc_url($image_url) . '" alt="Uploaded Image" style="max-width: 80px; height: auto; border-radius: 4px; border: 1px solid #ddd; margin-top: 5px;">';
                    echo '</div>';
                }
            }
            
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    public function add_bulk_modal() {
        ?>
        <!-- Bulk Buy Modal - Added by WSSC Plugin -->
        <div id="wssc-bulk-modal" class="wssc-modal" style="display: none;">
            <div class="wssc-box">
                <h3>Request Bulk Purchase</h3>
                <form id="wssc-bulk-form">
                    <input type="hidden" id="bulk-product-id" name="product_id">
                    
                    <label for="bulk-name">Name *</label>
                    <input type="text" id="bulk-name" name="name" required>
                    
                    <label for="bulk-phone">Phone *</label>
                    <input type="tel" id="bulk-phone" name="phone" required>
                    
                    <label for="bulk-email">Email</label>
                    <input type="email" id="bulk-email" name="email">
                    
                    <label for="bulk-quantity">Quantity</label>
                    <input type="number" id="bulk-quantity" name="quantity" value="10" min="1">
                    
                    <label for="bulk-message">Message</label>
                    <textarea id="bulk-message" name="message" rows="3" placeholder="Any special requirements..."></textarea>
                    
                    <div style="margin-top: 15px;">
                        <button type="submit" class="button">Submit Request</button>
                        <button type="button" class="button wssc-cancel-bulk" id="wssc-cancel-bulk">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Debug Test Button -->
        <div style="position: fixed; top: 10px; right: 10px; z-index: 9999; background: #fff; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
            <button id="wssc-test-modal" style="background: #0073aa; color: #fff; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Test Modal</button>
            <div id="wssc-debug-info" style="font-size: 12px; margin-top: 5px;"></div>
        </div>
        
        <script>
        // Debug: Check if modal was added to DOM
        console.log('WSSC: Bulk modal HTML added to page');
        console.log('WSSC: Modal element exists:', document.getElementById('wssc-bulk-modal') ? 'Yes' : 'No');
        
        // Test button functionality
        document.addEventListener('DOMContentLoaded', function() {
            var testBtn = document.getElementById('wssc-test-modal');
            var debugInfo = document.getElementById('wssc-debug-info');
            
            if (testBtn) {
                testBtn.addEventListener('click', function() {
                    console.log('Test button clicked');
                    var modal = document.getElementById('wssc-bulk-modal');
                    if (modal) {
                        modal.style.display = 'flex';
                        modal.style.opacity = '1';
                        modal.style.visibility = 'visible';
                        debugInfo.innerHTML = 'Modal opened manually';
                    } else {
                        debugInfo.innerHTML = 'Modal not found';
                    }
                });
            }
            
            // Update debug info
            var modal = document.getElementById('wssc-bulk-modal');
            if (modal) {
                debugInfo.innerHTML = 'Modal found in DOM';
            } else {
                debugInfo.innerHTML = 'Modal NOT found in DOM';
            }
        });
        </script>
        <?php
    }

    private function get_cart_quantity($product_id) {
        foreach (WC()->cart->get_cart() as $item) {
            if ($item['product_id'] == $product_id) {
                return $item['quantity'];
            }
        }
        return 0;
    }
}
