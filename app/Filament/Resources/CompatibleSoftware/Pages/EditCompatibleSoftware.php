<?php

namespace App\Filament\Resources\CompatibleSoftware\Pages;

use App\Filament\Resources\CompatibleSoftware\CompatibleSoftwareResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCompatibleSoftware extends EditRecord
{
    protected static string $resource = CompatibleSoftwareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
