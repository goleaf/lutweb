<?php

namespace App\Services\Checkout;

use Illuminate\Support\Str;

class OrderNumber
{
    public function make(): string
    {
        return 'ORD-'.now()->format('Ymd').'-'.Str::ulid();
    }
}
