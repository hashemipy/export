# 🔧 راهنمای اصلاح محصولات متغیر

## 🎯 مشکل اصلی
محصول شما **از نوع "متغیر" (variable) به نوع "ساده" (simple) تبدیل می‌شود** وقتی:
1. دانلود می‌کنید
2. به سایت دیگر اپلود می‌کنید

---

## 🐛 دلایل اصلی مشکل

### مشکل ۱: کد تکراری (DUPLICATE CODE)
**خط ۹۳۰-۹۷۷ و خط ۱۰۰۱-۱۰۵۱**

کد شما **محصول متغیر را دو بار پردازش می‌کند**:
- **اول**: خط ۹۳۰ تا ۹۷۷
- **دوم**: خط ۱۰۰۱ تا ۱۰۵۱ (کد تکراری)

این منجر می‌شود:
- Attributes دوبار set شود
- Type متغیر کم‌تر شود
- آخرین پردازش type را override کند

✅ **راه‌حل**: کد تکراری حذف شد

---

### مشکل ۲: ترتیب اجرای صحیح
**محصول متغیر باید به این ترتیب پردازش شود:**

```
1️⃣ Create Post (wp_insert_post)
   ↓
2️⃣ Set Basic Data (SKU, Price, Stock) - فقط داده‌های اصلی
   ↓
3️⃣ Set Type = 'variable' ← ⚠️ حتمی!
   ↓
4️⃣ Create/Sync Attributes
   ↓
5️⃣ Set Attributes on Product
   ↓
6️⃣ Create Variations
```

❌ **غلط**: اگر فقط `update_post_meta` استفاده شود
✅ **درست**: استفاده از `$product->set_type('variable')`

---

## 📋 تغییرات انجام شده

### کدام بخش‌ها اصلاح شدند؟

```php
// ❌ REMOVED (خطوط 982-1051) - کد تکراری
// این بخش دوبار محصول متغیر را پردازش می‌کرد

// ✅ KEPT (خطوط 930-977) - کد صحیح
if ($product_type === 'variable' && !empty($product_data['attributes'])) {
    $product->set_type('variable');  // ← حتمی!
    $product->save();                 // ← اول save
    
    // سپس attributes
    // سپس variations
}
```

---

## 🧪 تست کردن

### مرحله ۱: دانلود محصول متغیر
```
✓ محصولی انتخاب کنید که نوع آن "متغیر" است
✓ دانلود کنید (JSON یا CSV)
```

### مرحله ۲: بررسی JSON
```json
{
    "name": "تیشرت شلوارک",
    "type": "variable",        // ← باید "variable" باشد
    "attributes": {...},       // ← ویژگی‌ها موجود باشند
    "variations": [...]        // ← متغیرات موجود باشند
}
```

### مرحله ۳: اپلود به سایت دیگر
```
✓ JSON را اپلود کنید
✓ محصول اپلود شود
✓ نوع محصول = "متغیر" بماند (نه "ساده")
```

---

## 📌 نکات مهم

### ۱. JSON/CSV Format
محصول متغیر **باید** این فیلد‌ها داشته باشد:

```json
{
    "type": "variable",
    "attributes": {
        "color": {
            "values": [
                {"name": "قرمز", "slug": "red"},
                {"name": "آبی", "slug": "blue"}
            ]
        },
        "size": {
            "values": [
                {"name": "M", "slug": "m"},
                {"name": "L", "slug": "l"}
            ]
        }
    },
    "variations": [
        {
            "sku": "RED-M",
            "price": "100000",
            "attributes": {
                "color": "red",
                "size": "m"
            }
        }
    ]
}
```

### ۲. WooCommerce Behavior
- وقتی type = "variable" ہے، WooCommerce محصول کو **variable محصول** می‌شناسد
- اگر attributes یا variations نباشد → محصول **simple** می‌شود

### ۳. API/Plugin Compatibility
اگر سایت دیگر پلاگین دیگری برای import استفاده می‌کند:
- اطمینان حاصل کنید JSON format درست باشد
- اطمینان حاصل کنید type = "variable" صحیح فرستاده شود

---

## ❓ سوالات متداول

**Q: چرا محصول من از متغیر به ساده تبدیل شد؟**
> A: احتمالاً attributes یا variations در JSON نبود یا کد duplicate بود

**Q: JSON من کجا غلط است؟**
> A: بررسی کنید:
> - `"type": "variable"` موجود است؟
> - `"attributes"` خالی نیست؟
> - `"variations"` خالی نیست؟

**Q: اگر سایت دیگر اپلود نمی‌کند؟**
> A: بررسی کنید:
> - Plugin import دریافت شده چیست؟
> - آیا از WooCommerce API استفاده می‌کند؟
> - فرمت JSON مطابق است؟

---

## 🚀 نتیجه

**قبل**: محصول متغیر → دانلود → اپلود → محصول ساده ❌

**بعد**: محصول متغیر → دانلود → اپلود → محصول متغیر ✅

فایل `product-import-export.php` اصلاح شد و اکنون محصولات متغیر به‌درستی پردازش می‌شوند.
