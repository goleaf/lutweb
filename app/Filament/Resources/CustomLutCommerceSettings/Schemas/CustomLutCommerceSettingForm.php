<?php

namespace App\Filament\Resources\CustomLutCommerceSettings\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomLutCommerceSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Custom LUT Pricing')
                    ->schema([
                        Placeholder::make('launch_warning')
                            ->label('')
                            ->content('Live worldwide sales must remain disabled until the project\'s tax and legal launch requirements are complete.'),
                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_enabled')
                                    ->label('Custom LUT checkout enabled')
                                    ->required(),
                                TextInput::make('price')
                                    ->label('Price in EUR')
                                    ->helperText('Administrators enter a decimal EUR amount such as 19.99. The database stores integer cents.')
                                    ->required()
                                    ->regex('/^(0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/'),
                                TextInput::make('currency')
                                    ->label('Currency')
                                    ->default('EUR')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('price_cents')
                                    ->label('Stored integer cents')
                                    ->formatStateUsing(fn (mixed $state): string => (string) ((int) ($state ?? 0)))
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('version')
                                    ->label('Settings version')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('updatedBy.email')
                                    ->label('Last updated by')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
