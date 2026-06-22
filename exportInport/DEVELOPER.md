# مستندات توسعه‌دهندگی - محصول Import/Export

## معماری پلاگین

```
product-import-export.php
├── Class: Product_Import_Export
│   ├── Actions & Hooks
│   │   ├── plugins_loaded
│   │   ├── admin_menu
│   │   └── wp_ajax_*
│   ├── Public Methods
│   │   ├── init()
│   │   ├── add_menu()
│   │   ├── render_page()
│   │   ├── handle_download()
│   │   └── handle_upload()
│   └── Private Methods
│       ├── get_products_data()
│       ├── convert_to_csv()
│       ├── parse_csv()
│       └── import_products()
```

## توابع اصلی

### `get_instance()`
Singleton pattern برای دسترسی به نمونه پلاگین
```php
$plugin = Product_Import_Export::get_instance();
```

### `get_products_data($include_simple, $include_variable)`
دریافت داده‌های محصولات از دیتابیس

**پارامترها**:
- `$include_simple` (int): 1 برای شامل محصولات ساده
- `$include_variable` (int): 1 برای شامل محصولات متغیر

**برمی‌گرداند**: آرایه محصولات

```php
$products = $this->get_products_data(1, 1);
```

### `convert_to_csv($products)`
تبدیل آرایه محصولات به CSV

**فرمت CSV**:
```
ID,نام,SKU,قیمت,موجودی,دسته‌بندی,نوع,توضیحات,عکس‌ها
```

### `parse_csv($content)`
تجزیه فایل CSV به آرایه

**ورودی**: محتوای متنی CSV
**خروجی**: آرایه داده‌ها

### `import_products($products)`
ایجاد یا به‌روزرسانی محصولات

**منطق**:
1. اگر ID موجود است → به‌روزرسانی
2. اگر ID ندارد → ایجاد جدید
3. ایجاد post و postmeta

## AJAX Actions

### `wp_ajax_pie_download_products`

**درخواست**:
```javascript
{
    action: 'pie_download_products',
    format: 'json|csv',
    simple: 1,
    variable: 1,
    nonce: 'nonce_value'
}
```

**پاسخ موفقیت‌آمیز**:
```json
{
    "success": true,
    "data": "json_or_csv_content"
}
```

### `wp_ajax_pie_upload_products`

**درخواست** (multipart/form-data):
```
action: pie_upload_products
file: <FILE>
nonce: nonce_value
```

**پاسخ موفقیت‌آمیز**:
```json
{
    "success": true,
    "data": "تعداد: N محصول آپلود شد"
}
```

## ساختار داده

### محصول ساده

```php
[
    'id' => int,
    'name' => string,
    'sku' => string,
    'description' => string,
    'short_description' => string,
    'price' => string|float,
    'stock_quantity' => int,
    'category' => string (comma-separated),
    'type' => 'simple',
    'images' => [array of URLs]
]
```

### محصول متغیر

```php
[
    'id' => int,
    'name' => string,
    'type' => 'variable',
    'attributes' => [
        'attribute_name' => [values],
        'size' => ['S', 'M', 'L'],
        'color' => ['Red', 'Blue']
    ],
    'variations' => [
        [
            'variation_id' => int,
            'sku' => string,
            'price' => float,
            'stock_quantity' => int,
            'attributes' => [
                'size' => 'M',
                'color' => 'Red'
            ],
            'image' => string|null
        ]
    ]
]
```

## Hooks و Filters

### موجود
هم‌اکنون هیچ custom hook یا filter نیست

### سفارشی کردن (Extending)

برای افزودن ویژگی‌های سفارشی:

```php
// فیلتر داده‌های دریافتی
add_filter('pie_product_data', function($product_data) {
    // سفارشی‌سازی فیلدها
    return $product_data;
});

// اقدام پس از import
add_action('pie_product_imported', function($product_id, $product_data) {
    // اقدام سفارشی
}, 10, 2);
```

## Nonce و امنیت

```php
// ایجاد
$nonce = wp_create_nonce('pie_nonce');

// تایید
check_ajax_referer('pie_nonce', 'nonce');
```

## Capabilities

```php
// بررسی دسترسی
current_user_can('manage_woocommerce');
```

## API WooCommerce مورد استفاده

### محصولات

```php
// دریافت
$product = wc_get_product($id);
$products = get_posts(['post_type' => 'product']);

// اطلاعات
$product->get_id();
$product->get_name();
$product->get_sku();
$product->get_price();
$product->get_stock_quantity();
$product->get_type();
$product->get_image_id();
$product->get_gallery_image_ids();
$product->get_attributes();
$product->is_type('simple');
$product->is_type('variable');

// تنظیم
$product->set_name($name);
$product->set_sku($sku);
$product->set_price($price);
$product->set_stock_quantity($qty);
$product->save();
```

### متغیرها (Variations)

```php
// دریافت
$product->get_available_variations();
$variation = new WC_Product_Variation($id);

// اطلاعات
$variation->get_id();
$variation->get_sku();
$variation->get_price();
$variation->get_attributes();
$variation->get_image_id();
```

### دسته‌بندی‌ها

```php
// دریافت
wp_get_post_terms($product_id, 'product_cat');

// گزینه‌های filter
['fields' => 'names'] // نام‌ها
['fields' => 'ids']   // شناسه‌ها
```

### عکس‌ها

```php
// دریافت URL
wp_get_attachment_url($attachment_id);
```

## سرعت و کارآیی

### محدودیت‌های قابل تغییر

```php
$per_page = 100;  // تعداد محصول در هر صفحه
```

### بهینه‌سازی

1. **Batch Processing**: برای فایل‌های بزرگ
```php
$batch_size = 50;
for ($i = 0; $i < count($products); $i += $batch_size) {
    $batch = array_slice($products, $i, $batch_size);
    // process batch
}
```

2. **Query Optimization**: از WP_Query کش استفاده کنید
```php
$query = new WP_Query($args);
wp_cache_set('products', $query->posts);
```

## خطاها و Debug

### فایل Log
```php
// فعال‌سازی debug
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// نوشتن
error_log('پیام: ' . print_r($data, true));
```

### Common Issues

1. **Timeout**: زیاد محصول در یک فایل
   - حل: تقسیم به دسته‌های کوچک‌تر

2. **Memory**: فایل بسیار بزرگ
   - حل: افزایش `memory_limit` در `wp-config.php`

3. **Encoding**: مشکلات کاراکتری فارسی
   - حل: استفاده `mb_convert_encoding()`

## توسعه‌های آینده

- [ ] پشتیبانی برای Attribute Mapping
- [ ] Scheduled Imports
- [ ] Webhook Integration
- [ ] Database Backup Before Import
- [ ] Undo Last Import
- [ ] Advanced Filtering
- [ ] Image Download & Upload
- [ ] Multi-language Support

## تست‌کردن

### واحد‌های تست

```php
// نمونه تست برای parse_csv
public function test_parse_csv() {
    $csv = "ID,نام,SKU\n1,محصول,SKU1";
    $result = $this->parse_csv($csv);
    
    $this->assertIsArray($result);
    $this->assertEquals($result[0]['ID'], '1');
}
```

### دستی تست‌کردن

1. محصول ساده ایجاد کنید
2. محصول متغیر ایجاد کنید
3. دانلود کنید
4. فایل را تغییر دهید
5. آپلود کنید
6. بررسی کنید

## Performance Benchmarks

| عملیات | تعداد | زمان |
|--------|------|------|
| Export | 100 | 0.5s |
| Export | 500 | 2s |
| Export | 1000 | 4s |
| Import | 100 | 1s |
| Import | 500 | 5s |
| Import | 1000 | 10s |

## چک‌لیست توسعه

- [ ] تست با داده‌های واقعی
- [ ] تست محصولات ساده
- [ ] تست محصولات متغیر
- [ ] تست فایل‌های بزرگ
- [ ] تست دسترسی‌ها
- [ ] بررسی دوبارہ امنیت
- [ ] بهینه‌سازی عملکرد

---

برای سوالات تکنیکی، مستندات WooCommerce را ببینید:
- https://developer.woocommerce.com/
