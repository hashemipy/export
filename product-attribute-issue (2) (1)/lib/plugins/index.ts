import ProductDownloader from './downloader';
import DataProcessor from './processor';
import ProductUploader from './uploader';
import { DownloadedData, ProcessedData, UploadResult } from '@/lib/types';

/**
 * مدیر پلاگین‌های انتقال محصول
 * این کلاس تمام فرایند انتقال محصولات را مدیریت می‌کند
 */

export class ProductMigrationManager {
  private downloader: ProductDownloader;
  private processor: DataProcessor;
  private uploader: ProductUploader;

  constructor(sourceUrl: string, destinationUrl: string, apiKey?: string) {
    this.downloader = new ProductDownloader(sourceUrl);
    this.processor = new DataProcessor();
    this.uploader = new ProductUploader(destinationUrl, apiKey);
  }

  /**
   * فرایند کامل انتقال:
   * ۱. دانلود محصولات + دسته‌بندی‌ها + ویژگی‌ها
   * ۲. پردازش و بررسی یکپارچگی
   * ۳. اپلود به سایت مقصد
   */
  async migrateComplete(): Promise<{
    downloadedData: DownloadedData;
    processedData: ProcessedData;
    uploadResult: UploadResult;
  }> {
    console.log('[ProductMigrationManager] شروع فرایند انتقال محصولات...\n');

    try {
      // مرحله ۱: دانلود
      console.log('📥 مرحله ۱: دانلود محصولات...\n');
      const downloadedData = await this.downloader.downloadComplete();
      console.log(
        `✓ ${downloadedData.products.length} محصول دانلود شد\n` +
        `✓ ${downloadedData.categories.length} دسته‌بندی استخراج شد\n` +
        `✓ ${downloadedData.attributes.length} ویژگی استخراج شد\n`
      );

      // مرحله ۲: پردازش
      console.log('\n🔧 مرحله ۲: پردازش و بررسی داده‌ها...\n');
      const { data: processedData, errors } = this.processor.processAll(
        downloadedData.products,
        downloadedData.categories,
        downloadedData.attributes
      );

      if (errors.length > 0) {
        console.warn('⚠️  هشدارها:');
        errors.forEach((error) => console.warn(`  - ${error}`));
      } else {
        console.log('✓ تمام داده‌ها معتبر هستند');
      }

      // مرحله ۳: اپلود
      console.log('\n📤 مرحله ۳: اپلود به سایت مقصد...\n');
      const uploadResult = await this.uploader.uploadComplete(
        processedData.categories,
        processedData.attributes,
        processedData.products
      );

      console.log(
        '\n✓ انتقال محصولات با موفقیت انجام شد!' +
        `\n  - ${uploadResult.createdCategories} دسته‌بندی ایجاد شد` +
        `\n  - ${uploadResult.createdAttributes} ویژگی ایجاد شد` +
        `\n  - ${uploadResult.createdProducts} محصول ایجاد شد`
      );

      return {
        downloadedData,
        processedData,
        uploadResult,
      };
    } catch (error) {
      console.error('[ProductMigrationManager] خطا در انتقال:', error);
      throw error;
    }
  }

  /**
   * فقط دانلود
   */
  async downloadOnly(): Promise<DownloadedData> {
    return this.downloader.downloadComplete();
  }

  /**
   * فقط اپلود (فایل JSON موجود)
   */
  async uploadOnly(downloadedData: DownloadedData): Promise<UploadResult> {
    const { data: processedData, errors } = this.processor.processAll(
      downloadedData.products,
      downloadedData.categories,
      downloadedData.attributes
    );

    if (errors.length > 0) {
      console.warn('⚠️  خطاهای بررسی:');
      errors.forEach((error) => console.warn(`  - ${error}`));
    }

    return this.uploader.uploadComplete(
      processedData.categories,
      processedData.attributes,
      processedData.products
    );
  }

  /**
   * دانلود و دریافت فایل JSON
   */
  async downloadAndExport(): Promise<Blob> {
    const data = await this.downloader.downloadComplete();
    return this.downloader.downloadFile(data);
  }
}

export { ProductDownloader, DataProcessor, ProductUploader };
export * from '@/lib/types';
