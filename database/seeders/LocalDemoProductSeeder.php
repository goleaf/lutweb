<?php

namespace Database\Seeders;

use App\Enums\ProductFileKind;
use App\Enums\ProductMediaKind;
use App\Models\BundleItem;
use App\Models\Category;
use App\Models\CompatibleSoftware;
use App\Models\Product;
use App\Models\ProductExample;
use App\Models\ProductFile;
use App\Models\ProductMedia;
use App\Models\ProductVersion;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class LocalDemoProductSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->ensureLocalOrTesting();
        $this->call(CatalogSeeder::class);

        $admin = $this->demoUser(LocalDemoUserSeeder::AdminEmail);
        $single = $this->seedSingleProduct($admin);
        $free = $this->seedFreeProduct($admin);
        $bundle = $this->seedBundleProduct($admin);

        $this->attachReferenceData($single);
        $this->attachReferenceData($free);
        $this->attachReferenceData($bundle);
    }

    private function seedSingleProduct(User $admin): Product
    {
        $product = $this->product(
            slug: 'demo-cinematic-portrait',
            state: 'single',
            attributes: [
                'name' => 'Demo Cinematic Portrait LUT',
                'sku' => 'DEMO-LUT-001',
                'short_description' => 'A local demo single LUT with ready storefront media.',
                'description' => 'Local demo data for storefront, checkout and secure download workflows.',
                'price_cents' => 1999,
                'is_featured' => true,
                'is_testable' => true,
            ],
        );

        $version = $this->readyVersion($product, '1.0.0');
        $cube = $this->file($version, ProductFileKind::Cube33, 'products/demo/cinematic-portrait/cube-33.cube', 'cinematic-portrait.cube');
        $this->file($version, ProductFileKind::PackageZip, 'products/demo/cinematic-portrait/package.zip', 'cinematic-portrait.zip');
        $this->media($product, $admin, ProductMediaKind::Cover, 'Demo cover for Cinematic Portrait LUT', 0);
        $this->media($product, $admin, ProductMediaKind::Gallery, 'Demo gallery image for Cinematic Portrait LUT', 1);
        $this->example($product, $admin, $version, $cube, 'Demo Cinematic Portrait before and after');

        return $product;
    }

    private function seedFreeProduct(User $admin): Product
    {
        $product = $this->product(
            slug: 'demo-free-natural-lut',
            state: 'free',
            attributes: [
                'name' => 'Demo Free Natural LUT',
                'sku' => 'DEMO-LUT-FREE',
                'short_description' => 'A local demo free LUT with claimable catalog data.',
                'description' => 'Local demo data for free LUT entitlement workflows.',
                'is_featured' => false,
                'is_testable' => true,
            ],
        );

        $version = $this->readyVersion($product, '1.0.0');
        $cube = $this->file($version, ProductFileKind::Cube17, 'products/demo/free-natural/cube-17.cube', 'free-natural.cube');
        $this->file($version, ProductFileKind::PackageZip, 'products/demo/free-natural/package.zip', 'free-natural.zip');
        $this->media($product, $admin, ProductMediaKind::Cover, 'Demo cover for Free Natural LUT', 0);
        $this->example($product, $admin, $version, $cube, 'Demo Free Natural before and after');

        return $product;
    }

    private function seedBundleProduct(User $admin): Product
    {
        $bundle = $this->product(
            slug: 'demo-creator-bundle',
            state: 'bundle',
            attributes: [
                'name' => 'Demo Creator Bundle',
                'sku' => 'DEMO-BUNDLE-001',
                'short_description' => 'A local demo bundle linked to included LUT products.',
                'description' => 'Local demo bundle data for catalog and bundle preview workflows.',
                'price_cents' => 3999,
                'is_featured' => true,
            ],
        );

        $included = Product::query()
            ->whereIn('slug', ['demo-cinematic-portrait', 'demo-free-natural-lut'])
            ->get();

        foreach ($included as $sortOrder => $product) {
            if (! BundleItem::query()->where('bundle_id', $bundle->id)->where('product_id', $product->id)->exists()) {
                BundleItem::factory()->create([
                    'bundle_id' => $bundle->id,
                    'product_id' => $product->id,
                    'sort_order' => $sortOrder,
                ]);
            }
        }

        $previewProduct = Product::query()->where('slug', 'demo-cinematic-portrait')->firstOrFail();
        $previewVersion = $previewProduct->currentVersion()->firstOrFail();
        $previewCube = $previewVersion->files()->where('kind', ProductFileKind::Cube33->value)->first();
        $version = $this->readyVersion($bundle, '1.0.0');
        $this->file($version, ProductFileKind::PackageZip, 'products/demo/creator-bundle/package.zip', 'creator-bundle.zip');
        $this->media($bundle, $admin, ProductMediaKind::Cover, 'Demo cover for Creator Bundle', 0);
        $this->example($bundle, $admin, $previewVersion, $previewCube, 'Demo Creator Bundle preview', $previewProduct);

        return $bundle;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $slug, string $state, array $attributes): Product
    {
        $product = Product::query()->where('slug', $slug)->first();

        if ($product instanceof Product) {
            return $product;
        }

        $factory = Product::factory()->published();
        $factory = match ($state) {
            'free' => $factory->freeLut(),
            'bundle' => $factory->bundle(),
            default => $factory->singleLut(),
        };

        return $factory->create(array_merge($attributes, [
            'slug' => $slug,
            'meta_title' => $attributes['name'],
            'meta_description' => $attributes['short_description'],
        ]));
    }

    private function readyVersion(Product $product, string $versionLabel): ProductVersion
    {
        $version = ProductVersion::query()
            ->where('product_id', $product->id)
            ->where('version', $versionLabel)
            ->first();

        if (! $version instanceof ProductVersion) {
            $version = ProductVersion::factory()
                ->for($product)
                ->ready()
                ->current()
                ->create(['version' => $versionLabel]);
        }

        ProductVersion::query()
            ->where('product_id', $product->id)
            ->whereKeyNot($version->id)
            ->update(['is_current' => false]);

        return $version;
    }

    private function file(ProductVersion $version, ProductFileKind $kind, string $path, string $originalName): ProductFile
    {
        $file = ProductFile::query()
            ->where('product_version_id', $version->id)
            ->where('kind', $kind->value)
            ->first();

        if ($file instanceof ProductFile) {
            return $file;
        }

        $factory = match ($kind) {
            ProductFileKind::Cube33 => ProductFile::factory()->cube33(),
            ProductFileKind::Cube65 => ProductFile::factory()->cube65(),
            ProductFileKind::Cube17 => ProductFile::factory()->cube17(),
            ProductFileKind::SourceCube => ProductFile::factory()->sourceCube(),
            ProductFileKind::PackageZip => ProductFile::factory()->packageZip(),
            default => ProductFile::factory(),
        };

        return $factory->for($version)->create([
            'kind' => $kind,
            'path' => $path,
            'original_name' => $originalName,
            'sha256' => hash('sha256', $path),
        ]);
    }

    private function media(Product $product, User $admin, ProductMediaKind $kind, string $altText, int $sortOrder): ProductMedia
    {
        $media = ProductMedia::query()
            ->where('product_id', $product->id)
            ->where('kind', $kind->value)
            ->first();

        if ($media instanceof ProductMedia) {
            return $media;
        }

        $factory = $kind === ProductMediaKind::Cover
            ? ProductMedia::factory()->cover()
            : ProductMedia::factory();

        return $factory->for($product)->create([
            'alt_text' => $altText,
            'sort_order' => $sortOrder,
            'source_disk' => 'private',
            'source_path' => 'storefront-sources/'.$product->id.'/media/demo-source.png',
            'source_original_name' => 'demo-source.png',
            'source_mime_type' => 'image/png',
            'source_size_bytes' => 12_000,
            'source_width' => 1600,
            'source_height' => 1200,
            'source_sha256' => hash('sha256', 'media-'.$product->slug.'-'.$kind->value),
            'processing_fingerprint' => hash('sha256', 'media-fingerprint-'.$product->slug.'-'.$kind->value),
            'processed_at' => now(),
            'rights_confirmed_by' => $admin->id,
            'rights_note' => 'Local demo metadata only; replace before production use.',
        ]);
    }

    private function example(
        Product $product,
        User $admin,
        ProductVersion $version,
        ?ProductFile $file,
        string $title,
        ?Product $previewProduct = null,
    ): ProductExample {
        $example = ProductExample::query()
            ->where('product_id', $product->id)
            ->where('title', $title)
            ->first();

        if ($example instanceof ProductExample) {
            return $example;
        }

        return ProductExample::factory()->for($product)->active()->create([
            'title' => $title,
            'preview_product_id' => ($previewProduct ?? $product)->id,
            'processed_product_version_id' => $version->id,
            'processed_product_file_id' => $file?->id,
            'before_alt_text' => $title.' before LUT',
            'after_alt_text' => $title.' after LUT',
            'source_disk' => 'private',
            'source_path' => 'storefront-sources/'.$product->id.'/examples/demo-source.png',
            'source_original_name' => 'demo-example-source.png',
            'source_mime_type' => 'image/png',
            'source_size_bytes' => 16_000,
            'source_sha256' => hash('sha256', 'example-'.$product->slug),
            'processing_fingerprint' => hash('sha256', 'example-fingerprint-'.$product->slug),
            'processed_at' => now(),
            'rights_confirmed_by' => $admin->id,
            'rights_note' => 'Local demo metadata only; replace before production use.',
        ]);
    }

    private function attachReferenceData(Product $product): void
    {
        $category = Category::query()->orderBy('sort_order')->first();
        $tag = Tag::query()->orderBy('name')->first();
        $software = CompatibleSoftware::query()->orderBy('sort_order')->first();

        if ($category instanceof Category) {
            $product->categories()->syncWithoutDetaching([$category->id]);
        }

        if ($tag instanceof Tag) {
            $product->tags()->syncWithoutDetaching([$tag->id]);
        }

        if ($software instanceof CompatibleSoftware) {
            $product->compatibleSoftware()->syncWithoutDetaching([$software->id]);
        }
    }

    private function demoUser(string $email): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User) {
            return $user;
        }

        $this->call(LocalDemoUserSeeder::class);

        return User::query()
            ->where('email', $email)
            ->firstOrFail();
    }

    private function ensureLocalOrTesting(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Local demo product data may only be seeded in local or testing environments.');
        }
    }
}
