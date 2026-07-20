<?php

namespace App\Enums;

enum ProductFileKind: string
{
    case SourceCube = 'source_cube';
    case Cube17 = 'cube_17';
    case Cube33 = 'cube_33';
    case Cube65 = 'cube_65';
    case PackageZip = 'package_zip';
    case LicensePdf = 'license_pdf';
    case GuidePdf = 'guide_pdf';
    case Readme = 'readme';

    public function label(): string
    {
        return match ($this) {
            self::SourceCube => 'Source CUBE',
            self::Cube17 => 'CUBE 17',
            self::Cube33 => 'CUBE 33',
            self::Cube65 => 'CUBE 65',
            self::PackageZip => 'Package ZIP',
            self::LicensePdf => 'License PDF',
            self::GuidePdf => 'Guide PDF',
            self::Readme => 'README',
        };
    }
}
