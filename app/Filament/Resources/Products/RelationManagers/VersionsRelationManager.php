<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Actions\Catalog\SetCurrentProductVersion;
use App\Enums\ProductVersionStatus;
use App\Models\Product;
use App\Models\ProductVersion;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use LogicException;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('version')
                    ->required()
                    ->maxLength(255),
                Select::make('status')
                    ->options(self::statusOptions())
                    ->default(ProductVersionStatus::Draft->value)
                    ->required(),
                DateTimePicker::make('released_at')
                    ->seconds(false),
                Textarea::make('notes')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version')
            ->columns([
                TextColumn::make('version')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state)
                    ->sortable(),
                IconColumn::make('is_current')
                    ->label('Current')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('released_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()->authorize(true),
            ])
            ->recordActions([
                EditAction::make()->authorize(true),
                Action::make('markCurrent')
                    ->label('Mark current')
                    ->authorize(true)
                    ->visible(fn (ProductVersion $record): bool => ! $record->is_current)
                    ->action(function (ProductVersion $record): void {
                        app(SetCurrentProductVersion::class)->handle($this->ownerProduct(), $record);
                        Notification::make()->title('Current version updated')->success()->send();
                    }),
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
    private static function statusOptions(): array
    {
        return collect(ProductVersionStatus::cases())
            ->mapWithKeys(fn (ProductVersionStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    private function ownerProduct(): Product
    {
        $ownerRecord = $this->getOwnerRecord();

        if (! $ownerRecord instanceof Product) {
            throw new LogicException('Product versions can only be managed from a product record.');
        }

        return $ownerRecord;
    }
}
