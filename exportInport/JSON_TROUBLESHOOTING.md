# راهنمای اصلاح مشکلات JSON

## مشکلات فایل دانلود شده

فایل JSON دانلود شده از سایت اول دارای مشکلاتی است که باید اصلاح شود:

### 1. Attributes غلط

**مشکل:**
```json
"attributes": {
    "pa_color": {
        "values": [...]
    }
}
```

**حل:**
```json
"attributes": {
    "color": {
        "values": [
            {"name": "سفید", "slug": "white"},
            {"name": "قرمز", "slug": "red"}
        ]
    }
}
```

### 2. Slugs URL-Encoded

**مشکل:**
```json
"slug": "%d8%a2%d8%b3%d8%aa%db%8c%d9%86"
```

**حل:**
```json
"slug": "astin-kotah"
```

یا اگر فارسی استفاده می‌کنید:
```json
"slug": "آستین-کوتاه"
```

### 3. Variations خالی

**مشکل:**
```json
"variations": [
    {
        "sku": "",
        "price": "",
        "stock_quantity": null,
        "attributes": [],
        "image_url": null
    }
]
```

**حل:**
```json
"variations": [
    {
        "sku": "1523-white-45",
        "price": "1185000",
        "stock_quantity": 5,
        "attributes": {
            "color": "سفید",
            "size": "45"
        },
        "image_url": null
    },
    {
        "sku": "1523-white-50",
        "price": "1185000",
        "stock_quantity": 5,
        "attributes": {
            "color": "سفید",
            "size": "50"
        },
        "image_url": null
    }
]
```

### 4. Categories بدون Parent Name

**مشکل:**
```json
"categories": [
    {
        "name": "آستین کوتاه",
        "slug": "%d8%a2%d8%b3%d8%aa%db%8c%d9%86-%da%a9%d9%88%d8%aa%d8%a7%d9%87",
        "parent_id": 177
    }
]
```

**حل:**
```json
"categories": [
    {
        "name": "آستین کوتاه",
        "slug": "astin-kotah",
        "parent_id": 0
    }
]
```

---

## ساختار JSON صحیح

```json
{
    "name": "نام محصول",
    "sku": "SKU123",
    "description": "توضیحات",
    "short_description": "خلاصه",
    "price": "100000",
    "stock_quantity": 10,
    "type": "variable",
    "categories": [
        {
            "name": "دسته",
            "slug": "category-slug",
            "parent_id": 0
        }
    ],
    "image_urls": [
        "https://example.com/image1.jpg"
    ],
    "attributes": {
        "color": {
            "values": [
                {"name": "سفید", "slug": "white"},
                {"name": "سیاه", "slug": "black"}
            ],
            "visible": true
        },
        "size": {
            "values": [
                {"name": "S", "slug": "s"},
                {"name": "M", "slug": "m"}
            ],
            "visible": true
        }
    },
    "variations": [
        {
            "sku": "SKU-W-S",
            "price": "100000",
            "stock_quantity": 5,
            "attributes": {
                "color": "سفید",
                "size": "S"
            },
            "image_url": null
        },
        {
            "sku": "SKU-W-M",
            "price": "100000",
            "stock_quantity": 5,
            "attributes": {
                "color": "سفید",
                "size": "M"
            },
            "image_url": null
        }
    ]
}
```

---

## چگونه JSON را اصلاح کنم؟

### گزینه 1: استفاده از فایل نمونه
فایل `products-fixed.json` را به عنوان الگو استفاده کنید.

### گزینه 2: استفاده از Python
```python
import json
import urllib.parse

# فایل را بخوانید
with open('products.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

# URL decode کنید
for product in data:
    for cat in product.get('categories', []):
        cat['slug'] = urllib.parse.unquote(cat.get('slug', ''))
    
    for attr_name, attr_data in product.get('attributes', {}).items():
        for value in attr_data.get('values', []):
            value['slug'] = urllib.parse.unquote(value.get('slug', ''))

# دوباره بنویسید
with open('products-fixed.json', 'w', encoding='utf-8') as f:
    json.dump(data, f, ensure_ascii=False, indent=2)
```

### گزینه 3: دستی با VSCode
1. فایل JSON را باز کنید
2. Find & Replace استفاده کنید:
   - `%d8%a2`: `آ`
   - `%d8%ab`: `ث`
   - و غیره...

---

## خطاهای رایج

| خطا | علت | حل |
|------|------|-----|
| `"attributes": [] خالی` | Variations بدون attributes | مقادیر را اضافه کنید |
| `"price": "" خالی` | بدون قیمت برای متغیر | قیمت را اضافه کنید |
| `"stock_quantity": null` | موجودی معلوم نیست | مقدار عددی وارد کنید |
| خطای slugs | URL encoded است | Decode کنید یا ساده کنید |

---

## تست JSON

قبل از آپلود، JSON را تست کنید:
1. به https://jsonlint.com/ بروید
2. JSON را paste کنید
3. Validate کنید

---

## آپلود صحیح

1. فایل JSON را درست کنید
2. به WooCommerce برروید
3. Import/Export را باز کنید
4. فایل درست شده را آپلود کنید
5. گزارش را بخوانید
