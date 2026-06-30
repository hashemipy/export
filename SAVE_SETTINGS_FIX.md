# راهنمای تعمیر مشکل ذخیره تنظیمات sync_direction

## مشکل
هنگام ذخیره تنظیمات جهت sync (sync_direction)، خطا: "خطا در ذخیره" نمایش می‌دهد.

## تغییرات انجام‌شده

### 1. افزودن sync_direction به AJAX handler (Settings.php:190)
```php
'sync_direction' => sanitize_text_field($_POST['sync_direction'] ?? 'bidirectional')
```

### 2. افزودن sync_direction به JavaScript data collection (Settings.php:669)
```javascript
sync_direction: $('input[name="pie_site_config[sync_direction]"]:checked').val() || 'bidirectional',
```

### 3. اصلاح AJAX handler با error handling بهتر (Settings.php:177-217)
- استفاده از try-catch
- بررسی مستقیم nonce
- بهبود logic بررسی نتیجه ذخیره
- اضافه کردن verify logic

### 4. افزودن wp_ajax_nopriv hook (Settings.php:31)
```php
add_action('wp_ajax_nopriv_pie_save_settings', [$this, 'ajax_save_settings']);
```

### 5. بهبود error handling در JavaScript (Settings.php:673-693)
- اضافه کردن dataType: 'json'
- بهتر کردن console logging
- نمایش پیشنهاد F12 برای debugging

## چگونه test کنیم

### روش 1: استفاده از Browser Console
1. تنظیمات را باز کنید
2. F12 را فشار دهید (DevTools)
3. به تب Console بروید
4. یک گزینه را انتخاب کنید و ذخیره کنید
5. پیام‌های خطا را بررسی کنید

### روش 2: بررسی Network tab
1. DevTools را باز کنید → Network tab
2. ذخیره تنظیمات را کلیک کنید
3. درخواست `admin-ajax.php` را جستجو کنید
4. Response را بررسی کنید

### روش 3: بررسی WordPress Debug Log
1. در `wp-config.php` اضافه کنید:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```
2. `/wp-content/debug.log` را بررسی کنید

## احتمالات خطا

| خطا | علت | راه‌حل |
|-----|-----|--------|
| `Nonce verification failed` | Nonce منقضی شده | صفحه را refresh کنید |
| `دسترسی ندارید` | User logged-out | دوباره login کنید |
| خطای شبکه 404 | `admin-ajax.php` پیدا نشد | WordPress صحیح install نشده |
| Database error | Permission مشکل | `wp_options` table اجازه write ندارد |

## Commits
```
- fix: حل سه مسئله Stock Sync
- fix: مشکل ذخیره sync_direction و بهتر کردن نمایش
- fix: اضافه کردن wp_ajax_nopriv hook برای ذخیره تنظیمات
- debug: اضافه کردن debug logs برای مشکل ذخیره تنظیمات
- refactor: ساده کردن AJAX save handler برای تعمیر خطا
- improve: بهتر کردن error handling و debugging
```

## اگر مشکل ادامه داشت

لطفا به من اطلاع دهید:
1. دقیق error message را کپی کنید
2. Browser Console logs را فرستادید
3. WordPress debug.log را فرستادید
4. Network Response را فرستادید

تمام اطلاعات قبلی برای debugging مفید است!
