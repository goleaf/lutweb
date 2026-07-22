<?php

use App\Actions\Storefront\GenerateStorefrontPreviewPackage;
use App\Enums\ProductFileKind;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVersionStatus;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductVersion;
use App\Models\User;
use App\Services\LutTester\ProductLutTestEligibility;
use App\Services\LutTester\ResolveProductPreviewLut;
use App\Support\Storefront\StorefrontPreviewCatalog;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function lutTesterIdentityCube(): string
{
    return <<<'CUBE'
TITLE "Identity"
LUT_3D_SIZE 2
0 0 0
0 0 1
0 1 0
0 1 1
1 0 0
1 0 1
1 1 0
1 1 1
CUBE;
}

function lutTesterProduct(array $productOverrides = []): Product
{
    return Product::factory()
        ->singleLut()
        ->published()
        ->testable()
        ->create([
            'name' => 'Previewable LUT',
            'slug' => 'previewable-lut-'.fake()->unique()->numberBetween(1000, 9999),
            'published_at' => now()->subHour(),
            ...$productOverrides,
        ]);
}

function lutTesterVersion(Product $product, array $overrides = []): ProductVersion
{
    return ProductVersion::factory()
        ->ready()
        ->current()
        ->for($product)
        ->create($overrides);
}

function lutTesterCubeFile(ProductVersion $version, ProductFileKind $kind = ProductFileKind::Cube33, array $overrides = []): ProductFile
{
    $path = $overrides['path'] ?? 'products/luts/'.fake()->uuid().'.cube';
    Storage::disk($overrides['disk'] ?? 'private')->put($path, lutTesterIdentityCube());

    return ProductFile::factory()
        ->for($version, 'productVersion')
        ->create([
            'kind' => $kind,
            'disk' => $overrides['disk'] ?? 'private',
            'path' => $path,
            'original_name' => 'identity.cube',
            'mime_type' => 'text/plain',
            ...$overrides,
        ]);
}

beforeEach(function (): void {
    Storage::fake('private');
    Storage::fake('public');
});

test('a published single LUT with a current ready version and valid Cube33 file may be testable', function () {
    $product = lutTesterProduct();
    $version = lutTesterVersion($product);
    lutTesterCubeFile($version, ProductFileKind::Cube33);

    expect(app(ProductLutTestEligibility::class)->canTest($product->refresh()))->toBeTrue();
});

test('a generated storefront release enables the tester route and product link', function () {
    $this->artisan('db:seed', [
        '--class' => 'Database\\Seeders\\StorefrontPreviewSeeder',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $product = Product::query()->where('sku', 'PREVIEW-TRAVEL-001')->firstOrFail();
    $entry = collect((new StorefrontPreviewCatalog)->entries())
        ->first(fn (array $entry): bool => $entry['attributes']['sku'] === $product->sku);

    expect($entry)->toBeArray();

    app(GenerateStorefrontPreviewPackage::class)->handle($product, $entry);
    $product->refresh();

    expect(app(ProductLutTestEligibility::class)->canTest($product))->toBeTrue();

    $user = User::factory()->verified()->create();

    $this->actingAs($user)
        ->get(route('shop.tester.create', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Try')
            ->where('product.slug', $product->slug)
            ->where('product.try_url', route('shop.tester.create', $product->slug)));

    $this->get(route('shop.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Show')
            ->where('product.try_url', route('shop.tester.create', $product->slug)));
});

test('is_testable false makes a product unavailable', function () {
    $product = lutTesterProduct(['is_testable' => false]);
    $version = lutTesterVersion($product);
    lutTesterCubeFile($version);

    expect(app(ProductLutTestEligibility::class)->canTest($product->refresh()))->toBeFalse();
});

test('draft archived future and soft deleted products cannot be tested', function (array $overrides, bool $delete = false) {
    $product = lutTesterProduct($overrides);
    $version = lutTesterVersion($product);
    lutTesterCubeFile($version);

    if ($delete) {
        $product->delete();
    }

    expect(app(ProductLutTestEligibility::class)->canTest($product->refresh()))->toBeFalse();
})->with([
    'draft' => [['status' => ProductStatus::Draft, 'published_at' => null], false],
    'archived' => [['status' => ProductStatus::Archived], false],
    'future scheduled' => [['published_at' => now()->addHour()], false],
    'soft deleted' => [[], true],
]);

test('a bundle cannot be testable', function () {
    $product = lutTesterProduct([
        'type' => ProductType::Bundle,
        'is_testable' => true,
    ]);

    expect($product->refresh()->is_testable)->toBeFalse()
        ->and(app(ProductLutTestEligibility::class)->canTest($product))->toBeFalse();
});

test('a product without a current ready version cannot be tested', function (?ProductVersionStatus $status) {
    $product = lutTesterProduct();

    if ($status instanceof ProductVersionStatus) {
        $version = lutTesterVersion($product, ['status' => $status]);
        lutTesterCubeFile($version);
    }

    expect(app(ProductLutTestEligibility::class)->canTest($product->refresh()))->toBeFalse();
})->with([
    'without current version' => [null],
    'with draft current version' => [ProductVersionStatus::Draft],
]);

test('a product without a supported CUBE file cannot be tested', function () {
    $product = lutTesterProduct();
    lutTesterVersion($product);

    expect(app(ProductLutTestEligibility::class)->canTest($product->refresh()))->toBeFalse();
});

test('Cube33 has selection priority over Cube65 and Cube17', function () {
    $product = lutTesterProduct();
    $version = lutTesterVersion($product);
    lutTesterCubeFile($version, ProductFileKind::Cube17);
    lutTesterCubeFile($version, ProductFileKind::Cube65);
    $cube33 = lutTesterCubeFile($version, ProductFileKind::Cube33);

    $resolved = app(ResolveProductPreviewLut::class)->resolve($product->refresh());

    expect($resolved->file->is($cube33))->toBeTrue();
});

test('Cube65 has priority over Cube17 when Cube33 is missing', function () {
    $product = lutTesterProduct();
    $version = lutTesterVersion($product);
    lutTesterCubeFile($version, ProductFileKind::Cube17);
    $cube65 = lutTesterCubeFile($version, ProductFileKind::Cube65);

    $resolved = app(ResolveProductPreviewLut::class)->resolve($product->refresh());

    expect($resolved->file->is($cube65))->toBeTrue();
});

test('SourceCube is used only when it passes 3D inspection', function () {
    $product = lutTesterProduct();
    $version = lutTesterVersion($product);
    $source = lutTesterCubeFile($version, ProductFileKind::SourceCube);

    $resolved = app(ResolveProductPreviewLut::class)->resolve($product->refresh());

    expect($resolved->file->is($source))->toBeTrue()
        ->and($resolved->inspection->size)->toBe(2);
});

test('a ProductFile on a non-private disk is rejected', function () {
    Storage::disk('public')->put('products/luts/public.cube', lutTesterIdentityCube());
    $product = lutTesterProduct();
    $version = lutTesterVersion($product);

    ProductFile::withoutEvents(fn () => ProductFile::factory()
        ->for($version, 'productVersion')
        ->create([
            'kind' => ProductFileKind::Cube33,
            'disk' => 'public',
            'path' => 'products/luts/public.cube',
            'original_name' => 'public.cube',
        ]));

    expect(app(ProductLutTestEligibility::class)->canTest($product->refresh()))->toBeFalse();
});
