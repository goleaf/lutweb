<?php

namespace App\Filament\Resources\CustomLutBuilds\Pages;

use App\Filament\Resources\CustomLutBuilds\CustomLutBuildResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomLutBuilds extends ListRecords
{
    protected static string $resource = CustomLutBuildResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
