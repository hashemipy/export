# ساختار JSON صحیح برای محصولات متغیّر

## مشکل اصلی کدی که دانلود کردید:

کد شما محصول **بدون نوع** را ایجاد می‌کرد و **ویژگی‌ها** sync نمی‌شدند.

---

## ۱. ساختار JSON برای محصول ساده:

```json
[
  {
    "id": 123,
    "name": "تی‌شرت آبی",
    "type": "simple",
    "sku": "TS-BLUE-001",
    "regular_price": "150000",
    "sale_price": "120000",
    "description": "توضیحات کامل",
    "short_description": "خلاصه",
    "categories": [
      {
        "id": 1,
        "name": "لباس",
        "parent": null
      }
    ],
    "images": [
      {
        "id": 1,
        "src": "https://example.com/image1.jpg"
      }
    ],
    "attributes": [],
    "variations": []
  }
]
```

---

## ۲. ساختار JSON برای محصول متغیّر (⭐ اهم):

```json
[
  {
    "id": 456,
    "name": "تی‌شرت رنگی",
    "type": "variable",
    "sku": "TS-COLOR-001",
    "regular_price": "150000",
    "description": "توضیحات",
    "categories": [
      {
        "id": 1,
        "name": "لباس"
      }
    ],
    "images": [
      {
        "id": 1,
        "src": "https://example.com/main.jpg"
      }
    ],
    "attributes": [
      {
        "id": 1,
        "name": "رنگ",
        "options": ["قرمز", "آبی", "سبز"]
      },
      {
        "id": 2,
        "name": "سایز",
        "options": ["Small", "Medium", "Large"]
      }
    ],
    "variations": [
      {
        "id": 1001,
        "sku": "TS-RED-S",
        "regular_price": "150000",
        "stock_quantity": 10,
        "attributes": [
          {
            "id": 1,
            "name": "رنگ",
            "option": "قرمز"
          },
          {
            "id": 2,
            "name": "سایز",
            "option": "Small"
          }
        ],
        "image": {
          "id": 2,
          "src": "https://example.com/red-small.jpg"
        }
      },
      {
        "id": 1002,
        "sku": "TS-BLUE-M",
        "regular_price": "150000",
        "sale_price": "120000",
        "stock_quantity": 5,
        "attributes": [
          {
            "id": 1,
            "name": "رنگ",
            "option": "آبی"
          },
          {
            "id": 2,
            "name": "سایز",
            "option": "Medium"
          }
        ],
        "image": {
          "id": 3,
          "src": "https://example.com/blue-medium.jpg"
        }
      }
    ]
  }
]
```

---

## ۳. الفرق اساسی:

| بخش | محصول ساده | محصول متغیّر |
|------|----------|-----------|
| `type` | `"simple"` | `"variable"` |
| `attributes` | خالی `[]` | شامل ویژگی‌ها |
| `variations` | خالی `[]` | شامل متغیّرها |
| قیمت | در محصول | در هر متغیّر |
| تصویر | 1 تصویر | تصویر برای هر متغیّر |

---

## ۴. ترتیب اهم مراحل:

```
۱️⃣ ایجاد دسته‌بندی‌ها
    ↓
۲️⃣ sync کردن ویژگی‌ها (attributes)
    ↓
۳️⃣ ایجاد محصول اصلی با type = "variable"
    ↓
۴️⃣ تنظیم ویژگی‌های محصول
    ↓
۵️⃣ ایجاد متغیّرها (variations)
```

**⚠️ اگر این ترتیب رعایت نشود، متغیّرها ایجاد نمی‌شوند!**

---

## ۵. کیفیت بررسی:

```php
// برای محصول متغیّر:
- type === "variable" ✅
- attributes.length > 0 ✅
- variations.length > 0 ✅
- هر variation دارای attributes ✅
- هر attribute دارای option ✅
```

---

## ۶. مثال از دانلود صحیح:

فایل که دانلود کردید `products-1782132317842.json` باید این ساختار داشته باشد:

```json
[
  {
    "id": 12345,
    "name": "محصول متغیّر",
    "type": "variable",
    ...
    "attributes": [
      {
        "id": 1,
        "name": "رنگ",
        "options": ["قرمز", "آبی"]
      }
    ],
    "variations": [
      {
        "sku": "var-color-1",
        "attributes": [
          {
            "id": 1,
            "name": "رنگ",
            "option": "قرمز"
          }
        ]
      }
    ]
  }
]
```

**اگر `variations` خالی است یا `attributes` وجود ندارد، محصول ساده ایجاد می‌شود!**
