<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $order_id
 * @property PaymentProvider $provider
 * @property PaymentStatus $status
 * @property int $amount_cents
 * @property string $currency
 * @property string|null $paypal_order_id
 * @property string|null $paypal_capture_id
 * @property string $create_request_id
 * @property string|null $capture_request_id
 * @property string|null $payer_email
 * @property Carbon|null $completed_at
 */
#[Fillable([
    'id',
    'order_id',
    'provider',
    'status',
    'amount_cents',
    'currency',
    'paypal_order_id',
    'paypal_capture_id',
    'create_request_id',
    'capture_request_id',
    'payer_id',
    'payer_email',
    'payer_country_code',
    'payee_merchant_id',
    'paypal_fee_cents',
    'net_amount_cents',
    'refunded_amount_cents',
    'provider_debug_id',
    'failure_code',
    'approved_at',
    'completed_at',
    'reversed_at',
    'refunded_at',
])]
#[Hidden([
    'payer_email',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === PaymentStatus::Completed;
    }

    public function needsReview(): bool
    {
        return $this->status === PaymentStatus::NeedsReview;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'payer_email' => 'encrypted',
            'paypal_fee_cents' => 'integer',
            'net_amount_cents' => 'integer',
            'refunded_amount_cents' => 'integer',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'reversed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }
}
