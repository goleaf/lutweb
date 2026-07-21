<?php

namespace App\Actions\Commerce;

use App\Models\CustomLutCommerceSetting;
use App\Models\User;
use App\Support\Catalog\EurMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class UpdateCustomLutCommerceSettings
{
    public function handle(User $administrator, string $price, bool $enabled): CustomLutCommerceSetting
    {
        if (! $administrator->is_admin || ! $administrator->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'administrator' => 'Only verified administrators may update Custom LUT pricing.',
            ]);
        }

        try {
            $priceCents = EurMoney::parseDecimalToCents($price);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'price' => $exception->getMessage(),
            ]);
        }

        if ($priceCents < 0) {
            throw ValidationException::withMessages([
                'price' => 'The Custom LUT price cannot be negative.',
            ]);
        }

        if ($enabled && $priceCents <= 0) {
            throw ValidationException::withMessages([
                'price' => 'Enter a Custom LUT price greater than 0.00 before enabling sales.',
            ]);
        }

        return DB::transaction(function () use ($administrator, $priceCents, $enabled): CustomLutCommerceSetting {
            $settings = CustomLutCommerceSetting::query()
                ->where('scope', CustomLutCommerceSetting::Scope)
                ->lockForUpdate()
                ->first();

            if (! $settings instanceof CustomLutCommerceSetting) {
                $settings = CustomLutCommerceSetting::query()->create([
                    'scope' => CustomLutCommerceSetting::Scope,
                    'is_enabled' => false,
                    'price_cents' => 0,
                    'currency' => 'EUR',
                    'version' => 1,
                ]);

                $settings->refresh();
            }

            $changed = $settings->is_enabled !== $enabled
                || $settings->price_cents !== $priceCents
                || $settings->currency !== 'EUR';

            $settings->forceFill([
                'is_enabled' => $enabled,
                'price_cents' => $priceCents,
                'currency' => 'EUR',
                'version' => $changed ? $settings->version + 1 : $settings->version,
                'updated_by' => $administrator->id,
            ])->save();

            return $settings->refresh()->load('updatedBy');
        }, attempts: 3);
    }
}
