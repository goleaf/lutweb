<?php

namespace App\Filament\Resources\CustomLutCommerceSettings\Tables;

use App\Support\Catalog\EurMoney;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomLutCommerceSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('scope')
                    ->searchable(),
                IconColumn::make('is_enabled')
                    ->label('Checkout enabled')
                    ->boolean(),
                TextColumn::make('price_cents')
                    ->label('Price')
                    ->formatStateUsing(fn (int $state): string => 'EUR '.EurMoney::formatCents($state))
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('version')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updatedBy.email')
                    ->label('Last updated by')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Last updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
