# تغییرات فنی - نسخه 1.1.0

## خلاصه تغییرات

### 🎯 هدف اصلی
تغییر از **دانلود تمام محصولات یا فیلتر ساده/متغیر** به **انتخاب دقیق محصولات و دانلود فقط انتخاب‌شده‌ها**

---

## تغییرات در کد

### 1. متدهای جدید

#### `handle_get_products_list()`
```php
// برای بارگذاری لیست محصولات به صورت JSON
// استفاده: AJAX call برای جدول محصولات
// خروجی: آرایه محصولات با اطلاعات ساده
```

**پارامترهای ورودی:**
- `nonce`: نامه امنیتی

**خروجی (JSON):**
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "name": "نام محصول",
      "sku": "SKU123",
      "price": "150,000 تومان",
      "stock_quantity": 10,
      "type": "simple"
    }
  ]
}
```

#### `get_selected_products_data($product_ids)`
```php
// برای دریافت داده‌های کامل محصولات انتخاب‌شده
// استفاده: دانلود محصولات انتخاب‌شده
// خروجی: آرایه کامل محصولات با تمام اطلاعات
```

**پارامترهای ورودی:**
- `$product_ids`: آرایه ID های محصولات
  ```php
  $product_ids = [123, 456, 789];
  ```

**خروجی:**
```php
[
  [
    'id' => 123,
    'name' => 'محصول',
    'sku' => 'SKU',
    'price' => 100,
    'stock_quantity' => 5,
    'type' => 'simple',
    'images' => ['url1', 'url2'],
    'category' => 'دسته‌بندی',
    // ...
  ]
]
```

---

### 2. متدهای حذف‌شده

#### `get_products_data($include_simple, $include_variable)` ❌
**دلیل:** جایگزین شد با `get_selected_products_data()`

**قبل:**
```php
$products = $this->get_products_data(1, 1);
// تمام محصولات دریافت می‌کند
```

**بعد:**
```php
$product_ids = [123, 456];
$products = $this->get_selected_products_data($product_ids);
// فقط محصولات انتخاب‌شده
```

---

### 3. متدهای اصلاح‌شده

#### `handle_download()` 🔄
**تغییر:** پارامترهای ورودی تغییر کرده

**پارامترهای قدیم:**
```javascript
data: {
    action: 'pie_download_products',
    format: 'json',
    simple: 1,
    variable: 1,
    nonce: '...'
}
```

**پارامترهای جدید:**
```javascript
data: {
    action: 'pie_download_products',
    format: 'json',
    product_ids: "123,456,789",
    nonce: '...'
}
```

**کد:**
```php
// قدیم
$products = $this->get_products_data($include_simple, $include_variable);

// جدید
$ids_array = array_map('intval', explode(',', $product_ids));
$products = $this->get_selected_products_data($ids_array);
```

#### `render_page()` 🔄
**تغییر:** رابط کاربری کاملاً جدید

**طراحی:**
- **قبل:** فرم ساده با رادیو و چک‌باکس
- **بعد:** جدول محصولات + پنل تنظیمات

---

## تغییرات JavaScript

### جاوااسکریپت جدید

#### `loadProductsList()`
```javascript
// بارگذاری لیست محصولات از سرور
// AJAX: pie_get_products_list
// خروجی: رندر جدول
```

#### `renderProductsTable(products)`
```javascript
// رندر جدول محصولات
// ورودی: آرایه محصولات
// خروجی: HTML جدول
```

#### `updateSelectedCount()`
```javascript
// به‌روزرسانی تعداد انتخاب‌شده
// اجرا: هر بار چک‌باکس تغییر یابد
```

#### فیلتر عملکرد
```javascript
// فیلتر کردن محصولات
// "همه" / "ساده" / "متغیر"
// رندر مجدد جدول
```

---

## تغییرات AJAX

### درخواست جدید ⭐

**نام:** `pie_get_products_list`
**متد:** POST
**نامه:** nonce

**نتیجه:**
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "name": "محصول",
      "sku": "SKU",
      "price": "100 تومان",
      "stock_quantity": 5,
      "type": "simple"
    }
  ]
}
```

### درخواست اصلاح‌شده 🔄

**نام:** `pie_download_products`

**قبل:**
```javascript
{
    action: 'pie_download_products',
    format: 'json',
    simple: 1,
    variable: 1
}
```

**بعد:**
```javascript
{
    action: 'pie_download_products',
    format: 'json',
    product_ids: "123,456,789"
}
```

---

## بهینه‌سازی عملکرد

### 1. سرور
- **قبل:** پردازش تمام محصولات
- **بعد:** پردازش فقط انتخاب‌شده‌ها ⚡

### 2. شبکه
- **قبل:** فایل بزرگ برای دانلود
- **بعد:** فایل کوچک‌تر ⚡

### 3. کاربر
- **قبل:** دانلود و فیلتر دستی
- **بعد:** دانلود مستقیم ⚡

---

## تغییرات UI

### ساختار HTML

**قبل:**
```
┌──────────────────┬──────────────────┐
│ فرم دانلود      │ فرم آپلود       │
├──────────────────┼──────────────────┤
│ ○ ساده           │ فایل + دکمه     │
│ ○ متغیر          │ نوار پیشرفت    │
│ ○ JSON/CSV      │                  │
│ [دانلود]         │                  │
└──────────────────┴──────────────────┘
```

**بعد:**
```
┌────────────────────────────┬──────────────────┐
│ جدول محصولات (انتخابی)  │ تنظیمات:        │
├────────────────────────────┤                  │
│ ☐ محصول 1               │ ○ همه           │
│ ☑ محصول 2               │ ○ ساده          │
│ ☐ محصول 3               │ ○ متغیر         │
│                            │                  │
│ انتخاب: 1                 │ [دانلود]        │
│                            │ [آپلود]         │
└────────────────────────────┴──────────────────┘
```

### CSS تغییرات
- جدول: `display: table; border-collapse: collapse`
- ردیف‌ها: `:hover` برای highlight
- چک‌باکس: استاندارد HTML
- فیلتر: inline فیلتر رادیو

---

## سازگاری

### WordPress
- ✅ 5.0+
- ✅ 6.0+
- ✅ 6.4+

### WooCommerce
- ✅ 7.0+
- ✅ 8.0+
- ✅ 8.5+

### PHP
- ✅ 7.4+
- ✅ 8.0+
- ✅ 8.1+
- ✅ 8.2+
- ✅ 8.3+

---

## امنیت

### نامه امنیتی (Nonce)
```php
// درخواست نیاز دارد:
wp_verify_nonce($_POST['nonce'], 'pie_nonce')

// تولید:
wp_create_nonce('pie_nonce')
```

### دسترسی
```php
// تمام AJAX actions به صورت:
if (!current_user_can('manage_woocommerce')) {
    wp_send_json_error('دسترسی رد شد');
}
```

### Sanitization
```php
// تمام ورودی‌ها:
$format = sanitize_text_field($_POST['format']);
$ids = array_map('intval', explode(',', $product_ids));
```

---

## تست‌های پیشنهادی

### تست دستی

```
1. ✓ بارگذاری صفحه
   └─ جدول محصولات نمایش داده شود

2. ✓ انتخاب محصول
   └─ تعداد انتخاب‌شده به‌روز شود

3. ✓ انتخاب همه
   └─ تمام ردیف‌ها انتخاب شوند

4. ✓ فیلتر
   └─ جدول به‌روز شود

5. ✓ دانلود
   └─ فایل دانلود شود
   └─ فقط انتخاب‌شده‌ها شامل شوند

6. ✓ JSON/CSV
   └─ فرمت درست باشد

7. ✓ آپلود
   └─ محصولات نهایی شوند
   └─ جدول به‌روز شود
```

---

## نکات توسعه‌ای

### Hooks موجود
```php
// فیلترها:
apply_filters('pie_products_data', $products)
apply_filters('pie_csv_content', $csv)
apply_filters('pie_json_content', $json)

// اکشن‌ها:
do_action('pie_before_download', $product_ids)
do_action('pie_after_download', $products)
do_action('pie_before_import', $products)
do_action('pie_after_import', $count)
```

### توسعه آتی
```php
// برای اضافه کردن ویژگی:
1. فیلتر جدید؟
   └─ در renderProductsTable تغییر دهید

2. ستون جدید؟
   └─ در handle_get_products_list اضافه کنید

3. صادرات جدید؟
   └─ در convert_to_X متد بسازید
```

---

## خلاصه تغییرات

| بخش | قبل | بعد | تغییر |
|------|------|-----|--------|
| رابط | فرم ساده | جدول | ✅ |
| انتخاب | فیلتر | چک‌باکس | ✅ |
| دانلود | تمام | انتخاب‌شده | ✅ |
| سرعت | عادی | سریع | ⚡ |
| کد | ساده | بهتر | 💪 |

---

**نسخه:** 1.1.0
**وضعیت:** ✅ تکمیل شده
**تاریخ:** 22 خردادماه 1402
