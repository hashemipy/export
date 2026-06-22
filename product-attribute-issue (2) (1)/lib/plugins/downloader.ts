import { Product, DownloadedData, Category, Attribute } from '@/lib/types';

/**
 * پلاگین دانلود محصولات از سایت مبدا
 * این پلاگین مسئول دانلود تمام اطلاعات است:
 * - محصولات
 * - دسته‌بندی‌ها (مادر و فرزند)
 * - ویژگی‌ها (رنگ، سایز، و...)
 */

export class ProductDownloader {
  private sourceUrl: string;

  constructor(sourceUrl: string) {
    this.sourceUrl = sourceUrl;
  }

  /**
   * دانلود تمام محصولات متغیر از سایت مبدا
   */
  async downloadProducts(filters?: { category?: string; status?: string }): Promise<Product[]> {
    console.log('[ProductDownloader] شروع دانلود محصولات...');
    
    // این متد باید از API سایت مبدا استفاده کند
    // یا از فایل JSON دریافت کند
    // برای اینجا، ما نمونه‌ای نشان می‌دهیم
    
    try {
      const response = await fetch(`${this.sourceUrl}/api/products`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
        },
      });

      if (!response.ok) {
        throw new Error(`خطا در دانلود: ${response.statusText}`);
      }

      const products = await response.json();
      console.log(`[ProductDownloader] ${products.length} محصول دانلود شد`);
      
      return products;
    } catch (error) {
      console.error('[ProductDownloader] خطا:', error);
      throw error;
    }
  }

  /**
   * استخراج تمام دسته‌بندی‌های منحصر به فرد از محصولات
   */
  extractCategories(products: Product[]): Category[] {
    const categoriesMap = new Map<string, Category>();

    products.forEach((product) => {
      product.categories?.forEach((category) => {
        const key = `${category.name}-${category.parent_id}`;
        
        if (!categoriesMap.has(key)) {
          categoriesMap.set(key, {
            id: category.id,
            name: category.name,
            slug: category.slug,
            parent_id: category.parent_id,
            description: category.description || `دسته‌بندی: ${category.name}`,
          });
        }
      });
    });

    return Array.from(categoriesMap.values());
  }

  /**
   * استخراج تمام ویژگی‌های منحصر به فرد از محصولات متغیر
   */
  extractAttributes(products: Product[]): Attribute[] {
    const attributesMap = new Map<string, Attribute>();

    products.forEach((product) => {
      if (product.type === 'variable' && product.attributes) {
        Object.entries(product.attributes).forEach(([attrKey, attribute]) => {
          if (!attributesMap.has(attrKey)) {
            attributesMap.set(attrKey, {
              name: attribute.name,
              slug: attribute.slug,
              values: attribute.values || [],
              visible: attribute.visible !== false,
            });
          } else {
            // اگر ویژگی قبلاً وجود دارد، مقادیر جدید را اضافه کن
            const existing = attributesMap.get(attrKey)!;
            const newValues = attribute.values || [];
            
            newValues.forEach((newValue) => {
              const exists = existing.values.some((v) => v.slug === newValue.slug);
              if (!exists) {
                existing.values.push(newValue);
              }
            });
          }
        });
      }
    });

    return Array.from(attributesMap.values());
  }

  /**
   * تمام فرایند دانلود: محصولات + دسته‌بندی‌ها + ویژگی‌ها
   */
  async downloadComplete(): Promise<DownloadedData> {
    console.log('[ProductDownloader] شروع دانلود کامل...');

    // دانلود محصولات
    const products = await this.downloadProducts();

    // استخراج دسته‌بندی‌ها
    const categories = this.extractCategories(products);
    console.log(`[ProductDownloader] ${categories.length} دسته‌بندی استخراج شد`);

    // استخراج ویژگی‌ها
    const attributes = this.extractAttributes(products);
    console.log(`[ProductDownloader] ${attributes.length} ویژگی استخراج شد`);

    const downloadedData: DownloadedData = {
      products,
      categories,
      attributes,
      metadata: {
        exportDate: new Date().toISOString(),
        sourceUrl: this.sourceUrl,
        totalProducts: products.length,
        totalCategories: categories.length,
      },
    };

    return downloadedData;
  }

  /**
   * تحویل داده‌ها به صورت JSON
   */
  async exportToJSON(data: DownloadedData): Promise<string> {
    return JSON.stringify(data, null, 2);
  }

  /**
   * دانلود فایل JSON
   */
  async downloadFile(data: DownloadedData, filename: string = 'products-export.json'): Promise<Blob> {
    const jsonString = await this.exportToJSON(data);
    return new Blob([jsonString], { type: 'application/json' });
  }
}

export default ProductDownloader;
