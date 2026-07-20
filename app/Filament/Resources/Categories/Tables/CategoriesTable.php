<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->sortable(),
                TextColumn::make('products_count')
                    ->label('Products')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->authorize(true),
                Action::make('delete')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(true)
                    ->action(function (Category $record): void {
                        if ($record->products()->exists()) {
                            Notification::make()
                                ->title('Category is attached to products')
                                ->body('Detach this category from products before deleting it.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->delete();
                    }),
            ]);
    }
}
