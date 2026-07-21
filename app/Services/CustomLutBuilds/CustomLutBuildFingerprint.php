<?php

namespace App\Services\CustomLutBuilds;

use App\Models\WizardProject;

class CustomLutBuildFingerprint
{
    public function make(WizardProject $project, PackageName $packageName, ResolvedPackageDocuments $documents): string
    {
        return hash('sha256', json_encode([
            'package_schema_version' => (string) config('custom-lut-builds.package_schema_version'),
            'transform_version' => $project->transform_version->value,
            'generator_version' => (string) config('custom-lut-builds.generator_version'),
            'package_name' => $project->name,
            'package_stem' => $packageName->stem,
            'parameters_sha256' => $project->parameters_hash,
            'cube_sizes' => array_values(array_map('intval', (array) config('custom-lut-builds.cube_sizes', [17, 33, 65]))),
            'cube_precision' => (int) config('custom-lut-builds.cube_precision', 9),
            'license' => [
                'kind' => $documents->license->kind->value,
                'id' => $documents->license->id,
                'version' => $documents->license->version,
                'content_sha256' => $documents->license->contentHash,
            ],
            'guide' => [
                'kind' => $documents->guide->kind->value,
                'id' => $documents->guide->id,
                'version' => $documents->guide->version,
                'content_sha256' => $documents->guide->contentHash,
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
