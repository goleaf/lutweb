<?php

namespace App\Enums;

enum CustomLutBuildFileKind: string
{
    case Cube17 = 'cube_17';
    case Cube33 = 'cube_33';
    case Cube65 = 'cube_65';
    case LicensePdf = 'license_pdf';
    case GuidePdf = 'guide_pdf';
    case Readme = 'readme';
    case Manifest = 'manifest';
    case Checksums = 'checksums';
    case PackageZip = 'package_zip';

    public function label(): string
    {
        return match ($this) {
            self::Cube17 => '17-point CUBE',
            self::Cube33 => '33-point CUBE',
            self::Cube65 => '65-point CUBE',
            self::LicensePdf => 'License PDF',
            self::GuidePdf => 'Installation Guide PDF',
            self::Readme => 'README',
            self::Manifest => 'Manifest',
            self::Checksums => 'Checksums',
            self::PackageZip => 'Package ZIP',
        };
    }
}
