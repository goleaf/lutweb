<?php

namespace App\Filament\Resources\PackageDocumentTemplates;

use App\Filament\Resources\PackageDocumentTemplates\Pages\CreatePackageDocumentTemplate;
use App\Filament\Resources\PackageDocumentTemplates\Pages\EditPackageDocumentTemplate;
use App\Filament\Resources\PackageDocumentTemplates\Pages\ListPackageDocumentTemplates;
use App\Filament\Resources\PackageDocumentTemplates\Schemas\PackageDocumentTemplateForm;
use App\Filament\Resources\PackageDocumentTemplates\Tables\PackageDocumentTemplatesTable;
use App\Models\PackageDocumentTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PackageDocumentTemplateResource extends Resource
{
    protected static ?string $model = PackageDocumentTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 60;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PackageDocumentTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PackageDocumentTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPackageDocumentTemplates::route('/'),
            'create' => CreatePackageDocumentTemplate::route('/create'),
            'edit' => EditPackageDocumentTemplate::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
