<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Catalog product authorization: permission + ownership.
 *
 * Platform staff with products.manage_any may mutate any product.
 * Vendors require products.* permission AND products.vendor_id ownership.
 */
class ProductPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Product $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        if (! $user->isActiveAccount()) {
            return false;
        }

        if ($user->hasPermission('products.manage_any') && $user->hasPermission('products.create')) {
            return true;
        }

        return $user->hasPermission('products.create')
            && $user->isVendor()
            && $user->vendor !== null;
    }

    public function update(User $user, Product $product): bool
    {
        if (! $user->isActiveAccount() || ! $user->hasPermission('products.update')) {
            return false;
        }

        if ($user->hasPermission('products.manage_any')) {
            return true;
        }

        return $this->owns($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        if (! $user->isActiveAccount() || ! $user->hasPermission('products.delete')) {
            return false;
        }

        if ($user->hasPermission('products.manage_any')) {
            return true;
        }

        return $this->owns($user, $product);
    }

    public function publish(User $user, Product $product): bool
    {
        if (! $user->isActiveAccount() || ! $user->hasPermission('products.publish')) {
            return false;
        }

        if ($user->hasPermission('products.manage_any')) {
            return true;
        }

        return $this->owns($user, $product);
    }

    public function unpublish(User $user, Product $product): bool
    {
        if (! $user->isActiveAccount() || ! $user->hasPermission('products.unpublish')) {
            return false;
        }

        if ($user->hasPermission('products.manage_any')) {
            return true;
        }

        return $this->owns($user, $product);
    }

    public function adjustInventory(User $user, Product $product): bool
    {
        if (! $user->isActiveAccount() || ! $user->hasPermission('inventory.adjust')) {
            return false;
        }

        if ($user->hasPermission('products.manage_any') || $user->hasPermission('admin.access')) {
            return true;
        }

        return $this->owns($user, $product);
    }

    protected function owns(User $user, Product $product): bool
    {
        return $user->isVendor()
            && $user->vendor
            && (int) $product->vendor_id === (int) $user->vendor->id;
    }
}
