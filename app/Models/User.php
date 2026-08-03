<?php

namespace App\Models;

use App\Services\Authorization\PermissionResolver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Authenticatable marketplace user.
 *
 * Legacy `users.role` remains for marketplace identity (customer/vendor/admin).
 * Granular authorization is resolved via RBAC roles/permissions.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar',
        'password',
        // `role` intentionally excluded from mass assignment (privilege escalation).
        'is_active',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\Vendor, $this>
     */
    public function vendor()
    {
        return $this->hasOne(Vendor::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Order, $this>
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Address, $this>
     */
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Wishlist, $this>
     */
    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    /**
     * Permission check (deny by default via PermissionResolver).
     */
    public function hasPermission(string $permission): bool
    {
        return app(PermissionResolver::class)->has($this, $permission);
    }

    public function hasAnyPermission(string ...$permissions): bool
    {
        return app(PermissionResolver::class)->hasAny($this, $permissions);
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return app(PermissionResolver::class)->permissionsFor($this);
    }

    public function isActiveAccount(): bool
    {
        return $this->is_active !== false;
    }

    /**
     * Platform admin shell access (permission-based, legacy-compatible).
     */
    public function isAdmin(): bool
    {
        return $this->isActiveAccount() && (
            $this->hasPermission('admin.access')
            || $this->role === 'admin'
        );
    }

    /**
     * Marketplace vendor account flag (legacy role column).
     */
    public function isVendor(): bool
    {
        return $this->isActiveAccount() && $this->role === 'vendor';
    }

    public function hasVendorStore(): bool
    {
        return $this->isVendor() && $this->vendor()->exists();
    }

    public function isSuperAdmin(): bool
    {
        if (! $this->isActiveAccount()) {
            return false;
        }

        if ($this->roles()->where('name', 'super_admin')->exists()) {
            return true;
        }

        // Legacy bridge before RBAC rows exist.
        return $this->role === 'admin' && ! $this->roles()->exists();
    }

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
