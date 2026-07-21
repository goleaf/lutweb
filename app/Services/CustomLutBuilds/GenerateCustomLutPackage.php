<?php

namespace App\Services\CustomLutBuilds;

use App\Color\CubeSize;
use App\Enums\CustomLutBuildFileKind;
use App\Models\CustomLutBuild;
use App\ValueObjects\LutTransformParameters;
use Illuminate\Support\Facades\File;
use RuntimeException;

class GenerateCustomLutPackage
{
    public function __construct(
        private readonly WriteCubeFile $cubeWriter,
        private readonly ValidateGeneratedCube $cubeValidator,
        private readonly MeasurePreviewParity $parity,
        private readonly ValidateCubeWithFfmpeg $ffmpeg,
        private readonly GeneratePackagePdf $pdf,
        private readonly GeneratePackageTextFiles $textFiles,
        private readonly CreateCustomLutPackageZip $zip,
    ) {}

    public function handle(CustomLutBuild $build, string $workDir): GeneratedCustomLutPackage
    {
        $parameters = LutTransformParameters::fromArray($build->parameters ?? []);
        $packageName = new PackageName($build->project_name_snapshot, $build->package_stem);
        $documents = new ResolvedPackageDocuments(
            license: PackageDocumentSnapshot::fromArray($build->license_document_snapshot ?? []),
            guide: PackageDocumentSnapshot::fromArray($build->guide_document_snapshot ?? []),
        );
        $generatedAtUtc = now('UTC')->toISOString();
        $root = $workDir.'/'.$packageName->stem;
        $cubeDir = $root.'/CUBE';
        $licenseDir = $root.'/LICENSE';
        $guideDir = $root.'/GUIDE';

        File::ensureDirectoryExists($cubeDir);
        File::ensureDirectoryExists($licenseDir);
        File::ensureDirectoryExists($guideDir);

        $files = [];
        $sortOrder = 10;

        foreach ($this->cubeSizes() as $sizeValue) {
            $size = new CubeSize($sizeValue);
            $kind = CustomLutBuildFileKind::from('cube_'.$sizeValue);
            $path = $cubeDir.'/'.$packageName->cubeFilename($sizeValue);
            $relativePath = $packageName->stem.'/CUBE/'.$packageName->cubeFilename($sizeValue);

            $this->cubeWriter->handle($path, $size, $packageName, $parameters, $build->parameters_hash);
            $this->cubeValidator->handle($path, $size, $parameters);
            $this->ffmpeg->handle($path, $workDir);

            $files[] = new LocalPackageFile(
                kind: $kind,
                localPath: $path,
                relativePackagePath: $relativePath,
                safeDownloadName: $packageName->cubeFilename($sizeValue),
                mimeType: $this->textFiles->mimeFor($kind),
                sortOrder: $sortOrder,
            );
            $sortOrder += 10;
        }

        $parityMetrics = $this->parity->handle($parameters);

        $licensePath = $licenseDir.'/license.pdf';
        $this->pdf->handle($licensePath, $documents->license, $packageName, $build->parameters_hash, $generatedAtUtc);
        $files[] = new LocalPackageFile(CustomLutBuildFileKind::LicensePdf, $licensePath, $packageName->stem.'/LICENSE/license.pdf', 'license.pdf', 'application/pdf', 40);

        $guidePath = $guideDir.'/installation-guide.pdf';
        $this->pdf->handle($guidePath, $documents->guide, $packageName, $build->parameters_hash, $generatedAtUtc);
        $files[] = new LocalPackageFile(CustomLutBuildFileKind::GuidePdf, $guidePath, $packageName->stem.'/GUIDE/installation-guide.pdf', 'installation-guide.pdf', 'application/pdf', 50);

        $readmePath = $root.'/README.txt';
        $this->textFiles->readme($readmePath, $packageName, $build->parameters_hash, $generatedAtUtc, $documents->containsDraftDocuments());
        $files[] = new LocalPackageFile(CustomLutBuildFileKind::Readme, $readmePath, $packageName->stem.'/README.txt', 'README.txt', $this->textFiles->mimeFor(CustomLutBuildFileKind::Readme), 60);

        $manifestPath = $root.'/manifest.json';
        $this->textFiles->manifest($manifestPath, $packageName, $build->id, $build->parameters_hash, $generatedAtUtc, $documents, $files);
        $files[] = new LocalPackageFile(CustomLutBuildFileKind::Manifest, $manifestPath, $packageName->stem.'/manifest.json', 'manifest.json', 'application/json', 70);

        $checksumsPath = $root.'/CHECKSUMS.txt';
        $this->textFiles->checksums($checksumsPath, $files);
        $files[] = new LocalPackageFile(CustomLutBuildFileKind::Checksums, $checksumsPath, $packageName->stem.'/CHECKSUMS.txt', 'CHECKSUMS.txt', $this->textFiles->mimeFor(CustomLutBuildFileKind::Checksums), 80);

        $zipPath = $workDir.'/'.$packageName->zipFilename();
        $this->zip->handle($zipPath, $files);
        $uncompressedSize = $this->zip->validate($zipPath, $files);
        $zipFile = new LocalPackageFile(
            kind: CustomLutBuildFileKind::PackageZip,
            localPath: $zipPath,
            relativePackagePath: $packageName->zipFilename(),
            safeDownloadName: $packageName->zipFilename(),
            mimeType: 'application/zip',
            sortOrder: 90,
        );

        if ($zipFile->sizeBytes() > (int) config('custom-lut-builds.maximum_package_size_bytes', 104_857_600)) {
            throw new RuntimeException('Generated ZIP package exceeds the configured size limit.');
        }

        return new GeneratedCustomLutPackage(
            files: [...$files, $zipFile],
            zip: $zipFile,
            uncompressedSizeBytes: $uncompressedSize,
            parityMetrics: $parityMetrics,
            documents: $documents,
        );
    }

    /**
     * @return list<int>
     */
    private function cubeSizes(): array
    {
        $sizes = array_values(array_unique(array_map('intval', (array) config('custom-lut-builds.cube_sizes', [17, 33, 65]))));
        sort($sizes);

        if ($sizes !== [17, 33, 65]) {
            throw new RuntimeException('Custom LUT packages require CUBE sizes 17, 33, and 65.');
        }

        return $sizes;
    }
}
