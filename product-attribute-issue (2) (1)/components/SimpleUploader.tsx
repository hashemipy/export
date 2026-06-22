'use client';

import { useState, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { AlertCircle, CheckCircle, Loader, Download } from 'lucide-react';

interface UploadProgress {
  currentStep: string;
  productsUploaded: number;
  variationsUploaded: number;
  totalVariations: number;
  errors: string[];
}

export default function SimpleUploader() {
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [destinationUrl, setDestinationUrl] = useState('');
  const [isUploading, setIsUploading] = useState(false);
  const [progress, setProgress] = useState<UploadProgress | null>(null);
  const [uploadedFile, setUploadedFile] = useState<any>(null);
  const [completed, setCompleted] = useState(false);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (event) => {
      try {
        const json = JSON.parse(event.target?.result as string);
        setUploadedFile(json);
        console.log('[SimpleUploader] فایل بارگذاری شد:', json);
      } catch (error) {
        alert('فایل JSON معتبر نیست');
      }
    };
    reader.readAsText(file);
  };

  const handleUpload = async () => {
    if (!uploadedFile || !destinationUrl) {
      alert('لطفا فایل و آدرس سایت را وارد کنید');
      return;
    }

    setIsUploading(true);
    setProgress({
      currentStep: 'اتصال به سایت مقصد...',
      productsUploaded: 0,
      variationsUploaded: 0,
      totalVariations: uploadedFile.reduce(
        (sum: number, p: any) => sum + (p.variations?.length || 0),
        0
      ),
      errors: [],
    });

    try {
      setProgress((prev) =>
        prev ? { ...prev, currentStep: 'ارسال محصولات...' } : null
      );

      const response = await fetch('/api/upload', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          products: uploadedFile,
          destinationUrl,
          method: 'rest',
        }),
      });

      const result = await response.json();

      if (!response.ok) {
        throw new Error(result.error || 'خطا در اپلود');
      }

      setProgress({
        currentStep: 'تمام شد',
        productsUploaded: result.productsUploaded,
        variationsUploaded: result.variationsUploaded,
        totalVariations: result.variationsUploaded,
        errors: result.errors || [],
      });

      setCompleted(true);
      console.log('[SimpleUploader] اپلود تمام شد:', result);
    } catch (error) {
      const errorMsg = error instanceof Error ? error.message : 'خطای نامعلوم';
      setProgress((prev) =>
        prev
          ? {
            ...prev,
            currentStep: 'خطا',
            errors: [...prev.errors, errorMsg],
          }
          : null
      );
      console.error('[SimpleUploader] خطا:', error);
    } finally {
      setIsUploading(false);
    }
  };

  const downloadTemplate = () => {
    const template = [
      {
        id: 1000,
        name: 'محصول نمونه',
        sku: '1000',
        type: 'variable',
        description: 'توضیح محصول',
        price: 100000,
        stock_quantity: 10,
        status: 'publish',
        categories: [
          {
            id: 1,
            name: 'دسته‌بندی',
            slug: 'category',
          },
        ],
        images: [
          {
            id: 1,
            src: 'https://example.com/image.jpg',
            alt: 'تصویر محصول',
          },
        ],
        attributes: [
          {
            id: 1,
            name: 'رنگ',
            slug: 'rang',
            position: 0,
            visible: true,
            variation: true,
            options: ['سرمه ای', 'سفید'],
          },
          {
            id: 2,
            name: 'سایز',
            slug: 'saiz',
            position: 1,
            visible: true,
            variation: true,
            options: ['کوچک', 'بزرگ'],
          },
        ],
        variations: [
          {
            id: 10001,
            sku: '1000-DARK-SMALL',
            name: 'محصول - سرمه ای - کوچک',
            price: 100000,
            stock: 5,
            attributes: {
              رنگ: 'سرمه ای',
              سایز: 'کوچک',
            },
            image_id: 1,
          },
          {
            id: 10002,
            sku: '1000-DARK-LARGE',
            name: 'محصول - سرمه ای - بزرگ',
            price: 100000,
            stock: 5,
            attributes: {
              رنگ: 'سرمه ای',
              سایز: 'بزرگ',
            },
            image_id: 1,
          },
          {
            id: 10003,
            sku: '1000-WHITE-SMALL',
            name: 'محصول - سفید - کوچک',
            price: 100000,
            stock: 5,
            attributes: {
              رنگ: 'سفید',
              سایز: 'کوچک',
            },
            image_id: 1,
          },
          {
            id: 10004,
            sku: '1000-WHITE-LARGE',
            name: 'محصول - سفید - بزرگ',
            price: 100000,
            stock: 5,
            attributes: {
              رنگ: 'سفید',
              سایز: 'بزرگ',
            },
            image_id: 1,
          },
        ],
      },
    ];

    const element = document.createElement('a');
    element.setAttribute(
      'href',
      'data:text/plain;charset=utf-8,' + encodeURIComponent(JSON.stringify(template, null, 2))
    );
    element.setAttribute('download', 'template.json');
    element.style.display = 'none';
    document.body.appendChild(element);
    element.click();
    document.body.removeChild(element);
  };

  return (
    <div className="space-y-6">
      {/* فرم آپلود */}
      {!completed && (
        <div className="rounded-lg border border-slate-700 bg-slate-800/50 p-6 space-y-4">
          <div>
            <h3 className="text-lg font-semibold text-white mb-4">
              آپلود محصولات متغیر
            </h3>

            {/* دانلود الگو */}
            <div className="mb-6">
              <button
                onClick={downloadTemplate}
                className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition-colors"
              >
                <Download className="h-4 w-4" />
                دانلود الگو (Template)
              </button>
              <p className="text-sm text-slate-400 mt-2">
                این الگو را تکمیل کنید و آپلود کنید
              </p>
            </div>

            {/* انتخاب فایل */}
            <div className="space-y-3">
              <label className="block">
                <span className="text-sm font-medium text-slate-300 mb-2 block">
                  فایل JSON
                </span>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept=".json"
                  onChange={handleFileChange}
                  className="w-full px-3 py-2 rounded-lg border border-slate-600 bg-slate-700 text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700"
                />
              </label>

              {uploadedFile && (
                <div className="text-sm text-green-400">
                  فایل بارگذاری شد: {uploadedFile.length} محصول
                </div>
              )}
            </div>

            {/* آدرس سایت مقصد */}
            <div className="space-y-3">
              <label className="block">
                <span className="text-sm font-medium text-slate-300 mb-2 block">
                  آدرس سایت مقصد
                </span>
                <input
                  type="url"
                  placeholder="https://destination-site.com"
                  value={destinationUrl}
                  onChange={(e) => setDestinationUrl(e.target.value)}
                  className="w-full rounded-lg border border-slate-600 bg-slate-700 px-4 py-2 text-white placeholder-slate-500 focus:border-blue-500 focus:outline-none"
                />
              </label>
            </div>

            {/* دکمه آپلود */}
            <Button
              onClick={handleUpload}
              disabled={!uploadedFile || !destinationUrl || isUploading}
              className="w-full bg-green-600 hover:bg-green-700 disabled:bg-slate-600 disabled:cursor-not-allowed py-2 h-auto flex items-center justify-center gap-2"
            >
              {isUploading && <Loader className="h-4 w-4 animate-spin" />}
              {isUploading ? 'درحال اپلود...' : 'شروع آپلود'}
            </Button>
          </div>

          {progress && !completed && (
            <div className="rounded-lg border border-blue-500/20 bg-blue-500/10 p-4">
              <p className="text-blue-400 font-medium mb-2">{progress.currentStep}</p>
              <div className="space-y-1 text-sm text-slate-400">
                <p>محصولات اپلود شده: {progress.productsUploaded}</p>
                <p>متغیرها اپلود شده: {progress.variationsUploaded} / {progress.totalVariations}</p>
                {progress.errors.length > 0 && (
                  <div className="mt-3 space-y-1">
                    <p className="text-red-400">خطاها:</p>
                    {progress.errors.map((error, i) => (
                      <p key={i} className="text-red-400 text-xs">
                        • {error}
                      </p>
                    ))}
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      )}

      {/* نتیجه نهایی */}
      {completed && progress && (
        <div className="rounded-lg border border-green-500/20 bg-green-500/10 p-6">
          <div className="flex items-start gap-3">
            <CheckCircle className="h-6 w-6 text-green-400 mt-1" />
            <div className="flex-1">
              <h3 className="text-lg font-semibold text-green-400 mb-3">
                اپلود با موفقیت انجام شد
              </h3>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-sm text-slate-400">محصولات</p>
                  <p className="text-2xl font-bold text-white">
                    {progress.productsUploaded}
                  </p>
                </div>
                <div>
                  <p className="text-sm text-slate-400">متغیرها</p>
                  <p className="text-2xl font-bold text-white">
                    {progress.variationsUploaded}
                  </p>
                </div>
              </div>

              {progress.errors.length > 0 && (
                <div className="mt-4 rounded-lg border border-yellow-500/20 bg-yellow-500/10 p-3">
                  <p className="text-yellow-400 text-sm font-medium mb-2">
                    {progress.errors.length} هشدار:
                  </p>
                  <ul className="space-y-1">
                    {progress.errors.map((error, i) => (
                      <li key={i} className="text-xs text-yellow-400">
                        • {error}
                      </li>
                    ))}
                  </ul>
                </div>
              )}

              <Button
                onClick={() => {
                  setCompleted(false);
                  setProgress(null);
                  setUploadedFile(null);
                  setDestinationUrl('');
                  if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                  }
                }}
                className="w-full mt-4 bg-blue-600 hover:bg-blue-700"
              >
                آپلود دیگری
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
