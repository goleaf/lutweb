<?php

namespace App\Filament\Resources\PackageDocumentTemplates\Pages;

use App\Filament\Resources\PackageDocumentTemplates\PackageDocumentTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPackageDocumentTemplates extends ListRecords
{
    protected static string $resource = PackageDocumentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->authorize(true),
        ];
    }
}
