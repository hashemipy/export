import { Product, Category, Attribute, ProcessedData } from '@/lib/types';

/**
 * پلاگین پردازش داده‌ها
 * این پلاگین مسئول تنظیف و ساختاردهی داده‌ها برای اپلود است
 */

export class DataProcessor {
  /**
   * تنظیف و ساختاردهی دسته‌بندی‌ها
   * ایجاد ارتباطات مادر-فرزند درست
   */
  processCategories(categories: Category[]): Category[] {
    console.log('[DataProcessor] پردازش دسته‌بندی‌ها...');

    // حذف تکراری‌ها
    const uniqueCategories = Array.from(
      new Map(categories.map((cat) => [cat.slug, cat])).values()
    );

    // مرتب‌سازی برای اطمینان که دسته‌های مادر قبل از فرزندان می‌آیند
    uniqueCategories.sort((a, b) => {
      if (a.parent_id === 0 && b.parent_id !== 0) return -1;
      if (a.parent_id !== 0 && b.parent_id === 0) return 1;
      return 0;
    });

    console.log(`[DataProcessor] ${uniqueCategories.length} دسته‌بندی منحصر به فرد`);
    return uniqueCategories;
  }

  /**
   * تنظیف و ساختاردهی ویژگی‌ها
   * حذف تکراری‌ها، ترتیب مقادیر
   */
  processAttributes(attributes: Attribute[]): Attribute[] {
    console.log('[DataProcessor] پردازش ویژگی‌ها...');

    return attributes.map((attr) => ({
      ...attr,
      values: Array.from(
        new Map(attr.values.map((v) => [v.slug, v])).values()
      ),
    }));
  }

  /**
   * تنظیم محصولات متغیر
   * اطمینان از وجود تمام متغیرها برای هر combination
   */
  processVariableProducts(products: Product[]): Product[] {
    console.log('[DataProcessor] پردازش محصولات متغیر...');

    return products.map((product) => {
      if (product.type !== 'variable') {
        return product;
      }

      // اگر محصول متغیر است اما متغیر ندارد، هشدار بده
      if (!product.variations || product.variations.length === 0) {
        console.warn(
          `[DataProcessor] هشدار: محصول "${product.name}" متغیری ندارد`
        );
      }

      // حذف تکراری‌های SKU
      const uniqueVariations = Array.from(
        new Map(
          (product.variations || []).map((v) => [v.sku, v])
        ).values()
      );

      return {
        ...product,
        variations: uniqueVariations,
        stock_quantity: uniqueVariations.reduce(
          (sum, v) => sum + (v.stock_quantity || 0),
          0
        ),
      };
    });
  }

  /**
   * بررسی یکپارچگی داده‌ها
   */
  validateData(
    products: Product[],
    categories: Category[],
    attributes: Attribute[]
  ): string[] {
    const errors: string[] = [];

    console.log('[DataProcessor] بررسی یکپارچگی داده‌ها...');

    // بررسی محصولات
    products.forEach((product, index) => {
      // SKU باید منحصر به فرد باشد
      const duplicates = products.filter((p) => p.sku === product.sku);
      if (duplicates.length > 1) {
        errors.push(
          `محصول #${index}: SKU "${product.sku}" تکراری است`
        );
      }

      // نام الزامی است
      if (!product.name) {
        errors.push(`محصول #${index}: نام مشخص نیست`);
      }

      // برای محصولات متغیر، ویژگی‌ها الزامی هستند
      if (product.type === 'variable') {
        if (!product.attributes || Object.keys(product.attributes).length === 0) {
          errors.push(
            `محصول "${product.name}": محصول متغیر ویژگی‌ای ندارد`
          );
        }

        // تمام متغیرها باید ویژگی‌های صحیح داشته باشند
        product.variations?.forEach((variation, varIndex) => {
          Object.entries(variation.attributes || {}).forEach(
            ([attrKey, attrValue]) => {
              if (!product.attributes?.[attrKey]) {
                errors.push(
                  `متغیر #${varIndex} از محصول "${product.name}": ویژگی "${attrKey}" در محصول تعریف نشده`
                );
              }
            }
          );
        });
      }
    });

    console.log(
      `[DataProcessor] بررسی انجام شد: ${errors.length} خطا پیدا شد`
    );

    return errors;
  }

  /**
   * پردازش کامل تمام داده‌ها
   */
  processAll(
    products: Product[],
    categories: Category[],
    attributes: Attribute[]
  ): { data: ProcessedData; errors: string[] } {
    console.log('[DataProcessor] شروع پردازش کامل...');

    const processedCategories = this.processCategories(categories);
    const processedAttributes = this.processAttributes(attributes);
    const processedProducts = this.processVariableProducts(products);

    const errors = this.validateData(
      processedProducts,
      processedCategories,
      processedAttributes
    );

    return {
      data: {
        categories: processedCategories,
        attributes: processedAttributes,
        products: processedProducts,
      },
      errors,
    };
  }
}

export default DataProcessor;
