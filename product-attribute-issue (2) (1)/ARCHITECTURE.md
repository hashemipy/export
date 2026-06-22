# معماری سیستم

## درخت پروژه

```
product-attribute-issue/
├── app/
│   ├── layout.tsx           # Layout اصلی
│   ├── page.tsx             # صفحه اصلی (3 تب)
│   └── globals.css          # استایل‌های جهانی
│
├── components/
│   ├── ui/
│   │   └── button.tsx       # دکمه shadcn
│   ├── MigrationManager.tsx # کامپوننت انتقال کامل
│   └── FileUpload.tsx       # کامپوننت اپلود فایل
│
├── lib/
│   ├── types.ts             # انواع TypeScript
│   ├── utils.ts             # توابع کمکی
│   └── plugins/
│       ├── downloader.ts    # دانلود‌کننده محصولات
│       ├── processor.ts     # پردازش‌کننده داده‌ها
│       ├── uploader.ts      # اپلود‌کننده محصولات
│       └── index.ts         # مدیر و صادرات
│
├── public/                  # فایل‌های استاتیک
│
├── مستندات/
│   ├── PLUGIN_DOCUMENTATION.md    # توضیح تکنیکی
│   ├── README_FA.md               # راهنمای کاربر
│   ├── QUICK_START.md             # شروع سریع
│   ├── ARCHITECTURE.md            # این فایل
│   ├── CORRECT_JSON_FORMAT.json   # ساختار JSON صحیح
│   ├── EXAMPLE_COMPLETE_EXPORT.json # نمونه کامل
│   └── package.json               # وابستگی‌ها
│
└── کنفیگ/
    ├── tsconfig.json        # TypeScript کنفیگ
    ├── tailwind.config.ts   # Tailwind کنفیگ
    ├── next.config.ts       # Next.js کنفیگ
    └── components.json      # shadcn کنفیگ
```

---

## معماری لایه‌ها

```
┌─────────────────────────────────────┐
│     🎨 رابط کاربری (UI)              │
│  ┌──────────────┬──────────────────┐│
│  │   صفحه اصلی  │  تب‌های مختلف    ││
│  │  (page.tsx)  │ (Download/Upload)││
│  └──────────────┴──────────────────┘│
└─────────────────────────────────────┘
              ↓ استفاده از
┌─────────────────────────────────────┐
│     💻 اجزاء React (Components)      │
│  ┌──────────────┬──────────────────┐│
│  │ Migration    │  File            ││
│  │ Manager      │  Upload          ││
│  └──────────────┴──────────────────┘│
└─────────────────────────────────────┘
              ↓ استفاده از
┌─────────────────────────────────────┐
│     🔌 پلاگین‌ها (Plugins)           │
│  ┌──────────────────────────────────┐│
│  │      ProductMigrationManager     ││
│  │  ┌──────────┬──────┬──────────┐  ││
│  │  │Download  │ Proc │  Upload  │  ││
│  │  └──────────┴──────┴──────────┘  ││
│  └──────────────────────────────────┘│
└─────────────────────────────────────┘
              ↓ استفاده از
┌─────────────────────────────────────┐
│     📦 انواع و اینترفیس (Types)       │
│  ┌──────────────────────────────────┐│
│  │  Product, Category, Attribute    ││
│  │  Variation, DownloadedData, ...  ││
│  └──────────────────────────────────┘│
└─────────────────────────────────────┘
```

---

## جریان داده‌ها

### شاخه انتقال کامل

```
UI (MigrationManager.tsx)
        ↓ form submit
ProductMigrationManager
        ↓
downloader.downloadComplete()
        ├─ downloadProducts()
        ├─ extractCategories()
        ├─ extractAttributes()
        └─ returns DownloadedData
        ↓
processor.processAll()
        ├─ processCategories()
        ├─ processAttributes()
        ├─ processVariableProducts()
        ├─ validateData()
        └─ returns ProcessedData + errors
        ↓
uploader.uploadComplete()
        ├─ uploadCategories()
        ├─ uploadAttributes()
        ├─ uploadProducts()
        └─ returns UploadResult
        ↓
UI (result display)
```

### شاخه دانلود و ذخیره

```
UI (page.tsx - tab: download)
        ↓ user click
downloader.downloadComplete()
        ↓
downloader.downloadFile()
        ↓
Browser download (JSON file)
```

### شاخه اپلود فایل

```
UI (FileUpload.tsx)
        ↓ file selected
File.text() → parse JSON
        ↓
ProductMigrationManager.uploadOnly()
        ↓
processor.processAll()
        ↓
uploader.uploadComplete()
        ↓
UI (result display)
```

---

## انتقال داده‌ها بین لایه‌ها

### ۱. دانلود (Downloader)

**Input:**
```typescript
sourceUrl: string
```

**Process:**
- GET request به /api/products
- استخراج دسته‌بندی‌ها
- استخراج ویژگی‌ها
- ترکیب داده‌ها

**Output:**
```typescript
DownloadedData {
  products: Product[]
  categories: Category[]
  attributes: Attribute[]
  metadata: {...}
}
```

### ۲. پردازش (Processor)

**Input:**
```typescript
products: Product[]
categories: Category[]
attributes: Attribute[]
```

**Process:**
- حذف تکراری‌ها
- ترتیب دسته‌های مادر → فرزند
- بررسی یکپارچگی
- محاسبه موجودی

**Output:**
```typescript
{
  data: ProcessedData {
    products: Product[]
    categories: Category[]
    attributes: Attribute[]
  }
  errors: string[]
}
```

### ۳. اپلود (Uploader)

**Input:**
```typescript
categories: Category[]
attributes: Attribute[]
products: Product[]
```

**Process:**
- ایجاد دسته‌بندی‌ها (مادر ابتدا)
- ایجاد ویژگی‌ها
- ایجاد محصولات (type: variable)
- ایجاد متغیرها

**Output:**
```typescript
UploadResult {
  success: boolean
  createdCategories: number
  createdAttributes: number
  createdProducts: number
  errors: string[]
}
```

---

## انتقال API

### سایت مبدا (Downloader)

```typescript
// GET /api/products
// پاسخ: Product[]

// GET /api/categories
// پاسخ: Category[]

// GET /api/attributes
// پاسخ: Attribute[]
```

### سایت مقصد (Uploader)

```typescript
// POST /api/categories
// بدنه: Category
// پاسخ: Category (با ID)

// POST /api/attributes
// بدنه: Attribute
// پاسخ: Attribute (با ID)

// POST /api/products
// بدنه: Product
// پاسخ: Product (با ID)

// POST /api/products/:id/variations
// بدنه: Variation
// پاسخ: Variation
```

---

## توالی اپلود صحیح

```
[Step 1] دسته‌بندی مادر
    ├─ name: "لباس"
    ├─ parent_id: 0
    └─ response: { id: 1 }

            ↓

[Step 2] دسته‌بندی فرزند
    ├─ name: "تیشرت"
    ├─ parent_id: 1  ← از پاسخ قبلی
    └─ response: { id: 2 }

            ↓

[Step 3] ویژگی "رنگ"
    ├─ name: "رنگ"
    ├─ values: [...]
    └─ response: { id: 10 }

            ↓

[Step 4] ویژگی "سایز"
    ├─ name: "سایز"
    ├─ values: [...]
    └─ response: { id: 11 }

            ↓

[Step 5] محصول
    ├─ name: "تیشرت قرمز"
    ├─ type: "variable"
    ├─ category_ids: [2]  ← از Step 2
    └─ response: { id: 100 }

            ↓

[Step 6] متغیرها
    ├─ Variation 1: sku: "1-red-40", stock: 5
    ├─ Variation 2: sku: "1-red-45", stock: 3
    └─ product_id: 100  ← از Step 5
```

---

## تعاملات TypeScript

### Product ←→ Attribute ←→ Variation

```typescript
// محصول دارای ویژگی‌ها است
Product {
  attributes: {
    "color": Attribute,      // فقط تعریف
    "size": Attribute        // فقط تعریف
  }
}

// هر متغیر استفاده می‌کند
Variation {
  attributes: {
    "color": "red",          // مقدار واقعی
    "size": "40"             // مقدار واقعی
  }
}

// Attribute دارای مقادیر ممکن است
Attribute {
  values: [
    { name: "قرمز", slug: "red" },
    { name: "آبی", slug: "blue" }
  ]
}
```

---

## نمودار وابستگی‌ها

```
              ┌─────────────┐
              │  page.tsx   │
              └──────┬──────┘
                     │ renders
        ┌────────────┼────────────┐
        │            │            │
    ┌───┴──┐    ┌────┴────┐ ┌────┴─────┐
    │ Tab1 │    │  Tab2   │ │   Tab3   │
    └───┬──┘    └────┬────┘ └────┬─────┘
        │ renders    │ renders   │ renders
    ┌───┴────────────┼───────────┴────┐
    │                │                │
┌───┴────────┐  ┌───┴──────┐  ┌──────┴────┐
│ Download   │  │FileUpload│  │ Migration │
│Component   │  │Component │  │ Manager   │
└───┬────────┘  └───┬──────┘  └──────┬────┘
    │ uses          │ uses         │ uses
    │               │             │
┌───┴─────┬─────┬───┴────────────┤
│ plugins │     │                │
└─────────┴────┬┴────────┬───────┴─────┐
          ├────┴────┐    │              │
      ┌───┴────┐┌──┴┐┌──┴──┐┌─────┐┌──┴──┐
      │Download││Pro│Upload││types││utils│
      │        │└──┘│      │└─────┘└─────┘
      └────────┘    └───────┘
```

---

## الگوهای تصمیم‌گیری

### ۱. چرا Processor الزامی است؟

```
❌ بدون Processor:
   Upload ← Raw Data
   └─ خطرات: تکراری‌ها، خطاهای بررسی نشده، ترتیب اشتباه

✅ با Processor:
   Upload ← Cleaned Data (تحقق شده)
   └─ نتایج: داده‌های صحیح، بدون خطا، ترتیب درست
```

### ۲. چرا Downloader جداگانه است؟

```
✅ جداگانه بودن:
   ├─ می‌توان فقط دانلود کرد (بک‌آپ)
   ├─ فایل JSON می‌تواند ذخیره شود
   ├─ می‌توان به دستی تعدیل کرد
   └─ می‌تواند دوباره اپلود شود

❌ اگر یکپارچه بود:
   └─ بدون انعطاف‌پذیری
```

### ۳. چرا Uploader ترتیبی است؟

```
❌ اگر بدون ترتیب:
   ├─ متغیر بدون محصول ❌
   ├─ محصول بدون ویژگی ❌
   └─ دسته‌ی فرزند بدون مادر ❌

✅ با ترتیب صحیح:
   1. Catgories (مادر ابتدا)
   2. Attributes (ویژگی‌ها)
   3. Product (محصول)
   4. Variations (متغیرها)
   └─ همه چیز کامل است ✓
```

---

## بهینه‌سازی‌ها

### ۱. Caching
```typescript
// Attributes استخراج شده را کش کنید
// تا در محصولات بعدی دوباره دانلود نشود
```

### ۲. Batch Upload
```typescript
// اگر صدها محصول وجود داشت
// می‌توان گروه‌های کوچک اپلود کنید
```

### ۳. Progress Tracking
```typescript
// هر متغیر اپلود شده می‌تواند
// درصد پیشرفت به‌روز کند
```

### ۴. Error Recovery
```typescript
// اگر اپلود ناموفق شد
// می‌توان از جایی که مانده شد شروع کرد
```

---

## نکات مهم معماری

✅ **Separation of Concerns**
- Downloader: فقط دانلود
- Processor: فقط پردازش
- Uploader: فقط اپلود

✅ **Type Safety**
- تمام اقسام در `types.ts` تعریف شدند
- TypeScript می‌توانست خطاها را پیدا کند

✅ **Error Handling**
- هر layer بررسی می‌کند
- خطاها به بالا منتقل می‌شوند

✅ **Extensibility**
- آسان است نوع ویژگی جدید اضافه کرد
- آسان است سایت جدید پیوند کرد

---

## کاربردهای آینده

### ۱. Real-time Sync
```typescript
// به جای دانلود/اپلود یکباره
// حقیقی‌وقتی محصولات را sync کنید
```

### ۲. Partial Migration
```typescript
// فقط محصولات خاصی را انتقال دهید
// نه همه
```

### ۳. Version Control
```typescript
// هر انتقال را ثبت کنید
// تاریخچه تغییرات
```

### ۴. Multi-Site
```typescript
// از یک سایت مبدا
// به چند سایت مقصد
```

---

این معماری طوری طراحی شده است که:
- **ساده** - فهم آن آسان است
- **مدولار** - بخش‌ها مستقل هستند
- **انعطاف‌پذیر** - می‌توان تغییر داد
- **قابل‌گسترش** - می‌توان اضافه کرد
