<?php

namespace App\Actions\Storefront;

use App\Actions\Catalog\SetCurrentProductVersion;
use App\Color\CubeSize;
use App\Enums\CustomLutBuildFileKind;
use App\Enums\ProductFileKind;
use App\Enums\ProductVersionStatus;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductVersion;
use App\Services\CustomLutBuilds\CreateCustomLutPackageZip;
use App\Services\CustomLutBuilds\LocalPackageFile;
use App\Services\CustomLutBuilds\PackageName;
use App\Services\CustomLutBuilds\PackageNameGenerator;
use App\Services\CustomLutBuilds\ValidateCubeWithFfmpeg;
use App\Services\CustomLutBuilds\ValidateGeneratedCube;
use App\Services\CustomLutBuilds\WriteCubeFile;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GenerateStorefrontPreviewPackage
{
    private const PACKAGE_SCHEMA = 'lut-web-storefront-customer-package-v2';

    /**
     * @var list<int>
     */
    private const CUBE_SIZES = [17, 33, 65];

    public function __construct(
        private readonly WriteCubeFile $writeCubeFile,
        private readonly ValidateGeneratedCube $validateGeneratedCube,
        private readonly ValidateCubeWithFfmpeg $validateCubeWithFfmpeg,
        private readonly CreateCustomLutPackageZip $createPackageZip,
        private readonly PackageNameGenerator $packageNameGenerator,
        private readonly SetCurrentProductVersion $setCurrentProductVersion,
    ) {}

    /**
     * @param  array{
     *     attributes: array{sku: string},
     *     parameters: LutTransformParameters
     * }  $entry
     */
    public function handle(Product $product, array $entry): ProductVersion
    {
        $this->assertConfiguration($product, $entry);

        $parameters = $entry['parameters'];
        $packageName = $this->packageNameGenerator->make($product->name, $product->sku);
        $fingerprint = $this->fingerprint($product, $parameters);
        $versionLabel = 'preview-'.substr($fingerprint, 0, 12);
        $prefix = 'products/storefront-preview/'.Str::lower($product->sku).'/'.$fingerprint;
        $definitions = $this->productFileDefinitions($prefix, $packageName);
        $existingVersion = ProductVersion::query()
            ->with('files')
            ->whereBelongsTo($product)
            ->where('version', $versionLabel)
            ->first();

        if ($existingVersion instanceof ProductVersion && $this->isComplete($existingVersion, $definitions)) {
            return $existingVersion;
        }

        if ($existingVersion instanceof ProductVersion
            && ($existingVersion->orderItems()->exists() || $existingVersion->entitlements()->exists())) {
            throw new RuntimeException('A purchased preview package version cannot be repaired in place.');
        }

        $workDirectory = storage_path(
            'app/private/'.trim((string) config('custom-lut-builds.work_prefix', 'custom-lut-build-work'), '/')
            .'/storefront-preview-package-'.$product->id.'-'.bin2hex(random_bytes(6)),
        );
        $createdStoragePaths = [];

        try {
            File::ensureDirectoryExists($workDirectory.'/CUBE');
            $packageFiles = $this->generatePackageFiles($workDirectory, $packageName, $product, $parameters, $fingerprint);
            $localProductFiles = $this->localProductFiles($packageFiles);

            foreach ($definitions as $kindValue => $definition) {
                $wasCreated = $this->storeAtomically(
                    $localProductFiles[$kindValue],
                    $definition['path'],
                );

                if ($wasCreated) {
                    $createdStoragePaths[] = $definition['path'];
                }
            }

            return DB::transaction(function () use ($product, $existingVersion, $versionLabel, $fingerprint, $definitions): ProductVersion {
                $version = $existingVersion ?? new ProductVersion;
                $version->forceFill([
                    'product_id' => $product->id,
                    'version' => $versionLabel,
                    'status' => ProductVersionStatus::Ready,
                    'is_current' => false,
                    'released_at' => $product->published_at ?? now(),
                    'notes' => 'Generated storefront customer package. Fingerprint: '.$fingerprint,
                ])->save();

                $expectedKinds = [];

                foreach ($definitions as $kindValue => $definition) {
                    $expectedKinds[] = $kindValue;

                    ProductFile::query()->updateOrCreate(
                        [
                            'product_version_id' => $version->id,
                            'kind' => $kindValue,
                        ],
                        [
                            'disk' => 'private',
                            'path' => $definition['path'],
                            'original_name' => $definition['original_name'],
                            'mime_type' => $definition['mime_type'],
                            'sort_order' => $definition['sort_order'],
                        ],
                    );
                }

                $version->files()
                    ->whereNotIn('kind', $expectedKinds)
                    ->get()
                    ->each
                    ->delete();

                return $this->setCurrentProductVersion->handle($product, $version)->load('files');
            });
        } catch (Throwable $exception) {
            foreach ($createdStoragePaths as $path) {
                Storage::disk('private')->delete($path);
            }

            throw $exception;
        } finally {
            if (is_dir($workDirectory) && ! is_link($workDirectory)) {
                File::deleteDirectory($workDirectory);
            }
        }
    }

    /**
     * @param  array{attributes: array{sku: string}, parameters: LutTransformParameters}  $entry
     */
    private function assertConfiguration(Product $product, array $entry): void
    {
        if (! $product->exists || $entry['attributes']['sku'] !== $product->sku) {
            throw new RuntimeException('The storefront preview package entry does not match the product.');
        }

        if ((string) config('custom-lut-builds.private_disk', 'private') !== 'private') {
            throw new RuntimeException('Storefront preview packages require the private filesystem disk.');
        }

        $cubeSizes = array_values(array_unique(array_map('intval', (array) config('custom-lut-builds.cube_sizes', []))));
        sort($cubeSizes);

        if ($cubeSizes !== self::CUBE_SIZES) {
            throw new RuntimeException('Storefront preview packages require CUBE sizes 17, 33, and 65.');
        }
    }

    private function fingerprint(Product $product, LutTransformParameters $parameters): string
    {
        return hash('sha256', json_encode([
            'schema' => self::PACKAGE_SCHEMA,
            'sku' => $product->sku,
            'slug' => $product->slug,
            'parameters_sha256' => $parameters->hash(),
            'transform_version' => config('custom-lut-builds.transform_version'),
            'generator_version' => config('custom-lut-builds.generator_version'),
            'cube_sizes' => self::CUBE_SIZES,
            'cube_precision' => config('custom-lut-builds.cube_precision'),
            'cube_domain_minimum' => config('custom-lut-builds.cube_domain_minimum'),
            'cube_domain_maximum' => config('custom-lut-builds.cube_domain_maximum'),
            'ffmpeg_interpolation' => config('custom-lut-builds.ffmpeg_interpolation'),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return list<LocalPackageFile>
     */
    private function generatePackageFiles(
        string $workDirectory,
        PackageName $packageName,
        Product $product,
        LutTransformParameters $parameters,
        string $fingerprint,
    ): array {
        $files = [];

        foreach (self::CUBE_SIZES as $index => $sizeValue) {
            $size = new CubeSize($sizeValue);
            $path = $workDirectory.'/CUBE/'.$packageName->cubeFilename($sizeValue);
            $this->writeCubeFile->handle($path, $size, $packageName, $parameters, $parameters->hash());
            $this->validateGeneratedCube->handle($path, $size, $parameters);

            if ($sizeValue === 33) {
                $this->validateCubeWithFfmpeg->handle($path, $workDirectory);
            }

            $files[] = new LocalPackageFile(
                kind: CustomLutBuildFileKind::from('cube_'.$sizeValue),
                localPath: $path,
                relativePackagePath: 'CUBE/'.$packageName->cubeFilename($sizeValue),
                safeDownloadName: $packageName->cubeFilename($sizeValue),
                mimeType: 'text/plain',
                sortOrder: ($index + 1) * 10,
            );
        }

        $readmePath = $workDirectory.'/README.txt';
        File::put($readmePath, $this->readme($packageName, $product, $parameters, $fingerprint));
        $files[] = new LocalPackageFile(
            CustomLutBuildFileKind::Readme,
            $readmePath,
            'README.txt',
            'README.txt',
            'text/plain',
            40,
        );

        $manifestPath = $workDirectory.'/manifest.json';
        File::put($manifestPath, $this->manifest($product, $parameters, $fingerprint, $files));
        $files[] = new LocalPackageFile(
            CustomLutBuildFileKind::Manifest,
            $manifestPath,
            'manifest.json',
            'manifest.json',
            'application/json',
            50,
        );

        $checksumsPath = $workDirectory.'/CHECKSUMS.txt';
        File::put($checksumsPath, $this->checksums($files));
        $files[] = new LocalPackageFile(
            CustomLutBuildFileKind::Checksums,
            $checksumsPath,
            'CHECKSUMS.txt',
            'CHECKSUMS.txt',
            'text/plain',
            60,
        );

        $zipPath = $workDirectory.'/'.$packageName->zipFilename();
        $this->createPackageZip->handle($zipPath, $files);
        $this->createPackageZip->validate($zipPath, $files);

        return [
            ...$files,
            new LocalPackageFile(
                CustomLutBuildFileKind::PackageZip,
                $zipPath,
                $packageName->zipFilename(),
                $packageName->zipFilename(),
                'application/zip',
                70,
            ),
        ];
    }

    /**
     * @param  list<LocalPackageFile>  $files
     */
    private function manifest(Product $product, LutTransformParameters $parameters, string $fingerprint, array $files): string
    {
        return json_encode([
            'schema' => self::PACKAGE_SCHEMA,
            'product' => [
                'sku' => $product->sku,
                'slug' => $product->slug,
                'name' => $product->name,
            ],
            'fingerprint' => $fingerprint,
            'parameters_sha256' => $parameters->hash(),
            'transform_version' => config('custom-lut-builds.transform_version'),
            'generator_version' => config('custom-lut-builds.generator_version'),
            'cube_sizes' => self::CUBE_SIZES,
            'license' => 'LUT Web License Agreement accepted at purchase',
            'files' => array_map(static fn (LocalPackageFile $file): array => [
                'path' => $file->relativePackagePath,
                'size_bytes' => $file->sizeBytes(),
                'sha256' => $file->sha256(),
            ], $files),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    private function readme(
        PackageName $packageName,
        Product $product,
        LutTransformParameters $parameters,
        string $fingerprint,
    ): string {
        return "LUT WEB LICENSED CUSTOMER PACKAGE\n\n"
            ."Product\n{$product->name}\n\n"
            ."SKU\n{$product->sku}\n\n"
            ."Release fingerprint\n{$fingerprint}\n\n"
            ."Parameters SHA-256\n{$parameters->hash()}\n\n"
            ."Included files\n"
            ."- CUBE/{$packageName->cubeFilename(17)}\n"
            ."- CUBE/{$packageName->cubeFilename(33)}\n"
            ."- CUBE/{$packageName->cubeFilename(65)}\n"
            ."- README.txt\n"
            ."- manifest.json\n"
            ."- CHECKSUMS.txt\n\n"
            ."Getting started\n"
            ."Use the 33-point CUBE first. Choose the 17-point file for lightweight compatibility or the 65-point file when your editing application supports higher sampling resolution. Import the selected file through your application's LUT or color-lookup controls, then adjust its mix to taste.\n\n"
            ."Color workflow\n"
            ."This is a display-referred creative RGB LUT, not a camera-specific Log conversion. Apply the correct technical color-space transform before this LUT when working with Log footage. Set exposure and white balance before the creative grade for the most predictable result.\n\n"
            ."License and support\n"
            ."This package is licensed under the LUT Web License Agreement accepted at purchase. Keep the original ZIP as your backup. Redistribution, resale, sharing, or publishing the LUT files is prohibited. Contact goleaf@gmail.com with the product name and release fingerprint for support.\n";
    }

    /**
     * @param  list<LocalPackageFile>  $files
     */
    private function checksums(array $files): string
    {
        return collect($files)
            ->map(fn (LocalPackageFile $file): string => $file->sha256().'  '.$file->relativePackagePath)
            ->implode("\n")."\n";
    }

    /**
     * @param  list<LocalPackageFile>  $files
     * @return array<string, string>
     */
    private function localProductFiles(array $files): array
    {
        return collect($files)
            ->filter(fn (LocalPackageFile $file): bool => in_array($file->kind, [
                CustomLutBuildFileKind::Cube17,
                CustomLutBuildFileKind::Cube33,
                CustomLutBuildFileKind::Cube65,
                CustomLutBuildFileKind::Readme,
                CustomLutBuildFileKind::PackageZip,
            ], true))
            ->mapWithKeys(fn (LocalPackageFile $file): array => [$file->kind->value => $file->localPath])
            ->all();
    }

    /**
     * @return array<string, array{path: string, original_name: string, mime_type: string, sort_order: int}>
     */
    private function productFileDefinitions(string $prefix, PackageName $packageName): array
    {
        return [
            ProductFileKind::Cube17->value => [
                'path' => $prefix.'/'.$packageName->cubeFilename(17),
                'original_name' => $packageName->cubeFilename(17),
                'mime_type' => 'text/plain',
                'sort_order' => 10,
            ],
            ProductFileKind::Cube33->value => [
                'path' => $prefix.'/'.$packageName->cubeFilename(33),
                'original_name' => $packageName->cubeFilename(33),
                'mime_type' => 'text/plain',
                'sort_order' => 20,
            ],
            ProductFileKind::Cube65->value => [
                'path' => $prefix.'/'.$packageName->cubeFilename(65),
                'original_name' => $packageName->cubeFilename(65),
                'mime_type' => 'text/plain',
                'sort_order' => 30,
            ],
            ProductFileKind::Readme->value => [
                'path' => $prefix.'/README.txt',
                'original_name' => 'README.txt',
                'mime_type' => 'text/plain',
                'sort_order' => 40,
            ],
            ProductFileKind::PackageZip->value => [
                'path' => $prefix.'/'.$packageName->zipFilename(),
                'original_name' => $packageName->zipFilename(),
                'mime_type' => 'application/zip',
                'sort_order' => 50,
            ],
        ];
    }

    /**
     * @param  array<string, array{path: string, original_name: string, mime_type: string, sort_order: int}>  $definitions
     */
    private function isComplete(ProductVersion $version, array $definitions): bool
    {
        if ($version->status !== ProductVersionStatus::Ready || ! $version->is_current || $version->files->count() !== count($definitions)) {
            return false;
        }

        $disk = Storage::disk('private');

        foreach ($definitions as $kindValue => $definition) {
            $file = $version->files->first(
                fn (ProductFile $file): bool => $file->kind->value === $kindValue,
            );

            if (! $file instanceof ProductFile
                || $file->disk !== 'private'
                || $file->path !== $definition['path']
                || ! $disk->exists($file->path)
                || $file->size_bytes !== $disk->size($file->path)
                || $file->sha256 === null
                || ! hash_equals($file->sha256, $this->storageSha256($disk, $file->path))) {
                return false;
            }
        }

        return true;
    }

    private function storeAtomically(string $localPath, string $targetPath): bool
    {
        if (! is_file($localPath) || is_link($localPath)) {
            throw new RuntimeException('A generated storefront preview package file is missing or unsafe.');
        }

        $disk = Storage::disk('private');
        $alreadyExisted = $disk->exists($targetPath);
        $temporaryPath = $targetPath.'.tmp-'.bin2hex(random_bytes(6));
        $source = fopen($localPath, 'rb');

        if ($source === false) {
            throw new RuntimeException('Unable to open a generated storefront preview package file.');
        }

        try {
            if (! $disk->put($temporaryPath, $source)) {
                throw new RuntimeException('Unable to stage a storefront preview package file.');
            }
        } finally {
            fclose($source);
        }

        try {
            $sourceSha256 = hash_file('sha256', $localPath);

            if ($sourceSha256 === false || ! hash_equals($sourceSha256, $this->storageSha256($disk, $temporaryPath))) {
                throw new RuntimeException('A staged storefront preview package file failed checksum validation.');
            }

            File::ensureDirectoryExists(dirname($disk->path($targetPath)));

            if (! rename($disk->path($temporaryPath), $disk->path($targetPath))) {
                throw new RuntimeException('Unable to finalize a storefront preview package file.');
            }
        } finally {
            $disk->delete($temporaryPath);
        }

        return ! $alreadyExisted;
    }

    private function storageSha256(FilesystemAdapter $disk, string $path): string
    {
        $stream = $disk->readStream($path);

        if ($stream === null) {
            throw new RuntimeException('Unable to read a storefront preview package file.');
        }

        $context = hash_init('sha256');

        try {
            hash_update_stream($context, $stream);
        } finally {
            fclose($stream);
        }

        return hash_final($context);
    }
}
