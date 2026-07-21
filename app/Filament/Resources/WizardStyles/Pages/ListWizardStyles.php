<?php

namespace App\Filament\Resources\WizardStyles\Pages;

use App\Filament\Resources\WizardStyles\WizardStyleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWizardStyles extends ListRecords
{
    protected static string $resource = WizardStyleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->authorize(true),
        ];
    }
}
