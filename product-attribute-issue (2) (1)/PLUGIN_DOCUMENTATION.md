# سیستم انتقال محصولات متغیر

## مرور کلی

این سیستم یک پلاگین کامل برای انتقال محصولات متغیر (Variable Products) از سایت مبدا به سایت مقصد ارائه می‌دهد.

### فرایند اصلی

```
سایت مبدا → دانلود → پردازش → اپلود → سایت مقصد
│           │          │        │         │
محصولات    استخراج    تنظیف    ترتیب    ایجاد
دسته‌بندی‌ها  تمام      و        درست    محصول
ویژگی‌ها    وابستگی‌ها بررسی  (۱،۲،۳) متغیر
```

---

## معماری پلاگین‌ها

### ۱. **ProductDownloader** - دانلود
**مسئول:** دانلود تمام داده‌های مورد نیاز از سایت مبدا

```typescript
// استفاده
const downloader = new ProductDownloader('https://source.com');
const data = await downloader.downloadComplete();
```

**خروجی:**
```json
{
  "products": [...],
  "categories": [...],
  "attributes": [...],
  "metadata": { ... }
}
```

---

### ۲. **DataProcessor** - پردازش
**مسئول:** تنظیف، ساختاردهی و بررسی یکپارچگی داده‌ها

**انجام موارد:**
- ✓ حذف تکراری‌های دسته‌بندی و ویژگی
- ✓ ترتیب دسته‌های مادر → فرزند
- ✓ تصحیح SKU تکراری در متغیرها
- ✓ محاسبه موجودی کل محصول
- ✓ بررسی یکپارچگی داده‌ها

```typescript
// استفاده
const processor = new DataProcessor();
const { data, errors } = processor.processAll(
  products,
  categories,
  attributes
);
```

---

### ۳. **ProductUploader** - اپلود
**مسئول:** اپلود داده‌ها به سایت مقصد به ترتیب صحیح

**ترتیب اپلود:**
```
۱. دسته‌بندی‌های مادر
   ↓
۲. دسته‌بندی‌های فرزند
   ↓
۳. ویژگی‌ها و مقادیرشان
   ↓
۴. محصول ساده (بدون متغیر)
   ↓
۵. متغیرها (برای هر combination)
```

```typescript
// استفاده
const uploader = new ProductUploader(
  'https://destination.com',
  'api-key-optional'
);

const result = await uploader.uploadComplete(
  categories,
  attributes,
  products
);
```

---

### ۴. **ProductMigrationManager** - مدیریت کل
**مسئول:** مدیریت تمام مراحل انتقال

```typescript
// استفاده
const manager = new ProductMigrationManager(
  'https://source.com',
  'https://destination.com',
  'api-key'
);

// انتقال کامل
const result = await manager.migrateComplete();

// یا فقط دانلود
const downloadedData = await manager.downloadOnly();

// یا فقط اپلود
const uploadResult = await manager.uploadOnly(downloadedData);
```

---

## ساختار داده

### Product (محصول)
```typescript
{
  id?: number;
  name: string;              // نام محصول
  sku: string;               // کد محصول منحصر به فرد
  description: string;       // توضیحات کامل
  short_description: string; // توضیحات مختصر
  price: string | number;    // قیمت محصول
  stock_quantity: number;    // کل موجودی
  type: 'simple' | 'variable'; // نوع محصول
  categories: Category[];     // دسته‌بندی‌های محصول
  image_urls: string[];      // تصاویر محصول
  attributes?: Record<string, Attribute>; // ویژگی‌های محصول
  variations?: Variation[]; // متغیرهای محصول
}
```

### Attribute (ویژگی)
```typescript
{
  name: string;    // نام ویژگی (مثل: "رنگ")
  slug: string;    // شناسه ویژگی (مثل: "color")
  values: [       // مقادیر ممکن
    {
      name: string;   // نام مقدار (مثل: "قرمز")
      slug: string;   // شناسه مقدار (مثل: "red")
    }
  ];
  visible: boolean; // آیا روی محصول نمایش داده شود
}
```

### Variation (متغیر)
```typescript
{
  sku: string;              // کد منحصر (مثل: "1521-red-40")
  price: string | number;   // قیمت این متغیر
  stock_quantity: number;   // موجودی این متغیر
  attributes: {             // مقادیر ویژگی‌ها
    color: "red",
    size: "40"
  };
  image_url?: string;       // تصویر مخصوص این متغیر
}
```

### Category (دسته‌بندی)
```typescript
{
  id?: number;
  name: string;      // نام دسته‌بندی
  slug: string;      // شناسه دسته‌بندی
  parent_id: number; // شناسه دسته‌ی مادر (0 برای دسته‌های اصلی)
  description?: string;
  image?: string;
}
```

---

## نمونه‌های استفاده

### مثال ۱: انتقال کامل
```typescript
async function migrateAllProducts() {
  const manager = new ProductMigrationManager(
    'https://source.example.com',
    'https://dest.example.com',
    process.env.API_KEY
  );

  try {
    const { downloadedData, processedData, uploadResult } = 
      await manager.migrateComplete();

    console.log('✓ انتقال موفق!');
    console.log(`دسته‌بندی‌ها: ${uploadResult.createdCategories}`);
    console.log(`ویژگی‌ها: ${uploadResult.createdAttributes}`);
    console.log(`محصولات: ${uploadResult.createdProducts}`);
  } catch (error) {
    console.error('✗ خطا:', error);
  }
}
```

### مثال ۲: دانلود و ذخیره فایل
```typescript
async function downloadAndSave() {
  const downloader = new ProductDownloader('https://source.com');
  
  const data = await downloader.downloadComplete();
  const jsonString = await downloader.exportToJSON(data);
  
  // ذخیره در فایل
  fs.writeFileSync('products-export.json', jsonString);
  console.log('✓ فایل ذخیره شد');
}
```

### مثال ۳: اپلود فایل موجود
```typescript
async function uploadFromFile(filePath: string) {
  const manager = new ProductMigrationManager(
    'https://source.com',
    'https://dest.com'
  );

  const fileContent = fs.readFileSync(filePath, 'utf-8');
  const data = JSON.parse(fileContent);

  const result = await manager.uploadOnly(data);
  console.log('✓ اپلود انجام شد:', result);
}
```

---

## ویژگی‌های کلیدی

### ✓ دانلود ذکی
- خودکار تمام وابستگی‌ها (دسته‌بندی، ویژگی) را استخراج می‌کند
- تکراری‌ها را حذف می‌کند
- ساختار مادر-فرزند را حفظ می‌کند

### ✓ پردازش توانمند
- بررسی خودکار یکپارچگی داده‌ها
- تنظیف SKU تکراری
- محاسبه موجودی کل
- لاگ دقیق برای دیباگ

### ✓ اپلود ترتیبی
- ۱. دسته‌بندی‌ها (مادر ابتدا)
- ۲. ویژگی‌ها
- ۳. محصولات
- ۴. متغیرها (با موجودی صحیح)

### ✓ مدیریت خطا
- بررسی مفصل قبل از اپلود
- لاگ‌های تفصیلی در کنسول
- بازگشت نتایج دقیق

---

## نکات مهم

### قبل از انتقال
- ✓ تأیید صحیح بودن داده‌های سایت مبدا
- ✓ دانلود فایل JSON برای بک‌آپ
- ✓ بررسی موجودی محصولات

### بعد از انتقال
- ✓ بررسی محصولات در سایت مقصد
- ✓ بررسی متغیرها و موجودی
- ✓ بررسی ویژگی‌ها و دسته‌بندی‌ها
- ✓ اگر مشکل بود، فایل JSON دوبار اپلود کنید

---

## ترابل‌شوتینگ

### مشکل: محصول بدون متغیر ایجاد شود
**حل:** بررسی کنید که:
- ✓ محصول type: "variable" باشد
- ✓ attributes تعریف شده باشد
- ✓ variations وجود داشته باشند

### مشکل: SKU تکراری خطا بدهد
**حل:** پلاگین خودکار SKU تکراری را تنظیم می‌کند

### مشکل: دسته‌بندی فرزند نشود
**حل:** parent_id را بررسی کنید - باید شناسه دسته‌ی مادر باشد

### مشکل: ویژگی‌های متغیر تطابق نداشته باشد
**حل:** مقادیر ویژگی درخت متغیر باید در محصول تعریف شده باشند

---

## API Reference

### ProductDownloader

```typescript
// دانلود محصولات
downloadProducts(filters?): Promise<Product[]>

// استخراج دسته‌بندی‌ها
extractCategories(products: Product[]): Category[]

// استخراج ویژگی‌ها
extractAttributes(products: Product[]): Attribute[]

// دانلود کامل
downloadComplete(): Promise<DownloadedData>

// تبدیل به JSON
exportToJSON(data: DownloadedData): Promise<string>

// دانلود فایل
downloadFile(data: DownloadedData, filename?: string): Promise<Blob>
```

### DataProcessor

```typescript
// پردازش دسته‌بندی‌ها
processCategories(categories: Category[]): Category[]

// پردازش ویژگی‌ها
processAttributes(attributes: Attribute[]): Attribute[]

// پردازش محصولات متغیر
processVariableProducts(products: Product[]): Product[]

// بررسی یکپارچگی
validateData(products, categories, attributes): string[]

// پردازش کامل
processAll(products, categories, attributes): { data, errors }
```

### ProductUploader

```typescript
// اپلود دسته‌بندی‌ها
uploadCategories(categories: Category[]): Promise<Category[]>

// اپلود ویژگی‌ها
uploadAttributes(attributes: Attribute[]): Promise<Attribute[]>

// اپلود محصولات
uploadProducts(products, categories): Promise<Product[]>

// اپلود کامل
uploadComplete(categories, attributes, products): Promise<UploadResult>
```

---

## پرسش‌های متداول

**Q: آیا می‌توام تا انتقال یک بار دیگر کنم؟**
A: بله، فایل JSON می‌تواند دوباره اپلود شود

**Q: اگر سایت مبدا آفلاین شود؟**
A: فایل JSON دانلود شده را می‌توان بعداً اپلود کرد

**Q: آیا متغیرهای نو به صورت خودکار اضافه شوند؟**
A: بله، با شناسه‌سازی و شماره‌گذاری خودکار

**Q: موجودی محصول چگونه محاسبه می‌شود؟**
A: مجموع موجودی تمام متغیرها

---

## مسئول‌پذیری و دعم

برای گزارش مشکل یا درخواست ویژگی جدید، لطفاً مستندات کامل و دقیق ارائه دهید.
