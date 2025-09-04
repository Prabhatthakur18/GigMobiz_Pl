<?php
if (!defined('ABSPATH')) exit;

class WSSC_Checkout {
    public function __construct() {
        // Add checkout integration
        add_action('woocommerce_review_order_before_payment', [$this, 'display_uploaded_images_checkout']);
        add_action('woocommerce_checkout_order_review', [$this, 'display_uploaded_images_checkout_review']);
        
        // Add styles for checkout page
        add_action('wp_head', [$this, 'add_checkout_styles']);
    }
    
    public function display_uploaded_images_checkout() {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }
        
        $uploaded_images = $this->get_cart_uploaded_images();
        
        if (empty($uploaded_images)) {
            return;
        }
        
        echo '<div class="wssc-checkout-images">';
        echo '<h3>📸 Your Uploaded Images</h3>';
        echo '<div class="wssc-images-grid">';
        
        foreach ($uploaded_images as $image) {
            $upload_dir = wp_upload_dir();
            $image_url = $upload_dir['baseurl'] . '/wssc-images/' . $image['image_name'];
            
            echo '<div class="wssc-image-item">';
            echo '<img src="' . esc_url($image_url) . '" alt="Uploaded Image">';
            echo '<div class="wssc-image-details">';
            echo '<strong>' . esc_html($image['product_name']) . '</strong><br>';
            if (!empty($image['mobile_brand']) && !empty($image['mobile_model'])) {
                echo '<small>' . esc_html($image['mobile_brand']) . ' - ' . esc_html($image['mobile_model']) . '</small>';
            }
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    public function display_uploaded_images_checkout_review() {
        // Alternative placement for themes that don't support the first hook
        if (!did_action('woocommerce_review_order_before_payment')) {
            $this->display_uploaded_images_checkout();
        }
    }
    
    private function get_cart_uploaded_images() {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return [];
        }
        
        $uploaded_images = [];
        global $wpdb;
        $table = $wpdb->prefix . 'wssc_product_images';
        
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (!empty($cart_item['wssc_image_id'])) {
                $image = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table WHERE id = %d",
                    $cart_item['wssc_image_id']
                ));
                
                if ($image) {
                    $product = wc_get_product($cart_item['product_id']);
                    $uploaded_images[] = [
                        'image_name' => $image->image_name,
                        'image_path' => $image->image_path,
                        'product_name' => $product ? $product->get_name() : 'Unknown Product',
                        'mobile_brand' => $image->mobile_brand,
                        'mobile_model' => $image->mobile_model
                    ];
                }
            }
        }
        
        return $uploaded_images;
    }
    
    public function add_checkout_styles() {
        if (!is_checkout()) {
            return;
        }
        ?>
        <style>
        .wssc-checkout-images {
            background: #f8f9fa;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .wssc-checkout-images h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 18px;
        }
        
        .wssc-images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .wssc-image-item {
            background: white;
            border-radius: 6px;
            padding: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .wssc-image-item img {
            max-width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 4px;
            margin-bottom: 8px;
        }
        
        .wssc-image-details {
            font-size: 14px;
            line-height: 1.4;
        }
        
        .wssc-image-details strong {
            color: #333;
        }
        
        .wssc-image-details small {
            color: #666;
        }
        
        @media (max-width: 768px) {
            .wssc-images-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 10px;
            }
            
            .wssc-image-item img {
                height: 100px;
            }
        }
        </style>
        <?php
    }
}

// Initialize the checkout class
new WSSC_Checkout();