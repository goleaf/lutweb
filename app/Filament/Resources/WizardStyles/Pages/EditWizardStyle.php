<?php

namespace App\Filament\Resources\WizardStyles\Pages;

use App\Filament\Resources\WizardStyles\WizardStyleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditWizardStyle extends EditRecord
{
    protected static string $resource = WizardStyleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->authorize(true),
            RestoreAction::make()->authorize(true),
        ];
    }
}
