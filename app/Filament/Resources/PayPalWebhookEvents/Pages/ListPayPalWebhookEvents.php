<?php

namespace App\Filament\Resources\PayPalWebhookEvents\Pages;

use App\Filament\Resources\PayPalWebhookEvents\PayPalWebhookEventResource;
use Filament\Resources\Pages\ListRecords;

class ListPayPalWebhookEvents extends ListRecords
{
    protected static string $resource = PayPalWebhookEventResource::class;
}
