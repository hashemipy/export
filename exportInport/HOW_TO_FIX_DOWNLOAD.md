# حل مشکل دانلود محصولات متغیر

## مشکل اصلی

وقتی محصول متغیر را دانلود می‌کنید، مشکلات زیر رخ می‌دهد:

1. **Attribute keys غلط**: `pa_color` بجای `color`
2. **Slugs URL-encoded**: `%d8%b3%d8%a8%d8%b2` بجای `sabz`
3. **Variations خالی**: `attributes: []`, `price: ""`, `sku: ""`

## حل

به‌روزرسانی کد دانلود انجام شد! حالا:

✓ Attribute keys صحیح دانلود می‌شود (`color`, `size` بجای `pa_color`, `pa_size`)
✓ Slugs صحیح دانلود می‌شود (فارسی یا معمولی)
✓ Variations کامل دانلود می‌شود (sku, price, stock, attributes)

## مراحل استفاده

### 1. به داشبورد WooCommerce برروید
- WooCommerce > Import/Export

### 2. محصول را انتخاب کنید
- از جدول سمت راست
- محصول متغیر را انتخاب کنید

### 3. دانلود کنید
- دکمه "دانلود" را کلیک کنید
- فایل JSON دانلود می‌شود

### 4. فایل صحیح است!
- Attributes صحیح
- Variations کامل
- آماده برای آپلود به سایت دوم

## اگر هنوز مشکل دارد

اگر دانلود جدید هم مشکل دارد، اسکریپت Python زیر را اجرا کنید:

```python
import json

# فایل را بخوانید
with open('products-1782126242991.json', 'r', encoding='utf-8') as f:
    products = json.load(f)

# برای هر محصول
for product in products:
    # تمیز کردن attribute keys
    if 'attributes' in product:
        new_attrs = {}
        for key, value in product['attributes'].items():
            # حذف pa_ پیشوند
            clean_key = key.replace('pa_', '')
            new_attrs[clean_key] = value
        product['attributes'] = new_attrs
    
    # تمیز کردن category slugs
    if 'categories' in product:
        for cat in product['categories']:
            if '%' in cat['slug']:
                # URL decode
                from urllib.parse import unquote
                cat['slug'] = unquote(cat['slug'])

# ذخیره کنید
with open('products-fixed.json', 'w', encoding='utf-8') as f:
    json.dump(products, f, ensure_ascii=False, indent=2)

print("✓ فایل اصلاح شد: products-fixed.json")
```

## اگر Python ندارید

استفاده کنید از: `products-correct-example.json`

## نتیجه

حالا می‌توانید:
1. فایل دانلود شده را بررسی کنید
2. دکمه "✓ بررسی فایل" را کلیک کنید
3. اگر معتبر بود، آپلود کنید

