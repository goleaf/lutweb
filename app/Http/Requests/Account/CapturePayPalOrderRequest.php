<?php

namespace App\Http\Requests\Account;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class CapturePayPalOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $order = $this->route('order');

        return $user !== null
            && $user->hasVerifiedEmail()
            && ! $user->is_suspended
            && $order instanceof Order
            && $order->belongsToUser($user);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'paypal_order_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
