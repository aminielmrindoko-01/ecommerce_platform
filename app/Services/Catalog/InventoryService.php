<?php

namespace App\Services\Catalog;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Services\Authorization\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Authoritative inventory mutations against products.stock (+ reserved_quantity).
 * All adjustments are transactional, locked, and audited.
 */
class InventoryService
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    /**
     * Adjust available stock by delta. Prevents negative available quantity.
     */
    public function adjust(
        Product $product,
        int $delta,
        string $reason,
        ?User $actor = null,
        string $type = InventoryMovement::TYPE_ADJUSTMENT,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): Product {
        if ($delta === 0) {
            throw new InvalidArgumentException('Adjustment delta cannot be zero.');
        }

        $reason = trim($reason);
        if ($reason === '' || strlen($reason) > 500) {
            throw new InvalidArgumentException('A valid reason is required (max 500 characters).');
        }

        return DB::transaction(function () use ($product, $delta, $reason, $actor, $type, $referenceType, $referenceId) {
            /** @var Product $locked */
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $before = (int) $locked->stock;
            $after = $before + $delta;
            if ($after < 0) {
                throw new InvalidArgumentException('Inventory cannot go below zero.');
            }

            $reservedBefore = (int) ($locked->reserved_quantity ?? 0);
            $locked->stock = $after;
            $locked->save();

            InventoryMovement::query()->create([
                'product_id' => $locked->id,
                'actor_user_id' => $actor?->id,
                'type' => $type,
                'quantity_before' => $before,
                'quantity_delta' => $delta,
                'quantity_after' => $after,
                'reserved_before' => $reservedBefore,
                'reserved_after' => $reservedBefore,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_at' => now(),
            ]);

            $this->audit->log(
                action: 'INVENTORY_ADJUSTED',
                actor: $actor,
                resourceType: 'product',
                resourceId: $locked->id,
                oldValues: ['stock' => $before, 'reserved_quantity' => $reservedBefore],
                newValues: [
                    'stock' => $after,
                    'delta' => $delta,
                    'type' => $type,
                    'reason' => $reason,
                ],
                category: 'business',
            );

            return $locked->fresh();
        });
    }

    /**
     * Set absolute available stock (used for controlled product create/update).
     */
    public function setAvailable(
        Product $product,
        int $quantity,
        string $reason,
        ?User $actor = null,
        string $type = InventoryMovement::TYPE_ADJUSTMENT,
    ): Product {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Stock cannot be negative.');
        }

        $delta = $quantity - (int) $product->stock;
        if ($delta === 0) {
            return $product;
        }

        return $this->adjust($product, $delta, $reason, $actor, $type);
    }

    /**
     * Integration point for Orders phase — reserve stock without committing sale.
     * Not wired to checkout yet; documented for future use.
     */
    public function reserve(Product $product, int $qty, ?User $actor = null, ?string $referenceId = null): Product
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Reserve quantity must be positive.');
        }

        return DB::transaction(function () use ($product, $qty, $actor, $referenceId) {
            /** @var Product $locked */
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $available = (int) $locked->stock;
            $reserved = (int) ($locked->reserved_quantity ?? 0);

            if ($available < $qty) {
                throw new InvalidArgumentException('Insufficient available stock to reserve.');
            }

            $locked->stock = $available - $qty;
            $locked->reserved_quantity = $reserved + $qty;
            $locked->save();

            InventoryMovement::query()->create([
                'product_id' => $locked->id,
                'actor_user_id' => $actor?->id,
                'type' => InventoryMovement::TYPE_RESERVE,
                'quantity_before' => $available,
                'quantity_delta' => -$qty,
                'quantity_after' => (int) $locked->stock,
                'reserved_before' => $reserved,
                'reserved_after' => (int) $locked->reserved_quantity,
                'reason' => 'Stock reserved for order',
                'reference_type' => 'order',
                'reference_id' => $referenceId,
                'created_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function releaseReserved(Product $product, int $qty, ?User $actor = null, ?string $referenceId = null): Product
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Release quantity must be positive.');
        }

        return DB::transaction(function () use ($product, $qty, $actor, $referenceId) {
            /** @var Product $locked */
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $available = (int) $locked->stock;
            $reserved = (int) ($locked->reserved_quantity ?? 0);

            if ($reserved < $qty) {
                throw new InvalidArgumentException('Cannot release more than reserved.');
            }

            $locked->stock = $available + $qty;
            $locked->reserved_quantity = $reserved - $qty;
            $locked->save();

            InventoryMovement::query()->create([
                'product_id' => $locked->id,
                'actor_user_id' => $actor?->id,
                'type' => InventoryMovement::TYPE_RELEASE,
                'quantity_before' => $available,
                'quantity_delta' => $qty,
                'quantity_after' => (int) $locked->stock,
                'reserved_before' => $reserved,
                'reserved_after' => (int) $locked->reserved_quantity,
                'reason' => 'Reserved stock released',
                'reference_type' => 'order',
                'reference_id' => $referenceId,
                'created_at' => now(),
            ]);

            return $locked->fresh();
        });
    }
}
