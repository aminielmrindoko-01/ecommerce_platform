<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

class CartTest extends TestCase
{
    use CreatesMarketplace;
    use RefreshDatabase;

    public function test_guest_can_add_product_to_cart_using_database_price(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, [
            'name' => 'Cart Item',
            'price' => 15000,
            'stock' => 3,
        ]);

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
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['stock' => 2]);

        $this->post(route('cart.add', $product->id), ['quantity' => 10])
            ->assertRedirect(route('cart.index'));

        $this->assertEquals(2, session('cart')[$product->id]['quantity']);
    }

    public function test_cart_index_refreshes_tampered_session_price(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['price' => 20000]);

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
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['stock' => 0]);

        $this->from(route('products.show', $product->id))
            ->post(route('cart.add', $product->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertEmpty(session('cart', []));
    }
}
