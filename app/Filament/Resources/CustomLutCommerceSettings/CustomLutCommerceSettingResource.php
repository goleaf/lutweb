<?php

namespace App\Filament\Resources\CustomLutCommerceSettings;

use App\Filament\Resources\CustomLutCommerceSettings\Pages\EditCustomLutCommerceSetting;
use App\Filament\Resources\CustomLutCommerceSettings\Pages\ListCustomLutCommerceSettings;
use App\Filament\Resources\CustomLutCommerceSettings\Pages\ViewCustomLutCommerceSetting;
use App\Filament\Resources\CustomLutCommerceSettings\Schemas\CustomLutCommerceSettingForm;
use App\Filament\Resources\CustomLutCommerceSettings\Schemas\CustomLutCommerceSettingInfolist;
use App\Filament\Resources\CustomLutCommerceSettings\Tables\CustomLutCommerceSettingsTable;
use App\Models\CustomLutCommerceSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomLutCommerceSettingResource extends Resource
{
    protected static ?string $model = CustomLutCommerceSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyEuro;

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Custom LUT Pricing';

    protected static ?string $pluralModelLabel = 'Custom LUT Pricing';

    protected static ?string $recordTitleAttribute = 'scope';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return CustomLutCommerceSettingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomLutCommerceSettingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomLutCommerceSettingsTable::configure($table);
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
            'index' => ListCustomLutCommerceSettings::route('/'),
            'view' => ViewCustomLutCommerceSetting::route('/{record}'),
            'edit' => EditCustomLutCommerceSetting::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('updatedBy')
            ->where('scope', CustomLutCommerceSetting::Scope);
    }
}
