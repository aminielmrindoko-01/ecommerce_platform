<?php

namespace App\Services\Finance;

use App\Services\PaymentService;
use InvalidArgumentException;

/**
 * Commission calculator with snapshot-friendly outputs.
 * Historical entitlements store the applied rate/type — never re-price from live config.
 */
class CommissionCalculator
{
    public function __construct(
        protected PaymentService $payments,
    ) {}

    /**
     * @return array{
     *   type:string,
     *   rate:string,
     *   commission:string,
     *   net:string,
     *   snapshot:array<string,mixed>
     * }
     */
    public function forGross(string $grossAmount, ?array $override = null): array
    {
        $gross = $this->payments->normalizeMoney($grossAmount);
        if (bccomp($gross, '0.00', 2) < 0) {
            throw new InvalidArgumentException('Gross amount cannot be negative.');
        }

        $type = (string) ($override['type'] ?? config('finance.commission.type', 'percentage'));
        $rate = $this->payments->normalizeMoney($override['rate'] ?? config('finance.commission.rate', '0.10'));
        // Rates like 0.10 need 4dp — normalizeMoney forces 2dp. Handle rate separately.
        $rateRaw = (string) ($override['rate'] ?? config('finance.commission.rate', '0.10'));
        if (! preg_match('/^\d+(\.\d+)?$/', trim($rateRaw))) {
            throw new InvalidArgumentException('Invalid commission rate.');
        }
        $rate = bcadd(trim($rateRaw), '0', 4);

        if ($type === 'fixed') {
            $commission = $this->payments->normalizeMoney(
                $override['fixed_amount'] ?? config('finance.commission.fixed_amount', '0.00')
            );
            if (bccomp($commission, $gross, 2) > 0) {
                $commission = $gross;
            }
        } elseif ($type === 'percentage') {
            if (bccomp($rate, '0.0000', 4) < 0 || bccomp($rate, '1.0000', 4) > 0) {
                throw new InvalidArgumentException('Commission rate must be between 0 and 1.');
            }
            $commission = bcmul($gross, $rate, 2);
        } else {
            throw new InvalidArgumentException('Unsupported commission type.');
        }

        $net = bcsub($gross, $commission, 2);
        if (bccomp($net, '0.00', 2) < 0) {
            $net = '0.00';
        }

        return [
            'type' => $type,
            'rate' => $rate,
            'commission' => $commission,
            'net' => $net,
            'snapshot' => [
                'type' => $type,
                'rate' => $rate,
                'basis' => config('finance.commission.basis', 'item_subtotal'),
                'gross' => $gross,
                'commission' => $commission,
                'net' => $net,
                'currency' => config('finance.currency', 'TZS'),
            ],
        ];
    }
}
