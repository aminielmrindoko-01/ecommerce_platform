<?php

namespace App\Events;

use App\Models\PaymentTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public PaymentTransaction $paymentTransaction) {}
}
