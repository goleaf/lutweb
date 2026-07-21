<?php

namespace App\Services\CustomLutBuilds;

use App\Enums\CustomLutBuildFileKind;
use RuntimeException;

class GeneratePackageTextFiles
{
    public function readme(string $path, PackageName $packageName, string $parametersHash, string $generatedAtUtc, bool $containsDraftDocuments): void
    {
        $draft = $containsDraftDocuments
            ? "DRAFT WARNING\nThis package contains draft placeholder documents and is not ready for production sale.\n\n"
            : '';

        $content = $draft
            ."Package name\n{$packageName->displayName}\n\n"
            ."Generated UTC\n{$generatedAtUtc}\n\n"
            .'Transform version'."\n".config('custom-lut-builds.transform_version')."\n\n"
            .'Generator version'."\n".config('custom-lut-builds.generator_version')."\n\n"
            ."Parameters SHA-256\n{$parametersHash}\n\n"
            ."Package contents\n- CUBE/{$packageName->cubeFilename(17)}\n- CUBE/{$packageName->cubeFilename(33)}\n- CUBE/{$packageName->cubeFilename(65)}\n- LICENSE/license.pdf\n- GUIDE/installation-guide.pdf\n- README.txt\n- manifest.json\n- CHECKSUMS.txt\n\n"
            ."Which CUBE size to try first\nUse the 33-point CUBE first where supported. The 17-point CUBE is useful for lightweight compatibility, and the 65-point CUBE can provide higher sampling resolution in host applications that support it.\n\n"
            ."Compatibility note\nThis LUT is a display-referred RGB creative look, not a camera-specific Log conversion LUT. Log footage may need a technical color-space transform before this creative LUT. Exact rendering can vary slightly by host application and interpolation method.\n\n"
            ."Support note\nKeep the original ZIP as a backup and contact support with the package name and shortened parameter hash if help is needed.\n\n"
            ."License reference\nRedistribution and usage are governed by LICENSE/license.pdf.\n";

        $this->writeUtf8($path, $content);
    }

    /**
     * @param  list<LocalPackageFile>  $files
     */
    public function manifest(
        string $path,
        PackageName $packageName,
        string $buildId,
        string $parametersHash,
        string $generatedAtUtc,
        ResolvedPackageDocuments $documents,
        array $files,
    ): void {
        $fileItems = array_map(static fn (LocalPackageFile $file): array => [
            'relative_path' => $file->relativePackagePath,
            'kind' => $file->kind->value,
            'size_bytes' => $file->sizeBytes(),
            'sha256' => $file->sha256(),
        ], $files);

        $content = json_encode([
            'schema_version' => (string) config('custom-lut-builds.package_schema_version'),
            'package_name' => $packageName->displayName,
            'package_stem' => $packageName->stem,
            'build_id' => $buildId,
            'transform_version' => (string) config('custom-lut-builds.transform_version'),
            'generator_version' => (string) config('custom-lut-builds.generator_version'),
            'parameters_sha256' => $parametersHash,
            'generated_at' => $generatedAtUtc,
            'cube_sizes' => array_values(array_map('intval', (array) config('custom-lut-builds.cube_sizes', [17, 33, 65]))),
            'license_version' => $documents->license->version,
            'guide_version' => $documents->guide->version,
            'contains_draft_documents' => $documents->containsDraftDocuments(),
            'files' => $fileItems,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n";

        $this->writeUtf8($path, $content);
    }

    /**
     * @param  list<LocalPackageFile>  $files
     */
    public function checksums(string $path, array $files): void
    {
        $content = '';

        foreach ($files as $file) {
            if (! preg_match('/^[a-f0-9]{64}$/', $file->sha256())) {
                throw new RuntimeException('Generated file hash is invalid.');
            }

            if (str_contains($file->relativePackagePath, '..') || str_contains($file->relativePackagePath, '\\') || str_starts_with($file->relativePackagePath, '/')) {
                throw new RuntimeException('Generated checksum path is unsafe.');
            }

            $content .= $file->sha256().'  '.$file->relativePackagePath."\n";
        }

        $this->writeUtf8($path, $content);
    }

    private function writeUtf8(string $path, string $content): void
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            throw new RuntimeException('Generated text must not contain a UTF-8 BOM.');
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $content);

        if (file_put_contents($path, $normalized) === false) {
            throw new RuntimeException('Unable to write generated package text file.');
        }
    }

    public function mimeFor(CustomLutBuildFileKind $kind): string
    {
        return match ($kind) {
            CustomLutBuildFileKind::Cube17,
            CustomLutBuildFileKind::Cube33,
            CustomLutBuildFileKind::Cube65,
            CustomLutBuildFileKind::Readme,
            CustomLutBuildFileKind::Checksums => 'text/plain; charset=UTF-8',
            CustomLutBuildFileKind::Manifest => 'application/json',
            CustomLutBuildFileKind::LicensePdf,
            CustomLutBuildFileKind::GuidePdf => 'application/pdf',
            CustomLutBuildFileKind::PackageZip => 'application/zip',
        };
    }
}
