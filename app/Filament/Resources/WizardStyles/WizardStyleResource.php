<?php

namespace App\Filament\Resources\WizardStyles;

use App\Filament\Resources\WizardStyles\Pages\CreateWizardStyle;
use App\Filament\Resources\WizardStyles\Pages\EditWizardStyle;
use App\Filament\Resources\WizardStyles\Pages\ListWizardStyles;
use App\Filament\Resources\WizardStyles\Schemas\WizardStyleForm;
use App\Filament\Resources\WizardStyles\Tables\WizardStylesTable;
use App\Models\WizardStyle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WizardStyleResource extends Resource
{
    protected static ?string $model = WizardStyle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'LUT Wizard';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return WizardStyleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WizardStylesTable::configure($table);
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
            'index' => ListWizardStyles::route('/'),
            'create' => CreateWizardStyle::route('/create'),
            'edit' => EditWizardStyle::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
