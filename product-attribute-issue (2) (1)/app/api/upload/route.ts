import { NextRequest, NextResponse } from 'next/server';

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { products, destinationUrl, method } = body;

    if (!products || !Array.isArray(products)) {
      return NextResponse.json(
        { error: 'محصولات معتبر نیستند' },
        { status: 400 }
      );
    }

    if (!destinationUrl) {
      return NextResponse.json(
        { error: 'آدرس سایت مقصد الزامی است' },
        { status: 400 }
      );
    }

    console.log('[API] شروع اپلود محصولات...');
    console.log('[API] تعداد محصولات:', products.length);

    let results = {
      success: true,
      productsUploaded: 0,
      variationsUploaded: 0,
      errors: [] as string[],
      details: [] as any[],
    };

    for (const product of products) {
      try {
        console.log(`[API] پردازش محصول: ${product.name}`);

        // مرحله 1: اپلود محصول متغیر
        const productPayload = {
          name: product.name,
          type: 'variable',
          description: product.description,
          sku: product.sku,
          price: product.price,
          status: 'publish',
          categories: product.categories,
          images: product.images,
          attributes: product.attributes.map((attr: any) => ({
            id: attr.id,
            name: attr.name,
            options: attr.options,
            position: attr.position,
            visible: attr.visible,
            variation: attr.variation,
          })),
        };

        // ارسال محصول به سایت مقصد
        let createdProductId = product.id;

        try {
          const productResponse = await fetch(
            `${destinationUrl}/wp-json/wc/v3/products`,
            {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                Authorization: `Bearer ${process.env.WOOCOMMERCE_API_TOKEN || ''}`,
              },
              body: JSON.stringify(productPayload),
            }
          );

          if (productResponse.ok) {
            const createdProduct = await productResponse.json();
            createdProductId = createdProduct.id;
            console.log(`[API] محصول ایجاد شد: ID ${createdProductId}`);
          } else {
            console.warn(
              `[API] هشدار: نتوانست محصول را ایجاد کند - ${productResponse.statusText}`
            );
          }
        } catch (productError) {
          console.error('[API] خطا در ایجاد محصول:', productError);
          results.errors.push(
            `خطا در ایجاد محصول "${product.name}": ${productError}`
          );
        }

        // مرحله 2: اپلود تمام متغیرها
        if (product.variations && Array.isArray(product.variations)) {
          console.log(
            `[API] اپلود ${product.variations.length} متغیر برای محصول ${product.name}`
          );

          for (const variation of product.variations) {
            try {
              const variationPayload = {
                sku: variation.sku,
                price: variation.price,
                stock_quantity: variation.stock,
                attributes: Object.entries(variation.attributes).map(
                  ([name, value]) => ({
                    name,
                    option: value,
                  })
                ),
                image: variation.image_id
                  ? { id: variation.image_id }
                  : undefined,
              };

              const variationResponse = await fetch(
                `${destinationUrl}/wp-json/wc/v3/products/${createdProductId}/variations`,
                {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${process.env.WOOCOMMERCE_API_TOKEN || ''}`,
                  },
                  body: JSON.stringify(variationPayload),
                }
              );

              if (variationResponse.ok) {
                results.variationsUploaded++;
                console.log(
                  `[API] متغیر اپلود شد: ${variation.sku}`
                );
              } else {
                const errorData = await variationResponse.text();
                console.warn(
                  `[API] خطا در اپلود متغیر ${variation.sku}: ${variationResponse.statusText}`
                );
                results.errors.push(
                  `خطا در اپلود متغیر ${variation.sku}: ${variationResponse.statusText}`
                );
              }
            } catch (variationError) {
              console.error(
                `[API] خطا در اپلود متغیر:`,
                variationError
              );
              results.errors.push(
                `خطا در اپلود متغیر: ${variationError}`
              );
            }
          }
        }

        results.productsUploaded++;
        results.details.push({
          product: product.name,
          variations: product.variations?.length || 0,
          status: 'ایجاد شد',
        });
      } catch (error) {
        const errorMessage = error instanceof Error ? error.message : String(error);
        console.error(`[API] خطا در پردازش محصول:`, error);
        results.errors.push(`خطا در محصول "${product.name}": ${errorMessage}`);
      }
    }

    console.log('[API] خلاصه نتایج:', results);

    return NextResponse.json(results);
  } catch (error) {
    console.error('[API] خطا در درخواست:', error);
    return NextResponse.json(
      { error: 'خطا در سرور' },
      { status: 500 }
    );
  }
}
