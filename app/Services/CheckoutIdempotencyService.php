<?php

namespace App\Services;

use App\Models\CheckoutIdempotencyKey;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutIdempotencyService
{
    /**
     * Issue a cryptographically random one-time checkout token for the user.
     */
    public function issue(User|int $user): string
    {
        $userId = $user instanceof User ? (int) $user->id : $user;
        $token = Str::random(64);

        CheckoutIdempotencyKey::query()->create([
            'token' => $token,
            'user_id' => $userId,
        ]);

        return $token;
    }

    /**
     * Lock the idempotency row and ensure it is still consumable.
     * Must be called inside an open database transaction that will create the order.
     *
     * @throws \InvalidArgumentException
     */
    public function lockConsumable(string $token, int $userId): CheckoutIdempotencyKey
    {
        $row = CheckoutIdempotencyKey::query()
            ->where('token', $token)
            ->lockForUpdate()
            ->first();

        if (! $row || (int) $row->user_id !== $userId) {
            throw new \InvalidArgumentException('Invalid or expired checkout token. Please reload checkout and try again.');
        }

        if ($row->consumed_at !== null) {
            throw new \InvalidArgumentException('This checkout submission was already processed.');
        }

        return $row;
    }

    /**
     * Atomically mark the locked token as consumed and attach the created order.
     * Must be called while still holding the row lock from lockConsumable().
     */
    public function markConsumed(CheckoutIdempotencyKey $row, int $orderId): void
    {
        $row->forceFill([
            'consumed_at' => now(),
            'order_id' => $orderId,
        ])->save();
    }

    /**
     * Test helper: atomically try to consume a token without creating an order.
     * Returns true only for the first successful consumer.
     */
    public function tryConsumeForTest(string $token, int $userId): bool
    {
        return (bool) DB::transaction(function () use ($token, $userId) {
            try {
                $row = $this->lockConsumable($token, $userId);
            } catch (\InvalidArgumentException) {
                return false;
            }

            $row->forceFill(['consumed_at' => now()])->save();

            return true;
        });
    }
}
