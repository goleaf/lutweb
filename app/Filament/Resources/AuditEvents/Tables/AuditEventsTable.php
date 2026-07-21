<?php

namespace App\Filament\Resources\AuditEvents\Tables;

use App\Models\AuditEvent;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('action')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('actor.email')
                    ->label('Actor')
                    ->searchable(),
                TextColumn::make('targetUser.email')
                    ->label('Target user')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('auditable_type')
                    ->label('Auditable')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : 'None')
                    ->toggleable(),
                TextColumn::make('auditable_id')
                    ->label('Auditable ID')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('request_id')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->formatStateUsing(fn (?string $state): string => $state ? preg_replace('/\d+$/', '0', $state) ?? 'masked' : 'None')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options(fn (): array => AuditEvent::query()
                        ->select(['action'])
                        ->distinct()
                        ->orderBy('action')
                        ->pluck('action', 'action')
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
