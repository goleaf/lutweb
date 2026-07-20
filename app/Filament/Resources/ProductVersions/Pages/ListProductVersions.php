<?php

namespace App\Filament\Resources\ProductVersions\Pages;

use App\Filament\Resources\ProductVersions\ProductVersionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductVersions extends ListRecords
{
    protected static string $resource = ProductVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
