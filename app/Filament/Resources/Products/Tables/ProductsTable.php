<?php

namespace App\Filament\Resources\Products\Tables;

use App\Actions\Catalog\ArchiveProduct;
use App\Actions\Catalog\DuplicateProductAsDraft;
use App\Actions\Catalog\PublishProduct;
use App\Actions\Catalog\UnpublishProduct;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Support\Catalog\EurMoney;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state)
                    ->sortable(),
                TextColumn::make('price_cents')
                    ->label('Price')
                    ->formatStateUsing(fn (int $state): string => 'EUR '.EurMoney::formatCents($state))
                    ->sortable(),
                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_testable')
                    ->label('Testable')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('categories_count')
                    ->label('Categories')
                    ->sortable(),
                TextColumn::make('currentVersion.version')
                    ->label('Current version')
                    ->placeholder('None'),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options(self::productStatusOptions()),
            ])
            ->recordActions([
                EditAction::make()->authorize(true),
                Action::make('publish')
                    ->authorize(true)
                    ->requiresConfirmation()
                    ->visible(fn (Product $record): bool => $record->status !== ProductStatus::Published)
                    ->action(fn (Product $record) => self::publish($record)),
                Action::make('unpublish')
                    ->authorize(true)
                    ->requiresConfirmation()
                    ->visible(fn (Product $record): bool => $record->status === ProductStatus::Published)
                    ->action(function (Product $record): void {
                        app(UnpublishProduct::class)->handle($record);
                        Notification::make()->title('Product unpublished')->success()->send();
                    }),
                Action::make('archive')
                    ->authorize(true)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Product $record): bool => $record->status !== ProductStatus::Archived)
                    ->action(function (Product $record): void {
                        app(ArchiveProduct::class)->handle($record);
                        Notification::make()->title('Product archived')->success()->send();
                    }),
                Action::make('duplicateDraft')
                    ->label('Duplicate draft')
                    ->authorize(true)
                    ->action(function (Product $record): void {
                        $copy = app(DuplicateProductAsDraft::class)->handle($record);
                        Notification::make()
                            ->title('Draft duplicated')
                            ->body($copy->name.' was created without files, media, or examples.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorize(true),
                    ForceDeleteBulkAction::make()->authorize(true),
                    RestoreBulkAction::make()->authorize(true),
                ]),
            ]);
    }

    private static function publish(Product $record): void
    {
        try {
            app(PublishProduct::class)->handle($record, $record->published_at);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Product cannot be published')
                ->body(collect($exception->errors())->flatten()->implode("\n"))
                ->danger()
                ->send();

            return;
        }

        Notification::make()->title('Product published')->success()->send();
    }

    /**
     * @return array<string, string>
     */
    private static function productStatusOptions(): array
    {
        return collect(ProductStatus::cases())
            ->mapWithKeys(fn (ProductStatus $status): array => [$status->value => $status->label()])
            ->all();
    }
}
