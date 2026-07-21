<?php

namespace App\Services\PayPal;

use App\Models\Order;
use App\Support\Catalog\EurMoney;
use Illuminate\Support\Str;

class CreatePayPalOrder
{
    public function __construct(
        private readonly PayPalHttpClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Order $order): array
    {
        $order->loadMissing(['item', 'payment']);
        $item = $order->item;
        $payment = $order->payment;

        if ($item === null || $payment === null) {
            throw new PayPalApiException('Local order is incomplete.');
        }

        $cancelUrl = $item->isCustomLutBuild() && $item->wizard_project_id !== null && $item->custom_lut_build_id !== null
            ? route('custom-lut.checkout.show', [$item->wizard_project_id, $item->custom_lut_build_id])
            : route('shop.show', $item->product_slug);

        return $this->client->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $order->number,
                    'custom_id' => $order->id,
                    'invoice_id' => $order->number,
                    'amount' => [
                        'currency_code' => 'EUR',
                        'value' => EurMoney::formatCents($order->total_cents),
                        'breakdown' => [
                            'item_total' => [
                                'currency_code' => 'EUR',
                                'value' => EurMoney::formatCents($order->subtotal_cents),
                            ],
                            'tax_total' => [
                                'currency_code' => 'EUR',
                                'value' => EurMoney::formatCents($order->tax_cents),
                            ],
                        ],
                    ],
                    'items' => [
                        [
                            'name' => Str::limit($item->displayName(), 120, ''),
                            'sku' => $item->product_sku ?: $item->product_slug,
                            'unit_amount' => [
                                'currency_code' => 'EUR',
                                'value' => EurMoney::formatCents($item->unit_price_cents),
                            ],
                            'quantity' => '1',
                            'category' => 'DIGITAL_GOODS',
                        ],
                    ],
                ],
            ],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'brand_name' => Str::limit((string) config('paypal.brand_name', 'LUT Web'), 127, ''),
                        'user_action' => 'PAY_NOW',
                        'shipping_preference' => 'NO_SHIPPING',
                        'return_url' => route('account.orders.show', $order),
                        'cancel_url' => $cancelUrl,
                    ],
                ],
            ],
        ], $payment->create_request_id);
    }
}
