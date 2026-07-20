<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Enums\ProductMediaKind;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
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
                Select::make('kind')
                    ->options(self::kindOptions())
                    ->default(ProductMediaKind::Gallery->value)
                    ->required(),
                Hidden::make('disk')
                    ->default('public')
                    ->dehydrateStateUsing(fn (): string => 'public'),
                FileUpload::make('path')
                    ->label('Image')
                    ->disk('public')
                    ->directory('catalog/product-media')
                    ->visibility('public')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(20480)
                    ->storeFileNamesIn('original_name')
                    ->preventFilePathTampering()
                    ->required(),
                TextInput::make('alt_text')
                    ->label('Alt text')
                    ->required()
                    ->maxLength(255),
                TextInput::make('sort_order')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('alt_text')
            ->columns([
                ImageColumn::make('path')
                    ->disk('public')
                    ->visibility('public')
                    ->square(),
                TextColumn::make('kind')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state)
                    ->sortable(),
                TextColumn::make('alt_text')
                    ->searchable(),
                TextColumn::make('width')
                    ->toggleable(),
                TextColumn::make('height')
                    ->toggleable(),
                TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()->authorize(true),
            ])
            ->recordActions([
                EditAction::make()->authorize(true),
                DeleteAction::make()->authorize(true),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorize(true),
                ]),
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
