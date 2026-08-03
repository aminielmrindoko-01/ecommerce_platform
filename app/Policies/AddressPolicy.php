<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;

/**
 * Customer address ownership (IDOR protection).
 */
class AddressPolicy
{
    public function view(User $user, Address $address): bool
    {
        return $user->isActiveAccount()
            && $user->hasPermission('addresses.view')
            && (int) $address->user_id === (int) $user->id;
    }

    public function update(User $user, Address $address): bool
    {
        return $user->isActiveAccount()
            && $user->hasPermission('addresses.manage')
            && (int) $address->user_id === (int) $user->id;
    }

    public function delete(User $user, Address $address): bool
    {
        return $this->update($user, $address);
    }
}
