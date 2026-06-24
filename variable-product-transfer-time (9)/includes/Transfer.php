<?php
/**
 * سیستم انتقال مستقیم محصولات
 * Direct Product Transfer Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class PIE_Transfer {
    
    private static $instance = null;
    private $settings = null;
    private $logging = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        $this->settings = PIE_Settings::get_instance();
        $this->logging = PIE_Logging::get_instance();
        
        // AJAX handlers
        add_action('wp_ajax_pie_send_products', [$this, 'handle_send_products']);
        add_action('wp_ajax_pie_pending_products', [$this, 'handle_pending_products']);
        add_action('wp_ajax_pie_approve_upload', [$this, 'handle_approve_upload']);
        add_action('wp_ajax_pie_confirm_preview', [$this, 'handle_confirm_preview']);
    }
    
    /**
     * ارسال محصولات به سایت دیگر
     */
    public function handle_send_products() {
        check_ajax_referer('pie_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('شما دسترسی ندارید');
        }
        
        $config = $this->settings->get_config();
        
        if ($config['site_role'] !== 'site1') {
            wp_send_json_error('این عملیات فقط برای سایت ۱ در دسترس است');
        }
        
        if (empty($config['remote_site_url']) || empty($config['remote_api_key'])) {
            $this->logging->log('send', 'failed', 'تنظیمات API ناقص است');
            wp_send_json_error('تنظیمات API ناقص است. لطفا به تنظیمات رفته و کلید API سایت دیگر را وارد کنید.');
        }
        
        $product_ids = isset($_POST['product_ids']) ? explode(',', sanitize_text_field($_POST['product_ids'])) : [];
        $product_ids = array_map('intval', $product_ids);
        
        if (empty($product_ids)) {
            wp_send_json_error('هیچ محصولی انتخاب نشده است');
        }
        
        $success = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) {
                $failed++;
                $this->logging->log('send', 'failed', "محصول ID {$product_id} پیدا نشد", [
                    'product_id' => $product_id,
                    'error_code' => 'PRODUCT_NOT_FOUND'
                ]);
                continue;
            }
            
            $result = $this->send_product_to_site2($product_id, $product, $config);
            if ($result['success']) {
                $success++;
                $this->logging->log('send', 'success', "محصول '{$product->get_name()}' برای preview آماده شد", [
                    'product_id' => $product_id,
                    'product_name' => $product->get_name(),
                    'preview_key' => $result['preview_key'] ?? '',
                    'request_data' => $result['request_data'] ?? [],
                    'response_data' => $result['response_data'] ?? []
                ]);

                // ثبت خودکار mapping موجودی
                $response_data   = $result['response_data'] ?? [];
                $s2_product_id   = $response_data['product_id']    ?? null;
                $s2_variation_ids = $response_data['variation_ids'] ?? [];

                if ($s2_product_id) {
                    $stock_sync = PIE_StockSync::get_instance();

                    if ($product->is_type('variable') && !empty($s2_variation_ids)) {
                        // محصول متغیر: mapping برای هر variation بر اساس SKU
                        foreach ($product->get_children() as $s1_var_id) {
                            $s1_variation = wc_get_product($s1_var_id);
                            if (!$s1_variation) continue;

                            $var_sku  = $s1_variation->get_sku();
                            $s2_var_id = $s2_variation_ids[$var_sku] ?? null;

                            if ($s2_var_id) {
                                $attrs = [];
                                foreach ($s1_variation->get_variation_attributes() as $attr => $val) {
                                    $attrs[] = wc_attribute_label(str_replace('attribute_', '', $attr)) . ':' . $val;
                                }
                                $stock_sync->register_mapping(
                                    $product_id,
                                    $s2_product_id,
                                    $s1_var_id,
                                    $s2_var_id,
                                    $product->get_name(),
                                    implode('|', $attrs)
                                );
                            }
                        }
                    } else {
                        // محصول ساده
                        $stock_sync->register_mapping(
                            $product_id,
                            $s2_product_id,
                            null,
                            null,
                            $product->get_name()
                        );
                    }
                }
            } else {
                $failed++;
                $errors[] = $result['error'];
                $this->logging->log('send', 'failed', "محصول '{$product->get_name()}': {$result['error']}", [
                    'product_id' => $product_id,
                    'product_name' => $product->get_name(),
                    'error_code' => $result['error_code'] ?? 'UNKNOWN_ERROR',
                    'details' => $result['details'] ?? [],
                    'request_data' => $result['request_data'] ?? [],
                    'response_data' => $result['response_data'] ?? []
                ]);
            }
        }
        
        wp_send_json_success([
            'success' => $success,
            'failed' => $failed,
            'message' => "{$success} محصول برای preview آماده شد" . ($failed > 0 ? ", {$failed} ناموفق" : ""),
            'preview_key' => $product_ids[0] ? $result['preview_key'] ?? null : null,
            'product_data' => $product_ids[0] ? $result['product_data'] ?? null : null
        ]);
    }
    
    /**
     * ارسال یک محصول
     * از همان ساختار JSON که export دستی تولید می‌کند استفاده می‌شود
     * تا سایت ۲ دقیقاً مثل آپلود دستی آن را import کند
     */
    private function send_product_to_site2($product_id, $product, $config) {
        try {
            // استفاده از همان تابع export که دانلود دستی استفاده می‌کند
            // این تضمین می‌کند ساختار JSON ارسالی دقیقاً مثل فایل JSON دستی باشد
            $plugin = Product_Import_Export::get_instance();
            $product_data = $plugin->get_products_for_export_public([$product_id]);
            if (empty($product_data)) {
                return ['success' => false, 'error' => 'داده محصول خالی است', 'error_code' => 'EMPTY_EXPORT'];
            }
            
            // URL Endpoint - استفاده از preview برای نمایش کاربر
            $endpoint = rtrim($config['remote_site_url'], '/') . '/wp-json/pie/v1/preview-product';
            
            // بررسی credentials - کلیدهای جدید
            if (empty($config['remote_api_key']) || empty($config['remote_api_secret'])) {
                $error = 'کلید API سایت مقابل تنظیم نشده است';
                error_log("[PIE] ERROR: {$error}");
                error_log("[PIE] remote_api_key empty: " . (empty($config['remote_api_key']) ? 'YES' : 'NO'));
                error_log("[PIE] remote_api_secret empty: " . (empty($config['remote_api_secret']) ? 'YES' : 'NO'));
                return [
                    'success' => false,
                    'error' => $error,
                    'error_code' => 'MISSING_API_CREDENTIALS'
                ];
            }
            
            // ساخت auth header
            $key = trim($config['remote_api_key']);
            $secret = trim($config['remote_api_secret']);
            
            if (empty($key) || empty($secret)) {
                error_log("[PIE] ERROR: Credentials empty after trim");
                return [
                    'success' => false,
                    'error' => 'کلید API خالی است',
                    'error_code' => 'EMPTY_AFTER_TRIM'
                ];
            }
            
            $auth = base64_encode($key . ':' . $secret);
            
            error_log("[PIE] Sending product {$product_id} to: {$endpoint}");
            error_log("[PIE] API Key starts with: " . substr($key, 0, 15));
            
            // ارسال POST
            $response = wp_remote_post(
                $endpoint,
                [
                    'method' => 'POST',
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Basic ' . $auth
                    ],
                    'body' => wp_json_encode($product_data),
                    'timeout' => 30,
                    'sslverify' => false,
                    'blocking' => true
                ]
            );
            
            // بررسی خطاهای شبکه
            if (is_wp_error($response)) {
                $error_msg = $response->get_error_message();
                error_log("[PIE] Network error: " . $error_msg);
                return [
                    'success' => false,
                    'error' => 'خطای شبکه: ' . $error_msg,
                    'error_code' => 'NETWORK_ERROR',
                    'details' => ['error_codes' => $response->get_error_codes()],
                    'request_data' => ['endpoint' => $endpoint]
                ];
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $response_data = json_decode($body, true);
            
            error_log("[PIE] Response code: " . $http_code);
            error_log("[PIE] Response body: " . $body);
            
            if ($http_code === 200 || $http_code === 201) {
                return [
                    'success' => true,
                    'request_data' => ['endpoint' => $endpoint, 'auth_header' => 'Basic ***'],
                    'response_data' => $response_data,
                    'preview_key' => $response_data['preview_key'] ?? null,
                    'product_data' => $response_data['product_data'] ?? null
                ];
            } else {
                $error = isset($response_data['message']) ? $response_data['message'] : "خطای سرور ({$http_code})";
                error_log("[PIE] Error response: " . $error);
                
                return [
                    'success' => false,
                    'error' => $error,
                    'error_code' => 'HTTP_' . $http_code,
                    'details' => [
                        'http_code' => $http_code,
                        'response_type' => gettype($response_data),
                        'response_keys' => is_array($response_data) ? array_keys($response_data) : 'N/A'
                    ],
                    'request_data' => ['endpoint' => $endpoint],
                    'response_data' => $response_data
                ];
            }
            
        } catch (Exception $e) {
            $error_msg = $e->getMessage();
            error_log("[PIE] Exception: " . $error_msg);
            
            return [
                'success' => false,
                'error' => 'خطا: ' . $error_msg,
                'error_code' => 'EXCEPTION',
                'details' => [
                    'exception_file' => $e->getFile(),
                    'exception_line' => $e->getLine()
                ]
            ];
        }
    }
    
    /**
     * تهیه داده‌های محصول
     */
    private function prepare_product_data($product) {
        $data = [
            'name' => $product->get_name(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'sku' => $product->get_sku(),
            'stock_quantity' => $product->get_stock_quantity(),
            'manage_stock' => $product->get_manage_stock(),
            'type' => $product->get_type(),
            'categories' => wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']),
            'tags' => wp_get_post_terms($product->get_id(), 'product_tag', ['fields' => 'names']),
            'images' => [],
            'attributes' => []
        ];
        
        // تصاویر
        $image_id = $product->get_image_id();
        if ($image_id) {
            $data['images'][] = [
                'url' => wp_get_attachment_image_url($image_id, 'full'),
                'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true)
            ];
        }
        
        // تصاویر اضافی
        foreach ($product->get_gallery_image_ids() as $image_id) {
            $data['images'][] = [
                'url' => wp_get_attachment_image_url($image_id, 'full'),
                'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true)
            ];
        }
        
        // برای محصولات متغیر
        if ($product->is_type('variable')) {
            // ⭐ استخراج attributes محصول - دقیقاً مثل دستی export
            $product_attributes = $product->get_attributes();
            foreach ($product_attributes as $attr_obj) {
                $attr_name = $attr_obj->get_name();
                // حذف 'pa_' prefix
                $attr_name_clean = str_replace('pa_', '', $attr_name);
                
                $values = [];
                $options = $attr_obj->get_options();
                
                foreach ($options as $option) {
                    // $option می‌تواند ID یا slug باشد
                    if (is_numeric($option)) {
                        $term = get_term($option);
                    } else {
                        $term = get_term_by('slug', $option, $attr_name);
                    }
                    
                    if ($term && !is_wp_error($term)) {
                        $values[] = [
                            'name' => $term->name,
                            'slug' => $term->slug
                        ];
                    }
                }
                
                if (!empty($values)) {
                    $data['attributes'][$attr_name_clean] = [
                        'values' => $values,
                        'visible' => $attr_obj->get_visible()
                    ];
                }
            }
            
            // ⭐ variations
            $data['variations'] = [];
            foreach ($product->get_children() as $variation_id) {
                $variation = wc_get_product($variation_id);
                $data['variations'][] = [
                    'sku' => $variation->get_sku(),
                    'price' => $variation->get_price(),
                    'regular_price' => $variation->get_regular_price(),
                    'stock_quantity' => $variation->get_stock_quantity(),
                    'attributes' => $variation->get_attributes()
                ];
            }
        }
        
        return $data;
    }
    
    /**
     * دریافت لیست محصولات منتظر (برای Site 2)
     */
    public function handle_pending_products() {
        check_ajax_referer('pie_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('شما دسترسی ندارید');
        }
        
        $pending = get_transient('pie_pending_products') ?: [];
        wp_send_json_success($pending);
    }
    
    /**
     * تأیید و آپلود محصول (برای Site 2)
     */
    public function handle_approve_upload() {
        check_ajax_referer('pie_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('شما دسترسی ندارید');
        }
        
        $product_key = sanitize_text_field($_POST['product_key'] ?? '');
        
        if (empty($product_key)) {
            wp_send_json_error('محصول مشخص نشده است');
        }
        
        $pending = get_transient('pie_pending_products') ?: [];
        
        if (!isset($pending[$product_key])) {
            wp_send_json_error('محصول پیدا نشد');
        }
        
        $product_data = $pending[$product_key];
        
        // ایجاد محصول
        $result = $this->create_woocommerce_product($product_data);
        
        if ($result['success']) {
            unset($pending[$product_key]);
            set_transient('pie_pending_products', $pending, WEEK_IN_SECONDS);
            $this->logging->log('upload', 'success', "محصول {$product_data['name']} آپلود شد");
            wp_send_json_success(['product_id' => $result['product_id']]);
        } else {
            $this->logging->log('upload', 'error', $result['error']);
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * ایجاد محصول از JSON - متد عمومی برای استفاده از بیرون
     */
    public function create_product_from_json($data) {
        return $this->create_woocommerce_product($data);
    }
    
    /**
     * ایجاد محصول WooCommerce - حفظ تمام انواع محصولات و variations
     */
    private function create_woocommerce_product($data) {
        try {
            // تعیین نوع محصول
            $product_type = $data['type'] ?? 'simple';
            
            // ایجاد محصول بر اساس نوع آن
            if ($product_type === 'variable') {
                $product = new WC_Product_Variable();
            } else {
                $product = new WC_Product_Simple();
            }
            
            // تنظیم فیلدهای عمومی
            $product->set_name($data['name'] ?? '');
            $product->set_description($data['description'] ?? '');
            $product->set_short_description($data['short_description'] ?? '');
            $product->set_regular_price($data['regular_price'] ?? 0);
            $product->set_sale_price($data['sale_price'] ?? '');
            $product->set_sku($data['sku'] ?? '');
            $product->set_stock_quantity($data['stock_quantity'] ?? 0);
            $product->set_manage_stock(!empty($data['manage_stock']));
            
            // دسته‌بندی‌ها
            if (!empty($data['categories'])) {
                $category_ids = [];
                foreach ($data['categories'] as $cat_name) {
                    $term = get_term_by('name', $cat_name, 'product_cat');
                    if (!$term) {
                        $term = wp_insert_term($cat_name, 'product_cat');
                        if (!is_wp_error($term)) {
                            $category_ids[] = $term['term_id'];
                        }
                    } else {
                        $category_ids[] = $term->term_id;
                    }
                }
                if (!empty($category_ids)) {
                    $product->set_category_ids($category_ids);
                }
            }
            
            // برچسب‌ها
            if (!empty($data['tags'])) {
                $tag_ids = [];
                foreach ($data['tags'] as $tag_name) {
                    $term = get_term_by('name', $tag_name, 'product_tag');
                    if (!$term) {
                        $term = wp_insert_term($tag_name, 'product_tag');
                        if (!is_wp_error($term)) {
                            $tag_ids[] = $term['term_id'];
                        }
                    } else {
                        $tag_ids[] = $term->term_id;
                    }
                }
                if (!empty($tag_ids)) {
                    $product->set_tag_ids($tag_ids);
                }
            }
            
            // ذخیره محصول
            $product_id = $product->save();
            
            if (!$product_id) {
                throw new Exception('خطا در ایجاد محصول');
            }
            
            // تصاویر
            if (!empty($data['images'])) {
                $featured_set = false;
                foreach ($data['images'] as $index => $image) {
                    $image_id = $this->download_and_attach_image($image['url'] ?? '', $product_id);
                    if ($image_id && !$featured_set) {
                        $product->set_image_id($image_id);
                        $featured_set = true;
                    } else if ($image_id && $index > 0) {
                        // افزودن به گالری
                        add_post_meta($product_id, '_product_image_gallery', $image_id);
                    }
                }
                if ($featured_set) {
                    $product->save();
                }
            }
            
            // محصولات متغیر - ایجاد variations
            if ($product_type === 'variable' && !empty($data['variations'])) {
                $this->create_variations($product_id, $data['variations']);
            }
            
            return ['success' => true, 'product_id' => $product_id];
            
        } catch (Exception $e) {
            error_log("[PIE] Create product error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * ایجاد variations برای محصول متغیر
     */
    private function create_variations($product_id, $variations) {
        foreach ($variations as $var_data) {
            try {
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($product_id);
                $variation->set_sku($var_data['sku'] ?? '');
                $variation->set_regular_price($var_data['price'] ?? 0);
                $variation->set_stock_quantity($var_data['stock_quantity'] ?? 0);
                
                // تنظیم attributes
                if (!empty($var_data['attributes'])) {
                    foreach ($var_data['attributes'] as $attr_key => $attr_value) {
                        $variation->set_attributes([
                            $attr_key => $attr_value
                        ]);
                    }
                }
                
                $variation->save();
            } catch (Exception $e) {
                error_log("[PIE] Create variation error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * دانلود و ضمیمه تصویر
     */
    private function download_and_attach_image($url, $product_id, $featured = false) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        
        $tmp = download_url($url);
        
        if (is_wp_error($tmp)) {
            error_log("[PIE] Image download failed: " . $tmp->get_error_message());
            return false;
        }
        
        $file_array = [
            'name' => basename($url),
            'tmp_name' => $tmp
        ];
        
        $attachment_id = media_handle_sideload($file_array, $product_id);
        
        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            error_log("[PIE] Media handle failed: " . $attachment_id->get_error_message());
            return false;
        }
        
        return $attachment_id;
    }
    
    /**
     * تأیید و آپلود محصول دریافت‌شده از preview
     * Confirm and upload product from preview (for Site 2)
     */
    public function handle_confirm_preview() {
        check_ajax_referer('pie_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('شما دسترسی ندارید');
        }
        
        $config = $this->settings->get_config();
        
        if ($config['site_role'] !== 'site2') {
            wp_send_json_error('این عملیات فقط برای سایت ۲ در دسترس است');
        }
        
        $preview_key = sanitize_text_field($_POST['preview_key'] ?? '');
        
        if (empty($preview_key)) {
            wp_send_json_error('preview_key ارسال نشده است');
        }
        
        // دریافت محصول از transient
        $product_data = get_transient($preview_key);
        
        if (!$product_data) {
            error_log("[PIE] Preview key not found or expired: {$preview_key}");
            wp_send_json_error('Preview منقضی شده است. لطفا دوباره محصول را ارسال کنید');
        }
        
        // ایجاد محصول
        $result = $this->create_product_from_json($product_data);
        
        if ($result['success']) {
            // حذف preview
            delete_transient($preview_key);
            
            $this->logging->log('preview_confirm', 'success', "محصول '{$product_data['name']}' تأیید و آپلود شد (ID: {$result['product_id']})");
            wp_send_json_success([
                'product_id' => $result['product_id'],
                'message' => "محصول '{$product_data['name']}' با موفقیت آپلود شد"
            ]);
        } else {
            error_log("[PIE] Product creation failed during preview confirmation: {$result['error']}");
            $this->logging->log('preview_confirm', 'failed', "خطا: {$result['error']}");
            wp_send_json_error($result['error']);
        }
    }
}

// فعال‌سازی
PIE_Transfer::get_instance();
