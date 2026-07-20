<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Product;
use App\Support\Catalog\EurMoney;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Product')
                    ->tabs([
                        Tab::make('Basic information')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Select::make('type')
                                            ->options(self::productTypeOptions())
                                            ->required()
                                            ->live()
                                            ->default(ProductType::SingleLut->value)
                                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                if ($state === ProductType::Bundle->value) {
                                                    $set('is_testable', false);
                                                }
                                            }),
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Set $set, ?string $state, ?Product $record): void {
                                                if ($record?->isPublished()) {
                                                    return;
                                                }

                                                $set('slug', Str::slug((string) $state));
                                            }),
                                        TextInput::make('slug')
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(ignoreRecord: true),
                                        TextInput::make('sku')
                                            ->label('SKU')
                                            ->maxLength(255)
                                            ->unique(ignoreRecord: true),
                                    ]),
                                TextInput::make('short_description')
                                    ->required()
                                    ->maxLength(500)
                                    ->columnSpanFull(),
                                Textarea::make('description')
                                    ->rows(8)
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Pricing')
                            ->schema([
                                Section::make('EUR price')
                                    ->description('Free LUT products must use 0.00. Paid single LUT and bundle products must be greater than 0.00.')
                                    ->schema([
                                        TextInput::make('price_cents')
                                            ->label('EUR price')
                                            ->required()
                                            ->default('0.00')
                                            ->rule('regex:/^(0|[1-9][0-9]*)(\\.[0-9]{1,2})?$/')
                                            ->formatStateUsing(fn ($state): string => EurMoney::formatCents((int) ($state ?? 0)))
                                            ->dehydrateStateUsing(fn (string $state): int => EurMoney::parseDecimalToCents($state)),
                                        TextInput::make('currency')
                                            ->default('EUR')
                                            ->disabled()
                                            ->saved(true)
                                            ->dehydrateStateUsing(fn (): string => 'EUR'),
                                    ]),
                            ]),
                        Tab::make('Classification')
                            ->schema([
                                Select::make('categories')
                                    ->relationship('categories', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->required(),
                                Select::make('tags')
                                    ->relationship('tags', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),
                                Select::make('compatibleSoftware')
                                    ->label('Compatible software')
                                    ->relationship('compatibleSoftware', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),
                            ]),
                        Tab::make('Publishing')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Select::make('status')
                                            ->options(self::productStatusOptions())
                                            ->disabled()
                                            ->saved(false),
                                        Toggle::make('is_featured')
                                            ->label('Featured'),
                                        Toggle::make('is_testable')
                                            ->label('Allow photo testing')
                                            ->helperText('Testing requires a published single or free LUT with a ready current version and a valid private 3D CUBE file.')
                                            ->visible(fn (Get $get): bool => $get('type') !== ProductType::Bundle->value)
                                            ->dehydrateStateUsing(fn (bool $state, Get $get): bool => $get('type') === ProductType::Bundle->value ? false : $state),
                                        DateTimePicker::make('published_at')
                                            ->seconds(false),
                                        TextInput::make('meta_title')
                                            ->maxLength(255),
                                        TextInput::make('meta_description')
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function productTypeOptions(): array
    {
        return collect(ProductType::cases())
            ->mapWithKeys(fn (ProductType $type): array => [$type->value => $type->label()])
            ->all();
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
