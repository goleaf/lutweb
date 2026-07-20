<?php

namespace App\Filament\Resources\ProductVersions;

use App\Filament\Resources\ProductVersions\Pages\CreateProductVersion;
use App\Filament\Resources\ProductVersions\Pages\EditProductVersion;
use App\Filament\Resources\ProductVersions\Pages\ListProductVersions;
use App\Filament\Resources\ProductVersions\RelationManagers\FilesRelationManager;
use App\Filament\Resources\ProductVersions\Schemas\ProductVersionForm;
use App\Filament\Resources\ProductVersions\Tables\ProductVersionsTable;
use App\Models\ProductVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductVersionResource extends Resource
{
    protected static ?string $model = ProductVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'version';

    public static function form(Schema $schema): Schema
    {
        return ProductVersionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductVersionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            FilesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductVersions::route('/'),
            'create' => CreateProductVersion::route('/create'),
            'edit' => EditProductVersion::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('product');
    }
}
