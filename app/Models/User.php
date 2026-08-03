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
 * `users.role` is marketplace identity (customer|vendor|admin) — NOT an
 * authorization authority. Permissions come only from RBAC roles.
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
        // Sensitive authz fields intentionally excluded: role, mfa_*, is_active via admin only.
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

    /**
     * @return list<string>
     */
    public function roleNames(): array
    {
        return $this->roles()->pluck('name')->all();
    }

    public function isActiveAccount(): bool
    {
        return $this->is_active !== false;
    }

    /**
     * Platform admin shell access — permission only (no users.role override).
     */
    public function isAdmin(): bool
    {
        return $this->isActiveAccount() && $this->hasPermission('admin.access');
    }

    /**
     * Marketplace vendor identity (account type column) + active.
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
        return $this->isActiveAccount()
            && in_array('super_admin', $this->roleNames(), true);
    }

    public function requiresMfaEnrollment(): bool
    {
        $required = (array) config('authorization.mfa.required_roles', []);

        return count(array_intersect($this->roleNames(), $required)) > 0;
    }

    public function hasMfaEnabled(): bool
    {
        return (bool) $this->mfa_enabled && filled($this->mfa_secret);
    }

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
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
            'mfa_enabled' => 'boolean',
            'mfa_confirmed_at' => 'datetime',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
        ];
    }
}
