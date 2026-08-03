<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Category authorization — admin/staff permissions only (no tenant ownership).
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActiveAccount() && $user->hasPermission('categories.view');
    }

    public function view(User $user, Category $category): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isActiveAccount() && $user->hasPermission('categories.create');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->isActiveAccount() && $user->hasPermission('categories.update');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->isActiveAccount() && $user->hasPermission('categories.delete');
    }
}
