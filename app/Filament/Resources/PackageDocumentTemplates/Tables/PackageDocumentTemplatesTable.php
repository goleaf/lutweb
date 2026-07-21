<?php

namespace App\Filament\Resources\PackageDocumentTemplates\Tables;

use App\Actions\CustomLutBuilds\ActivatePackageDocumentTemplate;
use App\Enums\PackageDocumentKind;
use App\Enums\PackageDocumentStatus;
use App\Models\PackageDocumentTemplate;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PackageDocumentTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('kind')
                    ->badge()
                    ->formatStateUsing(fn (PackageDocumentKind|string $state): string => $state instanceof PackageDocumentKind ? $state->label() : $state)
                    ->sortable(),
                TextColumn::make('version')->searchable()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PackageDocumentStatus|string $state): string => $state instanceof PackageDocumentStatus ? $state->label() : $state)
                    ->sortable(),
                IconColumn::make('is_current')->label('Current')->boolean()->sortable(),
                TextColumn::make('activated_at')->dateTime()->sortable()->toggleable(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('kind')->options([
                    PackageDocumentKind::License->value => PackageDocumentKind::License->label(),
                    PackageDocumentKind::InstallationGuide->value => PackageDocumentKind::InstallationGuide->label(),
                ]),
                SelectFilter::make('status')->options([
                    PackageDocumentStatus::Draft->value => PackageDocumentStatus::Draft->label(),
                    PackageDocumentStatus::Active->value => PackageDocumentStatus::Active->label(),
                    PackageDocumentStatus::Archived->value => PackageDocumentStatus::Archived->label(),
                ]),
            ])
            ->recordActions([
                EditAction::make()->authorize(true),
                Action::make('duplicate')
                    ->authorize(true)
                    ->action(function (PackageDocumentTemplate $record): void {
                        $copy = $record->replicate();
                        $copy->version = $record->version.'-copy-'.now()->format('YmdHis');
                        $copy->title = $record->title.' Copy';
                        $copy->status = PackageDocumentStatus::Draft;
                        $copy->is_current = false;
                        $copy->activated_at = null;
                        $copy->save();

                        Notification::make()->title('Document template duplicated')->success()->send();
                    }),
                Action::make('activate')
                    ->authorize(true)
                    ->requiresConfirmation()
                    ->visible(fn (PackageDocumentTemplate $record): bool => ! $record->trashed())
                    ->action(function (PackageDocumentTemplate $record, ActivatePackageDocumentTemplate $activate): void {
                        $activate->handle($record);
                        Notification::make()->title('Document template activated')->success()->send();
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
}
