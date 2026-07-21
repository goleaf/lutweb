<?php

namespace App\Filament\Resources\PayPalWebhookEvents;

use App\Enums\PayPalWebhookProcessingStatus;
use App\Enums\PayPalWebhookVerificationStatus;
use App\Filament\Resources\PayPalWebhookEvents\Pages\ListPayPalWebhookEvents;
use App\Filament\Resources\PayPalWebhookEvents\Pages\ViewPayPalWebhookEvent;
use App\Jobs\ProcessPayPalWebhook;
use App\Models\PayPalWebhookEvent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PayPalWebhookEventResource extends Resource
{
    protected static ?string $model = PayPalWebhookEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 40;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('paypal_event_id')->label('PayPal event ID')->searchable()->copyable(),
                TextColumn::make('event_type')->searchable()->sortable(),
                TextColumn::make('verification_status')->badge()->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state),
                TextColumn::make('processing_status')->badge()->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state),
                TextColumn::make('processing_attempts')->numeric()->sortable(),
                TextColumn::make('failure_code')->toggleable(),
                TextColumn::make('processed_at')->dateTime()->sortable(),
                TextColumn::make('payload_purged_at')->dateTime()->sortable()->toggleable(),
            ])
            ->recordActions([
                ViewAction::make()->authorize(true),
                Action::make('retry')
                    ->authorize(true)
                    ->requiresConfirmation()
                    ->visible(fn (PayPalWebhookEvent $record): bool => $record->verification_status->value === PayPalWebhookVerificationStatus::Verified->value
                        && $record->processing_status->value === PayPalWebhookProcessingStatus::Failed->value)
                    ->action(function (PayPalWebhookEvent $record): void {
                        $record->forceFill([
                            'processing_status' => PayPalWebhookProcessingStatus::Pending,
                            'failure_code' => null,
                        ])->save();
                        ProcessPayPalWebhook::dispatch($record->id);
                        Notification::make()->title('Webhook retry queued')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayPalWebhookEvents::route('/'),
            'view' => ViewPayPalWebhookEvent::route('/{record}'),
        ];
    }
}
