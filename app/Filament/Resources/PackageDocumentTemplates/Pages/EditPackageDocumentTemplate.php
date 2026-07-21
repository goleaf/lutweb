<?php

namespace App\Filament\Resources\PackageDocumentTemplates\Pages;

use App\Filament\Resources\PackageDocumentTemplates\PackageDocumentTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPackageDocumentTemplate extends EditRecord
{
    protected static string $resource = PackageDocumentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->authorize(true),
            RestoreAction::make()->authorize(true),
        ];
    }
}
