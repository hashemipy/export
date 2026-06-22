# مشکل آپلود - راهنمای حل

## خلاصه مشکل

فایل JSON دانلود شده از سایت اول دارای **سه مشکل اصلی** است:

### 1. Attributes غلط فرمت
- Attribute keys درست نیستند
- Slugs URL-encoded هستند

### 2. Variations خالی
- تمام متغیرات بدون attributes هستند
- بدون price و stock_quantity

### 3. Categories بدون parent name
- فقط parent_id وجود دارد نه نام

---

## سه راه برای حل

### راه 1: استفاده از فایل نمونه (ساده‌ترین)
```
فایل: products-fixed.json
```
- این فایل به صورت صحیح فرمت شده است
- می‌توانید از آن به عنوان الگو استفاده کنید
- فقط اطلاعات محصول خود را جایگزین کنید

### راه 2: اصلاح خودکار با Python (توصیه شده)
```bash
python fix_json.py products-1782124853186.json products-fixed.json
```

**ویژگی‌ها:**
- ✓ تمام URL-encoded strings را decode می‌کند
- ✓ Attribute keys را تمیز می‌کند
- ✓ Slugs ساده را ایجاد می‌کند
- ✓ Variations را بررسی می‌کند

### راه 3: اصلاح دستی (اگر Python ندارید)

#### Step 1: JSON Validator استفاده کنید
بروید به: https://jsonlint.com/

#### Step 2: مشکلات را پیدا کنید
```json
// ❌ غلط
"attributes": {
    "pa_color": { ... }
}

// ✓ درست
"attributes": {
    "color": { ... }
}
```

#### Step 3: Attributes را اصلاح کنید
```json
// ❌ غلط
"slug": "%d8%a2%d8%b3%d8%aa%db%8c%d9%86"

// ✓ درست
"slug": "category-slug"
```

#### Step 4: Variations را پر کنید
```json
// ❌ خالی
"variations": [
    {
        "sku": "",
        "price": "",
        "attributes": []
    }
]

// ✓ پر شده
"variations": [
    {
        "sku": "SKU-1",
        "price": "100000",
        "stock_quantity": 5,
        "attributes": {
            "color": "سفید",
            "size": "M"
        }
    }
]
```

---

## بعد از اصلاح: آپلود صحیح

1. **فایل JSON درست شده را آپلود کنید**
   ```
   به WooCommerce → Import/Export برروید
   فایل را انتخاب کنید
   آپلود کنید
   ```

2. **گزارش را بخوانید**
   ```
   ✓ محصولات: X
   ✓ دسته‌بندی‌ها: Y
   ✓ ویژگی‌ها: Z
   ✓ متغیرات: N
   ```

3. **اگر خطا داشت**
   ```
   خطاها:
   • محصول 1: ...
   • محصول 2: ...
   ```

---

## نمایش فایل‌های کمک

| فایل | توضیح |
|------|--------|
| `products-fixed.json` | فایل نمونه اصلاح شده |
| `JSON_TROUBLESHOOTING.md` | راهنمای اصلاح تفصیلی |
| `fix_json.py` | اسکریپت اصلاح خودکار |

---

## مثال: نسخه خالی vs صحیح

### ❌ نسخه خالی (مشکل دار)
```json
{
    "name": "تیشرت",
    "type": "variable",
    "attributes": {
        "pa_color": {
            "values": [
                {"name": "سفید", "slug": "%d8%b3%d9%81%db%8c%d8%af"}
            ]
        }
    },
    "variations": [
        {
            "sku": "",
            "price": "",
            "attributes": []
        }
    ]
}
```

### ✓ نسخه صحیح
```json
{
    "name": "تیشرت",
    "sku": "SHIRT-001",
    "type": "variable",
    "price": "100000",
    "attributes": {
        "color": {
            "values": [
                {"name": "سفید", "slug": "white"}
            ]
        }
    },
    "variations": [
        {
            "sku": "SHIRT-001-W",
            "price": "100000",
            "stock_quantity": 10,
            "attributes": {
                "color": "سفید"
            }
        }
    ]
}
```

---

## نکات مهم

⚠️ **اطمینان حاصل کنید:**
- ✓ تمام JSON valid است
- ✓ تمام variations دارای attributes هستند
- ✓ تمام variations دارای price هستند
- ✓ slugs معمولی هستند (نه URL-encoded)

---

## کمک بیشتر؟

اگر هنوز مشکل دارید:
1. `JSON_TROUBLESHOOTING.md` را بخوانید
2. `products-fixed.json` را به عنوان الگو استفاده کنید
3. گزارش خطا را با دقت بخوانید
