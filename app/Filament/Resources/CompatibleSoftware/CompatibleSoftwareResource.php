<?php

namespace App\Filament\Resources\CompatibleSoftware;

use App\Filament\Resources\CompatibleSoftware\Pages\CreateCompatibleSoftware;
use App\Filament\Resources\CompatibleSoftware\Pages\EditCompatibleSoftware;
use App\Filament\Resources\CompatibleSoftware\Pages\ListCompatibleSoftware;
use App\Filament\Resources\CompatibleSoftware\Schemas\CompatibleSoftwareForm;
use App\Filament\Resources\CompatibleSoftware\Tables\CompatibleSoftwareTable;
use App\Models\CompatibleSoftware;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CompatibleSoftwareResource extends Resource
{
    protected static ?string $model = CompatibleSoftware::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CompatibleSoftwareForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompatibleSoftwareTable::configure($table);
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
            'index' => ListCompatibleSoftware::route('/'),
            'create' => CreateCompatibleSoftware::route('/create'),
            'edit' => EditCompatibleSoftware::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('products');
    }
}
