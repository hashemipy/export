#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import json
import sys
import urllib.parse
from pathlib import Path

def decode_url_encoded_strings(obj):
    """تمام strings URL-encoded را decode کن"""
    if isinstance(obj, dict):
        return {k: decode_url_encoded_strings(v) for k, v in obj.items()}
    elif isinstance(obj, list):
        return [decode_url_encoded_strings(item) for item in obj]
    elif isinstance(obj, str):
        try:
            # اگر URL-encoded است، decode کن
            decoded = urllib.parse.unquote(obj)
            if decoded != obj:
                return decoded
        except:
            pass
        return obj
    return obj

def generate_simple_slug(text):
    """slug ساده‌ای از متن ایجاد کن"""
    # برای فارسی، فقط حروف و اعداد نگه دار
    slug = ""
    for char in text:
        if char.isalnum() or char in '-_ ':
            slug += char
    return slug.strip().replace(' ', '-').lower()

def fix_variations(product):
    """متغیرات خالی را با مقادیر پیش‌فرض پر کن"""
    if product.get('type') != 'variable':
        return product
    
    variations = product.get('variations', [])
    attributes = product.get('attributes', {})
    
    if not variations or all(not v.get('attributes') for v in variations):
        # متغیرات خالی است، بایستی آپلودکننده آن را پر کند
        return product
    
    for i, var in enumerate(variations):
        if not var.get('sku'):
            var['sku'] = f"{product.get('sku', 'product')}-var-{i+1}"
        
        if not var.get('price'):
            var['price'] = product.get('price', '0')
        
        if var.get('stock_quantity') is None:
            var['stock_quantity'] = 1
    
    return product

def fix_json(input_file, output_file=None):
    """فایل JSON را اصلاح کن"""
    
    if output_file is None:
        output_file = input_file.replace('.json', '-fixed.json')
    
    print(f"در حال خواندن: {input_file}")
    
    try:
        with open(input_file, 'r', encoding='utf-8') as f:
            data = json.load(f)
    except Exception as e:
        print(f"خطا در خواندن فایل: {e}")
        return False
    
    if not isinstance(data, list):
        data = [data]
    
    print("در حال اصلاح...")
    
    fixed_data = []
    for idx, product in enumerate(data):
        # Decode URL-encoded strings
        product = decode_url_encoded_strings(product)
        
        # اصلاح categories
        if 'categories' in product:
            for cat in product['categories']:
                if 'slug' not in cat or not cat['slug']:
                    cat['slug'] = generate_simple_slug(cat.get('name', ''))
                if 'parent_id' not in cat:
                    cat['parent_id'] = 0
        
        # اصلاح attributes
        if 'attributes' in product and isinstance(product['attributes'], dict):
            fixed_attrs = {}
            for attr_name, attr_data in product['attributes'].items():
                # حذف 'pa_' prefix اگر موجود باشد
                clean_name = attr_name.replace('pa_', '')
                
                if isinstance(attr_data, dict):
                    if 'values' in attr_data:
                        for value in attr_data['values']:
                            if 'slug' not in value or not value['slug']:
                                value['slug'] = generate_simple_slug(value.get('name', ''))
                
                fixed_attrs[clean_name] = attr_data
            
            product['attributes'] = fixed_attrs
        
        # اصلاح variations
        product = fix_variations(product)
        
        fixed_data.append(product)
        print(f"✓ محصول {idx+1}: {product.get('name', 'Unknown')}")
    
    print(f"\nدر حال ذخیره: {output_file}")
    
    try:
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(fixed_data, f, ensure_ascii=False, indent=2)
        print("✓ فایل اصلاح شده ذخیره شد")
        return True
    except Exception as e:
        print(f"خطا در ذخیره فایل: {e}")
        return False

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print("استفاده: python fix_json.py <input_file> [output_file]")
        print("مثال: python fix_json.py products.json products-fixed.json")
        sys.exit(1)
    
    input_file = sys.argv[1]
    output_file = sys.argv[2] if len(sys.argv) > 2 else None
    
    if not Path(input_file).exists():
        print(f"فایل یافت نشد: {input_file}")
        sys.exit(1)
    
    if fix_json(input_file, output_file):
        print("\nموفق!")
        sys.exit(0)
    else:
        print("\nناموفق!")
        sys.exit(1)
