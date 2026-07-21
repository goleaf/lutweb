<?php

namespace App\Filament\Resources\Users;

use App\Actions\Audit\RecordAuditEvent;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'email';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('country_code')->label('Country')->sortable(),
                IconColumn::make('is_admin')->label('Admin')->boolean()->sortable(),
                IconColumn::make('is_suspended')->label('Suspended')->boolean()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                ViewAction::make()->authorize(true),
                Action::make('suspend')
                    ->authorize(true)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => ! $record->is_suspended)
                    ->action(function (User $record): void {
                        $record->forceFill(['is_suspended' => true])->save();
                        app(RecordAuditEvent::class)->handle('user.suspended', actor: auth()->user() instanceof User ? auth()->user() : null, auditable: $record, targetUser: $record);
                        Notification::make()->title('Account suspended')->success()->send();
                    }),
                Action::make('restore')
                    ->authorize(true)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->is_suspended)
                    ->action(function (User $record): void {
                        $record->forceFill(['is_suspended' => false])->save();
                        app(RecordAuditEvent::class)->handle('user.restored', actor: auth()->user() instanceof User ? auth()->user() : null, auditable: $record, targetUser: $record);
                        Notification::make()->title('Account restored')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['orders', 'entitlements']);
    }
}
