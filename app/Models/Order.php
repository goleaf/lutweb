<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $number
 * @property int|null $user_id
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property FulfillmentStatus $fulfillment_status
 * @property string $currency
 * @property int $subtotal_cents
 * @property int $tax_cents
 * @property int $total_cents
 * @property string $checkout_idempotency_key
 * @property string $customer_name
 * @property string $customer_email
 * @property string|null $customer_country_code
 * @property Carbon $terms_of_sale_accepted_at
 * @property Carbon $license_accepted_at
 * @property Carbon $digital_delivery_consent_at
 * @property string $terms_of_sale_version
 * @property string $license_version
 * @property string $refund_policy_version
 * @property string $digital_delivery_consent_version
 * @property string|null $acceptance_ip_address
 * @property string|null $acceptance_user_agent
 * @property Carbon|null $paid_at
 * @property Carbon|null $fulfilled_at
 * @property Carbon|null $cancelled_at
 */
#[Fillable([
    'id',
    'number',
    'user_id',
    'status',
    'payment_status',
    'fulfillment_status',
    'currency',
    'subtotal_cents',
    'tax_cents',
    'total_cents',
    'checkout_idempotency_key',
    'customer_name',
    'customer_email',
    'customer_country_code',
    'terms_of_sale_accepted_at',
    'license_accepted_at',
    'digital_delivery_consent_at',
    'terms_of_sale_version',
    'license_version',
    'refund_policy_version',
    'digital_delivery_consent_version',
    'acceptance_ip_address',
    'acceptance_user_agent',
    'paid_at',
    'fulfilled_at',
    'cancelled_at',
])]
#[Hidden([
    'customer_email',
    'acceptance_ip_address',
    'acceptance_user_agent',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => OrderStatus::Pending->value,
        'payment_status' => PaymentStatus::Created->value,
        'fulfillment_status' => FulfillmentStatus::Pending->value,
        'currency' => 'EUR',
        'tax_cents' => 0,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<OrderItem, $this>
     */
    public function item(): HasOne
    {
        return $this->hasOne(OrderItem::class);
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * @return HasOne<Entitlement, $this>
     */
    public function entitlement(): HasOne
    {
        return $this->hasOne(Entitlement::class);
    }

    /**
     * @return HasMany<DownloadEvent, $this>
     */
    public function downloadEvents(): HasMany
    {
        return $this->hasMany(DownloadEvent::class);
    }

    public function isPaid(): bool
    {
        return in_array($this->payment_status, [PaymentStatus::Completed, PaymentStatus::NotRequired], true);
    }

    public function isFulfilled(): bool
    {
        return $this->fulfillment_status === FulfillmentStatus::Ready;
    }

    public function isDownloadable(): bool
    {
        return $this->isPaid() && $this->isFulfilled();
    }

    public function requiresPayment(): bool
    {
        return $this->payment_status !== PaymentStatus::NotRequired && $this->total_cents > 0;
    }

    public function belongsToUser(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'subtotal_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'customer_email' => 'encrypted',
            'acceptance_ip_address' => 'encrypted',
            'terms_of_sale_accepted_at' => 'datetime',
            'license_accepted_at' => 'datetime',
            'digital_delivery_consent_at' => 'datetime',
            'paid_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
