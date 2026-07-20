<?php

namespace App\Filament\Resources\ProductVersions\Schemas;

use App\Enums\ProductVersionStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductVersionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Version')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TextInput::make('version')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('status')
                                    ->options(self::statusOptions())
                                    ->default(ProductVersionStatus::Draft->value)
                                    ->required(),
                                Toggle::make('is_current')
                                    ->disabled()
                                    ->saved(false),
                                DateTimePicker::make('released_at')
                                    ->seconds(false),
                            ]),
                        Textarea::make('notes')
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        return collect(ProductVersionStatus::cases())
            ->mapWithKeys(fn (ProductVersionStatus $status): array => [$status->value => $status->label()])
            ->all();
    }
}
