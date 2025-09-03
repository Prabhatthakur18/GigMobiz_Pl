<?php
if (!defined('ABSPATH')) exit;

class WSSC_Mobile_Admin {
    private $mobile_selector;

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_wssc_upload_mobile_csv', [$this, 'handle_mobile_csv']);
        add_action('admin_post_wssc_upload_brands_csv', [$this, 'handle_brands_csv']);
        add_action('admin_post_wssc_upload_models_csv', [$this, 'handle_models_csv']);
        add_action('admin_post_wssc_add_brand', [$this, 'handle_add_brand']);
        add_action('admin_post_wssc_add_model', [$this, 'handle_add_model']);
        add_action('admin_post_wssc_delete_brand', [$this, 'handle_delete_brand']);
        add_action('admin_post_wssc_delete_model', [$this, 'handle_delete_model']);
    }
    
    private function get_mobile_selector() {
        global $wssc_mobile_selector;
        if (!$wssc_mobile_selector) {
            $wssc_mobile_selector = new WSSC_Mobile_Selector();
        }
        return $wssc_mobile_selector;
    }

    public function add_menu() {
        add_submenu_page(
            'wssc-settings', 
            'Mobile Selector', 
            'Mobile Selector',
            'manage_options', 
            'wssc-mobile-selector', 
            [$this, 'mobile_page']
        );
    }

    public function mobile_page() {
        $brands = $this->get_mobile_selector()->get_all_brands();
        ?>
        <div class="wrap">
            <h1>📱 Mobile Brands & Models Management</h1>
            <p class="description">Manage mobile brands and models for the mobile selector feature. This data is used when customers select their mobile device on product pages.</p>

            <?php if (isset($_GET['success'])): ?>
                <div class="wssc-admin-toast success">
                    ✅ <?php echo esc_html($_GET['message'] ?? 'Operation completed successfully!'); ?>
                </div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="wssc-admin-toast error">
                    ❌ <?php echo esc_html($_GET['message'] ?? 'An error occurred.'); ?>
                </div>
            <?php endif; ?>

            <!-- CSV Upload Section -->
            <div class="wssc-upload-section">
                <h3>📁 Upload CSV File</h3>
                <p>Upload CSV files for brands and models. Use the format from your sample files:</p>
                <ol>
                    <li><strong>Brands CSV:</strong> id, brand_name (e.g., 1, Samsung)</li>
                    <li><strong>Models CSV:</strong> id, brand_id, model_name (e.g., 1, 1, Samsung S8)</li>
                </ol>
                
                <!-- Brands CSV Upload -->
                <div style="margin-bottom: 20px;">
                    <h4>📱 Upload Brands CSV</h4>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" class="wssc-upload-form">
                        <input type="hidden" name="action" value="wssc_upload_brands_csv">
                        <?php wp_nonce_field('wssc_brands_csv', 'wssc_brands_nonce'); ?>
                        
                        <div class="upload-area">
                            <input type="file" name="wssc_brands_csv" id="wssc_brands_csv" required accept=".csv" class="file-input">
                            <label for="wssc_brands_csv" class="file-label">
                                <span class="file-icon">📁</span>
                                <span class="file-text">Choose Brands CSV File</span>
                            </label>
                        </div>
                        
                        <button type="submit" class="button button-primary button-large">
                            <span class="upload-icon">⬆️</span>
                            Upload Brands CSV
                        </button>
                    </form>
                </div>

                <!-- Models CSV Upload -->
                <div style="margin-bottom: 20px;">
                    <h4>📱 Upload Models CSV</h4>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" class="wssc-upload-form">
                        <input type="hidden" name="action" value="wssc_upload_models_csv">
                        <?php wp_nonce_field('wssc_models_csv', 'wssc_models_nonce'); ?>
                        
                        <div class="upload-area">
                            <input type="file" name="wssc_models_csv" id="wssc_models_csv" required accept=".csv" class="file-input">
                            <label for="wssc_models_csv" class="file-label">
                                <span class="file-icon">📁</span>
                                <span class="file-text">Choose Models CSV File</span>
                            </label>
                        </div>
                        
                        <button type="submit" class="button button-primary button-large">
                            <span class="upload-icon">⬆️</span>
                            Upload Models CSV
                        </button>
                    </form>
                </div>

                <!-- Combined Upload (Legacy) -->
                <div style="border-top: 1px solid #ddd; padding-top: 20px;">
                    <h4>📱 Upload Combined CSV (Legacy Format)</h4>
                    <p>Upload a single CSV with format: Brand Name, Model Name</p>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" class="wssc-upload-form">
                        <input type="hidden" name="action" value="wssc_upload_mobile_csv">
                        <?php wp_nonce_field('wssc_mobile_csv', 'wssc_mobile_nonce'); ?>
                        
                        <div class="upload-area">
                            <input type="file" name="wssc_mobile_csv" id="wssc_mobile_csv" required accept=".csv" class="file-input">
                            <label for="wssc_mobile_csv" class="file-label">
                                <span class="file-icon">📁</span>
                                <span class="file-text">Choose Combined CSV File</span>
                            </label>
                        </div>
                        
                        <button type="submit" class="button button-primary button-large">
                            <span class="upload-icon">⬆️</span>
                            Upload Combined CSV
                        </button>
                    </form>
                </div>
            </div>

            <!-- Manual Add Section -->
            <div class="wssc-upload-section">
                <h3>➕ Add Brand Manually</h3>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="wssc-upload-form">
                    <input type="hidden" name="action" value="wssc_add_brand">
                    <?php wp_nonce_field('wssc_add_brand', 'wssc_brand_nonce'); ?>
                    
                    <input type="text" name="brand_name" placeholder="Enter brand name" required style="padding: 8px 12px; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <button type="submit" class="button button-primary">Add Brand</button>
                </form>
            </div>

            <!-- Brands & Models Display -->
            <div class="wssc-upload-section">
                <h3>📱 Current Brands & Models</h3>
                
                <?php if (empty($brands)): ?>
                    <div class="no-requests">
                        <p>No brands found. Upload a CSV file or add brands manually.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($brands as $brand): ?>
                        <div class="wssc-brand-section" style="margin-bottom: 30px; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
                            <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 15px;">
                                <h4 style="margin: 0; color: #0073aa;">📱 <?php echo esc_html($brand->brand_name); ?></h4>
                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                                    <input type="hidden" name="action" value="wssc_delete_brand">
                                    <input type="hidden" name="brand_id" value="<?php echo $brand->id; ?>">
                                    <?php wp_nonce_field('wssc_delete_brand', 'wssc_delete_brand_nonce'); ?>
                                    <button type="submit" class="button button-small" onclick="return confirm('Delete this brand and all its models?')" style="background: #d63638; color: #fff;">🗑️ Delete Brand</button>
                                </form>
                            </div>

                            <!-- Add Model Form -->
                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-bottom: 15px;">
                                <input type="hidden" name="action" value="wssc_add_model">
                                <input type="hidden" name="brand_id" value="<?php echo $brand->id; ?>">
                                <?php wp_nonce_field('wssc_add_model', 'wssc_model_nonce'); ?>
                                
                                <input type="text" name="model_name" placeholder="Add new model" required style="padding: 6px 10px; margin-right: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <button type="submit" class="button button-secondary button-small">➕ Add Model</button>
                            </form>

                            <!-- Models List -->
                            <?php 
                            $models = $this->get_mobile_selector()->get_models_by_brand($brand->id);
                            if (!empty($models)): ?>
                                <div class="wssc-models-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">
                                    <?php foreach ($models as $model): ?>
                                        <div style="display: flex; justify-content: space-between; align-items: center; background: #f9f9f9; padding: 8px 12px; border-radius: 4px; border: 1px solid #eee;">
                                            <span><?php echo esc_html($model->model_name); ?></span>
                                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                                                <input type="hidden" name="action" value="wssc_delete_model">
                                                <input type="hidden" name="model_id" value="<?php echo $model->id; ?>">
                                                <?php wp_nonce_field('wssc_delete_model', 'wssc_delete_model_nonce'); ?>
                                                <button type="submit" class="button button-small" onclick="return confirm('Delete this model?')" style="background: none; border: none; color: #d63638; cursor: pointer; font-size: 14px;">🗑️</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p style="color: #666; font-style: italic;">No models added yet.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Shortcode Usage -->
            <div class="wssc-upload-section">
                <h3>🔧 Usage Instructions</h3>
                <p>To display the mobile selector on product pages, use this shortcode:</p>
                <code style="background: #f1f1f1; padding: 10px; display: block; border-radius: 4px;">[mobile_selector]</code>
                <p style="margin-top: 10px;"><strong>Optional parameters:</strong></p>
                <ul>
                    <li><code>required="false"</code> - Make selection optional</li>
                    <li><code>class="custom-class"</code> - Add custom CSS class</li>
                </ul>
                <p><strong>Example:</strong> <code>[mobile_selector required="false" class="my-mobile-selector"]</code></p>
            </div>
        </div>
        <?php
    }

    public function handle_mobile_csv() {
        if (!isset($_POST['wssc_mobile_nonce']) || !wp_verify_nonce($_POST['wssc_mobile_nonce'], 'wssc_mobile_csv')) {
            wp_die('Invalid nonce');
        }

        $success = false;
        $message = '';
        $brands_added = 0;
        $models_added = 0;

        if (!empty($_FILES['wssc_mobile_csv']['tmp_name'])) {
            $file = fopen($_FILES['wssc_mobile_csv']['tmp_name'], 'r');

            if ($file) {
                global $wpdb;
                $brand_table = $wpdb->prefix . 'wssc_mobile_brands';
                $model_table = $wpdb->prefix . 'wssc_mobile_models';

                while (($row = fgetcsv($file)) !== false) {
                    if (count($row) >= 2) {
                        $brand_name = trim($row[0]);
                        $model_name = trim($row[1]);

                        if (!empty($brand_name) && !empty($model_name)) {
                            // Get or create brand
                            $brand_id = $wpdb->get_var($wpdb->prepare(
                                "SELECT id FROM $brand_table WHERE brand_name = %s", 
                                $brand_name
                            ));

                            if (!$brand_id) {
                                $result = $wpdb->insert($brand_table, ['brand_name' => $brand_name]);
                                if ($result) {
                                    $brand_id = $wpdb->insert_id;
                                    $brands_added++;
                                }
                            }

                            // Add model if brand exists
                            if ($brand_id) {
                                $existing_model = $wpdb->get_var($wpdb->prepare(
                                    "SELECT id FROM $model_table WHERE brand_id = %d AND model_name = %s", 
                                    $brand_id, $model_name
                                ));

                                if (!$existing_model) {
                                    $result = $wpdb->insert($model_table, [
                                        'brand_id' => $brand_id,
                                        'model_name' => $model_name
                                    ]);
                                    if ($result) {
                                        $models_added++;
                                    }
                                }
                            }
                        }
                    }
                }

                fclose($file);
                $success = true;
                $message = "Added $brands_added brands and $models_added models successfully!";
            }
        }

        // Redirect with result
        $redirect_url = admin_url('admin.php?page=wssc-mobile-selector');
        if ($success) {
            $redirect_url .= '&success=1&message=' . urlencode($message);
        } else {
            $redirect_url .= '&error=1&message=' . urlencode('Failed to process CSV file');
        }

        wp_redirect($redirect_url);
        exit;
    }

    public function handle_brands_csv() {
        if (!isset($_POST['wssc_brands_nonce']) || !wp_verify_nonce($_POST['wssc_brands_nonce'], 'wssc_brands_csv')) {
            wp_die('Invalid nonce');
        }

        $success = false;
        $message = '';
        $brands_added = 0;

        if (!empty($_FILES['wssc_brands_csv']['tmp_name'])) {
            $file = fopen($_FILES['wssc_brands_csv']['tmp_name'], 'r');

            if ($file) {
                global $wpdb;
                $brand_table = $wpdb->prefix . 'wssc_mobile_brands';

                // Skip header row
                $header = fgetcsv($file);

                while (($row = fgetcsv($file)) !== false) {
                    if (count($row) >= 2) {
                        $brand_id = intval($row[0]);
                        $brand_name = trim($row[1]);

                        if (!empty($brand_name)) {
                            // Check if brand already exists
                            $existing = $wpdb->get_var($wpdb->prepare(
                                "SELECT id FROM $brand_table WHERE brand_name = %s", 
                                $brand_name
                            ));

                            if (!$existing) {
                                $result = $wpdb->insert($brand_table, ['brand_name' => $brand_name]);
                                if ($result) {
                                    $brands_added++;
                                }
                            }
                        }
                    }
                }

                fclose($file);
                $success = true;
                $message = "Added $brands_added brands successfully!";
            }
        }

        // Redirect with result
        $redirect_url = admin_url('admin.php?page=wssc-mobile-selector');
        if ($success) {
            $redirect_url .= '&success=1&message=' . urlencode($message);
        } else {
            $redirect_url .= '&error=1&message=' . urlencode('Failed to process brands CSV file');
        }

        wp_redirect($redirect_url);
        exit;
    }

    public function handle_models_csv() {
        if (!isset($_POST['wssc_models_nonce']) || !wp_verify_nonce($_POST['wssc_models_nonce'], 'wssc_models_csv')) {
            wp_die('Invalid nonce');
        }

        $success = false;
        $message = '';
        $models_added = 0;

        if (!empty($_FILES['wssc_models_csv']['tmp_name'])) {
            $file = fopen($_FILES['wssc_models_csv']['tmp_name'], 'r');

            if ($file) {
                global $wpdb;
                $brand_table = $wpdb->prefix . 'wssc_mobile_brands';
                $model_table = $wpdb->prefix . 'wssc_mobile_models';

                // Skip header row
                $header = fgetcsv($file);

                while (($row = fgetcsv($file)) !== false) {
                    if (count($row) >= 3) {
                        $model_id = intval($row[0]);
                        $brand_id = intval($row[1]);
                        $model_name = trim($row[2]);

                        if ($brand_id > 0 && !empty($model_name)) {
                            // Check if brand exists
                            $brand_exists = $wpdb->get_var($wpdb->prepare(
                                "SELECT id FROM $brand_table WHERE id = %d", 
                                $brand_id
                            ));

                            if ($brand_exists) {
                                // Check if model already exists
                                $existing_model = $wpdb->get_var($wpdb->prepare(
                                    "SELECT id FROM $model_table WHERE brand_id = %d AND model_name = %s", 
                                    $brand_id, $model_name
                                ));

                                if (!$existing_model) {
                                    $result = $wpdb->insert($model_table, [
                                        'brand_id' => $brand_id,
                                        'model_name' => $model_name
                                    ]);
                                    if ($result) {
                                        $models_added++;
                                    }
                                }
                            }
                        }
                    }
                }

                fclose($file);
                $success = true;
                $message = "Added $models_added models successfully!";
            }
        }

        // Redirect with result
        $redirect_url = admin_url('admin.php?page=wssc-mobile-selector');
        if ($success) {
            $redirect_url .= '&success=1&message=' . urlencode($message);
        } else {
            $redirect_url .= '&error=1&message=' . urlencode('Failed to process models CSV file');
        }

        wp_redirect($redirect_url);
        exit;
    }

    public function handle_add_brand() {
        if (!isset($_POST['wssc_brand_nonce']) || !wp_verify_nonce($_POST['wssc_brand_nonce'], 'wssc_add_brand')) {
            wp_die('Invalid nonce');
        }

        $brand_name = sanitize_text_field($_POST['brand_name']);
        $redirect_url = admin_url('admin.php?page=wssc-mobile-selector');

        if (!empty($brand_name)) {
            $result = $this->get_mobile_selector()->add_brand($brand_name);
            if ($result) {
                $redirect_url .= '&success=1&message=' . urlencode("Brand '$brand_name' added successfully!");
            } else {
                $redirect_url .= '&error=1&message=' . urlencode('Failed to add brand (may already exist)');
            }
        } else {
            $redirect_url .= '&error=1&message=' . urlencode('Brand name is required');
        }

        wp_redirect($redirect_url);
        exit;
    }

    public function handle_add_model() {
        if (!isset($_POST['wssc_model_nonce']) || !wp_verify_nonce($_POST['wssc_model_nonce'], 'wssc_add_model')) {
            wp_die('Invalid nonce');
        }

        $brand_id = intval($_POST['brand_id']);
        $model_name = sanitize_text_field($_POST['model_name']);
        $redirect_url = admin_url('admin.php?page=wssc-mobile-selector');

        if ($brand_id > 0 && !empty($model_name)) {
            $result = $this->get_mobile_selector()->add_model($brand_id, $model_name);
            if ($result) {
                $redirect_url .= '&success=1&message=' . urlencode("Model '$model_name' added successfully!");
            } else {
                $redirect_url .= '&error=1&message=' . urlencode('Failed to add model (may already exist)');
            }
        } else {
            $redirect_url .= '&error=1&message=' . urlencode('Invalid data provided');
        }

        wp_redirect($redirect_url);
        exit;
    }

    public function handle_delete_brand() {
        if (!isset($_POST['wssc_delete_brand_nonce']) || !wp_verify_nonce($_POST['wssc_delete_brand_nonce'], 'wssc_delete_brand')) {
            wp_die('Invalid nonce');
        }

        $brand_id = intval($_POST['brand_id']);
        $redirect_url = admin_url('admin.php?page=wssc-mobile-selector');

        if ($brand_id > 0) {
            $result = $this->get_mobile_selector()->delete_brand($brand_id);
            if ($result) {
                $redirect_url .= '&success=1&message=' . urlencode('Brand and all its models deleted successfully!');
            } else {
                $redirect_url .= '&error=1&message=' . urlencode('Failed to delete brand');
            }
        }

        wp_redirect($redirect_url);
        exit;
    }

    public function handle_delete_model() {
        if (!isset($_POST['wssc_delete_model_nonce']) || !wp_verify_nonce($_POST['wssc_delete_model_nonce'], 'wssc_delete_model')) {
            wp_die('Invalid nonce');
        }

        $model_id = intval($_POST['model_id']);
        $redirect_url = admin_url('admin.php?page=wssc-mobile-selector');

        if ($model_id > 0) {
            $result = $this->get_mobile_selector()->delete_model($model_id);
            if ($result) {
                $redirect_url .= '&success=1&message=' . urlencode('Model deleted successfully!');
            } else {
                $redirect_url .= '&error=1&message=' . urlencode('Failed to delete model');
            }
        }

        wp_redirect($redirect_url);
        exit;
    }
}