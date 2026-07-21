<?php

namespace App\Filament\Resources\CustomLutCommerceSettings\Pages;

use App\Filament\Resources\CustomLutCommerceSettings\CustomLutCommerceSettingResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomLutCommerceSettings extends ListRecords
{
    protected static string $resource = CustomLutCommerceSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
