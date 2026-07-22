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
use Inertia\Testing\AssertableInertia as Assert;

const STOREFRONT_PREVIEW_PRODUCT_SLUGS = [
    'alpine-morning-travel-lut',
    'coastal-film-travel-lut',
    'desert-road-travel-lut',
    'golden-city-travel-lut',
    'night-market-travel-lut',
    'nordic-air-travel-lut',
];

function seedStorefrontPreview(): void
{
    test()->artisan('db:seed', [
        '--class' => 'Database\\Seeders\\StorefrontPreviewSeeder',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
}

test('storefront preview seeder creates an idempotent travel catalog without accounts or assets', function (): void {
    seedStorefrontPreview();

    $travelCategory = Category::query()->where('slug', 'travel')->firstOrFail();
    $initialProductIds = Product::query()
        ->whereIn('slug', STOREFRONT_PREVIEW_PRODUCT_SLUGS)
        ->orderBy('slug')
        ->pluck('id')
        ->all();

    expect($initialProductIds)->toHaveCount(6)
        ->and(Product::query()->count())->toBe(6)
        ->and(Product::query()->where('status', ProductStatus::Published)->count())->toBe(6)
        ->and($travelCategory->products()->count())->toBe(6)
        ->and(User::query()->count())->toBe(0)
        ->and(Order::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0)
        ->and(Entitlement::query()->count())->toBe(0)
        ->and(ProductVersion::query()->count())->toBe(0)
        ->and(ProductFile::query()->count())->toBe(0)
        ->and(ProductMedia::query()->count())->toBe(0)
        ->and(ProductExample::query()->count())->toBe(0);

    seedStorefrontPreview();

    $reseededProductIds = Product::query()
        ->whereIn('slug', STOREFRONT_PREVIEW_PRODUCT_SLUGS)
        ->orderBy('slug')
        ->pluck('id')
        ->all();

    expect($reseededProductIds)->toBe($initialProductIds)
        ->and(Product::query()->count())->toBe(6)
        ->and($travelCategory->products()->count())->toBe(6);

    $this->get(route('categories.show', 'travel'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Categories/Show')
            ->where('resultCount', 6)
            ->has('products.data', 6));
});
