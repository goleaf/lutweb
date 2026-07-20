<?php

namespace App\Filament\Resources\CompatibleSoftware\Pages;

use App\Filament\Resources\CompatibleSoftware\CompatibleSoftwareResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCompatibleSoftware extends ListRecords
{
    protected static string $resource = CompatibleSoftwareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
