<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    public function view(User $user, Order $order): Response
    {
        return $order->belongsToUser($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function capture(User $user, Order $order): Response
    {
        return $order->belongsToUser($user)
            && ! $user->is_suspended
            && $order->payment_status !== PaymentStatus::NotRequired
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function cancel(User $user, Order $order): Response
    {
        return $order->belongsToUser($user)
            && ! $user->is_suspended
            && $order->status === OrderStatus::Pending
            && ! $order->isPaid()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
