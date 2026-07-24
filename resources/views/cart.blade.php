@extends('layouts.app')

@section('title', 'Cart')

@section('content')
<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">Your cart</h1>
        <p>{{ count($cart) }} item(s) · Save for later & coupons supported</p>
    </div>
    <a href="{{ route('products.index') }}" class="btn btn-ghost">Continue shopping</a>
</div>

@if(count($cart) > 0)
<div style="display:grid;grid-template-columns:1.5fr .9fr;gap:1.25rem;align-items:start;">
    <div class="panel" style="padding:0;">
        @foreach($cart as $id => $item)
            <div style="display:grid;grid-template-columns:88px 1fr auto;gap:1rem;padding:1.1rem;border-bottom:1px solid var(--color-border);align-items:center;">
                <img src="{{ $item['image'] ?? 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=200&q=80' }}" alt="" width="88" height="88" style="border-radius:10px;object-fit:cover;width:88px;height:88px;">
                <div>
                    <strong>{{ $item['name'] }}</strong>
                    <div style="color:var(--color-ink-muted);margin-top:.25rem;">{{ $item['brand'] ?? '' }} · TSh {{ number_format($item['price'], 0) }}</div>
                    <div style="display:flex;gap:.45rem;margin-top:.65rem;flex-wrap:wrap;align-items:center;">
                        <form method="POST" action="{{ route('cart.decrease', $id) }}">@csrf<button class="btn btn-ghost" type="submit" style="padding:.35rem .65rem;">−</button></form>
                        <strong>{{ $item['quantity'] }}</strong>
                        <form method="POST" action="{{ route('cart.increase', $id) }}">@csrf<button class="btn btn-ghost" type="submit" style="padding:.35rem .65rem;">+</button></form>
                        <form method="POST" action="{{ route('cart.save', $id) }}">@csrf<button class="btn btn-ghost" type="submit" style="padding:.35rem .75rem;">Save for later</button></form>
                        <form method="POST" action="{{ route('cart.remove', $id) }}">@csrf<button class="btn btn-danger" type="submit" style="padding:.35rem .75rem;">Remove</button></form>
                    </div>
                </div>
                <div style="font-weight:800;">TSh {{ number_format($item['price'] * $item['quantity'], 0) }}</div>
            </div>
        @endforeach
    </div>

    <aside class="panel">
        <h2 style="margin-top:0;font-size:1.15rem;">Order summary</h2>
        <div style="display:grid;gap:.55rem;font-size:.95rem;">
            <div style="display:flex;justify-content:space-between;"><span>Subtotal</span><strong>TSh {{ number_format($subtotal, 0) }}</strong></div>
            <div style="display:flex;justify-content:space-between;"><span>Discount</span><strong>- TSh {{ number_format($discount, 0) }}</strong></div>
            <div style="display:flex;justify-content:space-between;"><span>Shipping est.</span><strong>{{ $shipping == 0 ? 'FREE' : 'TSh '.number_format($shipping, 0) }}</strong></div>
            <div style="display:flex;justify-content:space-between;"><span>VAT (18%)</span><strong>TSh {{ number_format($tax, 0) }}</strong></div>
            <div style="display:flex;justify-content:space-between;border-top:1px solid var(--color-border);padding-top:.75rem;font-size:1.1rem;"><span>Total</span><strong>TSh {{ number_format($total, 0) }}</strong></div>
        </div>

        <form method="POST" action="{{ route('cart.coupon.apply') }}" style="margin-top:1rem;display:flex;gap:.4rem;">
            @csrf
            <input class="form-control" name="code" placeholder="Coupon code" value="{{ $couponCode }}">
            <button class="btn btn-primary" type="submit">Apply</button>
        </form>
        @if($couponCode)
            <form method="POST" action="{{ route('cart.coupon.remove') }}" style="margin-top:.4rem;">
                @csrf
                @method('DELETE')
                <button class="btn btn-ghost" type="submit" style="width:100%;">Remove {{ $couponCode }}</button>
            </form>
        @else
            <p style="font-size:.85rem;color:var(--color-ink-muted);margin:.5rem 0 0;">Try <strong>SANA10</strong> or <strong>FLASH50K</strong></p>
        @endif

        @auth
            <a href="{{ route('checkout') }}" class="btn btn-accent" style="width:100%;margin-top:1rem;">Proceed to checkout</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-accent" style="width:100%;margin-top:1rem;">Login to checkout</a>
            <p style="font-size:.85rem;color:var(--color-ink-muted);margin:.5rem 0 0;">Guest cart is saved in your session. Create an account to place the order.</p>
        @endauth
    </aside>
</div>
@else
    <div class="panel" style="text-align:center;padding:2.5rem;">
        <p style="margin:0 0 1rem;color:var(--color-ink-muted);">Your cart is empty.</p>
        <a class="btn btn-primary" href="{{ route('products.index') }}">Browse products</a>
    </div>
@endif

@if(count($saved) > 0)
<section class="section" style="margin-top:2rem;">
    <h2>Saved for later</h2>
    <div class="panel" style="padding:0;">
        @foreach($saved as $id => $item)
            <div style="display:flex;justify-content:space-between;gap:1rem;padding:1rem;border-bottom:1px solid var(--color-border);align-items:center;flex-wrap:wrap;">
                <div>
                    <strong>{{ $item['name'] }}</strong>
                    <div style="color:var(--color-ink-muted);">TSh {{ number_format($item['price'], 0) }}</div>
                </div>
                <div style="display:flex;gap:.4rem;">
                    <form method="POST" action="{{ route('cart.move', $id) }}">@csrf<button class="btn btn-primary" type="submit">Move to cart</button></form>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

<style>
@media (max-width: 900px) {
    .site-main > div[style*="1.5fr"] { grid-template-columns: 1fr !important; }
}
</style>
@endsection
