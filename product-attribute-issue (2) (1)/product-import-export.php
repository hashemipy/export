<?php
/**
 * Plugin Name: محصول Import/Export (با دسته‌بندی و ویژگی‌ها)
 * Plugin URI: https://example.com/product-import-export
 * Description: دانلود و آپلود سریع محصولات ساده و متغیر - با ایجاد خودکار دسته‌بندی‌ها و ویژگی‌ها
 * Version: 2.0.0
 * Author: شما
 * License: GPL v2 or later
 * WC tested up to: 8.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PIE_VERSION', '2.0.0');
define('PIE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PIE_PLUGIN_URL', plugin_dir_url(__FILE__));

class Product_Import_Export {
    
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
        add_action('wp_ajax_pie_download_products', [$this, 'handle_download']);
        add_action('wp_ajax_pie_upload_products', [$this, 'handle_upload']);
        add_action('wp_ajax_pie_get_products_list', [$this, 'handle_get_products_list']);
        add_action('wp_ajax_pie_validate_file', [$this, 'handle_validate_file']);
    }
    
    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Product Import/Export نیاز به WooCommerce دارد!</p></div>';
            });
            return;
        }
    }
    
    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            'Import/Export محصولات',
            'Import/Export',
            'manage_woocommerce',
            'pie-import-export',
            [$this, 'render_page']
        );
    }
    
    public function render_page() {
        ?>
        <div class="wrap">
            <h1>Import/Export محصولات</h1>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
                
                <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px;">
                    <h2>دانلود محصولات</h2>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: inline-block; margin-right: 20px;">
                            <input type="checkbox" id="select-all"> انتخاب همه
                        </label>
                        <label style="display: inline-block; margin-right: 20px;">
                            <input type="radio" name="filter" value="all" checked> همه
                        </label>
                        <label style="display: inline-block; margin-right: 20px;">
                            <input type="radio" name="filter" value="simple"> ساده
                        </label>
                        <label style="display: inline-block;">
                            <input type="radio" name="filter" value="variable"> متغیر
                        </label>
                    </div>
                    
                    <div id="products-loading" style="text-align: center; padding: 20px; display: none;">
                        <p>در حال بارگذاری محصولات...</p>
                    </div>
                    
                    <table id="products-table" style="width: 100%; border-collapse: collapse; display: none;">
                        <thead>
                            <tr style="background: #f9f9f9;">
                                <th style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd; width: 40px;"></th>
                                <th style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">نام محصول</th>
                                <th style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">کد (SKU)</th>
                                <th style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">قیمت</th>
                                <th style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">موجودی</th>
                                <th style="padding: 10px; text-align: right; border-bottom: 1px solid #ddd;">نوع</th>
                            </tr>
                        </thead>
                        <tbody id="products-tbody"></tbody>
                    </table>
                    
                    <div id="products-empty" style="text-align: center; padding: 20px; color: #999;">
                        محصولی موجود نیست
                    </div>
                    
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                        <p>تعداد انتخاب شده: <strong id="selected-count">0</strong></p>
                    </div>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px; background: #fafafa;">
                        <h3>تنظیمات دانلود</h3>
                        <form id="download-form">
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 10px; font-weight: bold;">فرمت:</label>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="radio" name="format" value="json" checked> JSON
                                </label>
                                <label style="display: block;">
                                    <input type="radio" name="format" value="csv"> CSV
                                </label>
                            </div>
                            <button type="submit" class="button button-primary" style="width: 100%; padding: 10px;">
                                دانلود
                            </button>
                            <span id="download-status" style="display: block; margin-top: 10px; text-align: center;"></span>
                        </form>
                    </div>
                    
                    <div style="border: 1px solid #ddd; padding: 20px; border-radius: 5px; background: #fafafa;">
                        <h3>آپلود محصولات</h3>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: bold;">انتخاب فایل:</label>
                            <input type="file" id="upload-file" accept=".json,.csv" style="display: block; margin-bottom: 15px; width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
                        </div>
                        
                        <button id="validate-btn" class="button button-secondary" style="width: 100%; padding: 10px; margin-bottom: 10px;">
                            ✓ بررسی فایل
                        </button>
                        
                        <div id="validation-results" style="display: none; margin-bottom: 15px; padding: 12px; border-radius: 3px; max-height: 250px; overflow-y: auto; background: #f5f5f5; border: 1px solid #ddd;">
                            <div id="validation-content"></div>
                        </div>
                        
                        <button id="upload-btn" class="button button-primary" style="width: 100%; padding: 10px; margin-bottom: 10px; display: none;">
                            → آپلود محصولات
                        </button>
                        
                        <span id="upload-status" style="display: block; margin-top: 10px; text-align: center;"></span>
                        
                        <div id="upload-progress" style="margin-top: 10px; display: none;">
                            <div style="background: #f0f0f0; border-radius: 3px; overflow: hidden;">
                                <div id="progress-bar" style="height: 20px; background: #0073aa; width: 0%;"></div>
                            </div>
                            <span id="progress-text">0%</span>
                        </div>
                        
                        <div id="upload-messages" style="margin-top: 10px; display: none; background: #e8f5e9; padding: 10px; border-radius: 3px;"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            let allProducts = [];
            
            function loadProductsList() {
                $('#products-loading').show();
                $('#products-table').hide();
                $('#products-empty').hide();
                
                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'pie_get_products_list',
                        nonce: '<?php echo wp_create_nonce('pie_nonce'); ?>'
                    },
                    success: function(response) {
                        $('#products-loading').hide();
                        if (response.success && response.data.length > 0) {
                            allProducts = response.data;
                            renderProductsTable(allProducts);
                            $('#products-table').show();
                        } else {
                            $('#products-empty').show();
                        }
                    }
                });
            }
            
            function renderProductsTable(products) {
                const tbody = $('#products-tbody');
                tbody.html('');
                
                products.forEach(function(product) {
                    const row = $('<tr>').css('border-bottom', '1px solid #eee');
                    row.html(`
                        <td style="padding: 10px;">
                            <input type="checkbox" class="product-checkbox" value="${product.id}">
                        </td>
                        <td style="padding: 10px; text-align: right;"><strong>${product.name}</strong></td>
                        <td style="padding: 10px; text-align: right;">${product.sku || '-'}</td>
                        <td style="padding: 10px; text-align: right;">${product.price || '-'}</td>
                        <td style="padding: 10px; text-align: right;">${product.stock_quantity !== null ? product.stock_quantity : '-'}</td>
                        <td style="padding: 10px; text-align: right;">
                            <span style="background: ${product.type === 'simple' ? '#e8f5e9' : '#e3f2fd'}; padding: 4px 8px; border-radius: 3px; font-size: 12px;">
                                ${product.type === 'simple' ? 'ساده' : 'متغیر'}
                            </span>
                        </td>
                    `);
                    tbody.append(row);
                });
            }
            
            $('#select-all').on('change', function() {
                $('.product-checkbox').prop('checked', $(this).prop('checked'));
                updateSelectedCount();
            });
            
            $('input[name="filter"]').on('change', function() {
                const filter = $(this).val();
                let filtered = allProducts;
                
                if (filter === 'simple') {
                    filtered = allProducts.filter(p => p.type === 'simple');
                } else if (filter === 'variable') {
                    filtered = allProducts.filter(p => p.type === 'variable');
                }
                
                renderProductsTable(filtered);
                updateSelectedCount();
            });
            
            $(document).on('change', '.product-checkbox', updateSelectedCount);
            
            function updateSelectedCount() {
                $('#selected-count').text($('.product-checkbox:checked').length);
            }
            
            $('#download-form').on('submit', function(e) {
                e.preventDefault();
                const selectedIds = [];
                $('.product-checkbox:checked').each(function() {
                    selectedIds.push($(this).val());
                });
                
                if (selectedIds.length === 0) {
                    alert('لطفا حداقل یک محصول را انتخاب کنید');
                    return;
                }
                
                const format = $('input[name="format"]:checked').val();
                $('#download-status').text('در حال دانلود...');
                
                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'pie_download_products',
                        format: format,
                        product_ids: selectedIds.join(','),
                        nonce: '<?php echo wp_create_nonce('pie_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            const link = document.createElement('a');
                            link.href = 'data:' + (format === 'json' ? 'application/json' : 'text/csv') + ';charset=utf-8,' + encodeURIComponent(response.data);
                            link.download = 'products-' + new Date().getTime() + '.' + format;
                            link.click();
                            $('#download-status').html('<span style="color: green;">✓ تکمیل شد</span>');
                        } else {
                            $('#download-status').html('<span style="color: red;">✗ خطا: ' + response.data + '</span>');
                        }
                    }
                });
            });
            
            // بررسی فایل
            $('#validate-btn').on('click', function() {
                const file = $('#upload-file')[0].files[0];
                if (!file) {
                    alert('لطفا فایل را انتخاب کنید');
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        const content = e.target.result;
                        let products = [];
                        
                        if (file.name.endsWith('.json')) {
                            products = JSON.parse(content);
                        } else if (file.name.endsWith('.csv')) {
                            products = parseCSV(content);
                        } else {
                            alert('فرمت فایل غیرمعتبر است');
                            return;
                        }
                        
                        validateProducts(products);
                    } catch (err) {
                        showValidationError('خطا در پارسینگ فایل: ' + err.message);
                    }
                };
                reader.readAsText(file);
            });
            
            function parseCSV(content) {
                const lines = content.trim().split('\n');
                if (lines.length < 2) return [];
                
                const headers = lines[0].split(',').map(h => h.trim());
                const products = [];
                
                for (let i = 1; i < lines.length; i++) {
                    const values = lines[i].split(',').map(v => v.trim());
                    if (values.length !== headers.length) continue;
                    
                    const product = {};
                    headers.forEach((h, idx) => {
                        product[h] = values[idx];
                    });
                    products.push(product);
                }
                
                return products;
            }
            
            function validateProducts(products) {
                if (!Array.isArray(products) || products.length === 0) {
                    showValidationError('فایل محصول ندارد');
                    return;
                }
                
                let errors = [];
                let warnings = [];
                let validCount = 0;
                
                products.forEach((product, idx) => {
                    const productNum = idx + 1;
                    
                    // بررسی نام
                    if (!product.name) {
                        errors.push(`محصول ${productNum}: نام موجود نیست`);
                        return;
                    }
                    
                    // بررسی قیمت برای محصولات ساده
                    if (product.type === 'simple' && !product.price) {
                        errors.push(`محصول "${product.name}": قیمت موجود نیست`);
                        return;
                    }
                    
                    // بررسی محصولات متغیر
                    if (product.type === 'variable') {
                        if (!product.attributes || Object.keys(product.attributes).length === 0) {
                            errors.push(`محصول "${product.name}": ویژگی‌ها موجود نیست`);
                            return;
                        }
                        
                        if (!product.variations || product.variations.length === 0) {
                            errors.push(`محصول "${product.name}": متغیرات موجود نیست`);
                            return;
                        }
                        
                        // بررسی هر متغیر
                        product.variations.forEach((v, vIdx) => {
                            if (!v.price) {
                                warnings.push(`محصول "${product.name}" - متغیر ${vIdx + 1}: قیمت موجود نیست`);
                            }
                            if (!v.attributes || Object.keys(v.attributes).length === 0) {
                                errors.push(`محصول "${product.name}" - متغیر ${vIdx + 1}: ویژگی‌های متغیر خالی است`);
                                return;
                            }
                        });
                    }
                    
                    validCount++;
                });
                
                showValidationResults(validCount, errors, warnings, products.length);
            }
            
            function showValidationResults(valid, errors, warnings, total) {
                let html = '<div style="padding: 0;">';
                
                // خلاصه
                html += `<div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">
                    <strong>خلاصه:</strong><br>
                    ✓ ${valid}/${total} محصول معتبر
                </div>`;
                
                // خطاها
                if (errors.length > 0) {
                    html += '<div style="margin-bottom: 10px;">';
                    html += '<strong style="color: red;">❌ خطاها (' + errors.length + '):</strong><br>';
                    errors.slice(0, 8).forEach(err => {
                        html += '• ' + err + '<br>';
                    });
                    if (errors.length > 8) {
                        html += '• ... و ' + (errors.length - 8) + ' خطای دیگر<br>';
                    }
                    html += '</div>';
                }
                
                // هشدارها
                if (warnings.length > 0) {
                    html += '<div style="margin-bottom: 10px;">';
                    html += '<strong style="color: orange;">⚠️ هشدارها (' + warnings.length + '):</strong><br>';
                    warnings.slice(0, 5).forEach(warn => {
                        html += '• ' + warn + '<br>';
                    });
                    if (warnings.length > 5) {
                        html += '• ... و ' + (warnings.length - 5) + ' هشدار دیگر<br>';
                    }
                    html += '</div>';
                }
                
                // پیام نتیجه
                if (errors.length === 0) {
                    html += '<div style="padding: 10px; background: #e8f5e9; border-radius: 3px; color: green;">';
                    html += '<strong>✓ فایل معتبر است! می‌توانید اپلود کنید</strong>';
                    html += '</div>';
                    $('#upload-btn').show();
                } else {
                    html += '<div style="padding: 10px; background: #ffebee; border-radius: 3px; color: red;">';
                    html += '<strong>✗ فایل دارای خطا است. لطفا اصلاح کنید</strong>';
                    html += '</div>';
                    $('#upload-btn').hide();
                }
                
                html += '</div>';
                
                $('#validation-content').html(html);
                $('#validation-results').show();
            }
            
            function showValidationError(message) {
                $('#validation-content').html(`<div style="padding: 10px; background: #ffebee; border-radius: 3px; color: red;">✗ ${message}</div>`);
                $('#validation-results').show();
                $('#upload-btn').hide();
            }
            
            // آپلود پس از تأیید
            $('#upload-btn').on('click', function() {
                const file = $('#upload-file')[0].files[0];
                if (!file) {
                    alert('لطفا فایل را انتخاب کنید');
                    return;
                }
                
                const formData = new FormData();
                formData.append('file', file);
                formData.append('action', 'pie_upload_products');
                formData.append('nonce', '<?php echo wp_create_nonce('pie_nonce'); ?>');
                
                $('#upload-progress').show();
                $('#upload-messages').hide().html('');
                $('#upload-status').text('در حال آپلود...');
                
                $.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $('#upload-progress').hide();
                        if (response.success) {
                            $('#upload-messages').show().html(response.data);
                            $('#upload-status').html('<span style="color: green;">✓ تکمیل شد</span>');
                            $('#validate-btn').text('✓ بررسی دوباره');
                            setTimeout(() => loadProductsList(), 1000);
                        } else {
                            $('#upload-messages').show().html('<span style="color: red;">خطا: ' + response.data + '</span>');
                            $('#upload-status').html('<span style="color: red;">✗ ناموفق</span>');
                        }
                    },
                    error: function() {
                        $('#upload-progress').hide();
                        $('#upload-messages').show().html('<span style="color: red;">خطای ارتباطی</span>');
                        $('#upload-status').html('<span style="color: red;">✗ خطا</span>');
                    }
                });
            });
            
            loadProductsList();
        });
        </script>
        <?php
    }
    
    public function handle_get_products_list() {
        check_ajax_referer('pie_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('دسترسی رد شد');
        }
        
        $products = [];
        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ];
        
        $query = new WP_Query($args);
        
        while ($query->have_posts()) {
            $query->the_post();
            $product = wc_get_product(get_the_ID());
            
            $products[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'price' => wc_price($product->get_price()),
                'stock_quantity' => $product->get_stock_quantity(),
                'type' => $product->get_type(),
            ];
        }
        
        wp_reset_postdata();
        wp_send_json_success($products);
    }
    
    public function handle_download() {
        check_ajax_referer('pie_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('دسترسی رد شد');
        }
        
        $format = sanitize_text_field($_POST['format'] ?? 'json');
        $product_ids = sanitize_text_field($_POST['product_ids'] ?? '');
        
        if (empty($product_ids)) {
            wp_send_json_error('محصولی انتخاب نشده');
        }
        
        $ids_array = array_map('intval', explode(',', $product_ids));
        $products_data = $this->get_products_for_export($ids_array);
        
        if (empty($products_data)) {
            wp_send_json_error('محصولی یافت نشد');
        }
        
        if ($format === 'json') {
            $content = json_encode($products_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $content = $this->convert_to_csv($products_data);
        }
        
        wp_send_json_success($content);
    }
    
    private function get_products_for_export($product_ids) {
        $products = [];
        
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;
            
            $product_data = [
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'description' => $product->get_description(),
                'short_description' => $product->get_short_description(),
                'price' => $product->get_price(),
                'stock_quantity' => $product->get_stock_quantity(),
                'type' => $product->get_type(),
                'categories' => [],
                'image_urls' => [],
                'attributes' => [],
                'variations' => []
            ];
            
            // دسته‌بندی‌ها
            $terms = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'all']);
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    // استفاده از نام بجای slug اگر slug URL-encoded باشد
                    $slug = $term->slug;
                    if (strpos($slug, '%') !== false) {
                        $slug = sanitize_title($term->name);
                    }
                    $product_data['categories'][] = [
                        'name' => $term->name,
                        'slug' => $slug,
                        'parent_id' => $term->parent,
                    ];
                }
            }
            
            // عکس‌ها - فقط URL
            if ($product->get_image_id()) {
                $url = wp_get_attachment_url($product->get_image_id());
                if ($url) $product_data['image_urls'][] = $url;
            }
            
            if ($product->get_gallery_image_ids()) {
                foreach ($product->get_gallery_image_ids() as $id) {
                    $url = wp_get_attachment_url($id);
                    if ($url) $product_data['image_urls'][] = $url;
                }
            }
            
            // برای محصولات متغیر
            if ($product->is_type('variable')) {
                $attributes = $product->get_attributes();
                foreach ($attributes as $attr_key => $attr) {
                    if (!$attr->is_taxonomy()) continue;
                    
                    // استخراج نام ویژگی (بدون پیشوند pa_)
                    $attr_name = str_replace('pa_', '', $attr_key);
                    $terms = $attr->get_options();
                    $values = [];
                    
                    foreach ($terms as $term_id) {
                        $term = get_term($term_id);
                        if ($term && !is_wp_error($term)) {
                            $values[] = [
                                'name' => $term->name,
                                'slug' => $term->slug,
                            ];
                        }
                    }
                    
                    $product_data['attributes'][$attr_name] = [
                        'values' => $values,
                        'visible' => $attr->get_visible(),
                    ];
                }
                
                // متغیرات
                $variations = $product->get_available_variations('objects');
                
                if (empty($variations)) {
                    // محصولات قدیمی: query direct
                    $variations = wc_get_products([
                        'type' => 'variation',
                        'parent' => $product->get_id(),
                        'limit' => -1,
                        'return' => 'objects'
                    ]);
                }
                
                foreach ($variations as $variation) {
                    if (!is_object($variation)) {
                        $variation = new WC_Product_Variation($variation);
                    }
                    
                    $var_data = [
                        'sku' => $variation->get_sku(),
                        'price' => $variation->get_price() ? (string)$variation->get_price() : '',
                        'stock_quantity' => $variation->get_stock_quantity() ? (int)$variation->get_stock_quantity() : 0,
                        'attributes' => [],
                        'image_url' => null,
                    ];
                    
                    foreach ($variation->get_attributes() as $attr_key => $attr_value) {
                        // استخراج نام ویژگی (بدون pa_ و attribute_)
                        $attr_name = str_replace(['attribute_', 'pa_'], '', $attr_key);
                        $var_data['attributes'][$attr_name] = $attr_value;
                    }
                    
                    if ($variation->get_image_id()) {
                        $var_data['image_url'] = wp_get_attachment_url($variation->get_image_id());
                    }
                    
                    $product_data['variations'][] = $var_data;
                }
            }
            
            $products[] = $product_data;
        }
        
        return $products;
    }
    
    public function handle_validate_file() {
        check_ajax_referer('pie_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('دسترسی رد ش��');
        }
        
        if (empty($_FILES['file'])) {
            wp_send_json_error('فایلی انتخاب نشده');
        }
        
        $file = $_FILES['file'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        if (!in_array($ext, ['json', 'csv'])) {
            wp_send_json_error('فقط JSON و CSV پذیرفته می‌شود');
        }
        
        $content = file_get_contents($file['tmp_name']);
        
        if ($ext === 'json') {
            $products = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error('فایل JSON معتبر نیست: ' . json_last_error_msg());
            }
        } else {
            $products = $this->parse_csv($content);
        }
        
        if (!is_array($products) || empty($products)) {
            wp_send_json_error('محصول یافت نشد');
        }
        
        // ب��رسی محصولات
        $validation = $this->validate_products($products);
        wp_send_json_success($validation);
    }
    
    private function validate_products($products) {
        $errors = [];
        $warnings = [];
        $valid_count = 0;
        
        foreach ($products as $idx => $product) {
            $product_num = $idx + 1;
            
            // بررسی نام
            if (empty($product['name'])) {
                $errors[] = "محصول {$product_num}: نام موجود نیست";
                continue;
            }
            
            $product_name = $product['name'];
            
            // بررسی قیمت برای محصولات ساده
            $type = $product['type'] ?? 'simple';
            if ($type === 'simple' && empty($product['price'])) {
                $errors[] = "محصول \"{$product_name}\": قیمت موجود نیست";
                continue;
            }
            
            // بررسی محصولات متغیر
            if ($type === 'variable') {
                if (empty($product['attributes'])) {
                    $errors[] = "محصول \"{$product_name}\": ویژگی‌ها موجود نیست";
                    continue;
                }
                
                if (empty($product['variations'])) {
                    $errors[] = "محصول \"{$product_name}\": متغیرات موجود نیست";
                    continue;
                }
                
                // بررسی هر متغیر - اما فقط warning نه error
                // محصولات قدیمی ممکن است متغیرات حذف شده داشته باشند
                foreach ($product['variations'] as $v_idx => $variation) {
                    if (empty($variation['price'])) {
                        $warnings[] = "محصول \"{$product_name}\" - متغیر " . ($v_idx + 1) . ": قیمت موجود نیست (قیمت محصول استفاده می‌شود)";
                    }
                    // فقط warning برای attributes خالی (برای محصولات قدیمی)
                    if (empty($variation['attributes'])) {
                        $warnings[] = "محصول \"{$product_name}\" - متغیر " . ($v_idx + 1) . ": ویژگی‌های متغیر خالی است (فقط ایجاد می‌شود)";
                    }
                }
            }
            
            $valid_count++;
        }
        
        return [
            'valid' => $valid_count,
            'total' => count($products),
            'errors' => $errors,
            'warnings' => $warnings,
            'can_upload' => empty($errors)
        ];
    }
    
    public function handle_upload() {
        check_ajax_referer('pie_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('دسترسی رد شد');
        }
        
        if (empty($_FILES['file'])) {
            wp_send_json_error('فایلی انتخاب نشده');
        }
        
        $file = $_FILES['file'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        if (!in_array($ext, ['json', 'csv'])) {
            wp_send_json_error('فقط JSON و CSV پذیرفته می‌شود');
        }
        
        $content = file_get_contents($file['tmp_name']);
        
        if ($ext === 'json') {
            $products = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error('فایل JSON معتبر نیست');
            }
        } else {
            $products = $this->parse_csv($content);
        }
        
        if (!is_array($products) || empty($products)) {
            wp_send_json_error('محصول یافت نشد');
        }
        
        $result = $this->import_products($products);
        wp_send_json_success($result['message']);
    }
    
    private function import_products($products) {
        $result = [
            'created' => 0,
            'categories' => 0,
            'attributes' => 0,
            'variations' => 0,
            'images' => 0,
            'errors' => [],
            'message' => ''
        ];
        
        foreach ($products as $idx => $product_data) {
            try {
                $name = $product_data['name'] ?? '';
                if (!$name) {
                    $result['errors'][] = "محصول شماره " . ($idx + 1) . ": نام ندارد";
                    continue;
                }
                
                // بررسی ویژگی‌های متغیر
                if (($product_data['type'] ?? 'simple') === 'variable') {
                    if (empty($product_data['attributes'])) {
                        $result['errors'][] = "محصول '$name': ویژگی (attributes) ندارد";
                        continue;
                    }
                    if (empty($product_data['variations'])) {
                        $result['errors'][] = "محصول '$name': متغیر (variations) ندارد";
                        continue;
                    }
                    
                    // بررسی متغیرات
                    foreach ($product_data['variations'] as $v_idx => $variation) {
                        if (empty($variation['attributes'])) {
                            $result['errors'][] = "محصول '$name' - متغیر " . ($v_idx + 1) . ": ویژگی‌های متغیر خالی است";
                            continue;
                        }
                        if (empty($variation['price'])) {
                            $result['errors'][] = "محصول '$name' - متغیر " . ($v_idx + 1) . ": قیمت ندارد";
                            continue;
                        }
                    }
                }
                
                // دسته‌بندی‌ها
                $category_ids = [];
                if (!empty($product_data['categories'])) {
                    foreach ($product_data['categories'] as $cat) {
                        $cat_id = $this->sync_category($cat);
                        if ($cat_id) {
                            $category_ids[] = $cat_id;
                            $result['categories']++;
                        }
                    }
                }
                
                // محصول ایجاد کن
                $post_id = wp_insert_post([
                    'post_title' => $name,
                    'post_content' => $product_data['description'] ?? '',
                    'post_excerpt' => $product_data['short_description'] ?? '',
                    'post_type' => 'product',
                    'post_status' => 'publish',
                ]);
                
                if (!$post_id) continue;
                
                // ابھی پہلے product ایجاد کریں
                $product = wc_get_product($post_id);
                
                // نوع معلوم کریں
                $product_type = $product_data['type'] ?? 'simple';
                
                // اگر متغیر ہے تو پہلے simple data save کریں
                if ($product_type === 'variable') {
                    // ابھی متغیر type set نہ کریں - یہ بعد میں ہوگا
                    // ابھی صرف base data
                    $product->set_sku($product_data['sku'] ?? '');
                    $product->set_price($product_data['price'] ?? 0);
                    $product->set_stock_quantity($product_data['stock_quantity'] ?? 0);
                    $product->save(); // پہلا save
                } else {
                    // سادہ محصول
                    $product->set_sku($product_data['sku'] ?? '');
                    $product->set_price($product_data['price'] ?? 0);
                    $product->set_stock_quantity($product_data['stock_quantity'] ?? 0);
                }
                
                if (!empty($category_ids)) {
                    wp_set_post_terms($post_id, $category_ids, 'product_cat');
                }
                
                // عکس‌ها
                if (!empty($product_data['image_urls'])) {
                    foreach ($product_data['image_urls'] as $idx => $image_url) {
                        $img_id = $this->download_image($image_url, $post_id);
                        if ($img_id) {
                            if ($idx === 0) {
                                $product->set_image_id($img_id);
                            } else {
                                $gallery = $product->get_gallery_image_ids() ?? [];
                                $gallery[] = $img_id;
                                $product->set_gallery_image_ids($gallery);
                            }
                            $result['images']++;
                        }
                    }
                }
                
                // برای محصولات متغیر
                if ($product_type === 'variable' && !empty($product_data['attributes'])) {
                    $attr_ids = [];
                    $wc_attributes = [];
                    
                    // پہلے تمام attributes sync کریں
                    foreach ($product_data['attributes'] as $attr_name => $attr_data) {
                        // URL-decode نام ویژگی
                        if (strpos($attr_name, '%') !== false) {
                            $attr_name = urldecode($attr_name);
                        }
                        
                        $attr_id = $this->sync_attribute($attr_name, $attr_data['values'] ?? []);
                        if ($attr_id) {
                            $attr_ids[$attr_name] = $attr_id;
                            $result['attributes']++;
                            
                            // ویژگی کو محصول میں شامل کریں
                            $clean_attr_name = sanitize_title($attr_name);
                            $wc_attributes['pa_' . $clean_attr_name] = [
                                'name' => 'pa_' . $clean_attr_name,
                                'value' => implode(' | ', array_map(function($v) {
                                    return isset($v['name']) ? (strpos($v['name'], '%') !== false ? urldecode($v['name']) : $v['name']) : '';
                                }, $attr_data['values'] ?? [])),
                                'position' => 0,
                                'visible' => 1,
                                'variation' => 1
                            ];
                        }
                    }
                    
                    // اب type کو variable سیٹ کریں اور attributes لگائیں
                    wp_set_object_terms($post_id, array_keys($wc_attributes), 'pa_variable');
                    
                    $product->set_attributes($wc_attributes);
                    $product->save();
                    
                    // اب type کو update کریں
                    update_post_meta($post_id, '_product_type', 'variable');
                    
                    // متغیرات ایجاد کریں
                    if (!empty($product_data['variations'])) {
                        foreach ($product_data['variations'] as $variation_data) {
                            $var_created = $this->create_variation($post_id, $variation_data, $attr_ids);
                            if ($var_created) {
                                $result['variations']++;
                            }
                        }
                    }
                } else {
                    // سادہ محصول کو save کریں
                    $product->save();
                }
                $result['created']++;
                
            } catch (Exception $e) {
                error_log('PIE Import Error: ' . $e->getMessage());
            }
        }
        
        $result['message'] = sprintf(
            '✓ محصولات: %d | دسته‌بندی‌ها: %d | ویژگی‌ها: %d | متغیرات: %d | عکس‌ها: %d',
            $result['created'],
            $result['categories'],
            $result['attributes'],
            $result['variations'],
            $result['images']
        );
        
        if (!empty($result['errors'])) {
            $result['message'] .= '<br><br><strong>خطاها:</strong><br>';
            foreach (array_slice($result['errors'], 0, 10) as $error) {
                $result['message'] .= '• ' . $error . '<br>';
            }
            if (count($result['errors']) > 10) {
                $result['message'] .= '• ... و ' . (count($result['errors']) - 10) . ' خطای دیگر';
            }
        }
        
        return $result;
    }
    
    private function sync_category($cat_data) {
        try {
            $parent_id = 0;
            
            // پدر دسته‌بندی را پردازش کنید اگر موجود باشد
            if (!empty($cat_data['parent_id']) && $cat_data['parent_id'] > 0) {
                // اول پدر را بررسی کنید
                $parent_term = get_term($cat_data['parent_id'], 'product_cat');
                if ($parent_term && !is_wp_error($parent_term)) {
                    $parent_id = $parent_term->term_id;
                }
            }
            
            // URL-decode slug و نام اگر لازم باشد
            $slug = $cat_data['slug'] ?? '';
            if (strpos($slug, '%') !== false) {
                $slug = urldecode($slug);
                $slug = sanitize_title($slug);
            }
            
            $name = $cat_data['name'] ?? '';
            if (strpos($name, '%') !== false) {
                $name = urldecode($name);
            }
            
            // دسته‌بندی را ایجاد یا دریافت کن
            $term = term_exists($slug, 'product_cat');
            
            if ($term) {
                return is_array($term) ? $term['term_id'] : $term;
            }
            
            $term = wp_insert_term(
                $name,
                'product_cat',
                [
                    'slug' => $slug ?: sanitize_title($name),
                    'parent' => $parent_id,
                ]
            );
            
            return is_array($term) ? $term['term_id'] : $term;
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function sync_attribute($attr_name, $values) {
        try {
            // URL-decode نام ویژگی
            if (strpos($attr_name, '%') !== false) {
                $attr_name = urldecode($attr_name);
            }
            
            $attr_slug = sanitize_title($attr_name);
            $attr_id = wc_attribute_taxonomy_id_by_name($attr_slug);
            
            if ($attr_id) {
                return $attr_id;
            }
            
            // ویژگی را ایجاد کن
            $attr_id = wc_create_attribute([
                'name' => $attr_name,
                'slug' => $attr_slug,
                'type' => 'select',
                'order_by' => 'menu_order',
                'has_archives' => false,
            ]);
            
            if (is_wp_error($attr_id)) {
                return null;
            }
            
            // مقادیر را اضافه کن
            if (!empty($values)) {
                $taxonomy = wc_attribute_taxonomy_name($attr_slug);
                foreach ($values as $value) {
                    $value_name = $value['name'] ?? '';
                    $value_slug = $value['slug'] ?? '';
                    
                    // URL-decode مقادیر
                    if (strpos($value_name, '%') !== false) {
                        $value_name = urldecode($value_name);
                    }
                    if (strpos($value_slug, '%') !== false) {
                        $value_slug = urldecode($value_slug);
                    }
                    
                    $term_slug = $value_slug ?: sanitize_title($value_name);
                    $term = term_exists($term_slug, $taxonomy);
                    
                    if (!$term) {
                        wp_insert_term(
                            $value_name,
                            $taxonomy,
                            ['slug' => $term_slug]
                        );
                    }
                }
            }
            
            return $attr_id;
        } catch (Exception $e) {
            error_log('Attribute sync error: ' . $e->getMessage());
            return null;
        }
    }
    
    private function create_variation($product_id, $variation_data, $attr_ids) {
        try {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            
            // ویژگی‌ها - حتی اگر خالی باشد
            $attributes = [];
            if (!empty($variation_data['attributes'])) {
                foreach ($variation_data['attributes'] as $attr_name => $attr_value) {
                    // URL-decode اگر لازم باشد
                    if (strpos($attr_name, '%') !== false) {
                        $attr_name = urldecode($attr_name);
                    }
                    if (strpos($attr_value, '%') !== false) {
                        $attr_value = urldecode($attr_value);
                    }
                    
                    if (!empty($attr_value)) {
                        $clean_attr_name = sanitize_title($attr_name);
                        $attributes['pa_' . $clean_attr_name] = $attr_value;
                    }
                }
            }
            
            if (!empty($attributes)) {
                $variation->set_attributes($attributes);
            }
            
            // داده‌های قیمت و موجودی
            // اگر قیمت خالی باشد، قیمت محصول اصلی استفاده می‌شود
            if (!empty($variation_data['price'])) {
                $variation->set_price($variation_data['price']);
            }
            
            $stock = $variation_data['stock_quantity'] ?? 0;
            $variation->set_stock_quantity($stock);
            
            if (!empty($variation_data['sku'])) {
                $variation->set_sku($variation_data['sku']);
            }
            
            // عکس
            if (!empty($variation_data['image_url'])) {
                $img_id = $this->download_image($variation_data['image_url'], 0);
                if ($img_id) {
                    $variation->set_image_id($img_id);
                }
            }
            
            $var_id = $variation->save();
            return !is_wp_error($var_id) && $var_id;
        } catch (Exception $e) {
            error_log('Variation creation error: ' . $e->getMessage());
            return false;
        }
    }
    
    private function download_image($image_url, $post_id) {
        try {
            if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
                error_log('Invalid image URL: ' . $image_url);
                return null;
            }
            
            // دانلود با timeout بیشتر
            $response = wp_remote_get($image_url, [
                'timeout' => 30,
                'sslverify' => false,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
            ]);
            
            if (is_wp_error($response)) {
                error_log('Image download error: ' . $response->get_error_message());
                return null;
            }
            
            $image_data = wp_remote_retrieve_body($response);
            if (empty($image_data)) {
                error_log('Empty image data for: ' . $image_url);
                return null;
            }
            
            $filename = basename(parse_url($image_url, PHP_URL_PATH));
            if (empty($filename) || !preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
                $filename = 'image-' . time() . '-' . rand(1000, 9999) . '.jpg';
            }
            
            $upload = wp_upload_bits($filename, null, $image_data);
            if (!empty($upload['error'])) {
                error_log('Upload error: ' . $upload['error']);
                return null;
            }
            
            $filetype = wp_check_filetype($filename);
            $attachment = [
                'post_mime_type' => $filetype['type'] ?: 'image/jpeg',
                'post_title' => sanitize_file_name($filename),
                'post_status' => 'inherit'
            ];
            
            if ($post_id > 0) {
                $attachment['post_parent'] = $post_id;
            }
            
            $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
            if (is_wp_error($attachment_id)) {
                error_log('Attachment insert error: ' . $attachment_id->get_error_message());
                return null;
            }
            
            if ($attachment_id) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
                if ($metadata) {
                    wp_update_attachment_metadata($attachment_id, $metadata);
                }
                return $attachment_id;
            }
            
            return null;
        } catch (Exception $e) {
            error_log('Image download exception: ' . $e->getMessage());
            return null;
        }
    }
    
    private function convert_to_csv($products) {
        $csv = "نام,SKU,قیمت,موجودی,نوع,دسته‌بندی\n";
        
        foreach ($products as $product) {
            $categories = '';
            if (!empty($product['categories'])) {
                $cat_names = [];
                foreach ($product['categories'] as $cat) {
                    $cat_names[] = $cat['name'];
                }
                $categories = implode(';', $cat_names);
            }
            
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s"' . "\n",
                str_replace('"', '""', $product['name']),
                $product['sku'] ?? '',
                $product['price'] ?? '',
                $product['stock_quantity'] ?? '',
                $product['type'] ?? 'simple',
                str_replace('"', '""', $categories)
            );
        }
        
        return mb_convert_encoding($csv, 'UTF-8', 'UTF-8');
    }
    
    private function parse_csv($content) {
        $lines = explode("\n", trim($content));
        if (empty($lines)) return [];
        
        $header = str_getcsv(array_shift($lines));
        $products = [];
        
        foreach ($lines as $line) {
            if (empty($line)) continue;
            $values = str_getcsv($line);
            if (count($values) !== count($header)) continue;
            
            $products[] = array_combine($header, $values);
        }
        
        return $products;
    }
}

// فعال‌سازی پلاگین
Product_Import_Export::get_instance();
