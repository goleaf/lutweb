<?php

namespace App\Filament\Resources\CustomLutCommerceSettings\Schemas;

use App\Models\CustomLutCommerceSetting;
use App\Support\Catalog\EurMoney;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomLutCommerceSettingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Custom LUT Pricing')
                    ->schema([
                        IconEntry::make('is_enabled')
                            ->label('Checkout enabled')
                            ->boolean(),
                        TextEntry::make('price_cents')
                            ->label('Price')
                            ->formatStateUsing(fn (int $state): string => 'EUR '.EurMoney::formatCents($state)),
                        TextEntry::make('stored_price_cents')
                            ->label('Stored integer cents')
                            ->state(fn (CustomLutCommerceSetting $record): int => $record->price_cents)
                            ->numeric(),
                        TextEntry::make('currency'),
                        TextEntry::make('version')
                            ->numeric(),
                        TextEntry::make('updatedBy.email')
                            ->label('Last updated by')
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->label('Last updated')
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(2),
            ]);
    }
}
