<?php

namespace App\Services\Finance;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Authorization\AuditLogger;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Append-only double-entry ledger writer.
 * Invariant: for every transaction, SUM(debits) == SUM(credits) in one currency.
 */
class LedgerService
{
    public function __construct(
        protected PaymentService $payments,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  list<array{account:string,debit?:string,credit?:string,vendor_id?:?int}>  $lines
     * @param  array{
     *   type:string,
     *   currency?:string,
     *   description?:?string,
     *   order_id?:?int,
     *   payment_transaction_id?:?int,
     *   payment_refund_id?:?int,
     *   vendor_id?:?int,
     *   actor?:?User,
     *   idempotency_key?:?string,
     *   reverses_transaction_id?:?int,
     *   metadata?:?array
     * }  $header
     */
    public function post(array $header, array $lines): LedgerTransaction
    {
        $type = (string) ($header['type'] ?? '');
        if ($type === '') {
            throw new InvalidArgumentException('Ledger transaction type is required.');
        }

        $currency = strtoupper((string) ($header['currency'] ?? config('finance.currency', 'TZS')));
        if ($currency !== 'TZS') {
            // Marketplace is TZS-only for Phase 6; multi-currency later.
            throw new InvalidArgumentException('Unsupported ledger currency.');
        }

        $idempotencyKey = isset($header['idempotency_key']) ? trim((string) $header['idempotency_key']) : null;
        if ($idempotencyKey === '') {
            $idempotencyKey = null;
        }

        if ($lines === []) {
            throw new InvalidArgumentException('Ledger transaction requires at least one entry.');
        }

        return DB::transaction(function () use ($header, $lines, $type, $currency, $idempotencyKey) {
            if ($idempotencyKey) {
                $existing = LedgerTransaction::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return $existing->load('entries');
                }
            }

            $normalized = [];
            $debitTotal = '0.00';
            $creditTotal = '0.00';

            foreach ($lines as $i => $line) {
                $accountCode = (string) ($line['account'] ?? '');
                if ($accountCode === '') {
                    throw new InvalidArgumentException("Line {$i}: account code is required.");
                }

                $debit = $this->payments->normalizeMoney($line['debit'] ?? '0');
                $credit = $this->payments->normalizeMoney($line['credit'] ?? '0');

                if (bccomp($debit, '0.00', 2) < 0 || bccomp($credit, '0.00', 2) < 0) {
                    throw new InvalidArgumentException('Ledger amounts cannot be negative.');
                }

                $hasDebit = bccomp($debit, '0.00', 2) > 0;
                $hasCredit = bccomp($credit, '0.00', 2) > 0;
                if ($hasDebit === $hasCredit) {
                    throw new InvalidArgumentException(
                        'Each ledger line must have either a debit or a credit (not both, not neither).'
                    );
                }

                if (isset($line['currency']) && strtoupper((string) $line['currency']) !== $currency) {
                    throw new InvalidArgumentException('Currency mismatch within ledger transaction.');
                }

                $account = LedgerAccount::query()->where('code', $accountCode)->lockForUpdate()->first();
                if (! $account) {
                    throw new InvalidArgumentException("Invalid ledger account: {$accountCode}");
                }
                if ($account->currency !== $currency) {
                    throw new InvalidArgumentException('Account currency mismatch.');
                }

                $debitTotal = bcadd($debitTotal, $debit, 2);
                $creditTotal = bcadd($creditTotal, $credit, 2);

                $normalized[] = [
                    'account' => $account,
                    'debit' => $debit,
                    'credit' => $credit,
                    'vendor_id' => $line['vendor_id'] ?? null,
                ];
            }

            if (bccomp($debitTotal, $creditTotal, 2) !== 0) {
                throw new InvalidArgumentException(
                    "Unbalanced ledger transaction: debits {$debitTotal} != credits {$creditTotal}."
                );
            }

            if (bccomp($debitTotal, '0.00', 2) <= 0) {
                throw new InvalidArgumentException('Ledger transaction amount must be greater than zero.');
            }

            /** @var User|null $actor */
            $actor = $header['actor'] ?? null;

            $txn = new LedgerTransaction;
            $txn->forceFill([
                'reference' => $this->generateReference(),
                'idempotency_key' => $idempotencyKey,
                'type' => $type,
                'currency' => $currency,
                'description' => $header['description'] ?? null,
                'order_id' => $header['order_id'] ?? null,
                'payment_transaction_id' => $header['payment_transaction_id'] ?? null,
                'payment_refund_id' => $header['payment_refund_id'] ?? null,
                'vendor_id' => $header['vendor_id'] ?? null,
                'actor_user_id' => $actor?->id,
                'reverses_transaction_id' => $header['reverses_transaction_id'] ?? null,
                'metadata' => $header['metadata'] ?? null,
                'posted_at' => now(),
            ])->save();

            foreach ($normalized as $row) {
                $entry = new LedgerEntry;
                $entry->forceFill([
                    'ledger_transaction_id' => $txn->id,
                    'ledger_account_id' => $row['account']->id,
                    'debit' => $row['debit'],
                    'credit' => $row['credit'],
                    'currency' => $currency,
                    'vendor_id' => $row['vendor_id'],
                ])->save();
            }

            $this->audit->log(
                action: 'LEDGER_TRANSACTION_CREATED',
                actor: $actor,
                resourceType: 'ledger_transaction',
                resourceId: $txn->id,
                newValues: [
                    'reference' => $txn->reference,
                    'type' => $type,
                    'currency' => $currency,
                    'amount' => $debitTotal,
                    'order_id' => $header['order_id'] ?? null,
                    'vendor_id' => $header['vendor_id'] ?? null,
                ],
            );

            return $txn->fresh('entries.account');
        });
    }

    public function accountByCode(string $code): LedgerAccount
    {
        $account = LedgerAccount::query()->where('code', $code)->first();
        if (! $account) {
            throw new InvalidArgumentException("Unknown ledger account: {$code}");
        }

        return $account;
    }

    /**
     * Credit balance for VENDOR_PAYABLE lines tagged with vendor_id (credits − debits).
     */
    public function vendorPayableBalance(int $vendorId, string $currency = 'TZS'): string
    {
        $account = $this->accountByCode('VENDOR_PAYABLE');

        $row = LedgerEntry::query()
            ->where('ledger_account_id', $account->id)
            ->where('vendor_id', $vendorId)
            ->where('currency', $currency)
            ->selectRaw('COALESCE(SUM(credit),0) as credits, COALESCE(SUM(debit),0) as debits')
            ->first();

        $credits = $this->payments->normalizeMoney($row->credits ?? '0');
        $debits = $this->payments->normalizeMoney($row->debits ?? '0');
        $balance = bcsub($credits, $debits, 2);

        return bccomp($balance, '0.00', 2) < 0 ? '0.00' : $balance;
    }

    protected function generateReference(): string
    {
        do {
            $reference = 'LDG-'.strtoupper(Str::random(12));
        } while (LedgerTransaction::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
