<?php

namespace App\Support\Finance;

use App\Contracts\PayoutGatewayInterface;
use App\Models\VendorPayout;

/**
 * Non-live payout stub. Never moves real money.
 */
class StubPayoutGateway implements PayoutGatewayInterface
{
    public function key(): string
    {
        return 'stub';
    }

    public function displayName(): string
    {
        return (string) config('finance.payout.gateways.stub.display_name', 'Stub / Sandbox Payout');
    }

    public function supportsLivePayouts(): bool
    {
        return false;
    }

    public function initiate(VendorPayout $payout): array
    {
        return [
            'status' => 'accepted',
            'provider_reference' => 'STUB-PAYOUT-'.$payout->reference,
            'message' => 'Sandbox payout accepted (no live transfer).',
            'metadata' => ['sandbox' => true],
        ];
    }

    public function verify(VendorPayout $payout): array
    {
        return [
            'status' => 'completed',
            'provider_reference' => $payout->provider_reference,
            'raw_status' => 'sandbox_completed',
        ];
    }
}
