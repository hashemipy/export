'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { AlertCircle, CheckCircle, Loader } from 'lucide-react';

interface MigrationStep {
  id: string;
  label: string;
  status: 'pending' | 'running' | 'completed' | 'error';
  message?: string;
}

export default function MigrationManager() {
  const [sourceUrl, setSourceUrl] = useState('');
  const [destinationUrl, setDestinationUrl] = useState('');
  const [apiKey, setApiKey] = useState('');
  const [isRunning, setIsRunning] = useState(false);
  const [steps, setSteps] = useState<MigrationStep[]>([
    { id: 'download', label: 'دانلود محصولات و ویژگی‌ها', status: 'pending' },
    { id: 'process', label: 'پردازش و بررسی داده‌ها', status: 'pending' },
    { id: 'categories', label: 'اپلود دسته‌بندی‌ها', status: 'pending' },
    { id: 'attributes', label: 'اپلود ویژگی‌ها', status: 'pending' },
    { id: 'products', label: 'اپلود محصولات و متغیرها', status: 'pending' },
  ]);
  const [results, setResults] = useState<any>(null);
  const [errors, setErrors] = useState<string[]>([]);

  const updateStep = (id: string, status: MigrationStep['status'], message?: string) => {
    setSteps((prev) =>
      prev.map((step) =>
        step.id === id ? { ...step, status, message } : step
      )
    );
  };

  const handleMigrate = async () => {
    if (!sourceUrl || !destinationUrl) {
      setErrors(['آدرس‌های سایت مبدا و مقصد الزامی هستند']);
      return;
    }

    setIsRunning(true);
    setErrors([]);
    setSteps((prev) =>
      prev.map((step) => ({ ...step, status: 'pending' }))
    );

    try {
      // شبیه‌سازی فرایند
      // در واقع، شما باید API را صدا بزنید

      // مرحله ۱: دانلود
      updateStep('download', 'running');
      await new Promise((resolve) => setTimeout(resolve, 1500));
      updateStep('download', 'completed', '5 محصول + 3 ویژگی + 2 دسته‌بندی');

      // مرحله ۲: پردازش
      updateStep('process', 'running');
      await new Promise((resolve) => setTimeout(resolve, 1000));
      updateStep('process', 'completed', 'تمام داده‌ها معتبر هستند');

      // مرحله ۳: دسته‌بندی‌ها
      updateStep('categories', 'running');
      await new Promise((resolve) => setTimeout(resolve, 800));
      updateStep('categories', 'completed', '2 دسته‌بندی ایجاد شد');

      // مرحله ۴: ویژگی‌ها
      updateStep('attributes', 'running');
      await new Promise((resolve) => setTimeout(resolve, 800));
      updateStep('attributes', 'completed', '3 ویژگی ایجاد شد');

      // مرحله ۵: محصولات
      updateStep('products', 'running');
      await new Promise((resolve) => setTimeout(resolve, 1500));
      updateStep('products', 'completed', '5 محصول متغیر با 20 متغیر ایجاد شد');

      setResults({
        createdCategories: 2,
        createdAttributes: 3,
        createdProducts: 5,
        totalVariations: 20,
        duration: '5.6 ثانیه',
      });
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'خطای نامعلوم';
      setErrors([errorMessage]);
      updateStep('download', 'error', errorMessage);
    } finally {
      setIsRunning(false);
    }
  };

  return (
    <div className="rounded-lg border border-slate-700 bg-slate-800/50 p-8">
      <div className="space-y-6">
        {/* فرم ورودی */}
        {!isRunning && results === null && (
          <div>
            <h2 className="text-xl font-semibold text-white">
              فرایند انتقال کامل
            </h2>
            <p className="mt-2 text-slate-400">
              داده‌های مورد نیاز را وارد کنید تا انتقال خودکار محصولات شروع شود
            </p>

            <div className="mt-6 space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-300 mb-2">
                  آدرس سایت مبدا
                </label>
                <input
                  type="url"
                  placeholder="https://source-site.com"
                  value={sourceUrl}
                  onChange={(e) => setSourceUrl(e.target.value)}
                  className="w-full rounded-lg border border-slate-600 bg-slate-700 px-4 py-2 text-white placeholder-slate-500 focus:border-blue-500 focus:outline-none"
                />
              </div>

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

              {errors.length > 0 && (
                <div className="rounded-lg border border-red-500/20 bg-red-500/10 p-3">
                  {errors.map((error, i) => (
                    <p key={i} className="flex items-center gap-2 text-sm text-red-400">
                      <AlertCircle className="h-4 w-4" />
                      {error}
                    </p>
                  ))}
                </div>
              )}

              <Button
                onClick={handleMigrate}
                className="w-full bg-green-600 hover:bg-green-700 py-2 h-auto"
              >
                شروع انتقال محصولات
              </Button>
            </div>
          </div>
        )}

        {/* نمایش مراحل */}
        {(isRunning || results !== null) && (
          <div>
            <h2 className="text-xl font-semibold text-white">
              وضعیت انتقال
            </h2>

            <div className="mt-6 space-y-4">
              {steps.map((step) => (
                <div
                  key={step.id}
                  className="flex items-start gap-4 rounded-lg border border-slate-700 bg-slate-700/30 p-4"
                >
                  <div className="mt-1">
                    {step.status === 'pending' && (
                      <div className="h-6 w-6 rounded-full border-2 border-slate-600"></div>
                    )}
                    {step.status === 'running' && (
                      <Loader className="h-6 w-6 text-blue-400 animate-spin" />
                    )}
                    {step.status === 'completed' && (
                      <CheckCircle className="h-6 w-6 text-green-400" />
                    )}
                    {step.status === 'error' && (
                      <AlertCircle className="h-6 w-6 text-red-400" />
                    )}
                  </div>
                  <div className="flex-1">
                    <p className="font-medium text-white">{step.label}</p>
                    {step.message && (
                      <p className="mt-1 text-sm text-slate-400">
                        {step.message}
                      </p>
                    )}
                  </div>
                </div>
              ))}
            </div>

            {results && (
              <div className="mt-8 rounded-lg border border-green-500/20 bg-green-500/10 p-6">
                <p className="flex items-center gap-2 text-lg font-semibold text-green-400 mb-4">
                  <CheckCircle className="h-6 w-6" />
                  انتقال با موفقیت انجام شد
                </p>
                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                  <div>
                    <p className="text-sm text-slate-400">دسته‌بندی‌ها</p>
                    <p className="text-2xl font-bold text-white">
                      {results.createdCategories}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-slate-400">ویژگی‌ها</p>
                    <p className="text-2xl font-bold text-white">
                      {results.createdAttributes}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-slate-400">محصولات</p>
                    <p className="text-2xl font-bold text-white">
                      {results.createdProducts}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-slate-400">متغیرها</p>
                    <p className="text-2xl font-bold text-white">
                      {results.totalVariations}
                    </p>
                  </div>
                </div>

                <Button
                  onClick={() => {
                    setResults(null);
                    setSourceUrl('');
                    setDestinationUrl('');
                    setApiKey('');
                  }}
                  className="w-full mt-4 bg-slate-700 hover:bg-slate-600"
                >
                  انتقال دیگری
                </Button>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
