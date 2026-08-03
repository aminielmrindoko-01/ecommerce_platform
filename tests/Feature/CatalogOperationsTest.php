<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Catalog\InventoryService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

/**
 * Phase 3 enterprise admin operations: products, categories, inventory.
 */
class CatalogOperationsTest extends TestCase
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

    public function test_product_manager_can_create_and_publish_product(): void
    {
        $manager = $this->assign(User::factory()->create(), 'product_manager');
        [, $store] = $this->createVendorUser();
        $category = Category::create(['name' => 'Phones', 'slug' => 'phones-'.uniqid()]);

        $this->actingAs($manager)->post(route('admin.products.store'), [
            'name' => 'Pixel Pro',
            'price' => 1500000,
            'stock' => 12,
            'vendor_id' => $store->id,
            'category_id' => $category->id,
            'status' => 'published',
            'sku' => 'PIX-PRO-1',
            'reorder_level' => 3,
        ])->assertRedirect();

        $product = Product::query()->where('sku', 'PIX-PRO-1')->first();
        $this->assertNotNull($product);
        $this->assertSame('published', $product->status);
        $this->assertSame(12, (int) $product->stock);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PRODUCT_CREATED']);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => 'initial',
            'quantity_after' => 12,
        ]);
    }

    public function test_customer_cannot_create_admin_product(): void
    {
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        [, $store] = $this->createVendorUser();

        $this->actingAs($customer)->post(route('admin.products.store'), [
            'name' => 'Hack',
            'price' => 100,
            'vendor_id' => $store->id,
            'stock' => 1,
        ])->assertForbidden();
    }

    public function test_duplicate_sku_is_rejected(): void
    {
        $manager = $this->assign(User::factory()->create(), 'admin');
        [, $store] = $this->createVendorUser();
        $this->createProductForVendor($store, ['sku' => 'DUP-1']);

        $this->actingAs($manager)->post(route('admin.products.store'), [
            'name' => 'Other',
            'price' => 1000,
            'vendor_id' => $store->id,
            'sku' => 'DUP-1',
            'stock' => 1,
            'status' => 'draft',
        ])->assertRedirect();

        $this->assertSame(1, Product::query()->where('sku', 'DUP-1')->count());
    }

    public function test_vendor_cannot_assign_another_vendor_id(): void
    {
        [$vendorA] = $this->createVendorUser(['email' => 'va-ops@example.com']);
        [, $storeB] = $this->createVendorUser(['email' => 'vb-ops@example.com']);
        $this->assign($vendorA, 'vendor', 'vendor');

        $this->actingAs($vendorA)->post(route('vendor.products.store'), [
            'name' => 'Mine',
            'price' => 5000,
            'stock' => 2,
            'vendor_id' => $storeB->id,
        ])->assertRedirect();

        $product = Product::query()->where('name', 'Mine')->first();
        $this->assertNotNull($product);
        $this->assertSame((int) $vendorA->vendor->id, (int) $product->vendor_id);
        $this->assertNotSame((int) $storeB->id, (int) $product->vendor_id);
    }

    public function test_inventory_manager_can_adjust_and_history_is_immutable(): void
    {
        $manager = $this->assign(User::factory()->create(), 'inventory_manager');
        [, $store] = $this->createVendorUser();
        $product = $this->createProductForVendor($store, ['stock' => 27, 'reorder_level' => 5, 'name' => 'Sony XM5']);

        $this->actingAs($manager)->post(route('admin.inventory.adjust', $product), [
            'delta' => -2,
            'reason' => 'Damaged items',
            'type' => 'damage',
        ])->assertRedirect();

        $this->assertSame(25, (int) $product->fresh()->stock);
        $this->assertDatabaseHas('audit_logs', ['action' => 'INVENTORY_ADJUSTED']);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'quantity_before' => 27,
            'quantity_delta' => -2,
            'quantity_after' => 25,
        ]);

        $movement = InventoryMovement::query()->first();
        $this->expectException(\RuntimeException::class);
        $movement->update(['reason' => 'forged']);
    }

    public function test_inventory_cannot_go_negative(): void
    {
        $manager = $this->assign(User::factory()->create(), 'inventory_manager');
        [, $store] = $this->createVendorUser();
        $product = $this->createProductForVendor($store, ['stock' => 2]);

        $this->actingAs($manager)->post(route('admin.inventory.adjust', $product), [
            'delta' => -5,
            'reason' => 'Over adjust',
        ])->assertRedirect();

        $this->assertSame(2, (int) $product->fresh()->stock);
    }

    public function test_product_manager_cannot_adjust_inventory(): void
    {
        $manager = $this->assign(User::factory()->create(), 'product_manager');
        [, $store] = $this->createVendorUser();
        $product = $this->createProductForVendor($store, ['stock' => 10]);

        $this->actingAs($manager)->post(route('admin.inventory.adjust', $product), [
            'delta' => -1,
            'reason' => 'Nope',
        ])->assertForbidden();
    }

    public function test_category_hierarchy_rejects_self_parent_and_cycles(): void
    {
        $admin = $this->assign(User::factory()->create(), 'admin');
        $root = Category::create(['name' => 'Electronics', 'slug' => 'electronics-'.uniqid()]);
        $child = Category::create(['name' => 'Audio', 'slug' => 'audio-'.uniqid(), 'parent_id' => $root->id]);

        $this->actingAs($admin)->put(route('admin.categories.update', $root), [
            'name' => 'Electronics',
            'parent_id' => $root->id,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertNull($root->fresh()->parent_id);

        $this->actingAs($admin)->put(route('admin.categories.update', $root), [
            'name' => 'Electronics',
            'parent_id' => $child->id,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_category_delete_requires_reassignment_when_products_exist(): void
    {
        $admin = $this->assign(User::factory()->create(), 'admin');
        $a = Category::create(['name' => 'A', 'slug' => 'a-'.uniqid()]);
        $b = Category::create(['name' => 'B', 'slug' => 'b-'.uniqid()]);
        [, $store] = $this->createVendorUser();
        $this->createProductForVendor($store, ['category_id' => $a->id]);

        $this->actingAs($admin)->delete(route('admin.categories.destroy', $a))->assertRedirect();
        $this->assertDatabaseHas('categories', ['id' => $a->id]);

        $this->actingAs($admin)->delete(route('admin.categories.destroy', $a), [
            'reassign_to' => $b->id,
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $a->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'CATEGORY_DELETED']);
    }

    public function test_unpublished_product_hidden_from_public_catalog(): void
    {
        [, $store] = $this->createVendorUser();
        $product = $this->createProductForVendor($store, [
            'name' => 'Hidden Gem',
            'status' => Product::STATUS_UNPUBLISHED,
        ]);

        $this->get(route('products.show', $product->id))->assertNotFound();
        $this->get(route('products.index'))->assertOk()->assertDontSee('Hidden Gem');
    }

    public function test_inventory_manager_lacks_user_role_permissions(): void
    {
        $manager = $this->assign(User::factory()->create(), 'inventory_manager');
        $this->assertFalse($manager->hasPermission('users.update'));
        $this->assertFalse($manager->hasPermission('roles.update'));
        $this->assertFalse($manager->hasPermission('refunds.create'));
        $this->assertFalse($manager->hasPermission('payouts.process'));
        $this->assertTrue($manager->hasPermission('inventory.adjust'));
    }

    public function test_low_stock_uses_product_reorder_level(): void
    {
        [, $store] = $this->createVendorUser();
        $product = $this->createProductForVendor($store, ['stock' => 4, 'reorder_level' => 5]);
        $this->assertTrue($product->isLowStock());
        $ok = $this->createProductForVendor($store, ['stock' => 20, 'reorder_level' => 5, 'name' => 'Plenty']);
        $this->assertFalse($ok->isLowStock());
    }

    public function test_concurrent_style_locked_adjustment_stays_non_negative(): void
    {
        $manager = $this->assign(User::factory()->create(), 'inventory_manager');
        [, $store] = $this->createVendorUser();
        $product = $this->createProductForVendor($store, ['stock' => 1]);

        $service = app(InventoryService::class);
        $service->adjust($product, -1, 'Sold last unit', $manager);
        $this->expectException(\InvalidArgumentException::class);
        $service->adjust($product->fresh(), -1, 'Would go negative', $manager);
    }
}
