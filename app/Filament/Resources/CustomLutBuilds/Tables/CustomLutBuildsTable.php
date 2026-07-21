<?php

namespace App\Filament\Resources\CustomLutBuilds\Tables;

use App\Enums\CustomLutBuildFileKind;
use App\Models\CustomLutBuild;
use App\Models\CustomLutBuildFile;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CustomLutBuildsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Build')
                    ->formatStateUsing(fn (string $state): string => substr($state, 0, 12))
                    ->copyable()
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Owner')
                    ->searchable(),
                TextColumn::make('project_name_snapshot')
                    ->label('Project')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state),
                IconColumn::make('sale_ready')
                    ->label('Sale ready')
                    ->boolean(),
                IconColumn::make('locked_for_commerce')
                    ->label('Locked')
                    ->state(fn (CustomLutBuild $record): bool => $record->isLockedForCommerce())
                    ->boolean(),
                IconColumn::make('purchased')
                    ->label('Purchased')
                    ->state(fn (CustomLutBuild $record): bool => $record->hasBeenPurchased())
                    ->boolean(),
                TextColumn::make('parameters_hash')
                    ->label('Parameters')
                    ->formatStateUsing(fn (string $state): string => substr($state, 0, 12))
                    ->toggleable(),
                TextColumn::make('transform_version')
                    ->toggleable(),
                TextColumn::make('packageFile.size_bytes')
                    ->label('Package')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? 'Missing' : number_format($state).' bytes')
                    ->toggleable(),
                TextColumn::make('packageFile.sha256')
                    ->label('SHA-256')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? 'Missing' : substr($state, 0, 12))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('order_items_count')
                    ->label('Orders')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('entitlements_count')
                    ->label('Entitlements')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('prepared_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('integrityCheck')
                    ->label('Run Integrity Check')
                    ->authorize(true)
                    ->requiresConfirmation()
                    ->action(function (CustomLutBuild $record): void {
                        $result = self::integrityCheck($record);

                        Notification::make()
                            ->title($result ? 'Package integrity passed' : 'Package integrity failed')
                            ->body($result ? 'The package exists and matches its stored metadata.' : 'The package is missing or does not match stored metadata.')
                            ->color($result ? 'success' : 'danger')
                            ->send();
                    }),
            ]);
    }

    private static function integrityCheck(CustomLutBuild $build): bool
    {
        $build->loadMissing('packageFile');
        $file = $build->packageFile;

        if (
            ! $file instanceof CustomLutBuildFile
            || $file->kind !== CustomLutBuildFileKind::PackageZip
            || $file->disk !== config('custom-lut-commerce.private_disk', 'private')
        ) {
            return false;
        }

        try {
            $disk = Storage::disk($file->disk);

            if (! $disk->exists($file->path) || $disk->size($file->path) !== $file->size_bytes) {
                return false;
            }

            if ($file->sha256 === null) {
                return false;
            }

            return self::streamHashMatches($file);
        } catch (Throwable) {
            return false;
        }
    }

    private static function streamHashMatches(CustomLutBuildFile $file): bool
    {
        $stream = Storage::disk($file->disk)->readStream($file->path);

        if ($stream === null) {
            return false;
        }

        try {
            $context = hash_init('sha256');

            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);

                if ($chunk === false) {
                    return false;
                }

                hash_update($context, $chunk);
            }

            return hash_equals(strtolower($file->sha256 ?? ''), hash_final($context));
        } finally {
            fclose($stream);
        }
    }
}
