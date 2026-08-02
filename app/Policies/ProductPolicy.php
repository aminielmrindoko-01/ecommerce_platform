<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Authorization for catalog product mutations.
 *
 * Admins may manage any product. Vendors may create products for their own
 * store and update/delete only products where products.vendor_id matches.
 */
class ProductPolicy
{
    /**
     * Anyone may browse the public catalog.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Anyone may view a single public product page.
     */
    public function view(?User $user, Product $product): bool
    {
        return true;
    }

    /**
     * Admins and vendors with a linked store may create products.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || ($user->isVendor() && $user->vendor !== null);
    }

    /**
     * Admin or owning vendor may update.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->isAdmin() || $this->owns($user, $product);
    }

    /**
     * Admin or owning vendor may delete.
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->isAdmin() || $this->owns($user, $product);
    }

    /**
     * Vendor ownership via products.vendor_id === auth user's vendor.id.
     */
    protected function owns(User $user, Product $product): bool
    {
        return $user->isVendor()
            && $user->vendor
            && (int) $product->vendor_id === (int) $user->vendor->id;
    }
}
