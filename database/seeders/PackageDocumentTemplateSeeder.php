<?php

namespace Database\Seeders;

use App\Enums\PackageDocumentKind;
use App\Enums\PackageDocumentStatus;
use App\Models\PackageDocumentTemplate;
use Illuminate\Database\Seeder;

class PackageDocumentTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedDraft(
            PackageDocumentKind::License,
            'draft-license-v1',
            'Draft Custom LUT License',
            <<<'TEXT'
DRAFT PLACEHOLDER - NOT FOR PRODUCTION SALE

This document is a structural placeholder for the Custom LUT package license.
Final licensing text must be reviewed and approved before production launch.

Package: {{ package_name }}
License version: {{ document_version }}
TEXT,
        );

        $this->seedDraft(
            PackageDocumentKind::InstallationGuide,
            'draft-installation-guide-v1',
            'Draft Custom LUT Installation Guide',
            <<<'TEXT'
DRAFT PLACEHOLDER - REVIEW BEFORE PRODUCTION SALE

This Custom LUT package contains 17-point, 33-point, and 65-point CUBE files.
Try the 33-point CUBE first where supported. The 17-point CUBE may be useful for lightweight compatibility, and the 65-point CUBE may provide higher sampling resolution where the host application supports it.

This LUT is a display-referred RGB creative look. It is not a camera-specific Log conversion LUT. Log footage may require an appropriate technical color-space transform before applying the creative LUT.

General host applications include Adobe Photoshop, Adobe Premiere Pro, DaVinci Resolve, Final Cut Pro, and Affinity Photo. Exact rendering can vary slightly by host application and interpolation method.

The project Intensity setting is baked into the generated CUBE files. Keep the original ZIP as a backup. Redistribution is governed by the final License Agreement.
TEXT,
        );
    }

    private function seedDraft(PackageDocumentKind $kind, string $version, string $title, string $body): void
    {
        PackageDocumentTemplate::query()->updateOrCreate(
            [
                'kind' => $kind->value,
                'version' => $version,
            ],
            [
                'status' => PackageDocumentStatus::Draft,
                'title' => $title,
                'body' => $body,
                'is_current' => true,
                'activated_at' => null,
            ],
        );
    }
}
