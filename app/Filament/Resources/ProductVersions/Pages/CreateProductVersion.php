<?php

namespace App\Filament\Resources\ProductVersions\Pages;

use App\Filament\Resources\ProductVersions\ProductVersionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductVersion extends CreateRecord
{
    protected static string $resource = ProductVersionResource::class;
}
