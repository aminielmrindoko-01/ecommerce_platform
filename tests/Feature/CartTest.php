<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(array $overrides = []): Product
    {
        $vendor = Vendor::create([
            'store_name' => 'Cart Vendor',
            'email' => 'cart-vendor@example.com',
            'is_verified' => true,
        ]);

        return Product::create(array_merge([
            'vendor_id' => $vendor->id,
            'name' => 'Cart Item',
            'slug' => 'cart-item-'.uniqid(),
            'price' => 15000,
            'stock' => 3,
            'description' => 'Cart test product',
        ], $overrides));
    }

    public function test_guest_can_add_product_to_cart_using_database_price(): void
    {
        $product = $this->createProduct(['price' => 15000, 'stock' => 3]);

        $this->post(route('cart.add', $product->id), ['quantity' => 2])
            ->assertRedirect(route('cart.index'));

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Cart Item');

        $cart = session('cart');
        $this->assertEquals(15000.0, (float) $cart[$product->id]['price']);
        $this->assertEquals(2, $cart[$product->id]['quantity']);
    }

    public function test_cart_quantity_is_clamped_to_stock(): void
    {
        $product = $this->createProduct(['stock' => 2]);

        $this->post(route('cart.add', $product->id), ['quantity' => 10])
            ->assertRedirect(route('cart.index'));

        $this->assertEquals(2, session('cart')[$product->id]['quantity']);
    }

    public function test_cart_index_refreshes_tampered_session_price(): void
    {
        $product = $this->createProduct(['price' => 20000]);

        $this->withSession([
            'cart' => [
                $product->id => [
                    'name' => $product->name,
                    'price' => 1,
                    'quantity' => 1,
                    'image' => null,
                    'brand' => null,
                ],
            ],
        ])->get(route('cart.index'))->assertOk();

        $this->assertEquals(20000.0, (float) session('cart')[$product->id]['price']);
    }

    public function test_out_of_stock_product_cannot_be_added(): void
    {
        $product = $this->createProduct(['stock' => 0]);

        $this->from(route('products.show', $product->id))
            ->post(route('cart.add', $product->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertEmpty(session('cart', []));
    }
}
