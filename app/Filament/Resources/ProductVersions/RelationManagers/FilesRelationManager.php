<?php

namespace App\Filament\Resources\ProductVersions\RelationManagers;

use App\Enums\ProductFileKind;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('kind')
                    ->options(self::kindOptions())
                    ->required(),
                Hidden::make('disk')
                    ->default('private')
                    ->dehydrateStateUsing(fn (): string => 'private'),
                FileUpload::make('path')
                    ->label('File')
                    ->disk('private')
                    ->directory('catalog/product-files')
                    ->visibility('private')
                    ->storeFileNamesIn('original_name')
                    ->preventFilePathTampering()
                    ->acceptedFileTypes(fn (Get $get): array => self::acceptedFileTypes((string) $get('kind')))
                    ->maxSize(fn (Get $get): int => self::maxSizeKilobytes((string) $get('kind')))
                    ->required(),
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
            ->recordTitleAttribute('original_name')
            ->columns([
                TextColumn::make('kind')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state)
                    ->sortable(),
                TextColumn::make('original_name')
                    ->searchable(),
                TextColumn::make('size_bytes')
                    ->label('Size')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('mime_type')
                    ->label('MIME')
                    ->toggleable(),
                TextColumn::make('sha256')
                    ->label('SHA-256')
                    ->copyable()
                    ->toggleable(),
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
        return collect(ProductFileKind::cases())
            ->mapWithKeys(fn (ProductFileKind $kind): array => [$kind->value => $kind->label()])
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function acceptedFileTypes(string $kind): array
    {
        return match ($kind) {
            ProductFileKind::SourceCube->value,
            ProductFileKind::Cube17->value,
            ProductFileKind::Cube33->value,
            ProductFileKind::Cube65->value => ['text/plain', 'application/octet-stream', 'chemical/x-cube'],
            ProductFileKind::PackageZip->value => ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'],
            ProductFileKind::LicensePdf->value,
            ProductFileKind::GuidePdf->value => ['application/pdf'],
            ProductFileKind::Readme->value => ['text/plain', 'text/markdown'],
            default => [],
        };
    }

    private static function maxSizeKilobytes(string $kind): int
    {
        return match ($kind) {
            ProductFileKind::PackageZip->value => 256000,
            ProductFileKind::Readme->value => 2048,
            default => 20480,
        };
    }
}
