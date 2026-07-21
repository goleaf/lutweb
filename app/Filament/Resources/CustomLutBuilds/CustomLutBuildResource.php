<?php

namespace App\Filament\Resources\CustomLutBuilds;

use App\Filament\Resources\CustomLutBuilds\Pages\ListCustomLutBuilds;
use App\Filament\Resources\CustomLutBuilds\Pages\ViewCustomLutBuild;
use App\Filament\Resources\CustomLutBuilds\Schemas\CustomLutBuildForm;
use App\Filament\Resources\CustomLutBuilds\Schemas\CustomLutBuildInfolist;
use App\Filament\Resources\CustomLutBuilds\Tables\CustomLutBuildsTable;
use App\Models\CustomLutBuild;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomLutBuildResource extends Resource
{
    protected static ?string $model = CustomLutBuild::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $modelLabel = 'Custom LUT Build';

    protected static ?string $pluralModelLabel = 'Custom LUT Builds';

    protected static ?int $navigationSort = 40;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return CustomLutBuildForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomLutBuildInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomLutBuildsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomLutBuilds::route('/'),
            'view' => ViewCustomLutBuild::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'wizardProject', 'packageFile'])
            ->withCount(['orderItems', 'entitlements']);
    }
}
