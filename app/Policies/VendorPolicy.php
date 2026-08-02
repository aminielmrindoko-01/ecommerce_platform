<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;

/**
 * Authorization for vendor store profile management.
 *
 * Admins may manage any vendor; vendors may manage only their own store.
 */
class VendorPolicy
{
    /**
     * Admin may view any vendor; a vendor may view their own store.
     */
    public function view(User $user, Vendor $vendor): bool
    {
        return $user->isAdmin() || $this->owns($user, $vendor);
    }

    /**
     * Only admins create vendor stores (seeders/admin flows).
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Vendor may update own profile; admin may update any.
     */
    public function update(User $user, Vendor $vendor): bool
    {
        return $user->isAdmin() || $this->owns($user, $vendor);
    }

    /**
     * Only admins delete vendor stores.
     */
    public function delete(User $user, Vendor $vendor): bool
    {
        return $user->isAdmin();
    }

    /**
     * Whether the user owns this vendor store.
     */
    protected function owns(User $user, Vendor $vendor): bool
    {
        return $user->isVendor()
            && $user->vendor
            && (int) $user->vendor->id === (int) $vendor->id;
    }
}
