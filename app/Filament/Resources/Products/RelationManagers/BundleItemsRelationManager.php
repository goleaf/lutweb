<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Enums\ProductType;
use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BundleItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'bundleItems';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Product && $ownerRecord->isBundle();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label('Component product')
                    ->relationship(
                        name: 'product',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            $ownerRecord = $this->getOwnerRecord();

                            if ($ownerRecord instanceof Product) {
                                $query->whereKeyNot($ownerRecord->getKey());
                            }

                            return $query->where('type', '!=', ProductType::Bundle->value);
                        },
                    )
                    ->searchable()
                    ->preload()
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
            ->recordTitleAttribute('product.name')
            ->columns([
                TextColumn::make('product.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state),
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
