<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case NotRequired = 'not_required';
    case Created = 'created';
    case Approved = 'approved';
    case Pending = 'pending';
    case Completed = 'completed';
    case Declined = 'declined';
    case Reversed = 'reversed';
    case Refunded = 'refunded';
    case Failed = 'failed';
    case NeedsReview = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'Not required',
            self::Created => 'Created',
            self::Approved => 'Approved',
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Declined => 'Declined',
            self::Reversed => 'Reversed',
            self::Refunded => 'Refunded',
            self::Failed => 'Failed',
            self::NeedsReview => 'Needs review',
        };
    }
}
