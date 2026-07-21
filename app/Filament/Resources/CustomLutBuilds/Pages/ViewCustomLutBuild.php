<?php

namespace App\Filament\Resources\CustomLutBuilds\Pages;

use App\Filament\Resources\CustomLutBuilds\CustomLutBuildResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomLutBuild extends ViewRecord
{
    protected static string $resource = CustomLutBuildResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
