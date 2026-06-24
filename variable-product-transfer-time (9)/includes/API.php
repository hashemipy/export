<?php
/**
 * REST API Endpoints برای ارسال/دریافت محصولات
 * REST API for Product Transfer
 */

if (!defined('ABSPATH')) {
    exit;
}

class PIE_API {
    
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
        add_action('rest_api_init', [$this, 'register_endpoints']);
    }
    
    /**
     * ثبت API endpoints
     */
    public function register_endpoints() {
        // Endpoint: دریافت محصول
        register_rest_route('pie/v1', '/receive-product', [
            'methods' => 'POST',
            'callback' => [$this, 'receive_product'],
            'permission_callback' => [$this, 'check_auth_for_receive']
        ]);
        
        // Endpoint: پیش‌نمایش محصول (بدون ذخیره نهایی)
        register_rest_route('pie/v1', '/preview-product', [
            'methods' => 'POST',
            'callback' => [$this, 'preview_product'],
            'permission_callback' => [$this, 'check_auth_for_receive']
        ]);
        
        // Endpoint: تأیید و آپلود نهایی
        register_rest_route('pie/v1', '/confirm-product', [
            'methods' => 'POST',
            'callback' => [$this, 'confirm_product'],
            'permission_callback' => [$this, 'check_auth_for_receive']
        ]);
        
        // Endpoint: بررسی ارتباط
        register_rest_route('pie/v1', '/health-check', [
            'methods' => 'GET',
            'callback' => [$this, 'health_check'],
            'permission_callback' => '__return_true'
        ]);

        // Endpoint: دریافت تغییر موجودی از سایت مقابل
        register_rest_route('pie/v1', '/update-stock', [
            'methods'             => 'POST',
            'callback'            => [$this, 'update_stock'],
            'permission_callback' => [$this, 'check_auth_for_receive'],
        ]);

        // Endpoint: لیست محصولات برای mapping دستی
        register_rest_route('pie/v1', '/list-products', [
            'methods'             => 'GET',
            'callback'            => [$this, 'list_products'],
            'permission_callback' => [$this, 'check_auth_for_receive'],
        ]);
    }
    
    /**
     * تأیید دسترسی برای دریافت محصول
     */
    public function check_auth_for_receive() {
        $config = $this->settings->get_config();
        
        // اگر Site 2 نباشد endpoint باید معطل بماند
        if ($config['site_role'] !== 'site2') {
            error_log("[PIE] Receive endpoint: site_role is not site2 (got: {$config['site_role']})");
            return new WP_Error('wrong_site', 'This endpoint is only available on Site 2', ['status' => 403]);
        }
        
        // بررسی Basic Auth header
        $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        
        if (empty($auth_header)) {
            error_log("[PIE] receive-product: no Authorization header found");
            return new WP_Error('no_auth', 'Authorization header is missing', ['status' => 401]);
        }
        
        if (strpos($auth_header, 'Basic ') !== 0) {
            error_log("[PIE] receive-product: Authorization header is not Basic format (got: {$auth_header})");
            return new WP_Error('invalid_auth_type', 'Only Basic Auth is supported', ['status' => 401]);
        }
        
        // تجزیه credentials
        $encoded = substr($auth_header, 6);
        $credentials = base64_decode($encoded, true);
        
        if ($credentials === false) {
            error_log("[PIE] receive-product: base64 decode failed");
            return new WP_Error('invalid_encoding', 'Invalid base64 encoding', ['status' => 401]);
        }
        
        $parts = explode(':', $credentials, 2);
        
        if (count($parts) !== 2) {
            error_log("[PIE] receive-product: credentials not in key:secret format (parts count: " . count($parts) . ")");
            return new WP_Error('invalid_format', 'Credentials must be in key:secret format', ['status' => 401]);
        }
        
        list($key, $secret) = $parts;
        
        error_log("[PIE] Validating API key: " . substr($key, 0, 15) . "... (length: " . strlen($key) . ")");
        
        // استفاده از APIKeyManager برای اعتبارسنجی
        $api_manager = PIE_APIKeyManager::get_instance();
        $valid_key = $api_manager->validate_key($key, $secret);
        
        if (!$valid_key) {
            error_log("[PIE] API key validation failed");
            return new WP_Error('invalid_credentials', 'Invalid API credentials', ['status' => 401]);
        }
        
        error_log("[PIE] API key validated successfully: " . $valid_key['name']);
        return true;
    }
    
    /**
     * تأیید دسترسی API
     */
    public function check_api_auth() {
        $config = $this->settings->get_config();
        
        if ($config['site_role'] !== 'site2') {
            error_log("[PIE] API Auth failed: site_role is not site2 (got: {$config['site_role']})");
            return false;
        }
        
        if (empty($config['api_consumer_key'])) {
            error_log("[PIE] API Auth failed: api_consumer_key is empty");
            return false;
        }
        
        // بررسی Basic Auth
        $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        
        if (empty($auth_header)) {
            error_log("[PIE] API Auth failed: no Authorization header");
            return false;
        }
        
        if (strpos($auth_header, 'Basic ') !== 0) {
            error_log("[PIE] API Auth failed: Authorization header is not Basic");
            return false;
        }
        
        $credentials = base64_decode(substr($auth_header, 6));
        $parts = explode(':', $credentials, 2);
        
        if (count($parts) !== 2) {
            error_log("[PIE] API Auth failed: credentials not in key:secret format");
            return false;
        }
        
        list($key, $secret) = $parts;
        
        error_log("[PIE] API Auth check: key={$key}, secret_len=" . strlen($secret));
        error_log("[PIE] Expected key: {$config['api_consumer_key']}, secret_len=" . strlen($config['api_consumer_secret']));
        
        if ($key !== $config['api_consumer_key'] || $secret !== $config['api_consumer_secret']) {
            error_log("[PIE] API Auth failed: credentials mismatch");
            return false;
        }
        
        return true;
    }
    
    /**
     * دریافت محصول - مستقیماً import بدون pending (دقیقاً مثل فایل آپلود شده)
     */
    public function receive_product(WP_REST_Request $request) {
        try {
            $body = $request->get_body();
            error_log("[PIE API] receive_product called, body length: " . strlen($body));
            
            // JSON می‌تواند array یا object باشد
            $json_data = json_decode($body, true);
            
            if (!$json_data) {
                error_log("[PIE API] JSON decode failed");
                $this->logging->log('api_receive', 'failed', 'خطا در تجزیه JSON');
                return new WP_REST_Response(['success' => false, 'message' => 'JSON decode error'], 400);
            }
            
            // اگر array باشد، اولین محصول را بگیر
            if (is_array($json_data) && isset($json_data[0])) {
                $json_data = $json_data[0];
            }
            
            if (!isset($json_data['name']) || empty($json_data['name'])) {
                error_log("[PIE API] Missing product name");
                $this->logging->log('api_receive', 'failed', 'نام محصول ارسال نشده است');
                return new WP_REST_Response(['success' => false, 'message' => 'نام محصول الزامی است'], 400);
            }
            
            error_log("[PIE API] Product JSON received: {$json_data['name']} (has attributes: " . (isset($json_data['attributes']) ? count($json_data['attributes']) : 0) . ", variations: " . (isset($json_data['variations']) ? count($json_data['variations']) : 0) . ")");
            
            // ⭐ مستقیماً import کن - دقیقاً مثل فایل JSON آپلود شده
            $transfer = PIE_Transfer::get_instance();
            $result = $transfer->create_product_from_json($json_data);
            
            if ($result['success']) {
                $this->logging->log('api_receive', 'success', "محصول '{$json_data['name']}' دریافت و آپلود شد (ID: {$result['product_id']}) - Attributes: " . (isset($json_data['attributes']) ? count($json_data['attributes']) : 0) . ", Variations: " . (isset($json_data['variations']) ? count($json_data['variations']) : 0));
                error_log("[PIE API] Product created successfully: ID {$result['product_id']}");
                return new WP_REST_Response(['success' => true, 'message' => 'محصول دریافت و آپلود شد', 'product_id' => $result['product_id']], 200);
            } else {
                error_log("[PIE API] Product creation failed: {$result['error']}");
                $this->logging->log('api_receive', 'failed', "خطا: {$result['error']}");
                return new WP_REST_Response(['success' => false, 'message' => $result['error']], 400);
            }
            
        } catch (Exception $e) {
            error_log("[PIE API] receive_product exception: " . $e->getMessage());
            $this->logging->log('api_receive', 'failed', "خطا: " . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * دریافت محصول از سایت ۱ و import مستقیم در سایت ۲
     * دقیقاً همان منطق handle_upload - از همان import_products استفاده می‌شود
     */
    public function preview_product(WP_REST_Request $request) {
        try {
            $body = $request->get_body();
            $products = json_decode($body, true);
            
            if (!$products) {
                return new WP_REST_Response(['success' => false, 'message' => 'JSON نامعتبر است'], 400);
            }
            
            // ساختار: آرایه‌ای از محصولات (مثل فایل JSON دستی)
            if (!isset($products[0])) {
                $products = [$products];
            }
            
            $plugin = Product_Import_Export::get_instance();
            $result = $plugin->import_products_public($products);

            $this->logging->log('api_receive', 'success', $result['message']);

            // برگرداندن product_id و variation_ids برای ثبت خودکار mapping در سایت ۱
            return new WP_REST_Response([
                'success'       => true,
                'message'       => $result['message'],
                'product_id'    => $result['product_id']    ?? null,
                'variation_ids' => $result['variation_ids'] ?? [],
            ], 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * تأیید و آپلود نهایی محصول
     */
    public function confirm_product(WP_REST_Request $request) {
        try {
            $json_data = $request->get_json_params();
            
            if (!isset($json_data['preview_key'])) {
                error_log("[PIE API] Missing preview_key in confirm");
                $this->logging->log('api_confirm', 'failed', 'preview_key ارسال نشده است');
                return new WP_REST_Response(['success' => false, 'message' => 'preview_key required'], 400);
            }
            
            // دریافت از transient
            $product_data = get_transient($json_data['preview_key']);
            
            if (!$product_data) {
                error_log("[PIE API] Preview key expired or not found: {$json_data['preview_key']}");
                $this->logging->log('api_confirm', 'failed', 'preview_key منقضی یا پیدا نشد');
                return new WP_REST_Response(['success' => false, 'message' => 'Preview منقضی شده است. دوباره ارسال کنید'], 400);
            }
            
            error_log("[PIE API] Confirming product: {$product_data['name']}");
            
            // ایجاد محصول
            $transfer = PIE_Transfer::get_instance();
            $result = $transfer->create_product_from_json($product_data);
            
            if ($result['success']) {
                // حذف preview
                delete_transient($json_data['preview_key']);
                
                $this->logging->log('api_confirm', 'success', "محصول '{$product_data['name']}' تأیید و آپلود شد (ID: {$result['product_id']})");
                error_log("[PIE API] Product confirmed and created: ID {$result['product_id']}");
                
                return new WP_REST_Response([
                    'success' => true,
                    'message' => 'محصول تأیید و آپلود شد',
                    'product_id' => $result['product_id']
                ], 200);
            } else {
                error_log("[PIE API] Product creation failed during confirm: {$result['error']}");
                $this->logging->log('api_confirm', 'failed', "خطا: {$result['error']}");
                return new WP_REST_Response(['success' => false, 'message' => $result['error']], 400);
            }
            
        } catch (Exception $e) {
            error_log("[PIE API] confirm_product exception: " . $e->getMessage());
            $this->logging->log('api_confirm', 'failed', "خطا: " . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * دریافت تغییر موجودی از سایت مقابل و اعمال آن
     *
     * منطق conflict resolution هنگام قطعی هاست:
     *  - اگر موجودی فعلی این سایت کمتر از مقدار دریافتی باشد
     *    مقدار کمتر را برگردان تا سایت فرستنده بتواند خودش را هم کاهش دهد
     */
    public function update_stock(WP_REST_Request $request) {
        $body = $request->get_json_params();

        $product_id   = intval($body['product_id']   ?? 0);
        $variation_id = intval($body['variation_id'] ?? 0) ?: null;
        $new_stock    = isset($body['new_stock']) ? intval($body['new_stock']) : null;
        $map_id       = intval($body['map_id']       ?? 0);

        if (!$product_id || $new_stock === null) {
            return new WP_REST_Response(['success' => false, 'message' => 'product_id و new_stock الزامی هستند'], 400);
        }

        // بارگذاری محصول صحیح (variation یا ساده)
        $target_id = $variation_id ?: $product_id;
        $product   = wc_get_product($target_id);

        if (!$product) {
            return new WP_REST_Response(['success' => false, 'message' => "محصول {$target_id} پیدا نشد"], 404);
        }

        $current_stock = (int) $product->get_stock_quantity();

        // conflict resolution: اگر موجودی local کمتر است، آن را مرجع قرار بده
        // این حالت وقتی هاست قطع بود و فروش در هر دو طرف اتفاق افتاد رخ می‌دهد
        if ($current_stock < $new_stock) {
            // موجودی local را اعمال نکن - مقدار کمتر را برگردان
            return new WP_REST_Response([
                'success'       => false,
                'message'       => 'conflict: موجودی local کمتر است',
                'current_stock' => $current_stock,
            ], 409);
        }

        // اعمال موجودی جدید
        wc_update_product_stock($product, $new_stock, 'set');

        // ثبت در لاگ
        $this->logging->log('stock_receive', 'success',
            "موجودی محصول {$target_id} از {$current_stock} به {$new_stock} تغییر کرد (map_id: {$map_id})"
        );

        return new WP_REST_Response([
            'success'       => true,
            'message'       => 'موجودی آپدیت شد',
            'previous_stock' => $current_stock,
            'new_stock'     => $new_stock,
        ], 200);
    }

    /**
     * لیست محصولات و متغیرهای این سایت برای mapping دستی
     */
    public function list_products(WP_REST_Request $request) {
        $products_wc = wc_get_products([
            'status'  => 'publish',
            'limit'   => 300,
            'type'    => ['simple', 'variable'],
            'orderby' => 'title',
            'order'   => 'ASC',
        ]);

        $result = [];

        foreach ($products_wc as $product) {
            $entry = [
                'product_id' => $product->get_id(),
                'type'       => $product->get_type(),
                'label'      => "#{$product->get_id()} - {$product->get_name()}",
                'variations' => [],
            ];

            if ($product->is_type('variable')) {
                foreach ($product->get_children() as $var_id) {
                    $variation = wc_get_product($var_id);
                    if (!$variation) continue;

                    $attrs = [];
                    foreach ($variation->get_variation_attributes() as $attr => $val) {
                        $attrs[] = wc_attribute_label(str_replace('attribute_', '', $attr)) . ':' . $val;
                    }

                    $entry['variations'][] = [
                        'id'    => $var_id,
                        'label' => implode(' | ', $attrs) ?: "متغیر #{$var_id}",
                        'stock' => $variation->get_stock_quantity(),
                    ];
                }
            }

            $result[] = $entry;
        }

        return new WP_REST_Response(['products' => $result], 200);
    }

    /**
     * بررسی ارتباط
     */
    public function health_check() {
        $config = $this->settings->get_config();
        return new WP_REST_Response([
            'status' => 'ok',
            'config' => [
                'site_role' => $config['site_role'],
                'plugin_version' => PIE_VERSION
            ]
        ]);
    }

}

// فعال‌سازی
PIE_API::get_instance();
