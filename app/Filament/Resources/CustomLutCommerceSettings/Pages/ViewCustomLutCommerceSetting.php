<?php

namespace App\Filament\Resources\CustomLutCommerceSettings\Pages;

use App\Filament\Resources\CustomLutCommerceSettings\CustomLutCommerceSettingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomLutCommerceSetting extends ViewRecord
{
    protected static string $resource = CustomLutCommerceSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
