<?php

namespace App\Filament\Resources\Entitlements;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\CustomLutBuildFileKind;
use App\Enums\DigitalAssetKind;
use App\Enums\EntitlementStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductFileKind;
use App\Filament\Resources\Entitlements\Pages\ListEntitlements;
use App\Filament\Resources\Entitlements\Pages\ViewEntitlement;
use App\Models\Entitlement;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class EntitlementResource extends Resource
{
    protected static ?string $model = Entitlement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 30;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.email')->label('Customer')->searchable(),
                TextColumn::make('order.number')->label('Order')->searchable(),
                TextColumn::make('digital_asset_kind')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state),
                TextColumn::make('orderItem.product_name')->label('Item')->searchable(),
                TextColumn::make('orderItem.product_version')->label('Version'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state),
                TextColumn::make('granted_at')->dateTime()->sortable(),
                TextColumn::make('revoked_at')->dateTime()->sortable()->toggleable(),
            ])
            ->recordActions([
                ViewAction::make()->authorize(true),
                Action::make('revoke')
                    ->authorize(true)
                    ->color('danger')
                    ->visible(fn (Entitlement $record): bool => $record->status === EntitlementStatus::Active)
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('reason')->required()->maxLength(190),
                    ])
                    ->action(function (Entitlement $record, array $data): void {
                        $record->forceFill([
                            'status' => EntitlementStatus::Revoked,
                            'revoked_at' => now(),
                            'revoke_reason' => (string) $data['reason'],
                        ])->save();

                        $record->order?->forceFill([
                            'fulfillment_status' => FulfillmentStatus::Revoked,
                        ])->save();

                        $actor = auth()->user();
                        app(RecordAuditEvent::class)->handle(
                            'entitlement.revoked',
                            actor: $actor instanceof User ? $actor : null,
                            auditable: $record,
                            targetUser: $record->user,
                            metadata: ['reason' => (string) $data['reason']],
                            allowedMetadataKeys: ['reason'],
                        );

                        Notification::make()->title('Entitlement revoked')->success()->send();
                    }),
                Action::make('restore')
                    ->authorize(true)
                    ->color('success')
                    ->visible(fn (Entitlement $record): bool => $record->status === EntitlementStatus::Revoked)
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('reason')->required()->maxLength(190),
                    ])
                    ->action(function (Entitlement $record, array $data): void {
                        $record->loadMissing(['order.payment', 'productFile', 'customLutBuildFile']);
                        $order = $record->order;

                        if ($order === null || ! self::packageExistsFor($record)) {
                            Notification::make()->title('Entitlement cannot be restored')->danger()->send();

                            return;
                        }

                        if (! in_array($order->payment_status, [PaymentStatus::Completed, PaymentStatus::NotRequired], true)) {
                            Notification::make()->title('Payment is not restorable without review')->danger()->send();

                            return;
                        }

                        $record->forceFill([
                            'status' => EntitlementStatus::Active,
                            'restored_at' => now(),
                            'revoke_reason' => 'Restored: '.$data['reason'],
                        ])->save();

                        $order->forceFill([
                            'fulfillment_status' => FulfillmentStatus::Ready,
                        ])->save();

                        $actor = auth()->user();
                        app(RecordAuditEvent::class)->handle(
                            'entitlement.restored',
                            actor: $actor instanceof User ? $actor : null,
                            auditable: $record,
                            targetUser: $record->user,
                            metadata: ['reason' => (string) $data['reason']],
                            allowedMetadataKeys: ['reason'],
                        );

                        Notification::make()->title('Entitlement restored')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEntitlements::route('/'),
            'view' => ViewEntitlement::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'order.payment', 'orderItem', 'productFile', 'customLutBuildFile']);
    }

    private static function packageExistsFor(Entitlement $entitlement): bool
    {
        if ($entitlement->digital_asset_kind === DigitalAssetKind::CatalogProduct) {
            $file = $entitlement->productFile;

            return $file !== null
                && $file->kind === ProductFileKind::PackageZip
                && $file->disk === 'private'
                && Storage::disk($file->disk)->exists($file->path);
        }

        $file = $entitlement->customLutBuildFile;

        return $file !== null
            && $file->kind === CustomLutBuildFileKind::PackageZip
            && $file->custom_lut_build_id === $entitlement->custom_lut_build_id
            && $file->disk === config('custom-lut-commerce.private_disk', 'private')
            && Storage::disk($file->disk)->exists($file->path);
    }
}
