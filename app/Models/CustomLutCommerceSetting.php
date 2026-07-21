<?php

namespace App\Models;

use Database\Factories\CustomLutCommerceSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $scope
 * @property bool $is_enabled
 * @property int $price_cents
 * @property string $currency
 * @property int $version
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'scope',
    'is_enabled',
    'price_cents',
    'currency',
    'version',
    'updated_by',
])]
class CustomLutCommerceSetting extends Model
{
    /** @use HasFactory<CustomLutCommerceSettingFactory> */
    use HasFactory;

    public const Scope = 'custom_lut';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'scope' => self::Scope,
        'is_enabled' => false,
        'price_cents' => 0,
        'currency' => 'EUR',
        'version' => 1,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function canAcceptNewSales(): bool
    {
        return $this->is_enabled
            && $this->currency === 'EUR'
            && $this->price_cents > 0;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'price_cents' => 'integer',
            'version' => 'integer',
        ];
    }
}
