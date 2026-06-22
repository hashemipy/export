'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Download, Upload, FileUp } from 'lucide-react';
import MigrationManager from '@/components/MigrationManager';
import FileUpload from '@/components/FileUpload';

export default function Page() {
  const [activeTab, setActiveTab] = useState<'download' | 'upload' | 'migrate'>('download');

  return (
    <main className="min-h-screen bg-gradient-to-br from-slate-900 to-slate-800">
      {/* هدر */}
      <div className="border-b border-slate-700 bg-slate-800/50 backdrop-blur">
        <div className="mx-auto max-w-6xl px-6 py-8">
          <h1 className="text-3xl font-bold text-white">
            انتقال محصولات متغیر
          </h1>
          <p className="mt-2 text-slate-400">
            دانلود محصولات، ویژگی‌ها و دسته‌بندی‌ها از سایت مبدا و اپلود به سایت مقصد
          </p>
        </div>
      </div>

      {/* محتوا */}
      <div className="mx-auto max-w-6xl px-6 py-8">
        {/* تب‌ها */}
        <div className="mb-8 flex gap-4 border-b border-slate-700">
          <button
            onClick={() => setActiveTab('download')}
            className={`flex items-center gap-2 px-4 py-2 font-medium transition-colors ${
              activeTab === 'download'
                ? 'border-b-2 border-blue-500 text-blue-400'
                : 'text-slate-400 hover:text-slate-300'
            }`}
          >
            <Download className="h-4 w-4" />
            دانلود محصولات
          </button>
          <button
            onClick={() => setActiveTab('upload')}
            className={`flex items-center gap-2 px-4 py-2 font-medium transition-colors ${
              activeTab === 'upload'
                ? 'border-b-2 border-blue-500 text-blue-400'
                : 'text-slate-400 hover:text-slate-300'
            }`}
          >
            <Upload className="h-4 w-4" />
            اپلود به سایت مقصد
          </button>
          <button
            onClick={() => setActiveTab('migrate')}
            className={`flex items-center gap-2 px-4 py-2 font-medium transition-colors ${
              activeTab === 'migrate'
                ? 'border-b-2 border-blue-500 text-blue-400'
                : 'text-slate-400 hover:text-slate-300'
            }`}
          >
            <FileUp className="h-4 w-4" />
            انتقال کامل
          </button>
        </div>

        {/* محتوای تب‌ها */}
        {activeTab === 'download' && (
          <div className="rounded-lg border border-slate-700 bg-slate-800/50 p-8">
            <div className="space-y-6">
              <div>
                <h2 className="text-xl font-semibold text-white">
                  دانلود از سایت مبدا
                </h2>
                <p className="mt-2 text-slate-400">
                  محصولات، ویژگی‌ها و دسته‌بندی‌ها را از سایت مبدا دانلود کنید
                </p>
              </div>

              <div className="grid gap-4">
                <div>
                  <label className="block text-sm font-medium text-slate-300 mb-2">
                    آدرس سایت مبدا
                  </label>
                  <input
                    type="url"
                    placeholder="https://source-site.com"
                    className="w-full rounded-lg border border-slate-600 bg-slate-700 px-4 py-2 text-white placeholder-slate-500 focus:border-blue-500 focus:outline-none"
                  />
                </div>

                <Button className="w-full bg-blue-600 hover:bg-blue-700">
                  <Download className="mr-2 h-4 w-4" />
                  دانلود محصولات
                </Button>
              </div>
            </div>
          </div>
        )}

        {activeTab === 'upload' && (
          <div className="rounded-lg border border-slate-700 bg-slate-800/50 p-8">
            <div className="space-y-6">
              <div>
                <h2 className="text-xl font-semibold text-white">
                  اپلود به سایت مقصد
                </h2>
                <p className="mt-2 text-slate-400">
                  فایل JSON دانلود شده را اپلود کنید تا محصولات، ویژگی‌ها و دسته‌بندی‌های لازم ایجاد شوند
                </p>
              </div>

              <FileUpload />
            </div>
          </div>
        )}

        {activeTab === 'migrate' && <MigrationManager />}
      </div>
    </main>
  );
}
