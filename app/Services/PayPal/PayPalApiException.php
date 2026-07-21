<?php

namespace App\Services\PayPal;

use RuntimeException;

class PayPalApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $debugId = null,
    ) {
        parent::__construct($message);
    }
}
