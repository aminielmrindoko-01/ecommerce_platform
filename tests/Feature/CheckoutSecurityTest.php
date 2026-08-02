<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(array $overrides = []): Product
    {
        $vendor = Vendor::create([
            'store_name' => 'Checkout Vendor',
            'email' => 'checkout-vendor@example.com',
            'is_verified' => true,
        ]);

        return Product::create(array_merge([
            'vendor_id' => $vendor->id,
            'name' => 'Checkout Item',
            'slug' => 'checkout-item-'.uniqid(),
            'price' => 50000,
            'stock' => 10,
            'description' => 'Checkout test product',
        ], $overrides));
    }

    public function test_checkout_uses_database_price_not_session_price(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(['price' => 50000, 'stock' => 5]);

        $response = $this->actingAs($user)
            ->withSession([
                'cart' => [
                    $product->id => [
                        'name' => $product->name,
                        'price' => 1, // tampered session price
                        'quantity' => 2,
                        'image' => null,
                        'brand' => null,
                    ],
                ],
            ])
            ->post(route('checkout.place'), [
                'full_name' => 'Buyer One',
                'phone' => '+255700000100',
                'line1' => '12 Market Street',
                'city' => 'Dar es Salaam',
                'payment_method' => 'cod',
                'shipping_method' => 'pickup',
            ]);

        $order = Order::first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('checkout.confirmation', $order));

        // 2 * 50000 + tax (18% of 100000) = 118000 with pickup shipping 0
        $this->assertEquals(118000.0, (float) $order->total_price);
        $this->assertEquals(50000.0, (float) $order->items()->first()->price);
        $this->assertEquals(3, $product->fresh()->stock);
    }

    public function test_checkout_rejects_insufficient_stock(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(['price' => 20000, 'stock' => 1]);

        $response = $this->actingAs($user)
            ->withSession([
                'cart' => [
                    $product->id => [
                        'name' => $product->name,
                        'price' => 20000,
                        'quantity' => 5,
                        'image' => null,
                        'brand' => null,
                    ],
                ],
            ])
            ->post(route('checkout.place'), [
                'full_name' => 'Buyer Two',
                'phone' => '+255700000101',
                'line1' => '12 Market Street',
                'city' => 'Dar es Salaam',
                'payment_method' => 'cod',
                'shipping_method' => 'pickup',
            ]);

        $response->assertRedirect(route('cart.index'));
        $this->assertSame(0, Order::count());
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_checkout_rejects_unknown_payment_method(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct();

        $this->actingAs($user)
            ->withSession([
                'cart' => [
                    $product->id => [
                        'name' => $product->name,
                        'price' => (float) $product->price,
                        'quantity' => 1,
                        'image' => null,
                        'brand' => null,
                    ],
                ],
            ])
            ->from(route('checkout'))
            ->post(route('checkout.place'), [
                'full_name' => 'Buyer Three',
                'phone' => '+255700000102',
                'line1' => '12 Market Street',
                'city' => 'Dar es Salaam',
                'payment_method' => 'not-a-real-gateway',
                'shipping_method' => 'pickup',
            ])
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(0, Order::count());
    }

    public function test_customer_cannot_view_another_users_order_confirmation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $product = $this->createProduct();

        $order = Order::create([
            'order_number' => 'SN-TESTORDER',
            'user_id' => $owner->id,
            'total_price' => 1000,
            'status' => 'pending',
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 1000,
        ]);

        $this->actingAs($intruder)
            ->get(route('checkout.confirmation', $order))
            ->assertStatus(403);
    }
}
