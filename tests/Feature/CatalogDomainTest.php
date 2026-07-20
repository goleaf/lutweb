<?php

use App\Actions\Catalog\PublishProduct;
use App\Actions\Catalog\SetCurrentProductVersion;
use App\Enums\ProductFileKind;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVersionStatus;
use App\Models\BundleItem;
use App\Models\Category;
use App\Models\CompatibleSoftware;
use App\Models\Product;
use App\Models\ProductExample;
use App\Models\ProductFile;
use App\Models\ProductMedia;
use App\Models\ProductVersion;
use App\Models\Tag;
use App\Support\Catalog\EurMoney;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function catalogPublishableProduct(array $overrides = []): Product
{
    $product = Product::factory()->singleLut()->create([
        'price_cents' => 1999,
        ...$overrides,
    ]);

    $product->categories()->attach(Category::factory()->create());
    ProductMedia::factory()->cover()->for($product)->create();
    ProductExample::factory()->active()->for($product)->create();

    $version = ProductVersion::factory()
        ->ready()
        ->current()
        ->for($product)
        ->create();

    ProductFile::factory()
        ->packageZip()
        ->for($version, 'productVersion')
        ->create();

    return $product->refresh();
}

function expectCatalogPublishFailure(Product $product, string $errorKey): void
{
    try {
        app(PublishProduct::class)->handle($product);
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($errorKey);

        return;
    }

    test()->fail('Publishing did not fail validation.');
}

test('CatalogSeeder is idempotent', function () {
    $this->seed(CatalogSeeder::class);
    $this->seed(CatalogSeeder::class);

    expect(Category::query()->count())->toBe(14)
        ->and(Tag::query()->count())->toBe(15)
        ->and(CompatibleSoftware::query()->count())->toBe(5);
});

test('Product model relationships work', function () {
    $product = Product::factory()->singleLut()->create();
    $category = Category::factory()->create();
    $tag = Tag::factory()->create();
    $software = CompatibleSoftware::factory()->create();
    $version = ProductVersion::factory()->for($product)->create();
    $file = ProductFile::factory()->for($version, 'productVersion')->create();
    $media = ProductMedia::factory()->for($product)->create();
    $example = ProductExample::factory()->for($product)->create();

    $product->categories()->attach($category);
    $product->tags()->attach($tag);
    $product->compatibleSoftware()->attach($software);

    expect($product->refresh()->categories)->toHaveCount(1)
        ->and($product->tags)->toHaveCount(1)
        ->and($product->compatibleSoftware)->toHaveCount(1)
        ->and($product->versions)->toHaveCount(1)
        ->and($version->refresh()->files->first()->is($file))->toBeTrue()
        ->and($product->media->first()->is($media))->toBeTrue()
        ->and($product->examples->first()->is($example))->toBeTrue();
});

test('Category tag and compatible software relationships work', function () {
    $product = Product::factory()->singleLut()->create();
    $category = Category::factory()->hasAttached($product)->create();
    $tag = Tag::factory()->hasAttached($product)->create();
    $software = CompatibleSoftware::factory()->hasAttached($product)->create();

    expect($category->products()->first()->is($product))->toBeTrue()
        ->and($tag->products()->first()->is($product))->toBeTrue()
        ->and($software->products()->first()->is($product))->toBeTrue();
});

test('Bundle item relationships work', function () {
    $bundle = Product::factory()->bundle()->create();
    $component = Product::factory()->singleLut()->create();

    $item = BundleItem::factory()
        ->for($bundle, 'bundle')
        ->for($component, 'product')
        ->create();

    expect($item->bundle->is($bundle))->toBeTrue()
        ->and($item->product->is($component))->toBeTrue()
        ->and($bundle->bundleItems()->first()->is($item))->toBeTrue()
        ->and($component->includedInBundles()->first()->is($item))->toBeTrue();
});

test('a bundle cannot contain itself', function () {
    $bundle = Product::factory()->bundle()->create();

    expect(fn () => BundleItem::factory()
        ->for($bundle, 'bundle')
        ->for($bundle, 'product')
        ->create())
        ->toThrow(ValidationException::class);
});

test('a bundle cannot contain another bundle', function () {
    $bundle = Product::factory()->bundle()->create();
    $nestedBundle = Product::factory()->bundle()->create();

    expect(fn () => BundleItem::factory()
        ->for($bundle, 'bundle')
        ->for($nestedBundle, 'product')
        ->create())
        ->toThrow(ValidationException::class);
});

test('Product price is stored as integer cents', function () {
    $product = Product::factory()->singleLut()->create([
        'price_cents' => EurMoney::parseDecimalToCents('19.99'),
    ]);

    expect($product->price_cents)->toBe(1999);
});

test('a free LUT requires zero cents', function () {
    $product = catalogPublishableProduct([
        'type' => ProductType::FreeLut,
        'price_cents' => 1999,
    ]);

    expectCatalogPublishFailure($product, 'price_cents');
});

test('a paid LUT requires more than zero cents', function () {
    $product = catalogPublishableProduct([
        'type' => ProductType::SingleLut,
        'price_cents' => 0,
    ]);

    expectCatalogPublishFailure($product, 'price_cents');
});

test('scopePublished includes an already published product', function () {
    $product = Product::factory()->published()->create([
        'published_at' => now()->subMinute(),
    ]);

    expect(Product::query()->published()->pluck('id'))->toContain($product->id);
});

test('scopePublished excludes drafts', function () {
    $product = Product::factory()->draft()->create();

    expect(Product::query()->published()->pluck('id'))->not->toContain($product->id);
});

test('scopePublished excludes archived products', function () {
    $product = Product::factory()->published()->create([
        'status' => ProductStatus::Archived,
        'published_at' => now()->subMinute(),
    ]);

    expect(Product::query()->published()->pluck('id'))->not->toContain($product->id);
});

test('scopePublished excludes future scheduled products', function () {
    $product = Product::factory()->published()->create([
        'published_at' => now()->addDay(),
    ]);

    expect(Product::query()->published()->pluck('id'))->not->toContain($product->id);
});

test('SetCurrentProductVersion leaves exactly one current version', function () {
    $product = Product::factory()->singleLut()->create();
    $oldVersion = ProductVersion::factory()->current()->for($product)->create();
    $newVersion = ProductVersion::factory()->ready()->for($product)->create();

    app(SetCurrentProductVersion::class)->handle($product, $newVersion);

    expect($oldVersion->refresh()->is_current)->toBeFalse()
        ->and($newVersion->refresh()->is_current)->toBeTrue()
        ->and($product->versions()->where('is_current', true)->count())->toBe(1);
});

test('PublishProduct rejects a product without a category', function () {
    $product = catalogPublishableProduct();
    $product->categories()->detach();

    expectCatalogPublishFailure($product->refresh(), 'categories');
});

test('PublishProduct rejects a product without a cover', function () {
    $product = catalogPublishableProduct();
    $product->media()->delete();

    expectCatalogPublishFailure($product->refresh(), 'cover');
});

test('PublishProduct rejects a product without an active example', function () {
    $product = catalogPublishableProduct();
    $product->examples()->update(['is_active' => false]);

    expectCatalogPublishFailure($product->refresh(), 'examples');
});

test('PublishProduct rejects a product without a current ready version', function () {
    $product = catalogPublishableProduct();
    $product->versions()->update([
        'is_current' => true,
        'status' => ProductVersionStatus::Draft,
    ]);

    expectCatalogPublishFailure($product->refresh(), 'current_version');
});

test('PublishProduct rejects a product without a ZIP package', function () {
    $product = catalogPublishableProduct();
    $product->currentVersion?->files()->where('kind', ProductFileKind::PackageZip)->delete();

    expectCatalogPublishFailure($product->refresh(), 'package_zip');
});

test('PublishProduct rejects an invalid price', function () {
    $product = catalogPublishableProduct([
        'type' => ProductType::Bundle,
        'price_cents' => 0,
    ]);

    expectCatalogPublishFailure($product, 'price_cents');
});

test('PublishProduct rejects an incomplete bundle', function () {
    $product = catalogPublishableProduct([
        'type' => ProductType::Bundle,
        'price_cents' => 4999,
    ]);

    expectCatalogPublishFailure($product, 'bundle_items');
});

test('PublishProduct successfully publishes a valid product', function () {
    $product = catalogPublishableProduct();

    app(PublishProduct::class)->handle($product, now()->addHour());

    expect($product->refresh()->status)->toBe(ProductStatus::Published)
        ->and($product->published_at)->not->toBeNull();
});

test('Private ProductFile paths are not available through a public route', function () {
    Storage::fake('private');

    $version = ProductVersion::factory()->create();
    $file = ProductFile::factory()
        ->packageZip()
        ->for($version, 'productVersion')
        ->create([
            'disk' => 'private',
            'path' => 'products/releases/private-package.zip',
        ]);

    Storage::disk('private')->put($file->path, 'zip-bytes');

    $this->get('/storage/'.$file->path)->assertNotFound();
});

test('Replacing or permanently deleting a ProductFile removes the obsolete physical file', function () {
    Storage::fake('private');

    Storage::disk('private')->put('products/old.zip', 'old');
    Storage::disk('private')->put('products/new.zip', 'new');

    $file = ProductFile::factory()->packageZip()->create([
        'disk' => 'private',
        'path' => 'products/old.zip',
    ]);

    $file->update(['path' => 'products/new.zip']);

    Storage::disk('private')->assertMissing('products/old.zip');
    Storage::disk('private')->assertExists('products/new.zip');

    $file->forceDelete();

    Storage::disk('private')->assertMissing('products/new.zip');
});

test('Product soft deletion does not delete its physical files', function () {
    Storage::fake('private');

    $product = catalogPublishableProduct();
    $file = $product->currentVersion?->files()->firstOrFail();

    Storage::disk('private')->put($file->path, 'zip-bytes');

    $product->delete();

    Storage::disk('private')->assertExists($file->path);
});
