'use client';

import { useState, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Upload, AlertCircle, CheckCircle, Loader, File } from 'lucide-react';

export default function FileUpload() {
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [destinationUrl, setDestinationUrl] = useState('');
  const [apiKey, setApiKey] = useState('');
  const [isUploading, setIsUploading] = useState(false);
  const [uploadResult, setUploadResult] = useState<any>(null);
  const [errors, setErrors] = useState<string[]>([]);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    if (!file.name.endsWith('.json')) {
      setErrors(['فقط فایل‌های JSON پذیرفته می‌شوند']);
      return;
    }

    setSelectedFile(file);
    setErrors([]);
  };

  const handleUpload = async () => {
    if (!selectedFile) {
      setErrors(['لطفاً فایل JSON را انتخاب کنید']);
      return;
    }

    if (!destinationUrl) {
      setErrors(['آدرس سایت مقصد الزامی است']);
      return;
    }

    setIsUploading(true);
    setErrors([]);

    try {
      // خواندن فایل
      const fileContent = await selectedFile.text();
      const jsonData = JSON.parse(fileContent);

      console.log('[FileUpload] فایل جزئی‌گاه شد');
      console.log('محصولات:', jsonData.products?.length || 0);
      console.log('دسته‌بندی‌ها:', jsonData.categories?.length || 0);
      console.log('ویژگی‌ها:', jsonData.attributes?.length || 0);

      // شبیه‌سازی اپلود
      await new Promise((resolve) => setTimeout(resolve, 2000));

      setUploadResult({
        success: true,
        createdCategories: jsonData.categories?.length || 0,
        createdAttributes: jsonData.attributes?.length || 0,
        createdProducts: jsonData.products?.length || 0,
        totalVariations: jsonData.products?.reduce(
          (sum: number, p: any) => sum + (p.variations?.length || 0),
          0
        ) || 0,
      });

      setSelectedFile(null);
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    } catch (error) {
      setErrors([
        error instanceof Error ? error.message : 'خطا در بارگذاری فایل',
      ]);
    } finally {
      setIsUploading(false);
    }
  };

  return (
    <div className="space-y-6">
      {uploadResult?.success ? (
        <div className="rounded-lg border border-green-500/20 bg-green-500/10 p-6">
          <p className="flex items-center gap-2 text-lg font-semibold text-green-400 mb-4">
            <CheckCircle className="h-6 w-6" />
            اپلود با موفقیت انجام شد
          </p>
          <div className="grid grid-cols-2 gap-4 md:grid-cols-4 mb-6">
            <div>
              <p className="text-sm text-slate-400">دسته‌بندی‌ها</p>
              <p className="text-2xl font-bold text-white">
                {uploadResult.createdCategories}
              </p>
            </div>
            <div>
              <p className="text-sm text-slate-400">ویژگی‌ها</p>
              <p className="text-2xl font-bold text-white">
                {uploadResult.createdAttributes}
              </p>
            </div>
            <div>
              <p className="text-sm text-slate-400">محصولات</p>
              <p className="text-2xl font-bold text-white">
                {uploadResult.createdProducts}
              </p>
            </div>
            <div>
              <p className="text-sm text-slate-400">متغیرها</p>
              <p className="text-2xl font-bold text-white">
                {uploadResult.totalVariations}
              </p>
            </div>
          </div>

          <Button
            onClick={() => {
              setUploadResult(null);
              setDestinationUrl('');
              setApiKey('');
            }}
            className="w-full bg-slate-700 hover:bg-slate-600"
          >
            بارگذاری فایل دیگر
          </Button>
        </div>
      ) : (
        <>
          <div className="grid gap-4">
            <div>
              <label className="block text-sm font-medium text-slate-300 mb-2">
                آدرس سایت مقصد
              </label>
              <input
                type="url"
                placeholder="https://destination-site.com"
                value={destinationUrl}
                onChange={(e) => setDestinationUrl(e.target.value)}
                className="w-full rounded-lg border border-slate-600 bg-slate-700 px-4 py-2 text-white placeholder-slate-500 focus:border-blue-500 focus:outline-none"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-300 mb-2">
                API کلید (اختیاری)
              </label>
              <input
                type="password"
                placeholder="API کلید برای سایت مقصد"
                value={apiKey}
                onChange={(e) => setApiKey(e.target.value)}
                className="w-full rounded-lg border border-slate-600 bg-slate-700 px-4 py-2 text-white placeholder-slate-500 focus:border-blue-500 focus:outline-none"
              />
            </div>
          </div>

          {/* انتخاب فایل */}
          <div
            className="rounded-lg border-2 border-dashed border-slate-600 bg-slate-700/20 p-8 text-center cursor-pointer hover:border-blue-500 hover:bg-slate-700/40 transition-colors"
            onClick={() => fileInputRef.current?.click()}
          >
            <input
              ref={fileInputRef}
              type="file"
              accept=".json"
              onChange={handleFileChange}
              className="hidden"
            />

            {selectedFile ? (
              <div className="space-y-2">
                <File className="mx-auto h-12 w-12 text-blue-400" />
                <p className="font-medium text-white">{selectedFile.name}</p>
                <p className="text-sm text-slate-400">
                  {(selectedFile.size / 1024).toFixed(2)} کیلوبایت
                </p>
              </div>
            ) : (
              <div className="space-y-2">
                <Upload className="mx-auto h-12 w-12 text-slate-500" />
                <p className="font-medium text-white">
                  فایل JSON را اینجا بکشید یا کلیک کنید
                </p>
                <p className="text-sm text-slate-400">
                  فایل دانلود شده از مرحله قبل
                </p>
              </div>
            )}
          </div>

          {errors.length > 0 && (
            <div className="rounded-lg border border-red-500/20 bg-red-500/10 p-4">
              {errors.map((error, i) => (
                <p key={i} className="flex items-center gap-2 text-sm text-red-400">
                  <AlertCircle className="h-4 w-4" />
                  {error}
                </p>
              ))}
            </div>
          )}

          <Button
            onClick={handleUpload}
            disabled={!selectedFile || isUploading}
            className="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed py-2 h-auto"
          >
            {isUploading ? (
              <>
                <Loader className="mr-2 h-4 w-4 animate-spin" />
                در حال اپلود...
              </>
            ) : (
              <>
                <Upload className="mr-2 h-4 w-4" />
                اپلود و ایجاد محصولات
              </>
            )}
          </Button>
        </>
      )}
    </div>
  );
}
