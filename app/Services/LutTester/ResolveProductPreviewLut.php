<?php

namespace App\Services\LutTester;

use App\Enums\ProductFileKind;
use App\Enums\ProductVersionStatus;
use App\Models\Product;
use App\Models\ProductFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ResolveProductPreviewLut
{
    public function __construct(
        private readonly InspectCubeFile $inspectCubeFile,
    ) {}

    public function resolve(Product $product): ResolvedPreviewLut
    {
        $product->loadMissing('currentVersion.files');
        $version = $product->currentVersion;

        if ($version === null || $version->status !== ProductVersionStatus::Ready) {
            throw new RuntimeException('The current LUT version is not ready for testing.');
        }

        foreach ($this->candidateKinds() as $kind) {
            $file = $version->files->first(
                fn (ProductFile $file): bool => $file->kind === $kind
                    && $file->disk === (string) config('lut-tester.disk', 'private'),
            );

            if (! $file instanceof ProductFile) {
                continue;
            }

            $disk = Storage::disk($file->disk);

            if (! $disk->exists($file->path)) {
                continue;
            }

            try {
                $inspection = $this->inspectCubeFile->inspect($file);
            } catch (RuntimeException) {
                continue;
            }

            return new ResolvedPreviewLut(
                version: $version,
                file: $file,
                absolutePath: $disk->path($file->path),
                inspection: $inspection,
            );
        }

        throw new RuntimeException('No supported private 3D CUBE file is available.');
    }

    /**
     * @return array<int, ProductFileKind>
     */
    private function candidateKinds(): array
    {
        return [
            ProductFileKind::Cube33,
            ProductFileKind::Cube65,
            ProductFileKind::Cube17,
            ProductFileKind::SourceCube,
        ];
    }
}
