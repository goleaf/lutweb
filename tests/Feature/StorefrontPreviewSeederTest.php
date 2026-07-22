<?php

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductExample;
use App\Models\ProductFile;
use App\Models\ProductMedia;
use App\Models\ProductVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

const STOREFRONT_PREVIEW_PRODUCT_SLUGS = [
    'alpine-morning-travel-lut',
    'coastal-film-travel-lut',
    'desert-road-travel-lut',
    'golden-city-travel-lut',
    'night-market-travel-lut',
    'nordic-air-travel-lut',
];

const STOREFRONT_PREVIEW_PRIMARY_CATEGORIES = [
    'cinematic' => 'CINEMATIC',
    'portrait' => 'PORTRAIT',
    'travel' => 'TRAVEL',
    'street' => 'STREET',
    'wedding' => 'WEDDING',
    'warm' => 'WARM',
    'cool' => 'COOL',
    'moody' => 'MOODY',
    'vintage' => 'VINTAGE',
    'pastel' => 'PASTEL',
];

function seedStorefrontPreview(): void
{
    test()->artisan('db:seed', [
        '--class' => 'Database\\Seeders\\StorefrontPreviewSeeder',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
}

test('storefront preview seeder creates thirty products per primary category without operational data', function (): void {
    seedStorefrontPreview();

    $initialProductIds = Product::query()
        ->where('sku', 'like', 'PREVIEW-%')
        ->orderBy('sku')
        ->pluck('id')
        ->all();
    $initialTagPivotCount = DB::table('product_tag')->count();

    expect($initialProductIds)->toHaveCount(300)
        ->and(Product::query()->count())->toBe(300)
        ->and(Product::query()->where('status', ProductStatus::Published)->count())->toBe(300)
        ->and(Product::query()->distinct()->count('sku'))->toBe(300)
        ->and(Product::query()->distinct()->count('slug'))->toBe(300)
        ->and(Product::query()->distinct()->count('name'))->toBe(300)
        ->and(User::query()->count())->toBe(0)
        ->and(Order::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0)
        ->and(Entitlement::query()->count())->toBe(0)
        ->and(ProductVersion::query()->count())->toBe(0)
        ->and(ProductFile::query()->count())->toBe(0)
        ->and(ProductMedia::query()->count())->toBe(0)
        ->and(ProductExample::query()->count())->toBe(0);

    Product::query()
        ->where('sku', 'like', 'PREVIEW-%')
        ->withCount('tags')
        ->each(function (Product $product): void {
            expect($product->is_testable)->toBeTrue()
                ->and($product->tags_count)->toBeBetween(8, 12);
        });

    foreach (STOREFRONT_PREVIEW_PRIMARY_CATEGORIES as $categorySlug => $skuSegment) {
        $category = Category::query()->where('slug', $categorySlug)->firstOrFail();
        $primaryProductCount = $category->products()
            ->where('sku', 'like', "PREVIEW-{$skuSegment}-%")
            ->count();

        expect($primaryProductCount)->toBe(30)
            ->and($category->products()->count())->toBeBetween(30, 50);
    }

    expect(Product::query()->whereIn('slug', STOREFRONT_PREVIEW_PRODUCT_SLUGS)->count())->toBe(6)
        ->and(Product::query()->where('slug', 'alpine-morning-travel-lut')->firstOrFail()->categories()->pluck('slug')->sort()->values()->all())
        ->toBe(['bright-clean', 'cool', 'travel'])
        ->and(Product::query()->where('slug', 'golden-city-travel-lut')->firstOrFail()->categories()->pluck('slug')->sort()->values()->all())
        ->toBe(['cinematic', 'street', 'travel', 'warm']);

    seedStorefrontPreview();

    $reseededProductIds = Product::query()
        ->where('sku', 'like', 'PREVIEW-%')
        ->orderBy('sku')
        ->pluck('id')
        ->all();

    expect($reseededProductIds)->toBe($initialProductIds)
        ->and(Product::query()->count())->toBe(300)
        ->and(DB::table('product_tag')->count())->toBe($initialTagPivotCount);

    $this->get(route('categories.show', 'travel'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Categories/Show')
            ->where('resultCount', 30)
            ->has('products.data', 12));
});
