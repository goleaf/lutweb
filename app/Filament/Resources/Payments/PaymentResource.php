<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\Payment;
use App\Support\Catalog\EurMoney;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.number')->label('Order')->searchable(),
                TextColumn::make('provider')->badge()->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state),
                TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->formatStateUsing(fn (int $state): string => 'EUR '.EurMoney::formatCents($state))
                    ->sortable(),
                TextColumn::make('paypal_order_id')->label('PayPal order')->copyable()->toggleable(),
                TextColumn::make('paypal_capture_id')->label('Capture')->copyable()->toggleable(),
                TextColumn::make('provider_debug_id')->label('Debug ID')->copyable()->toggleable(),
                TextColumn::make('completed_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                ViewAction::make()->authorize(true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('order');
    }
}
