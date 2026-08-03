<?php

namespace App\Contracts;

use App\Models\VendorPayout;

/**
 * Payout provider boundary.
 *
 * PAYOUT INTEGRATION STATUS: SANDBOX / NOT PRODUCTION-READY
 * Implementations must not store raw bank passwords, PINs, or card numbers.
 */
interface PayoutGatewayInterface
{
    public function key(): string;

    public function displayName(): string;

    public function supportsLivePayouts(): bool;

    /**
     * Initiate a provider payout for an approved local payout record.
     * Must not mark the payout completed — PayoutService owns status.
     *
     * @return array{status:string,provider_reference:?string,message:?string,metadata?:array}
     */
    public function initiate(VendorPayout $payout): array;

    /**
     * Verify provider status for reconciliation.
     *
     * @return array{status:string,provider_reference:?string,raw_status:?string}
     */
    public function verify(VendorPayout $payout): array;
}
