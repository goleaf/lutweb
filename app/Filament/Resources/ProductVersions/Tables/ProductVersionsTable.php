<?php

namespace App\Filament\Resources\ProductVersions\Tables;

use App\Actions\Catalog\SetCurrentProductVersion;
use App\Models\ProductVersion;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('version')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state)
                    ->sortable(),
                IconColumn::make('is_current')
                    ->label('Current')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('released_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->authorize(true),
                Action::make('markCurrent')
                    ->label('Mark current')
                    ->authorize(true)
                    ->visible(fn (ProductVersion $record): bool => ! $record->is_current)
                    ->action(function (ProductVersion $record): void {
                        app(SetCurrentProductVersion::class)->handle($record->product, $record);
                        Notification::make()->title('Current version updated')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorize(true),
                ]),
            ]);
    }
}
