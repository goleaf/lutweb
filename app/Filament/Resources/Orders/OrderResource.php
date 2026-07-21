<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Services\Orders\ReconcilePayPalOrder;
use App\Support\Catalog\EurMoney;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'number';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('customer_name')->searchable(),
                TextColumn::make('item.product_name')->label('Product')->searchable(),
                TextColumn::make('total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn (int $state): string => 'EUR '.EurMoney::formatCents($state))
                    ->sortable(),
                TextColumn::make('payment_status')->badge()->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state),
                TextColumn::make('fulfillment_status')->badge()->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                ViewAction::make()->authorize(true),
                Action::make('recheckPayPal')
                    ->label('Recheck PayPal Payment')
                    ->authorize(true)
                    ->visible(fn (Order $record): bool => $record->payment !== null)
                    ->requiresConfirmation()
                    ->action(function (Order $record): void {
                        app(ReconcilePayPalOrder::class)->handle($record);
                        Notification::make()->title('Payment rechecked')->success()->send();
                    }),
                Action::make('markForInvestigation')
                    ->label('Mark for Investigation')
                    ->authorize(true)
                    ->requiresConfirmation()
                    ->action(function (Order $record): void {
                        $record->forceFill(['status' => OrderStatus::NeedsReview])->save();
                        Notification::make()->title('Order marked for investigation')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'item', 'payment', 'entitlement'])
            ->withCount('downloadEvents');
    }
}
