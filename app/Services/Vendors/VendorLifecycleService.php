<?php

namespace App\Services\Vendors;

use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Authorization\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\RoleAssignmentService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Vendor marketplace lifecycle (application → review → approve/reject/suspend).
 */
class VendorLifecycleService
{
    public const STATUSES = [
        'pending',
        'under_review',
        'approved',
        'suspended',
        'rejected',
        'inactive',
    ];

    public function __construct(
        protected AuditLogger $audit,
        protected RoleAssignmentService $roles,
        protected PermissionResolver $permissions,
    ) {}

    /**
     * Customer applies to become a vendor (creates store in pending status).
     *
     * @param  array{store_name:string,email?:?string,description?:?string,location?:?string,application_notes?:?string}  $data
     */
    public function apply(User $user, array $data): Vendor
    {
        if ($user->vendor()->exists()) {
            throw new InvalidArgumentException('You already have a vendor store.');
        }

        return DB::transaction(function () use ($user, $data) {
            $vendor = new Vendor([
                'store_name' => $data['store_name'],
                'email' => $data['email'] ?? $user->email,
                'description' => $data['description'] ?? null,
                'location' => $data['location'] ?? null,
            ]);
            $vendor->user_id = $user->id;
            $vendor->forceFill([
                'status' => 'pending',
                'is_verified' => false,
                'application_notes' => $data['application_notes'] ?? null,
            ])->save();

            // Marketplace identity becomes vendor; RBAC vendor role for hub access after approval.
            // Keep customer role until approved — pending vendors cannot sell yet.
            $this->audit->log(
                action: 'VENDOR_APPLICATION_SUBMITTED',
                actor: $user,
                resourceType: 'vendor',
                resourceId: $vendor->id,
                newValues: ['store_name' => $vendor->store_name, 'status' => 'pending'],
                category: 'business',
            );

            return $vendor->fresh();
        });
    }

    public function transition(Vendor $vendor, string $nextStatus, User $actor, ?string $notes = null): Vendor
    {
        $nextStatus = strtolower(trim($nextStatus));
        if (! in_array($nextStatus, self::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid vendor status.');
        }

        $this->assertActorPermission($actor, $nextStatus);

        return DB::transaction(function () use ($vendor, $nextStatus, $actor, $notes) {
            /** @var Vendor $locked */
            $locked = Vendor::query()->whereKey($vendor->id)->lockForUpdate()->firstOrFail();
            $before = $locked->status ?: ($locked->is_verified ? 'approved' : 'pending');

            if ($before === $nextStatus) {
                return $locked;
            }

            $locked->forceFill([
                'status' => $nextStatus,
                'is_verified' => $nextStatus === 'approved',
                'reviewed_at' => now(),
                'reviewed_by' => $actor->id,
                'application_notes' => $notes ?? $locked->application_notes,
            ])->save();

            if ($nextStatus === 'approved' && $locked->user) {
                $this->roles->syncRoles($actor, $locked->user, ['vendor'], 'vendor');
            }

            if (in_array($nextStatus, ['suspended', 'rejected', 'inactive'], true) && $locked->user) {
                // Demote marketplace access but keep store record.
                $customerRole = Role::query()->where('name', 'customer')->first();
                if ($customerRole) {
                    $locked->user->roles()->sync([$customerRole->id]);
                    $locked->user->forceFill(['role' => 'customer'])->save();
                    $this->permissions->forget($locked->user);
                }
            }

            $action = match ($nextStatus) {
                'approved' => 'VENDOR_APPROVED',
                'rejected' => 'VENDOR_REJECTED',
                'suspended' => 'VENDOR_SUSPENDED',
                'under_review' => 'VENDOR_UNDER_REVIEW',
                'inactive' => 'VENDOR_INACTIVATED',
                default => 'VENDOR_STATUS_CHANGED',
            };

            $this->audit->log(
                action: $action,
                actor: $actor,
                resourceType: 'vendor',
                resourceId: $locked->id,
                oldValues: ['status' => $before, 'is_verified' => (bool) $vendor->is_verified],
                newValues: ['status' => $nextStatus, 'is_verified' => $nextStatus === 'approved'],
                reason: $notes,
                category: 'security',
            );

            return $locked->fresh();
        });
    }

    protected function assertActorPermission(User $actor, string $nextStatus): void
    {
        $needed = match ($nextStatus) {
            'approved' => 'vendors.approve',
            'rejected' => 'vendors.reject',
            'suspended', 'inactive' => 'vendors.suspend',
            'under_review', 'pending' => 'vendors.update',
            default => 'vendors.update',
        };

        if (! $actor->hasPermission($needed) && ! $actor->hasPermission('vendors.update')) {
            throw new InvalidArgumentException('Missing permission to change vendor status.');
        }
    }
}
