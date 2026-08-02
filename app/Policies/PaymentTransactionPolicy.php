<?php

namespace App\Policies;

use App\Models\PaymentTransaction;
use App\Models\User;

/**
 * Authorization for payment transaction visibility and admin mutation.
 */
class PaymentTransactionPolicy
{
    public function view(User $user, PaymentTransaction $paymentTransaction): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $paymentTransaction->loadMissing('order');

        return $paymentTransaction->order
            && (int) $paymentTransaction->order->user_id === (int) $user->id;
    }

    public function update(User $user, PaymentTransaction $paymentTransaction): bool
    {
        return $user->isAdmin();
    }

    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
