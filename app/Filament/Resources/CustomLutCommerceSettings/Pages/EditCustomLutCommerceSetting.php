<?php

namespace App\Filament\Resources\CustomLutCommerceSettings\Pages;

use App\Actions\Commerce\UpdateCustomLutCommerceSettings;
use App\Filament\Resources\CustomLutCommerceSettings\CustomLutCommerceSettingResource;
use App\Models\User;
use App\Support\Catalog\EurMoney;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCustomLutCommerceSetting extends EditRecord
{
    protected static string $resource = CustomLutCommerceSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['price'] = EurMoney::formatCents((int) ($data['price_cents'] ?? 0));

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return app(UpdateCustomLutCommerceSettings::class)->handle(
            $user,
            (string) $data['price'],
            (bool) ($data['is_enabled'] ?? false),
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
