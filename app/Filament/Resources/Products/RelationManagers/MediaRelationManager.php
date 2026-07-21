<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Enums\ProductMediaKind;
use App\Enums\StorefrontImageStatus;
use App\Jobs\ProcessProductMedia;
use App\Models\ProductMedia;
use App\Services\StorefrontMedia\DeleteProductMediaFiles;
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
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Private source')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('kind')
                                    ->options(self::kindOptions())
                                    ->default(ProductMediaKind::Gallery->value)
                                    ->required(),
                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required(),
                                TextInput::make('alt_text')
                                    ->label('English alt text')
                                    ->required()
                                    ->maxLength(255),
                                Toggle::make('rights_confirmed_at')
                                    ->label('I confirm LUT Web may use this image')
                                    ->default(false)
                                    ->afterStateHydrated(fn (Toggle $component, mixed $state): mixed => $component->state($state !== null))
                                    ->dehydrateStateUsing(fn (bool $state): mixed => $state ? now() : null)
                                    ->required(),
                                TextInput::make('source_credit')
                                    ->maxLength(255),
                                Toggle::make('source_credit_is_public')
                                    ->label('Show credit publicly')
                                    ->default(false),
                                Textarea::make('rights_note')
                                    ->label('Internal rights note')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                        Hidden::make('disk')
                            ->default('public')
                            ->dehydrateStateUsing(fn (): string => 'public'),
                        Hidden::make('path')
                            ->default('')
                            ->dehydrateStateUsing(fn (?string $state): string => $state ?? ''),
                        Hidden::make('source_disk')
                            ->default((string) config('storefront-media.private_disk', 'private'))
                            ->dehydrateStateUsing(fn (): string => (string) config('storefront-media.private_disk', 'private')),
                        Hidden::make('rights_confirmed_by')
                            ->dehydrateStateUsing(fn (): ?int => auth()->id() === null ? null : (int) auth()->id()),
                        Hidden::make('processing_status')
                            ->default(StorefrontImageStatus::Pending->value)
                            ->dehydrated(fn (?ProductMedia $record): bool => $record === null),
                        FileUpload::make('source_path')
                            ->label('Source image')
                            ->disk((string) config('storefront-media.private_disk', 'private'))
                            ->directory(trim((string) config('storefront-media.private_source_prefix', 'storefront-sources'), '/').'/incoming')
                            ->visibility('private')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize((int) ceil((int) config('storefront-media.maximum_upload_bytes', 30 * 1024 * 1024) / 1024))
                            ->storeFileNamesIn('source_original_name')
                            ->preventFilePathTampering()
                            ->required(fn (?ProductMedia $record): bool => $record === null)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('alt_text')
            ->modifyQueryUsing(fn ($query) => $query->withCount('variants'))
            ->columns([
                ImageColumn::make('path')
                    ->label('Legacy')
                    ->disk('public')
                    ->visibility('public')
                    ->square(),
                TextColumn::make('kind')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state)
                    ->sortable(),
                TextColumn::make('alt_text')
                    ->searchable(),
                TextColumn::make('processing_status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->value ?? (string) $state)
                    ->sortable(),
                TextColumn::make('variants_count')
                    ->label('Derivatives')
                    ->sortable(),
                TextColumn::make('width')
                    ->label('Legacy width')
                    ->toggleable(),
                TextColumn::make('height')
                    ->label('Legacy height')
                    ->toggleable(),
                TextColumn::make('source_size_bytes')
                    ->label('Source bytes')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->authorize(true)
                    ->after(fn (ProductMedia $record): mixed => ProcessProductMedia::dispatch($record)->afterCommit()),
            ])
            ->recordActions([
                EditAction::make()
                    ->authorize(true)
                    ->after(fn (ProductMedia $record): mixed => $record->source_path !== null && $record->hasConfirmedUsageRights()
                        ? ProcessProductMedia::dispatch($record)->afterCommit()
                        : null),
                Action::make('process')
                    ->label('Regenerate')
                    ->authorize(true)
                    ->requiresConfirmation()
                    ->visible(fn (ProductMedia $record): bool => $record->source_path !== null && $record->hasConfirmedUsageRights())
                    ->action(fn (ProductMedia $record): mixed => ProcessProductMedia::dispatch($record)->afterCommit()),
                Action::make('safeDelete')
                    ->label('Delete')
                    ->color('danger')
                    ->authorize(true)
                    ->requiresConfirmation()
                    ->action(function (ProductMedia $record): void {
                        app(DeleteProductMediaFiles::class)->delete($record);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function kindOptions(): array
    {
        return collect(ProductMediaKind::cases())
            ->mapWithKeys(fn (ProductMediaKind $kind): array => [$kind->value => $kind->label()])
            ->all();
    }
}
