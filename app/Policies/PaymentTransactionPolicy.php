<?php

namespace App\Policies;

use App\Models\PaymentTransaction;
use App\Models\User;

/**
 * Payment visibility / admin mutation — vendors never manage payments.
 */
class PaymentTransactionPolicy
{
    public function view(User $user, PaymentTransaction $paymentTransaction): bool
    {
        if (! $user->isActiveAccount()) {
            return false;
        }

        if ($user->hasPermission('payments.view') || $user->hasPermission('payments.manage')) {
            return true;
        }

        $paymentTransaction->loadMissing('order');

        return $paymentTransaction->order
            && (int) $paymentTransaction->order->user_id === (int) $user->id;
    }

    public function update(User $user, PaymentTransaction $paymentTransaction): bool
    {
        return $user->isActiveAccount() && $user->hasPermission('payments.manage');
    }

    public function manage(User $user): bool
    {
        return $user->isActiveAccount() && $user->hasPermission('payments.manage');
    }
}
