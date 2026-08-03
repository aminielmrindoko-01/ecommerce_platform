<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Authorization\PermissionResolver;
use App\Services\Catalog\InventoryService;
use App\Services\CheckoutIdempotencyService;
use App\Services\Orders\OrderService;
use App\Support\Marketplace;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

/**
 * Phase 4: multi-vendor marketplace orders, vendor lifecycle, cart/price security.
 */
class MarketplaceOrdersPhase4Test extends TestCase
{
    use CreatesMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    protected function assign(User $user, string $rbac, string $legacy = 'admin'): User
    {
        $user->forceFill(['role' => $legacy, 'is_active' => true])->save();
        $role = Role::query()->where('name', $rbac)->firstOrFail();
        $user->roles()->sync([$role->id]);
        app(PermissionResolver::class)->forget($user);

        return $user->fresh();
    }

    public function test_vendor_application_and_approval_lifecycle(): void
    {
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $manager = $this->assign(User::factory()->create(), 'vendor_manager');

        $this->actingAs($customer)
            ->post(route('vendor.apply.store'), [
                'store_name' => 'Sana Fresh Store',
                'description' => 'Local produce',
                'application_notes' => 'Please review',
            ])
            ->assertRedirect(route('account.index'));

        $vendor = Vendor::where('user_id', $customer->id)->first();
        $this->assertNotNull($vendor);
        $this->assertSame('pending', $vendor->status);
        $this->assertFalse($vendor->is_verified);

        // Pending store cannot sell even if marketplace role is forced.
        $customer->forceFill(['role' => 'vendor'])->save();
        $this->actingAs($customer->fresh())
            ->get(route('vendor.dashboard'))
            ->assertStatus(403);

        $this->actingAs($manager)
            ->post(route('admin.vendors.status', $vendor), [
                'status' => 'approved',
                'notes' => 'Looks good',
            ])
            ->assertRedirect();

        $vendor->refresh();
        $this->assertSame('approved', $vendor->status);
        $this->assertTrue($vendor->is_verified);

        $customer = $customer->fresh();
        app(PermissionResolver::class)->forget($customer);
        $this->assertTrue($customer->hasPermission('vendor.access'));
        $this->actingAs($customer)
            ->get(route('vendor.dashboard'))
            ->assertOk();

        $this->assertTrue(
            AuditLog::query()->where('action', 'VENDOR_APPROVED')->where('resource_id', $vendor->id)->exists()
        );
    }

    public function test_vendor_manager_cannot_assign_roles(): void
    {
        $manager = $this->assign(User::factory()->create(), 'vendor_manager');

        $this->assertTrue($manager->hasPermission('vendors.approve'));
        $this->assertFalse($manager->hasPermission('roles.update'));
        $this->assertFalse($manager->hasPermission('permissions.assign'));
        $this->assertFalse($manager->hasPermission('payouts.process'));
    }

    public function test_multi_vendor_order_preserves_item_snapshots_and_isolates_vendors(): void
    {
        [$vendorAUser, $vendorA] = $this->createVendorUser();
        [$vendorBUser, $vendorB] = $this->createVendorUser();
        $productA = $this->createProductForVendor($vendorA, ['name' => 'Alpha Lamp', 'price' => 10000, 'stock' => 5, 'sku' => 'A-1']);
        $productB = $this->createProductForVendor($vendorB, ['name' => 'Beta Chair', 'price' => 20000, 'stock' => 5, 'sku' => 'B-1']);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');

        $this->actingAs($customer)
            ->withSession([
                'cart' => [
                    $productA->id => ['name' => 'hacked', 'price' => 1, 'quantity' => 1],
                    $productB->id => ['name' => 'hacked', 'price' => 1, 'quantity' => 2],
                ],
            ])
            ->post(route('checkout.place'), [
                'full_name' => 'Buyer One',
                'phone' => '+255700000001',
                'line1' => 'Street 1',
                'city' => 'Dar es Salaam',
                'region' => 'Dar',
                'payment_method' => 'cod',
                'shipping_method' => 'pickup',
                'checkout_token' => app(CheckoutIdempotencyService::class)->issue($customer->id),
                'price' => 1,
                'subtotal' => 1,
                'total' => 1,
                'vendor_id' => $vendorB->id,
                'user_id' => 999999,
                'customer_id' => 999999,
                'status' => 'delivered',
            ])
            ->assertStatus(422);

        $this->actingAs($customer)
            ->withSession([
                'cart' => [
                    $productA->id => ['name' => 'hacked', 'price' => 1, 'quantity' => 1],
                    $productB->id => ['name' => 'hacked', 'price' => 1, 'quantity' => 2],
                ],
            ])
            ->post(route('checkout.place'), [
                'full_name' => 'Buyer One',
                'phone' => '+255700000001',
                'line1' => 'Street 1',
                'city' => 'Dar es Salaam',
                'region' => 'Dar',
                'payment_method' => 'cod',
                'shipping_method' => 'pickup',
                'checkout_token' => app(CheckoutIdempotencyService::class)->issue($customer->id),
            ])
            ->assertRedirect();

        $order = Order::where('user_id', $customer->id)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->status);
        $this->assertSame('TZS', $order->currency);
        $this->assertSame(2, $order->items()->count());

        $itemA = $order->items()->where('product_id', $productA->id)->first();
        $itemB = $order->items()->where('product_id', $productB->id)->first();
        $this->assertSame($vendorA->id, $itemA->vendor_id);
        $this->assertSame($vendorB->id, $itemB->vendor_id);
        $this->assertSame('Alpha Lamp', $itemA->product_name);
        $this->assertSame('Beta Chair', $itemB->product_name);
        $this->assertEquals('10000.00', number_format((float) $itemA->price, 2, '.', ''));
        $this->assertEquals('20000.00', number_format((float) $itemB->price, 2, '.', ''));

        $expectedSubtotal = 50000.0;
        $tax = round($expectedSubtotal * (float) Marketplace::taxRate(), 2);
        $this->assertEquals(
            number_format($expectedSubtotal + $tax, 2, '.', ''),
            number_format((float) $order->total_price, 2, '.', '')
        );

        $this->actingAs($vendorAUser)
            ->get(route('vendor.orders.show', $order))
            ->assertOk()
            ->assertSee('Alpha Lamp')
            ->assertDontSee('Beta Chair');

        $this->actingAs($vendorBUser)
            ->get(route('vendor.orders.show', $order))
            ->assertOk()
            ->assertSee('Beta Chair')
            ->assertDontSee('Alpha Lamp');

        $other = $this->assign(User::factory()->create(), 'customer', 'customer');
        $this->actingAs($other)
            ->get(route('account.orders.show', $order))
            ->assertStatus(403);

        $this->assertSame(4, (int) $productA->fresh()->stock);
        $this->assertSame(3, (int) $productB->fresh()->stock);
        $this->assertTrue(
            InventoryMovement::query()->where('product_id', $productA->id)->where('type', 'reserve')->exists()
        );
        $this->assertTrue(
            InventoryMovement::query()->where('product_id', $productA->id)->where('type', 'sale')->exists()
        );
        $this->assertTrue(
            AuditLog::query()->where('action', 'ORDER_CREATED')->where('resource_id', $order->id)->exists()
        );
    }

    public function test_customer_can_cancel_pending_order_and_stock_is_restored(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['stock' => 3, 'price' => 5000]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');

        $order = app(OrderService::class)->place($customer, [
            $product->id => ['quantity' => 2],
        ], [
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'checkout_token' => app(CheckoutIdempotencyService::class)->issue($customer->id),
            'shipping_address' => [
                'full_name' => 'Buyer',
                'phone' => '+255700000002',
                'line1' => 'A',
                'city' => 'Dar',
                'country' => 'Tanzania',
            ],
        ]);

        $this->assertSame(1, (int) $product->fresh()->stock);

        $this->actingAs($customer)
            ->post(route('account.orders.cancel', $order), ['reason' => 'Changed mind'])
            ->assertRedirect(route('account.orders.show', $order));

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertTrue(
            AuditLog::query()->where('action', 'ORDER_CANCELLED')->where('resource_id', $order->id)->exists()
        );
    }

    public function test_customer_cannot_cancel_shipped_order_or_manipulate_status(): void
    {
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $order = new Order([
            'order_number' => 'SN-TEST1',
            'total_price' => 1000,
            'status' => 'shipped',
            'payment_status' => 'paid',
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'shipping_cost' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'shipping_address' => ['full_name' => 'X', 'phone' => '1', 'line1' => 'A', 'city' => 'Dar'],
        ]);
        $order->user_id = $customer->id;
        $order->save();

        $this->actingAs($customer)
            ->post(route('account.orders.cancel', $order))
            ->assertStatus(403);

        $this->actingAs($customer)
            ->put(route('admin.orders.update', $order->id), ['status' => 'delivered'])
            ->assertStatus(403);
    }

    public function test_order_state_machine_rejects_invalid_transitions_and_paid_status(): void
    {
        $admin = $this->assign(User::factory()->create(), 'admin');
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $order = new Order([
            'order_number' => 'SN-TEST2',
            'total_price' => 1000,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'shipping_cost' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'shipping_address' => ['full_name' => 'X', 'phone' => '1', 'line1' => 'A', 'city' => 'Dar'],
        ]);
        $order->user_id = $customer->id;
        $order->save();

        $this->actingAs($admin)
            ->from(route('admin.orders'))
            ->put(route('admin.orders.update', $order->id), ['status' => 'shipped'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('pending', $order->fresh()->status);

        $this->actingAs($admin)
            ->from(route('admin.orders'))
            ->put(route('admin.orders.update', $order->id), ['status' => 'paid'])
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)
            ->put(route('admin.orders.update', $order->id), ['status' => 'confirmed'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertTrue(
            AuditLog::query()->where('action', 'ORDER_CONFIRMED')->where('resource_id', $order->id)->exists()
        );
    }

    public function test_order_manager_can_update_orders_but_not_roles(): void
    {
        $manager = $this->assign(User::factory()->create(), 'order_manager');

        $this->assertTrue($manager->hasPermission('orders.update'));
        $this->assertTrue($manager->hasPermission('orders.manage_any'));
        $this->assertFalse($manager->hasPermission('roles.update'));
        $this->assertFalse($manager->hasPermission('permissions.assign'));
        $this->assertFalse($manager->hasPermission('users.update'));
    }

    public function test_customer_support_cannot_manage_roles_or_users(): void
    {
        $support = $this->assign(User::factory()->create(), 'customer_support');

        $this->assertTrue($support->hasPermission('orders.view'));
        $this->assertTrue($support->hasPermission('customers.view'));
        $this->assertFalse($support->hasPermission('orders.update'));
        $this->assertFalse($support->hasPermission('roles.update'));
        $this->assertFalse($support->hasPermission('users.update'));
        $this->assertFalse($support->hasPermission('permissions.assign'));
    }

    public function test_inventory_reserve_prevents_oversell_under_lock(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['stock' => 1]);
        $inventory = app(InventoryService::class);
        $customer = User::factory()->create();

        $inventory->reserve($product, 1, $customer, 'race-a');

        $this->expectException(\InvalidArgumentException::class);
        $inventory->reserve($product->fresh(), 1, $customer, 'race-b');
    }

    public function test_concurrent_checkout_only_one_succeeds_with_stock_one(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['stock' => 1, 'price' => 8000]);
        $a = $this->assign(User::factory()->create(), 'customer', 'customer');
        $b = $this->assign(User::factory()->create(), 'customer', 'customer');
        $orders = app(OrderService::class);

        $payload = [
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'shipping_address' => [
                'full_name' => 'Buyer',
                'phone' => '+255700000099',
                'line1' => 'A',
                'city' => 'Dar',
                'country' => 'Tanzania',
            ],
        ];

        $ok = 0;
        $fail = 0;
        try {
            $orders->place($a, [$product->id => 1], $payload + [
                'checkout_token' => app(CheckoutIdempotencyService::class)->issue($a->id),
            ]);
            $ok++;
        } catch (\Throwable) {
            $fail++;
        }
        try {
            $orders->place($b, [$product->id => 1], $payload + [
                'checkout_token' => app(CheckoutIdempotencyService::class)->issue($b->id),
            ]);
            $ok++;
        } catch (\Throwable) {
            $fail++;
        }

        $this->assertSame(1, $ok);
        $this->assertSame(1, $fail);
        $this->assertSame(0, (int) $product->fresh()->stock);
        $this->assertSame(1, Order::count());
    }

    public function test_vendor_cannot_access_other_vendor_order_item_fulfillment(): void
    {
        [$vendorAUser] = $this->createVendorUser();
        [$vendorBUser, $vendorB] = $this->createVendorUser();
        $productB = $this->createProductForVendor($vendorB, ['stock' => 2]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');

        $order = app(OrderService::class)->place($customer, [
            $productB->id => 1,
        ], [
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'checkout_token' => app(CheckoutIdempotencyService::class)->issue($customer->id),
            'shipping_address' => [
                'full_name' => 'Buyer',
                'phone' => '+255700000003',
                'line1' => 'A',
                'city' => 'Dar',
                'country' => 'Tanzania',
            ],
        ]);

        $item = $order->items()->first();

        $this->actingAs($vendorAUser)
            ->patch(route('vendor.orders.items.fulfillment', [$order, $item]), [
                'fulfillment_status' => 'confirmed',
            ])
            ->assertStatus(403);

        $this->actingAs($vendorBUser)
            ->patch(route('vendor.orders.items.fulfillment', [$order, $item]), [
                'fulfillment_status' => 'confirmed',
            ])
            ->assertRedirect();
    }
}
