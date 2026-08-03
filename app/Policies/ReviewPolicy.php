<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

/**
 * Review moderation authorization. Moderators cannot edit original content.
 */
class ReviewPolicy
{
    public function view(User $user, Review $review): bool
    {
        return $user->isActiveAccount() && $user->hasPermission('reviews.view');
    }

    public function moderate(User $user, Review $review): bool
    {
        return $user->isActiveAccount() && $user->hasPermission('reviews.moderate');
    }

    public function approve(User $user, Review $review): bool
    {
        return $user->isActiveAccount() && $user->hasPermission('reviews.approve');
    }

    public function reject(User $user, Review $review): bool
    {
        return $user->isActiveAccount() && $user->hasPermission('reviews.reject');
    }

    public function hide(User $user, Review $review): bool
    {
        return $user->isActiveAccount() && $user->hasPermission('reviews.hide');
    }

    public function restore(User $user, Review $review): bool
    {
        return $user->isActiveAccount() && $user->hasPermission('reviews.restore');
    }

    public function flag(User $user, Review $review): bool
    {
        return $user->isActiveAccount() && $user->hasPermission('reviews.flag');
    }
}
