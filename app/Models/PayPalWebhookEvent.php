<?php

namespace App\Models;

use App\Enums\PayPalWebhookProcessingStatus;
use App\Enums\PayPalWebhookVerificationStatus;
use Database\Factories\PayPalWebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'id',
    'paypal_event_id',
    'event_type',
    'resource_type',
    'transmission_id',
    'transmission_time',
    'verification_status',
    'processing_status',
    'payload_sha256',
    'encrypted_payload',
    'processing_attempts',
    'failure_code',
    'processed_at',
    'payload_purged_at',
])]
#[Hidden([
    'encrypted_payload',
])]
class PayPalWebhookEvent extends Model
{
    /** @use HasFactory<PayPalWebhookEventFactory> */
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @param  Builder<PayPalWebhookEvent>  $query
     * @return Builder<PayPalWebhookEvent>
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', PayPalWebhookVerificationStatus::Verified);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_status' => PayPalWebhookVerificationStatus::class,
            'processing_status' => PayPalWebhookProcessingStatus::class,
            'encrypted_payload' => 'encrypted',
            'processing_attempts' => 'integer',
            'transmission_time' => 'datetime',
            'processed_at' => 'datetime',
            'payload_purged_at' => 'datetime',
        ];
    }
}
