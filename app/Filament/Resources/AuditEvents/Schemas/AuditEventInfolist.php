<?php

namespace App\Filament\Resources\AuditEvents\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuditEventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Audit event')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('occurred_at')->dateTime(),
                                TextEntry::make('action'),
                                TextEntry::make('actor.email')->label('Actor'),
                                TextEntry::make('targetUser.email')->label('Target user'),
                                TextEntry::make('auditable_type')->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : 'None'),
                                TextEntry::make('auditable_id')->label('Auditable ID'),
                                TextEntry::make('request_id'),
                                TextEntry::make('ip_address')->label('IP address'),
                                TextEntry::make('user_agent')->columnSpanFull(),
                                TextEntry::make('metadata')
                                    ->formatStateUsing(fn (mixed $state): string => json_encode($state ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
