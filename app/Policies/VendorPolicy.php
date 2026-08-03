<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;

/**
 * Vendor store authorization: permission + ownership.
 */
class VendorPolicy
{
    public function view(User $user, Vendor $vendor): bool
    {
        if (! $user->isActiveAccount()) {
            return false;
        }

        if ($user->hasPermission('vendors.view')) {
            return true;
        }

        return $this->owns($user, $vendor);
    }

    public function create(User $user): bool
    {
        return $user->isActiveAccount() && $user->hasPermission('vendors.create');
    }

    public function update(User $user, Vendor $vendor): bool
    {
        if (! $user->isActiveAccount()) {
            return false;
        }

        if ($user->hasPermission('vendors.update') && $user->hasPermission('vendors.view')) {
            // Platform vendor managers / admins
            if ($user->hasPermission('vendors.approve') || $user->hasPermission('vendors.suspend') || $user->hasPermission('admin.access')) {
                return true;
            }
        }

        // Vendor self-profile via profile.update + ownership
        return $user->hasPermission('profile.update') && $this->owns($user, $vendor);
    }

    public function delete(User $user, Vendor $vendor): bool
    {
        return $user->isActiveAccount() && $user->hasPermission('vendors.suspend');
    }

    protected function owns(User $user, Vendor $vendor): bool
    {
        return $user->isVendor()
            && $user->vendor
            && (int) $user->vendor->id === (int) $vendor->id;
    }
}
