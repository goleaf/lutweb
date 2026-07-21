<?php

namespace App\Services\CustomLutBuilds;

use App\Enums\CustomLutBuildFileKind;
use App\Enums\CustomLutBuildStatus;
use App\Models\CustomLutBuild;
use App\Models\CustomLutBuildFile;
use App\Models\WizardProject;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomLutBuildPurchaseEligibility
{
    public function isSaleReady(CustomLutBuild $build): bool
    {
        $project = $build->wizardProject;

        if (
            $build->status !== CustomLutBuildStatus::Ready
            || ! $project instanceof WizardProject
            || ! $build->isCurrentFor($project)
            || $build->contains_draft_documents
            || ! $build->zip_validation_completed
            || ! $build->parity_validation_passed
            || ! $build->ffmpeg_validation_passed
            || $build->license_version === ''
            || $build->guide_version === ''
            || Str::startsWith($build->license_version, 'draft-')
            || Str::startsWith((string) $build->guide_version, 'draft-')
        ) {
            return false;
        }

        $requiredKinds = [
            CustomLutBuildFileKind::Cube17,
            CustomLutBuildFileKind::Cube33,
            CustomLutBuildFileKind::Cube65,
            CustomLutBuildFileKind::LicensePdf,
            CustomLutBuildFileKind::GuidePdf,
            CustomLutBuildFileKind::Readme,
            CustomLutBuildFileKind::Manifest,
            CustomLutBuildFileKind::Checksums,
            CustomLutBuildFileKind::PackageZip,
        ];

        foreach ($requiredKinds as $kind) {
            $file = $build->files->first(fn (CustomLutBuildFile $candidate): bool => $candidate->kind === $kind);

            if (! $file instanceof CustomLutBuildFile || ! $file->existsOnPrivateStorage() || blank($file->sha256)) {
                return false;
            }

            if ($kind === CustomLutBuildFileKind::LicensePdf || $kind === CustomLutBuildFileKind::GuidePdf) {
                $contents = Storage::disk($file->disk)->get($file->path);

                if (str_contains($contents, 'DRAFT - NOT FOR SALE')) {
                    return false;
                }
            }
        }

        return true;
    }
}
