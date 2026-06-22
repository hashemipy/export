import { Product, Category, Attribute, UploadResult } from '@/lib/types';

/**
 * پلاگین اپلود محصولات به سایت مقصد
 * این پلاگین اطمینان می‌دهد که داده‌ها به ترتیب صحیح اپلود شوند:
 * ۱. دسته‌بندی‌ها
 * ۲. ویژگی‌ها
 * ۳. محصولات متغیر
 */

export class ProductUploader {
  private destinationUrl: string;
  private apiKey?: string;

  constructor(destinationUrl: string, apiKey?: string) {
    this.destinationUrl = destinationUrl;
    this.apiKey = apiKey;
  }

  /**
   * اپلود دسته‌بندی‌ها
   */
  async uploadCategories(categories: Category[]): Promise<Category[]> {
    console.log('[ProductUploader] شروع اپلود دسته‌بندی‌ها...');

    const createdCategories: Category[] = [];
    const parentCategories = categories.filter((cat) => cat.parent_id === 0);
    const childCategories = categories.filter((cat) => cat.parent_id !== 0);

    try {
      // اول دسته‌های مادر اپلود شوند
      for (const category of parentCategories) {
        const created = await this.createCategory(category);
        createdCategories.push(created);
        console.log(
          `[ProductUploader] دسته‌بندی ایجاد شد: "${category.name}" (ID: ${created.id})`
        );
      }

      // سپس دسته‌های فرزند
      for (const category of childCategories) {
        // پیدا کردن ID دسته‌ی مادر
        const parentCat = createdCategories.find(
          (c) => c.name === category.name && c.parent_id === category.parent_id
        );

        if (parentCat?.id) {
          category.parent_id = parentCat.id;
        }

        const created = await this.createCategory(category);
        createdCategories.push(created);
        console.log(
          `[ProductUploader] دسته‌بندی فرزند ایجاد شد: "${category.name}"`
        );
      }

      return createdCategories;
    } catch (error) {
      console.error('[ProductUploader] خطا در اپلود دسته‌بندی‌ها:', error);
      throw error;
    }
  }

  /**
   * اپلود ویژگی‌ها
   */
  async uploadAttributes(attributes: Attribute[]): Promise<Attribute[]> {
    console.log('[ProductUploader] شروع اپلود ویژگی‌ها...');

    const createdAttributes: Attribute[] = [];

    try {
      for (const attribute of attributes) {
        const created = await this.createAttribute(attribute);
        createdAttributes.push(created);
        console.log(
          `[ProductUploader] ویژگی ایجاد شد: "${attribute.name}" (${attribute.values.length} مقدار)`
        );
      }

      return createdAttributes;
    } catch (error) {
      console.error('[ProductUploader] خطا در اپلود ویژگی‌ها:', error);
      throw error;
    }
  }

  /**
   * اپلود محصولات متغیر
   * این متد تمام محصولات و متغیرهای آن را ایجاد می‌کند
   */
  async uploadProducts(
    products: Product[],
    createdCategories: Category[]
  ): Promise<Product[]> {
    console.log('[ProductUploader] شروع اپلود محصولات...');

    const createdProducts: Product[] = [];

    try {
      for (const product of products) {
        // به‌روزرسانی شناسه دسته‌بندی
        product.categories = product.categories.map((cat) => {
          const found = createdCategories.find(
            (c) => c.slug === cat.slug
          );
          return found || cat;
        });

        // ایجاد محصول
        const created = await this.createProduct(product);
        createdProducts.push(created);

        console.log(
          `[ProductUploader] محصول ایجاد شد: "${product.name}" (ID: ${created.id})`
        );

        // برای محصولات متغیر، ایجاد متغیرها
        if (product.type === 'variable' && product.variations) {
          for (const variation of product.variations) {
            await this.createVariation(created.id!, variation);
          }
          console.log(
            `[ProductUploader] ${product.variations.length} متغیر برای "${product.name}" ایجاد شد`
          );
        }
      }

      return createdProducts;
    } catch (error) {
      console.error('[ProductUploader] خطا در اپلود محصولات:', error);
      throw error;
    }
  }

  /**
   * فرایند کامل اپلود
   */
  async uploadComplete(
    categories: Category[],
    attributes: Attribute[],
    products: Product[]
  ): Promise<UploadResult> {
    console.log('[ProductUploader] شروع فرایند کامل اپلود...');

    const result: UploadResult = {
      success: false,
      createdCategories: 0,
      createdAttributes: 0,
      createdProducts: 0,
      errors: [],
    };

    try {
      // ۱. اپلود دسته‌بندی‌ها
      const createdCats = await this.uploadCategories(categories);
      result.createdCategories = createdCats.length;
      console.log(`[ProductUploader] ✓ ${createdCats.length} دسته‌بندی اپلود شد`);

      // ۲. اپلود ویژگی‌ها
      const createdAttrs = await this.uploadAttributes(attributes);
      result.createdAttributes = createdAttrs.length;
      console.log(`[ProductUploader] ✓ ${createdAttrs.length} ویژگی اپلود شد`);

      // ۳. اپلود محصولات و متغیرها
      const createdProds = await this.uploadProducts(products, createdCats);
      result.createdProducts = createdProds.length;
      console.log(`[ProductUploader] ✓ ${createdProds.length} محصول اپلود شد`);

      result.success = true;
      console.log('[ProductUploader] اپلود با موفقیت انجام شد!');

      return result;
    } catch (error) {
      result.errors.push(
        error instanceof Error ? error.message : 'خطای نامعلوم'
      );
      console.error('[ProductUploader] خطا در اپلود:', error);
      return result;
    }
  }

  // متدهای کمکی (توسط API سایت مقصد پیاده‌سازی می‌شوند)
  private async createCategory(category: Category): Promise<Category> {
    // این متد باید به API سایت مقصد متصل شود
    const response = await fetch(
      `${this.destinationUrl}/api/categories`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(this.apiKey && { Authorization: `Bearer ${this.apiKey}` }),
        },
        body: JSON.stringify(category),
      }
    );

    if (!response.ok) {
      throw new Error(`خطا در ایجاد دسته‌بندی: ${response.statusText}`);
    }

    return response.json();
  }

  private async createAttribute(attribute: Attribute): Promise<Attribute> {
    const response = await fetch(
      `${this.destinationUrl}/api/attributes`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(this.apiKey && { Authorization: `Bearer ${this.apiKey}` }),
        },
        body: JSON.stringify(attribute),
      }
    );

    if (!response.ok) {
      throw new Error(`خطا در ایجاد ویژگی: ${response.statusText}`);
    }

    return response.json();
  }

  private async createProduct(product: Product): Promise<Product> {
    const response = await fetch(
      `${this.destinationUrl}/api/products`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(this.apiKey && { Authorization: `Bearer ${this.apiKey}` }),
        },
        body: JSON.stringify(product),
      }
    );

    if (!response.ok) {
      throw new Error(`خطا در ایجاد محصول: ${response.statusText}`);
    }

    return response.json();
  }

  private async createVariation(productId: number, variation: any): Promise<any> {
    const response = await fetch(
      `${this.destinationUrl}/api/products/${productId}/variations`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(this.apiKey && { Authorization: `Bearer ${this.apiKey}` }),
        },
        body: JSON.stringify(variation),
      }
    );

    if (!response.ok) {
      throw new Error(`خطا در ایجاد متغیر: ${response.statusText}`);
    }

    return response.json();
  }
}

export default ProductUploader;
