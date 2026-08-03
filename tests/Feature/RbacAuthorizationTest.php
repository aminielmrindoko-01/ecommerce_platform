<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Authorization\RoleAssignmentService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

/**
 * Enterprise RBAC / ownership / IDOR authorization suite.
 */
class RbacAuthorizationTest extends TestCase
{
    use CreatesMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    protected function assignRole(User $user, string $roleName, string $legacyRole): User
    {
        $user->forceFill(['role' => $legacyRole, 'is_active' => true])->save();
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }

    public function test_customer_can_access_own_order_not_others(): void
    {
        $customer = $this->assignRole(User::factory()->create(), 'customer', 'customer');
        $other = $this->assignRole(User::factory()->create(), 'customer', 'customer');
        [, $store] = $this->createVendorUser();
        $product = $this->createProductForVendor($store);

        $own = Order::create([
            'order_number' => 'RBAC-OWN',
            'user_id' => $customer->id,
            'total_price' => 1000,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'shipping_address' => ['full_name' => 'A'],
        ]);
        OrderItem::recordPurchase($own->id, $product->id, 1, 1000);

        $foreign = Order::create([
            'order_number' => 'RBAC-OTHER',
            'user_id' => $other->id,
            'total_price' => 1000,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'shipping_address' => ['full_name' => 'B'],
        ]);

        $this->actingAs($customer)->get(route('account.orders.show', $own))->assertOk();
        $this->actingAs($customer)->get(route('account.orders.show', $foreign))->assertForbidden();
        $this->actingAs($customer)->get('/admin')->assertForbidden();
    }

    public function test_customer_cannot_delete_another_customers_address(): void
    {
        $customer = $this->assignRole(User::factory()->create(), 'customer', 'customer');
        $other = $this->assignRole(User::factory()->create(), 'customer', 'customer');

        $address = new Address([
            'label' => 'Home',
            'full_name' => 'Other',
            'phone' => '+255700000010',
            'line1' => 'Street',
            'city' => 'Dar',
            'region' => 'DSM',
            'is_default' => true,
        ]);
        $address->user_id = $other->id;
        $address->save();

        $this->actingAs($customer)
            ->delete(route('account.addresses.destroy', $address))
            ->assertForbidden();

        $this->assertDatabaseHas('addresses', ['id' => $address->id]);
    }

    public function test_vendor_cannot_update_another_vendors_product(): void
    {
        [$vendorA, $storeA] = $this->createVendorUser(['email' => 'rbac-va@example.com']);
        [$vendorB, $storeB] = $this->createVendorUser(['email' => 'rbac-vb@example.com']);
        $this->assignRole($vendorA, 'vendor', 'vendor');
        $this->assignRole($vendorB, 'vendor', 'vendor');

        $productB = $this->createProductForVendor($storeB, ['name' => 'B Product', 'price' => 2000]);

        $this->actingAs($vendorA)
            ->put(route('vendor.products.update', $productB), [
                'name' => 'Hacked',
                'price' => 1,
                'stock' => 1,
                'description' => 'x',
            ])
            ->assertForbidden();

        $this->assertNotSame('Hacked', $productB->fresh()->name);
        $this->actingAs($vendorA)->get('/admin')->assertForbidden();
        $this->actingAs($vendorA)->get(route('admin.roles'))->assertForbidden();
    }

    public function test_product_manager_can_manage_products_but_not_roles(): void
    {
        $manager = $this->assignRole(User::factory()->create(), 'product_manager', 'admin');

        $this->actingAs($manager)->get(route('admin.products.index'))->assertOk();
        $this->actingAs($manager)->get(route('admin.categories.index'))->assertOk();
        $this->actingAs($manager)->get(route('admin.roles'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.orders'))->assertForbidden();
        $this->assertFalse($manager->hasPermission('payments.manage'));
        $this->assertFalse($manager->hasPermission('roles.update'));
    }

    public function test_inventory_manager_can_view_inventory_not_users(): void
    {
        $manager = $this->assignRole(User::factory()->create(), 'inventory_manager', 'admin');

        $this->actingAs($manager)->get(route('admin.inventory.index'))->assertOk();
        $this->actingAs($manager)->get(route('admin.users'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.roles'))->assertForbidden();
    }

    public function test_review_moderator_can_moderate_not_manage_users_or_refunds(): void
    {
        $mod = $this->assignRole(User::factory()->create(), 'review_moderator', 'admin');
        [, $store] = $this->createVendorUser();
        $product = $this->createProductForVendor($store);

        $review = Review::create([
            'product_id' => $product->id,
            'user_id' => null,
            'author_name' => 'Anon',
            'rating' => 3,
            'title' => 'Ok',
            'body' => 'Fine product',
        ]);
        $review->forceFill(['status' => 'PENDING'])->save();

        $this->actingAs($mod)->get(route('admin.reviews'))->assertOk();
        $this->actingAs($mod)->patch(route('admin.reviews.moderate', $review), [
            'status' => 'APPROVED',
            'moderation_reason' => 'Looks fine',
        ])->assertRedirect();

        $this->assertSame('APPROVED', $review->fresh()->status);
        $this->assertSame('Fine product', $review->fresh()->body);
        $this->assertDatabaseHas('audit_logs', ['action' => 'REVIEW_APPROVED']);

        $this->actingAs($mod)->get(route('admin.users'))->assertForbidden();
        $this->actingAs($mod)->get(route('admin.roles'))->assertForbidden();
    }

    public function test_inactive_user_is_denied_admin_access(): void
    {
        $admin = $this->assignRole(User::factory()->admin()->create(), 'super_admin', 'admin');
        $admin->forceFill(['is_active' => false])->save();

        $this->actingAs($admin)->get('/admin')->assertForbidden();
    }

    public function test_mass_assignment_cannot_set_role_via_profile(): void
    {
        $customer = $this->assignRole(User::factory()->create(), 'customer', 'customer');

        $this->actingAs($customer)->put(route('account.profile.update'), [
            'name' => 'New Name',
            'email' => $customer->email,
            'phone' => '+255700000099',
            'role' => 'admin',
            'is_admin' => true,
        ])->assertRedirect();

        $this->assertSame('customer', $customer->fresh()->role);
        $this->assertFalse($customer->fresh()->isAdmin());
    }

    public function test_cannot_remove_last_super_admin(): void
    {
        $super = $this->assignRole(User::factory()->admin()->create(), 'super_admin', 'admin');
        $target = $super;

        $this->expectException(\InvalidArgumentException::class);
        app(RoleAssignmentService::class)->syncRoles($super, $target, ['customer'], 'customer');
    }

    public function test_permission_denied_creates_security_event(): void
    {
        $customer = $this->assignRole(User::factory()->create(), 'customer', 'customer');

        $this->actingAs($customer)->get('/admin')->assertForbidden();

        $this->assertTrue(
            SecurityEvent::query()->where('event', 'PERMISSION_DENIED')->exists()
            || AuditLog::query()->where('action', 'PERMISSION_DENIED')->exists()
        );
    }

    public function test_legacy_admin_factory_still_accesses_dashboard_without_explicit_seed_roles_on_user(): void
    {
        // Fresh admin with only legacy role — bridge must grant admin.access.
        $admin = User::factory()->admin()->create();
        // Detach any auto roles if present.
        $admin->roles()->detach();

        $this->assertTrue($admin->hasPermission('admin.access'));
        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_auditor_is_read_only_for_roles_module(): void
    {
        $auditor = $this->assignRole(User::factory()->create(), 'auditor', 'admin');

        $this->actingAs($auditor)->get(route('admin.audit-logs'))->assertOk();
        $this->actingAs($auditor)->get(route('admin.security-events'))->assertOk();
        $this->actingAs($auditor)->get(route('admin.users'))->assertForbidden();
        $this->actingAs($auditor)->put('/admin/users/'.$auditor->id, ['role' => 'admin'])->assertForbidden();
        $this->assertFalse($auditor->hasPermission('users.update'));
        $this->assertFalse($auditor->hasPermission('payments.manage'));
    }
}
