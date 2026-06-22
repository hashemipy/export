<?php
/**
 * Plugin Name: محصول Import/Export (نسخه اصلاح‌شده)
 * Description: دانلود و آپلود محصولات ساده و متغیر با ویژگی‌ها
 * Version: 3.0.0
 * Author: شما
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

class Product_Import_Export_Fixed {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('wp_ajax_pie_upload_products', [$this, 'handle_upload']);
    }
    
    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>نیاز به WooCommerce دارید!</p></div>';
            });
            return;
        }
    }
    
    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            'دانلود/آپلود محصول',
            'دانلود/آپلود محصول',
            'manage_woocommerce',
            'product-import-export',
            [$this, 'show_page']
        );
    }
    
    public function show_page() {
        ?>
        <div class="wrap">
            <h1>آپلود محصولات</h1>
            <form id="import-form" enctype="multipart/form-data">
                <input type="file" id="json-file" name="json_file" accept=".json" required>
                <button type="submit" class="button button-primary">آپلود</button>
            </form>
            <div id="upload-result"></div>
        </div>
        <script>
            document.getElementById('import-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const file = document.getElementById('json-file').files[0];
                const formData = new FormData();
                formData.append('action', 'pie_upload_products');
                formData.append('json_file', file);
                formData.append('nonce', '<?php echo wp_create_nonce("pie_upload"); ?>');
                
                fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    document.getElementById('upload-result').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                })
                .catch(e => console.error(e));
            });
        </script>
        <?php
    }
    
    public function handle_upload() {
        check_ajax_referer('pie_upload');
        
        if (!isset($_FILES['json_file'])) {
            wp_send_json_error('فایل انتخاب نشده');
        }
        
        $file = $_FILES['json_file'];
        $content = file_get_contents($file['tmp_name']);
        $products = json_decode($content, true);
        
        if (!is_array($products)) {
            wp_send_json_error('فایل JSON معتبر نیست');
        }
        
        $results = [
            'success' => 0,
            'failed' => 0,
            'products' => []
        ];
        
        foreach ($products as $product_data) {
            $result = $this->import_product($product_data);
            if (is_wp_error($result)) {
                $results['failed']++;
                $results['products'][] = [
                    'name' => $product_data['name'] ?? 'Unknown',
                    'status' => 'failed',
                    'error' => $result->get_error_message()
                ];
            } else {
                $results['success']++;
                $results['products'][] = [
                    'name' => $product_data['name'] ?? 'Unknown',
                    'status' => 'success',
                    'id' => $result
                ];
            }
        }
        
        wp_send_json_success($results);
    }
    
    private function import_product($product_data) {
        try {
            $product_type = $product_data['type'] ?? 'simple';
            
            // ⭐ مرحله ۱: ایجاد دسته‌بندی‌ها قبل از هر چیز
            $category_ids = [];
            if (!empty($product_data['categories'])) {
                foreach ($product_data['categories'] as $category) {
                    $cat_id = $this->ensure_category_exists($category);
                    if ($cat_id) {
                        $category_ids[] = $cat_id;
                    }
                }
            }
            
            // ⭐ مرحله ۲: sync کردن ویژگی‌ها (قبل از ایجاد محصول!)
            $attribute_map = []; // mapping: site1_attr_id => site2_attr_id
            if ($product_type === 'variable' && !empty($product_data['attributes'])) {
                foreach ($product_data['attributes'] as $attribute) {
                    $attr_id = $this->ensure_attribute_exists($attribute);
                    if ($attr_id) {
                        $attribute_map[$attribute['id']] = $attr_id;
                    }
                }
            }
            
            // ⭐ مرحله ۳: ایجاد محصول
            $post_data = [
                'post_type' => 'product',
                'post_title' => $product_data['name'] ?? '',
                'post_content' => $product_data['description'] ?? '',
                'post_excerpt' => $product_data['short_description'] ?? '',
                'post_status' => 'publish'
            ];
            
            $post_id = wp_insert_post($post_data);
            if (is_wp_error($post_id)) {
                return $post_id;
            }
            
            // تعیین دسته‌بندی
            if (!empty($category_ids)) {
                wp_set_post_terms($post_id, $category_ids, 'product_cat');
            }
            
            // ایجاد WC_Product object
            $product = wc_get_product($post_id);
            if (!$product) {
                return new WP_Error('product_creation_failed', 'نتوانستیم محصول ایجاد کنیم');
            }
            
            // تنظیم داده‌های عمومی
            $product->set_type($product_type);
            $product->set_sku($product_data['sku'] ?? '');
            $product->set_regular_price($product_data['regular_price'] ?? 0);
            if (!empty($product_data['sale_price'])) {
                $product->set_sale_price($product_data['sale_price']);
            }
            
            // ⭐ مرحله ۴: برای محصولات متغیّر، ویژگی‌ها را تنظیم کنید
            if ($product_type === 'variable' && !empty($product_data['attributes'])) {
                $wc_attributes = [];
                
                foreach ($product_data['attributes'] as $attribute) {
                    $attr_slug = 'pa_' . sanitize_title($attribute['name']);
                    $term_ids = [];
                    
                    // اطمینان حاصل کنید که terms برای این attribute وجود دارند
                    if (!empty($attribute['options'])) {
                        foreach ($attribute['options'] as $option) {
                            $term = get_term_by('name', $option, $attr_slug);
                            if (!$term) {
                                $term = wp_insert_term($option, $attr_slug);
                            }
                            if ($term && !is_wp_error($term)) {
                                $term_id = is_array($term) ? $term['term_id'] : $term->term_id;
                                $term_ids[] = $term_id;
                            }
                        }
                    }
                    
                    // set post terms
                    if (!empty($term_ids)) {
                        wp_set_post_terms($post_id, $term_ids, $attr_slug);
                    }
                    
                    // set product attributes
                    $wc_attributes[$attr_slug] = [
                        'name' => $attr_slug,
                        'value' => implode(' | ', $attribute['options'] ?? []),
                        'position' => 0,
                        'visible' => true,
                        'variation' => true
                    ];
                }
                
                $product->set_attributes($wc_attributes);
            }
            
            // دانلود و تنظیم تصاویر
            if (!empty($product_data['images'])) {
                $image_ids = [];
                foreach ($product_data['images'] as $idx => $image_url) {
                    $image_id = $this->download_and_attach_image($image_url, $post_id);
                    if ($image_id) {
                        $image_ids[] = $image_id;
                    }
                }
                
                if (!empty($image_ids)) {
                    $product->set_image_id($image_ids[0]);
                    if (count($image_ids) > 1) {
                        $product->set_gallery_image_ids(array_slice($image_ids, 1));
                    }
                }
            }
            
            // save محصول
            $product->save();
            
            // ⭐ مرحله ۵: ایجاد متغیّرها (بعد از save کردن محصول!)
            if ($product_type === 'variable' && !empty($product_data['variations'])) {
                foreach ($product_data['variations'] as $variation_data) {
                    $this->create_variation($post_id, $variation_data, $attribute_map);
                }
            }
            
            return $post_id;
            
        } catch (Exception $e) {
            return new WP_Error('import_error', $e->getMessage());
        }
    }
    
    private function ensure_category_exists($category) {
        $cat_name = $category['name'] ?? '';
        $parent_id = 0;
        
        // اگر parent category وجود داشت
        if (!empty($category['parent']) && is_array($category['parent'])) {
            $parent_id = $this->ensure_category_exists($category['parent']);
        }
        
        $existing = get_term_by('name', $cat_name, 'product_cat');
        if ($existing) {
            return $existing->term_id;
        }
        
        $result = wp_insert_term($cat_name, 'product_cat', ['parent' => $parent_id]);
        if (is_wp_error($result)) {
            return 0;
        }
        
        return is_array($result) ? $result['term_id'] : $result->term_id;
    }
    
    private function ensure_attribute_exists($attribute) {
        $attr_name = $attribute['name'] ?? '';
        $attr_slug = sanitize_title($attr_name);
        
        // بررسی اینکه attribute وجود دارد یا نه
        $attributes = wc_get_attribute_taxonomies();
        foreach ($attributes as $attr) {
            if ($attr->attribute_name === $attr_slug) {
                return $attr->attribute_id;
            }
        }
        
        // ایجاد attribute جدید
        $attr_id = wc_create_attribute([
            'name' => $attr_name,
            'slug' => $attr_slug,
            'type' => 'select',
            'orderby' => 'name',
            'has_archives' => false
        ]);
        
        if (is_wp_error($attr_id)) {
            return 0;
        }
        
        // اضافه کردن options/terms برای attribute
        if (!empty($attribute['options'])) {
            $taxonomy = 'pa_' . $attr_slug;
            foreach ($attribute['options'] as $option) {
                $term = get_term_by('name', $option, $taxonomy);
                if (!$term) {
                    wp_insert_term($option, $taxonomy);
                }
            }
        }
        
        return $attr_id;
    }
    
    private function create_variation($parent_id, $variation_data, $attribute_map) {
        try {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($parent_id);
            
            // ⭐ بسیار مهم: ویژگی‌های متغیّر
            $attributes = [];
            if (!empty($variation_data['attributes'])) {
                foreach ($variation_data['attributes'] as $attr) {
                    $attr_name = $attr['name'] ?? '';
                    $attr_value = $attr['option'] ?? '';
                    
                    if (!empty($attr_name) && !empty($attr_value)) {
                        $attr_slug = 'pa_' . sanitize_title($attr_name);
                        $attributes[$attr_slug] = $attr_value;
                    }
                }
            }
            
            if (empty($attributes)) {
                return false; // نمی‌تواند variation بدون attributes ایجاد شود
            }
            
            $variation->set_attributes($attributes);
            
            // قیمت
            if (!empty($variation_data['regular_price'])) {
                $variation->set_price($variation_data['regular_price']);
            }
            
            // تخفیف
            if (!empty($variation_data['sale_price'])) {
                $variation->set_sale_price($variation_data['sale_price']);
            }
            
            // موجودی
            $stock = intval($variation_data['stock_quantity'] ?? 0);
            $variation->set_stock_quantity($stock);
            $variation->set_manage_stock(true);
            $variation->set_stock_status('instock');
            
            // SKU
            if (!empty($variation_data['sku'])) {
                $variation->set_sku($variation_data['sku']);
            }
            
            // تصویر
            if (!empty($variation_data['image']['src'])) {
                $image_id = $this->download_and_attach_image($variation_data['image']['src'], 0);
                if ($image_id) {
                    $variation->set_image_id($image_id);
                }
            }
            
            $variation->save();
            return true;
            
        } catch (Exception $e) {
            error_log('Variation error: ' . $e->getMessage());
            return false;
        }
    }
    
    private function download_and_attach_image($image_url, $post_id = 0) {
        if (empty($image_url)) {
            return 0;
        }
        
        // بررسی اینکه آیا تصویر قبلاً دانلود شده
        $existing = $this->find_image_by_url($image_url);
        if ($existing) {
            return $existing;
        }
        
        // دانلود تصویر
        $response = wp_remote_get($image_url);
        if (is_wp_error($response)) {
            return 0;
        }
        
        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            return 0;
        }
        
        // ذخیره به صورت temp
        $tmp_file = wp_tempnam();
        file_put_contents($tmp_file, $image_data);
        
        // سازی attachment
        $filename = basename($image_url);
        $file_array = [
            'name' => $filename,
            'tmp_name' => $tmp_file
        ];
        
        $attachment_id = media_handle_sideload($file_array, $post_id);
        
        @unlink($tmp_file);
        
        if (is_wp_error($attachment_id)) {
            return 0;
        }
        
        // ذخیره URL برای reference
        update_post_meta($attachment_id, '_source_image_url', $image_url);
        
        return $attachment_id;
    }
    
    private function find_image_by_url($image_url) {
        global $wpdb;
        $attachment_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_source_image_url' AND meta_value = %s",
                $image_url
            )
        );
        return $attachment_id ? intval($attachment_id) : 0;
    }
}

// فعال‌سازی plugin
Product_Import_Export_Fixed::get_instance();
