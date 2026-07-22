<?php

namespace App\Console\Commands;

use App\Enums\DigitalAssetKind;
use App\Enums\EntitlementStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\ProductFileKind;
use App\Enums\ProductMediaKind;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\ProductVersionStatus;
use App\Enums\StorefrontImageFormat;
use App\Enums\StorefrontImageStatus;
use App\Enums\StorefrontImageVariantRole;
use App\Enums\StorefrontMediaPipelineVersion;
use App\Models\Category;
use App\Models\CompatibleSoftware;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductExample;
use App\Models\ProductFile;
use App\Models\ProductMedia;
use App\Models\ProductVersion;
use App\Models\StorefrontImageVariant;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

#[Signature('e2e:prepare {--output= : Path under storage/framework/testing for the temporary JSON state file}')]
#[Description('Prepare randomized local/testing-only browser acceptance fixtures.')]
class E2ePrepare extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('The e2e fixture command may only run in local or testing environments.');

            return self::FAILURE;
        }

        if (! function_exists('imagejpeg') || ! function_exists('imagewebp')) {
            $this->error('GD JPEG and WebP support is required for E2E image fixtures.');

            return self::FAILURE;
        }

        if (! class_exists(ZipArchive::class)) {
            $this->error('ZipArchive support is required for E2E package fixtures.');

            return self::FAILURE;
        }

        $outputPath = $this->resolveOutputPath();
        $password = $this->temporaryPassword();
        $suffix = Str::lower((string) Str::ulid());

        $admin = $this->createUser('E2E Admin', 'e2e-admin-'.$suffix.'@example.test', $password, true);
        $customer = $this->createUser('E2E Customer', 'e2e-customer-'.$suffix.'@example.test', $password, false);
        $shopper = $this->createUser('E2E Shopper', 'e2e-shopper-'.$suffix.'@example.test', $password, false);
        $product = $this->createProductFixture($customer, $suffix);

        $state = [
            'base_url' => rtrim((string) config('app.url'), '/'),
            'users' => [
                'admin' => [
                    'email' => $admin->email,
                    'password' => $password,
                ],
                'customer' => [
                    'email' => $customer->email,
                    'password' => $password,
                ],
                'shopper' => [
                    'email' => $shopper->email,
                    'password' => $password,
                ],
            ],
            'product' => [
                'id' => $product['product']->id,
                'slug' => $product['product']->slug,
                'url' => route('shop.show', $product['product']->slug, absolute: false),
                'checkout_url' => route('checkout.show', $product['product']->slug, absolute: false),
                'try_url' => route('shop.tester.create', $product['product']->slug, absolute: false),
            ],
            'order' => [
                'id' => $product['order']->id,
                'number' => $product['order']->number,
                'url' => route('account.orders.show', $product['order'], absolute: false),
            ],
            'entitlement' => [
                'id' => $product['entitlement']->id,
                'download_url' => route('account.luts.download', $product['entitlement'], absolute: false),
            ],
            'admin_url' => '/admin',
        ];

        File::put($outputPath, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);

        $this->info('E2E fixtures prepared.');
        $this->line('State file: '.$outputPath);

        return self::SUCCESS;
    }

    private function resolveOutputPath(): string
    {
        $rawPath = $this->option('output') ?: storage_path('framework/testing/e2e-state.json');
        $path = str_starts_with((string) $rawPath, DIRECTORY_SEPARATOR)
            ? (string) $rawPath
            : base_path((string) $rawPath);

        $testingRoot = storage_path('framework/testing');
        File::ensureDirectoryExists(dirname($path));

        $resolvedDirectory = realpath(dirname($path));
        $resolvedTestingRoot = realpath($testingRoot);

        if (
            $resolvedDirectory === false
            || $resolvedTestingRoot === false
            || ! str_starts_with($resolvedDirectory.DIRECTORY_SEPARATOR, $resolvedTestingRoot.DIRECTORY_SEPARATOR)
        ) {
            throw new RuntimeException('E2E state output must be written under storage/framework/testing.');
        }

        return $path;
    }

    private function temporaryPassword(): string
    {
        return Str::random(24).'aA9!';
    }

    private function createUser(string $name, string $email, string $password, bool $isAdmin): User
    {
        return User::factory()
            ->verified()
            ->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_admin' => $isAdmin,
                'is_suspended' => false,
            ]);
    }

    /**
     * @return array{product: Product, order: Order, entitlement: Entitlement}
     */
    private function createProductFixture(User $customer, string $suffix): array
    {
        $product = Product::query()->create([
            'type' => ProductType::SingleLut,
            'status' => ProductStatus::Published,
            'name' => 'E2E Release Candidate LUT',
            'slug' => 'e2e-release-candidate-lut-'.$suffix,
            'sku' => 'E2E-LUT-'.Str::upper(Str::substr($suffix, 0, 10)),
            'short_description' => 'Synthetic product used for browser release-candidate smoke tests.',
            'description' => 'This generated fixture verifies storefront, checkout, account and secure-download flows without external services.',
            'price_cents' => 1999,
            'currency' => 'EUR',
            'is_featured' => true,
            'is_testable' => true,
            'published_at' => now(),
            'meta_title' => 'E2E Release Candidate LUT',
            'meta_description' => 'Synthetic browser acceptance product.',
        ]);

        $this->attachReferenceData($product);
        $version = $this->createVersionAndFiles($product, $suffix);
        $this->createMedia($product, $suffix);
        $this->createExample($product, $version, $suffix);

        $package = $version->files()->where('kind', ProductFileKind::PackageZip->value)->firstOrFail();
        $order = $this->createCompletedOrder($customer);
        $item = $this->createOrderItem($order, $product, $version, $package);
        $this->createCompletedPayment($order);
        $entitlement = $this->createEntitlement($customer, $order, $item, $product, $version, $package);

        return [
            'product' => $product,
            'order' => $order,
            'entitlement' => $entitlement,
        ];
    }

    private function attachReferenceData(Product $product): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'e2e'],
            ['name' => 'E2E', 'description' => null, 'is_active' => true, 'sort_order' => 999],
        );
        $tag = Tag::query()->firstOrCreate(['slug' => 'e2e'], ['name' => 'E2E']);
        $software = CompatibleSoftware::query()->firstOrCreate(
            ['slug' => 'e2e-compatible-app'],
            ['name' => 'E2E Compatible App', 'website_url' => null, 'is_active' => true, 'sort_order' => 999],
        );

        $product->categories()->syncWithoutDetaching([$category->id]);
        $product->tags()->syncWithoutDetaching([$tag->id]);
        $product->compatibleSoftware()->syncWithoutDetaching([$software->id]);
    }

    private function createVersionAndFiles(Product $product, string $suffix): ProductVersion
    {
        $version = ProductVersion::query()->create([
            'product_id' => $product->id,
            'version' => 'e2e-'.$suffix,
            'status' => ProductVersionStatus::Ready,
            'is_current' => true,
            'released_at' => now(),
            'notes' => 'Synthetic E2E fixture version.',
        ]);

        $packagePath = 'catalog/product-files/e2e/'.$product->id.'/'.$suffix.'.zip';
        $packageBytes = $this->zipBytes();
        Storage::disk('private')->put($packagePath, $packageBytes);

        ProductFile::query()->create([
            'product_version_id' => $version->id,
            'kind' => ProductFileKind::PackageZip,
            'disk' => 'private',
            'path' => $packagePath,
            'original_name' => 'e2e-release-candidate-lut.zip',
            'mime_type' => 'application/zip',
            'size_bytes' => strlen($packageBytes),
            'sha256' => hash('sha256', $packageBytes),
            'sort_order' => 0,
        ]);

        $cubePath = 'catalog/product-files/e2e/'.$product->id.'/'.$suffix.'-visible-33.cube';
        $cubeBytes = $this->cubeBytes(33, true);
        Storage::disk('private')->put($cubePath, $cubeBytes);

        ProductFile::query()->create([
            'product_version_id' => $version->id,
            'kind' => ProductFileKind::Cube33,
            'disk' => 'private',
            'path' => $cubePath,
            'original_name' => 'visible-33.cube',
            'mime_type' => 'text/plain',
            'size_bytes' => strlen($cubeBytes),
            'sha256' => hash('sha256', $cubeBytes),
            'sort_order' => 1,
        ]);

        File::put(storage_path('framework/testing/e2e-identity-'.$suffix.'.cube'), $this->cubeBytes(17, false));

        return $version;
    }

    private function createMedia(Product $product, string $suffix): void
    {
        $sourcePath = 'storefront-sources/e2e/'.$product->id.'/cover-source.png';
        Storage::disk('private')->put($sourcePath, $this->pngBytes(1200, 900, [36, 83, 72], 'Cover'));

        $media = ProductMedia::query()->create([
            'product_id' => $product->id,
            'kind' => ProductMediaKind::Cover,
            'disk' => 'public',
            'path' => '',
            'original_name' => 'e2e-cover.jpg',
            'alt_text' => 'Synthetic E2E LUT cover image',
            'width' => 768,
            'height' => 576,
            'sort_order' => 0,
            'source_disk' => 'private',
            'source_path' => $sourcePath,
            'source_original_name' => 'cover-source.png',
            'source_mime_type' => 'image/png',
            'source_size_bytes' => Storage::disk('private')->size($sourcePath),
            'source_width' => 1200,
            'source_height' => 900,
            'source_sha256' => hash('sha256', Storage::disk('private')->get($sourcePath)),
            'processing_status' => StorefrontImageStatus::Ready,
            'pipeline_version' => StorefrontMediaPipelineVersion::V1,
            'processing_fingerprint' => hash('sha256', 'e2e-cover-'.$suffix),
            'processed_at' => now(),
            'rights_confirmed_at' => now(),
            'source_credit_is_public' => false,
        ]);

        $fallback = $this->storeVariants($media, StorefrontImageVariantRole::Media, 'cover', [36, 83, 72], $suffix);
        $media->forceFill(['path' => $fallback])->save();
    }

    private function createExample(Product $product, ProductVersion $version, string $suffix): void
    {
        $sourcePath = 'storefront-sources/e2e/'.$product->id.'/example-source.png';
        Storage::disk('private')->put($sourcePath, $this->pngBytes(1200, 900, [68, 64, 60], 'Before'));

        $cube = $version->files()->where('kind', ProductFileKind::Cube33->value)->firstOrFail();
        $example = ProductExample::query()->create([
            'product_id' => $product->id,
            'title' => 'Synthetic before and after',
            'before_disk' => 'public',
            'before_path' => '',
            'before_original_name' => 'before.jpg',
            'before_alt_text' => 'Synthetic image before applying the E2E LUT',
            'after_disk' => 'public',
            'after_path' => '',
            'after_original_name' => 'after.jpg',
            'after_alt_text' => 'Synthetic image after applying the E2E LUT',
            'is_active' => true,
            'sort_order' => 0,
            'source_disk' => 'private',
            'source_path' => $sourcePath,
            'source_original_name' => 'example-source.png',
            'source_mime_type' => 'image/png',
            'source_size_bytes' => Storage::disk('private')->size($sourcePath),
            'source_width' => 1200,
            'source_height' => 900,
            'source_sha256' => hash('sha256', Storage::disk('private')->get($sourcePath)),
            'preview_product_id' => $product->id,
            'processed_product_version_id' => $version->id,
            'processed_product_file_id' => $cube->id,
            'processing_status' => StorefrontImageStatus::Ready,
            'pipeline_version' => StorefrontMediaPipelineVersion::V1,
            'processing_fingerprint' => hash('sha256', 'e2e-example-'.$suffix),
            'processed_at' => now(),
            'rights_confirmed_at' => now(),
            'source_credit_is_public' => false,
        ]);

        $before = $this->storeVariants($example, StorefrontImageVariantRole::Before, 'before', [68, 64, 60], $suffix);
        $after = $this->storeVariants($example, StorefrontImageVariantRole::After, 'after', [109, 82, 54], $suffix);
        $example->forceFill([
            'before_path' => $before,
            'after_path' => $after,
            'processing_status' => StorefrontImageStatus::Ready,
            'processed_at' => now(),
        ])->save();
    }

    private function createCompletedOrder(User $customer): Order
    {
        return Order::query()->create([
            'number' => 'ORD-E2E-'.Str::upper(Str::random(10)),
            'user_id' => $customer->id,
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Completed,
            'fulfillment_status' => FulfillmentStatus::Ready,
            'currency' => 'EUR',
            'subtotal_cents' => 1999,
            'tax_cents' => 0,
            'total_cents' => 1999,
            'checkout_idempotency_key' => (string) Str::uuid(),
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_country_code' => $customer->country_code,
            'terms_of_sale_accepted_at' => now(),
            'license_accepted_at' => now(),
            'digital_delivery_consent_at' => now(),
            'terms_of_sale_version' => config('legal.terms_of_sale_version'),
            'license_version' => config('legal.license_version'),
            'refund_policy_version' => config('legal.refund_policy_version'),
            'digital_delivery_consent_version' => config('legal.digital_delivery_consent_version'),
            'acceptance_ip_address' => '127.0.0.1',
            'acceptance_user_agent' => 'Playwright E2E',
            'paid_at' => now(),
            'fulfilled_at' => now(),
        ]);
    }

    private function createOrderItem(Order $order, Product $product, ProductVersion $version, ProductFile $package): OrderItem
    {
        return OrderItem::query()->create([
            'order_id' => $order->id,
            'digital_asset_kind' => DigitalAssetKind::CatalogProduct,
            'product_id' => $product->id,
            'product_version_id' => $version->id,
            'product_file_id' => $package->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'product_type' => $product->type->value,
            'product_sku' => $product->sku,
            'product_version' => $version->version,
            'unit_price_cents' => 1999,
            'quantity' => 1,
            'total_cents' => 1999,
        ]);
    }

    private function createCompletedPayment(Order $order): void
    {
        Payment::query()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::PayPal,
            'status' => PaymentStatus::Completed,
            'amount_cents' => 1999,
            'currency' => 'EUR',
            'paypal_order_id' => 'E2E-PAYPAL-ORDER-'.Str::upper(Str::random(12)),
            'paypal_capture_id' => 'E2E-CAPTURE-'.Str::upper(Str::random(12)),
            'create_request_id' => (string) Str::uuid(),
            'capture_request_id' => (string) Str::uuid(),
            'payer_country_code' => 'US',
            'payee_merchant_id' => 'E2E-MERCHANT',
            'refunded_amount_cents' => 0,
            'completed_at' => now(),
        ]);
    }

    private function createEntitlement(
        User $customer,
        Order $order,
        OrderItem $item,
        Product $product,
        ProductVersion $version,
        ProductFile $package,
    ): Entitlement {
        return Entitlement::query()->create([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'digital_asset_kind' => DigitalAssetKind::CatalogProduct,
            'product_id' => $product->id,
            'product_version_id' => $version->id,
            'product_file_id' => $package->id,
            'status' => EntitlementStatus::Active,
            'granted_at' => now(),
        ]);
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function storeVariants(Model $imageable, StorefrontImageVariantRole $role, string $name, array $rgb, string $suffix): string
    {
        $fallback = '';

        foreach ([480, 768] as $width) {
            $height = (int) round($width * 0.75);

            foreach (StorefrontImageFormat::cases() as $format) {
                $bytes = $format === StorefrontImageFormat::Webp
                    ? $this->webpBytes($width, $height, $rgb, $name)
                    : $this->jpegBytes($width, $height, $rgb, $name);
                $sha256 = hash('sha256', $bytes);
                $extension = $format === StorefrontImageFormat::Webp ? 'webp' : 'jpeg';
                $path = 'storefront/e2e/'.$suffix.'/'.$name.'-'.$width.'-'.$sha256.'.'.$extension;

                Storage::disk('public')->put($path, $bytes);

                StorefrontImageVariant::query()->create([
                    'imageable_type' => $imageable::class,
                    'imageable_id' => $imageable->getKey(),
                    'role' => $role,
                    'format' => $format,
                    'disk' => 'public',
                    'path' => $path,
                    'mime_type' => $format === StorefrontImageFormat::Webp ? 'image/webp' : 'image/jpeg',
                    'width' => $width,
                    'height' => $height,
                    'quality' => $format === StorefrontImageFormat::Webp ? 82 : 84,
                    'size_bytes' => strlen($bytes),
                    'sha256' => $sha256,
                    'generated_at' => now(),
                ]);

                if ($format === StorefrontImageFormat::Jpeg && $width === 768) {
                    $fallback = $path;
                }
            }
        }

        return $fallback;
    }

    private function zipBytes(): string
    {
        $path = tempnam(storage_path('framework/testing'), 'e2e-package-');

        if ($path === false) {
            throw new RuntimeException('Unable to create E2E package temporary file.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to open E2E package ZIP.');
        }

        $zip->addFromString('README.txt', "E2E synthetic LUT package.\nNo production content.\n");
        $zip->addFromString('manifest.json', json_encode(['schema' => 'e2e'], JSON_THROW_ON_ERROR)."\n");
        $zip->close();

        $bytes = File::get($path);
        File::delete($path);

        return $bytes;
    }

    private function cubeBytes(int $size, bool $visible): string
    {
        $lines = [
            '# Generated for E2E release-candidate smoke tests',
            'TITLE "e2e-test-lut"',
            'LUT_3D_SIZE '.$size,
            'DOMAIN_MIN 0.000000000 0.000000000 0.000000000',
            'DOMAIN_MAX 1.000000000 1.000000000 1.000000000',
            '',
        ];

        for ($blue = 0; $blue < $size; $blue++) {
            for ($green = 0; $green < $size; $green++) {
                for ($red = 0; $red < $size; $red++) {
                    $r = $red / ($size - 1);
                    $g = $green / ($size - 1);
                    $b = $blue / ($size - 1);

                    if ($visible) {
                        $r = min(1.0, $r + 0.04);
                        $g = min(1.0, $g + 0.01);
                        $b = max(0.0, $b - 0.03);
                    }

                    $lines[] = sprintf('%.9F %.9F %.9F', $r, $g, $b);
                }
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function pngBytes(int $width, int $height, array $rgb, string $label): string
    {
        return $this->imageBytes($width, $height, $rgb, $label, 'png');
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function jpegBytes(int $width, int $height, array $rgb, string $label): string
    {
        return $this->imageBytes($width, $height, $rgb, $label, 'jpeg');
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function webpBytes(int $width, int $height, array $rgb, string $label): string
    {
        return $this->imageBytes($width, $height, $rgb, $label, 'webp');
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function imageBytes(int $width, int $height, array $rgb, string $label, string $format): string
    {
        if ($width < 1 || $height < 1) {
            throw new RuntimeException('E2E image dimensions must be positive.');
        }

        if (
            $rgb[0] < 0 || $rgb[0] > 255
            || $rgb[1] < 0 || $rgb[1] > 255
            || $rgb[2] < 0 || $rgb[2] > 255
        ) {
            throw new RuntimeException('E2E image colors must use values from 0 to 255.');
        }

        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            throw new RuntimeException('Unable to allocate E2E image.');
        }

        $background = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
        $accent = imagecolorallocate($image, min(255, $rgb[0] + 48), min(255, $rgb[1] + 48), min(255, $rgb[2] + 48));
        $text = imagecolorallocate($image, 255, 255, 255);

        if ($background === false || $accent === false || $text === false) {
            throw new RuntimeException('Unable to allocate E2E image colors.');
        }

        imagefilledrectangle($image, 0, 0, $width, $height, $background);
        imagefilledrectangle($image, (int) ($width * 0.12), (int) ($height * 0.15), (int) ($width * 0.88), (int) ($height * 0.85), $accent);
        imagestring($image, 5, 24, 24, 'LUT Web '.$label, $text);

        ob_start();

        match ($format) {
            'jpeg' => imagejpeg($image, quality: 84),
            'webp' => imagewebp($image, quality: 82),
            default => imagepng($image),
        };

        $bytes = (string) ob_get_clean();

        if ($bytes === '') {
            throw new RuntimeException('Unable to encode E2E image.');
        }

        return $bytes;
    }
}
