<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExamplesRelationManager extends RelationManager
{
    protected static string $relationship = 'examples';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->maxLength(255),
                Grid::make(2)
                    ->schema([
                        Hidden::make('before_disk')
                            ->default('public')
                            ->dehydrateStateUsing(fn (): string => 'public'),
                        Hidden::make('after_disk')
                            ->default('public')
                            ->dehydrateStateUsing(fn (): string => 'public'),
                        FileUpload::make('before_path')
                            ->label('Before image')
                            ->disk('public')
                            ->directory('catalog/product-examples')
                            ->visibility('public')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(20480)
                            ->storeFileNamesIn('before_original_name')
                            ->preventFilePathTampering()
                            ->required(),
                        FileUpload::make('after_path')
                            ->label('After image')
                            ->disk('public')
                            ->directory('catalog/product-examples')
                            ->visibility('public')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(20480)
                            ->storeFileNamesIn('after_original_name')
                            ->preventFilePathTampering()
                            ->required(),
                        TextInput::make('before_alt_text')
                            ->label('Before alt text')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('after_alt_text')
                            ->label('After alt text')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
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
}
