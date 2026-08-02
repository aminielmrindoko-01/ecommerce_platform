<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

class CheckoutSecurityTest extends TestCase
{
    use CreatesMarketplace;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $cart
     * @param  array<string, mixed>  $payload
     */
    protected function placeCheckout(User $user, array $cart, array $payload)
    {
        $token = (string) Str::uuid();

        return $this->actingAs($user)
            ->withSession([
                'cart' => $cart,
                'checkout_idempotency_token' => $token,
            ])
            ->post(route('checkout.place'), array_merge($payload, [
                'checkout_token' => $token,
            ]));
    }

    public function test_checkout_uses_database_price_not_session_price(): void
    {
        $user = User::factory()->create();
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['price' => 50000, 'stock' => 5, 'name' => 'Checkout Item']);

        $response = $this->placeCheckout($user, [
            $product->id => [
                'name' => $product->name,
                'price' => 1,
                'quantity' => 2,
                'image' => null,
                'brand' => null,
            ],
        ], [
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

        $this->assertEquals(118000.0, (float) $order->total_price);
        $this->assertEquals(50000.0, (float) $order->items()->first()->price);
        $this->assertEquals(3, $product->fresh()->stock);
        $this->assertSame('pending', $order->payment_status);
        $this->assertNotNull($order->latestPaymentTransaction);
    }

    public function test_checkout_rejects_insufficient_stock(): void
    {
        $user = User::factory()->create();
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['price' => 20000, 'stock' => 1]);

        $response = $this->placeCheckout($user, [
            $product->id => [
                'name' => $product->name,
                'price' => 20000,
                'quantity' => 5,
                'image' => null,
                'brand' => null,
            ],
        ], [
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
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor);
        $token = (string) Str::uuid();

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
                'checkout_idempotency_token' => $token,
            ])
            ->from(route('checkout'))
            ->post(route('checkout.place'), [
                'full_name' => 'Buyer Three',
                'phone' => '+255700000102',
                'line1' => '12 Market Street',
                'city' => 'Dar es Salaam',
                'payment_method' => 'not-a-real-gateway',
                'shipping_method' => 'pickup',
                'checkout_token' => $token,
            ])
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(0, Order::count());
    }

    public function test_customer_cannot_view_another_users_order_confirmation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor);

        $order = Order::create([
            'order_number' => 'SN-TESTORDER',
            'user_id' => $owner->id,
            'total_price' => 1000,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
        ]);

        OrderItem::recordPurchase($order->id, $product->id, 1, 1000);

        $this->actingAs($intruder)
            ->get(route('checkout.confirmation', $order))
            ->assertStatus(403);
    }
}
