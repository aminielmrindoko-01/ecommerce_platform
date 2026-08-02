<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Authorization for catalog product mutations.
 *
 * Storefront create/update/delete is restricted to admins. Vendors are not
 * linked to products via user_id yet, so vendor self-service is deferred.
 */
class ProductPolicy
{
    /**
     * Anyone may browse the catalog.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Anyone may view a single product page.
     */
    public function view(?User $user, Product $product): bool
    {
        return true;
    }

    /**
     * Only admins may create products via the storefront CRUD routes.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Only admins may update products.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }

    /**
     * Only admins may delete products.
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }
}
