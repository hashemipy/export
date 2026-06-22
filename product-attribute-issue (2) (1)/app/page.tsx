import React from 'react';
import SimpleUploader from '@/components/SimpleUploader';

export const metadata = {
  title: 'آپلود محصول متغیر | Product Upload',
  description: 'آپلود ساده و سریع محصولات متغیر به سایت مقصد',
};

export default function Page() {
  return (
    <div className="min-h-screen bg-gradient-to-b from-slate-900 to-slate-800">
      <div className="max-w-2xl mx-auto px-4 py-12">
        {/* Header */}
        <div className="text-center mb-12">
          <h1 className="text-4xl font-bold text-white mb-4">
            آپلود محصولات
          </h1>
          <p className="text-lg text-slate-400 mb-2">
            محصولات متغیر را به سایت مقصد منتقل کنید
          </p>
          <p className="text-sm text-slate-500">
            ✓ متغیرها ✓ ویژگی‌ها ✓ موجودی ✓ تصاویر
          </p>
        </div>

        {/* Main Content */}
        <SimpleUploader />

        {/* Instructions */}
        <div className="mt-12 rounded-lg border border-slate-700 bg-slate-800/30 p-6">
          <h3 className="text-lg font-semibold text-white mb-4">نحوه استفاده:</h3>
          <ol className="space-y-3 text-slate-300 text-sm">
            <li className="flex gap-3">
              <span className="flex-shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold">1</span>
              <span>دکمه "دانلود الگو" را کلیک کنید و فایل JSON را ذخیره کنید</span>
            </li>
            <li className="flex gap-3">
              <span className="flex-shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold">2</span>
              <span>فایل را ویرایش کنید: نام محصول، قیمت، ویژگی‌ها و متغیرها را اضافه کنید</span>
            </li>
            <li className="flex gap-3">
              <span className="flex-shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold">3</span>
              <span>فایل JSON را بارگذاری کنید</span>
            </li>
            <li className="flex gap-3">
              <span className="flex-shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold">4</span>
              <span>آدرس سایت مقصد را وارد کنید</span>
            </li>
            <li className="flex gap-3">
              <span className="flex-shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold">5</span>
              <span>"شروع آپلود" را کلیک کنید و منتظر بمانید</span>
            </li>
          </ol>
        </div>

        {/* Footer */}
        <div className="mt-12 text-center text-slate-500 text-sm">
          <p>نسخه 2.0 - ساده‌تر | سریع‌تر | قابل‌اعتمادتر</p>
        </div>
      </div>
    </div>
  );
}
