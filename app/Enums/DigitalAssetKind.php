<?php

namespace App\Enums;

enum DigitalAssetKind: string
{
    case CatalogProduct = 'catalog_product';
    case CustomLutBuild = 'custom_lut_build';

    public function label(): string
    {
        return match ($this) {
            self::CatalogProduct => 'Catalog LUT',
            self::CustomLutBuild => 'Custom LUT',
        };
    }
}
