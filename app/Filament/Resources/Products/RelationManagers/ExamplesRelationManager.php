<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Enums\ProductType;
use App\Enums\StorefrontImageStatus;
use App\Jobs\ProcessProductExample;
use App\Models\BundleItem;
use App\Models\Product;
use App\Models\ProductExample;
use App\Services\StorefrontMedia\DeleteProductExampleFiles;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExamplesRelationManager extends RelationManager
{
    protected static string $relationship = 'examples';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Automatic before/after source')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('title')
                                    ->maxLength(255),
                                Select::make('preview_product_id')
                                    ->label('Bundle preview component')
                                    ->options(fn (): array => $this->bundlePreviewOptions())
                                    ->visible(fn (): bool => $this->ownerIsBundle())
                                    ->required(fn (): bool => $this->ownerIsBundle()),
                                TextInput::make('before_alt_text')
                                    ->label('Before alt text')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('after_alt_text')
                                    ->label('After alt text')
                                    ->required()
                                    ->maxLength(255),
                                Toggle::make('rights_confirmed_at')
                                    ->label('I confirm LUT Web may use this image')
                                    ->default(false)
                                    ->afterStateHydrated(fn (Toggle $component, mixed $state): mixed => $component->state($state !== null))
                                    ->dehydrateStateUsing(fn (bool $state): mixed => $state ? now() : null)
                                    ->required(),
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                                TextInput::make('source_credit')
                                    ->maxLength(255),
                                Toggle::make('source_credit_is_public')
                                    ->label('Show credit publicly')
                                    ->default(false),
                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required(),
                                Textarea::make('rights_note')
                                    ->label('Internal rights note')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                        Hidden::make('before_disk')
                            ->default('public')
                            ->dehydrateStateUsing(fn (): string => 'public'),
                        Hidden::make('before_path')
                            ->default('')
                            ->dehydrateStateUsing(fn (?string $state): string => $state ?? ''),
                        Hidden::make('after_disk')
                            ->default('public')
                            ->dehydrateStateUsing(fn (): string => 'public'),
                        Hidden::make('after_path')
                            ->default('')
                            ->dehydrateStateUsing(fn (?string $state): string => $state ?? ''),
                        Hidden::make('source_disk')
                            ->default((string) config('storefront-media.private_disk', 'private'))
                            ->dehydrateStateUsing(fn (): string => (string) config('storefront-media.private_disk', 'private')),
                        Hidden::make('rights_confirmed_by')
                            ->dehydrateStateUsing(fn (): ?int => auth()->id() === null ? null : (int) auth()->id()),
                        Hidden::make('processing_status')
                            ->default(StorefrontImageStatus::Pending->value)
                            ->dehydrated(fn (?ProductExample $record): bool => $record === null),
                        FileUpload::make('source_path')
                            ->label('Original source image')
                            ->disk((string) config('storefront-media.private_disk', 'private'))
                            ->directory(trim((string) config('storefront-media.private_source_prefix', 'storefront-sources'), '/').'/incoming')
                            ->visibility('private')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize((int) ceil((int) config('storefront-media.maximum_upload_bytes', 30 * 1024 * 1024) / 1024))
                            ->storeFileNamesIn('source_original_name')
                            ->preventFilePathTampering()
                            ->required(fn (?ProductExample $record): bool => $record === null)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn ($query) => $query->withCount('variants')->with(['processedProductVersion', 'processedProductFile']))
            ->columns([
                ImageColumn::make('before_path')
                    ->label('Legacy before')
                    ->disk('public')
                    ->square()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('title')
                    ->placeholder('Untitled')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('before_alt_text')
                    ->toggleable(),
                TextColumn::make('after_alt_text')
                    ->toggleable(),
                TextColumn::make('processing_status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->value ?? (string) $state)
                    ->sortable(),
                TextColumn::make('variants_count')
                    ->label('Derivatives')
                    ->sortable(),
                TextColumn::make('processedProductVersion.version')
                    ->label('CUBE version')
                    ->placeholder('Not generated'),
                TextColumn::make('failure_message')
                    ->label('Failure')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->authorize(true)
                    ->after(fn (ProductExample $record): mixed => ProcessProductExample::dispatch($record)->afterCommit()),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize(true)
                    ->after(fn (ProductExample $record): mixed => $record->source_path !== null && $record->hasConfirmedUsageRights()
                        ? ProcessProductExample::dispatch($record)->afterCommit()
                        : null),
                Action::make('regenerate')
                    ->authorize(true)
                    ->requiresConfirmation()
                    ->visible(fn (ProductExample $record): bool => $record->source_path !== null && $record->hasConfirmedUsageRights())
                    ->action(fn (ProductExample $record): mixed => ProcessProductExample::dispatch($record)->afterCommit()),
                Action::make('safeDelete')
                    ->label('Delete')
                    ->color('danger')
                    ->authorize(true)
                    ->requiresConfirmation()
                    ->action(function (ProductExample $record): void {
                        app(DeleteProductExampleFiles::class)->delete($record);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private function bundlePreviewOptions(): array
    {
        $owner = $this->getOwnerRecord();

        if (! $owner instanceof Product) {
            return [];
        }

        return $owner->bundleItems()
            ->with('product')
            ->get()
            ->filter(fn (BundleItem $item): bool => $item->product instanceof Product && $item->product->type !== ProductType::Bundle)
            ->mapWithKeys(fn (BundleItem $item): array => [(int) $item->product_id => $item->product->name ?? 'Product '.$item->product_id])
            ->all();
    }

    private function ownerIsBundle(): bool
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof Product && $owner->type === ProductType::Bundle;
    }
}
