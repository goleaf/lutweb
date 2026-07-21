<?php

namespace App\Filament\Resources\WizardStyles\Tables;

use App\Enums\LutTransformVersion;
use App\Models\WizardStyle;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class WizardStylesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('transform_version')
                    ->badge()
                    ->formatStateUsing(fn (LutTransformVersion|string $state): string => $state instanceof LutTransformVersion ? $state->value : $state)
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TrashedFilter::make(),
                TernaryFilter::make('is_active')->label('Active'),
                TernaryFilter::make('is_featured')->label('Featured'),
            ])
            ->recordActions([
                EditAction::make()->authorize(true),
                Action::make('duplicate')
                    ->authorize(true)
                    ->action(function (WizardStyle $record): void {
                        $copy = $record->replicate();
                        $copy->name = $record->name.' Copy';
                        $copy->slug = self::uniqueSlug($record->slug.'-copy');
                        $copy->is_active = false;
                        $copy->is_featured = false;
                        $copy->save();

                        Notification::make()->title('Wizard style duplicated')->success()->send();
                    }),
                Action::make('activate')
                    ->authorize(true)
                    ->visible(fn (WizardStyle $record): bool => ! $record->is_active && ! $record->trashed())
                    ->action(function (WizardStyle $record): void {
                        $record->forceFill(['is_active' => true])->save();
                        Notification::make()->title('Wizard style activated')->success()->send();
                    }),
                Action::make('deactivate')
                    ->authorize(true)
                    ->requiresConfirmation()
                    ->visible(fn (WizardStyle $record): bool => $record->is_active && ! $record->trashed())
                    ->action(function (WizardStyle $record): void {
                        $record->forceFill(['is_active' => false, 'is_featured' => false])->save();
                        Notification::make()->title('Wizard style deactivated')->success()->send();
                    }),
                Action::make('feature')
                    ->authorize(true)
                    ->visible(fn (WizardStyle $record): bool => ! $record->is_featured && ! $record->trashed())
                    ->action(function (WizardStyle $record): void {
                        $record->forceFill(['is_featured' => true])->save();
                        Notification::make()->title('Wizard style featured')->success()->send();
                    }),
                DeleteAction::make()->authorize(true),
                RestoreAction::make()->authorize(true),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorize(true),
                    RestoreBulkAction::make()->authorize(true),
                ]),
            ]);
    }

    private static function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base);
        $candidate = $slug;
        $counter = 2;

        while (WizardStyle::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
