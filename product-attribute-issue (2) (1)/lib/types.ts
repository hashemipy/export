// انواع داده برای سیستم محصولات متغیر

export interface AttributeValue {
  name: string;
  slug: string;
}

export interface Attribute {
  name: string;
  slug: string;
  values: AttributeValue[];
  visible: boolean;
}

export interface Category {
  id?: number;
  name: string;
  slug: string;
  parent_id: number;
  description?: string;
  image?: string;
}

export interface ProductImage {
  id?: number;
  url: string;
  alt?: string;
  featured?: boolean;
}

export interface Variation {
  id?: number;
  sku: string;
  price: string | number;
  stock_quantity: number;
  attributes: Record<string, string>;
  image_url?: string;
  image_id?: number;
}

export interface Product {
  id?: number;
  name: string;
  slug?: string;
  sku: string;
  description: string;
  short_description: string;
  price: string | number;
  stock_quantity: number;
  type: 'simple' | 'variable';
  categories: Category[];
  image_urls: string[];
  attributes?: Record<string, Attribute>;
  variations?: Variation[];
  status?: 'publish' | 'draft';
  meta_data?: Record<string, any>;
}

export interface DownloadedData {
  products: Product[];
  categories: Category[];
  attributes: Attribute[];
  metadata: {
    exportDate: string;
    sourceUrl: string;
    totalProducts: number;
    totalCategories: number;
  };
}

export interface ProcessedData {
  categories: Category[];
  attributes: Attribute[];
  products: Product[];
}

export interface UploadResult {
  success: boolean;
  createdCategories: number;
  createdAttributes: number;
  createdProducts: number;
  errors: string[];
}
