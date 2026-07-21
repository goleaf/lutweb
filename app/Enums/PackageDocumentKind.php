<?php

namespace App\Enums;

enum PackageDocumentKind: string
{
    case License = 'license';
    case InstallationGuide = 'installation_guide';

    public function label(): string
    {
        return match ($this) {
            self::License => 'License',
            self::InstallationGuide => 'Installation Guide',
        };
    }
}
