<?php

namespace App\Filament\Resources\CustomLutBuilds\Schemas;

use App\Models\CustomLutBuild;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomLutBuildInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Support Metadata')
                    ->schema([
                        TextEntry::make('id')
                            ->label('Build ULID')
                            ->copyable(),
                        TextEntry::make('user.email')
                            ->label('Owner'),
                        TextEntry::make('project_name_snapshot')
                            ->label('Project snapshot'),
                        TextEntry::make('style_name_snapshot')
                            ->label('Style snapshot')
                            ->placeholder('Neutral'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state),
                        IconEntry::make('sale_ready')
                            ->label('Sale ready')
                            ->boolean(),
                        IconEntry::make('locked_for_commerce')
                            ->label('Locked')
                            ->state(fn (CustomLutBuild $record): bool => $record->isLockedForCommerce())
                            ->boolean(),
                        IconEntry::make('purchased')
                            ->label('Purchased')
                            ->state(fn (CustomLutBuild $record): bool => $record->hasBeenPurchased())
                            ->boolean(),
                    ])
                    ->columns(2),
                Section::make('Immutable Package Snapshot')
                    ->schema([
                        TextEntry::make('parameters_hash')
                            ->label('Parameters hash')
                            ->formatStateUsing(fn (string $state): string => substr($state, 0, 16)),
                        TextEntry::make('build_fingerprint')
                            ->label('Build fingerprint')
                            ->formatStateUsing(fn (string $state): string => substr($state, 0, 16)),
                        TextEntry::make('transform_version'),
                        TextEntry::make('generator_version'),
                        TextEntry::make('package_schema_version'),
                        TextEntry::make('packageFile.size_bytes')
                            ->label('Package size')
                            ->formatStateUsing(fn (?int $state): string => $state === null ? 'Missing' : number_format($state).' bytes'),
                        TextEntry::make('packageFile.sha256')
                            ->label('Package SHA-256')
                            ->formatStateUsing(fn (?string $state): string => $state === null ? 'Missing' : substr($state, 0, 16)),
                        TextEntry::make('license_version'),
                    ])
                    ->columns(2),
                Section::make('Commerce')
                    ->schema([
                        TextEntry::make('order_items_count')
                            ->label('Related orders')
                            ->numeric(),
                        TextEntry::make('entitlements_count')
                            ->label('Related entitlements')
                            ->numeric(),
                        TextEntry::make('prepared_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('locked_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('first_ordered_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('purchased_at')
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(2),
            ]);
    }
}
