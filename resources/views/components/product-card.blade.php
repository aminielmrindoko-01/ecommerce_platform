{{-- Reusable catalog card: media, badges, price, optional cart/wishlist actions --}}
@props([
    'product',
    'showActions' => true,
])

<article class="product-card">
    <a href="{{ route('products.show', $product->id) }}" class="product-card-media" aria-label="{{ $product->name }}">
        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy" width="400" height="400">
        <div style="position:absolute;top:0.65rem;left:0.65rem;display:flex;gap:0.35rem;flex-wrap:wrap;">
            @if($product->discount_percent)
                <span class="badge badge-sale">-{{ $product->discount_percent }}%</span>
            @endif
            @if($product->is_new)
                <span class="badge badge-new">{{ mt('badge.new') }}</span>
            @endif
        </div>
    </a>
    <div class="product-card-body">
        <div class="product-brand">{{ $product->brand ?? ($product->vendor->store_name ?? 'SANA') }}</div>
        <h3 class="product-name">
            <a href="{{ route('products.show', $product->id) }}">{{ $product->name }}</a>
        </h3>
        <div class="rating" aria-label="Rated {{ number_format($product->rating_avg, 1) }} out of 5">
            ★ {{ number_format($product->rating_avg ?: 4.5, 1) }}
            <span style="color:var(--color-ink-muted);font-weight:500;">({{ $product->rating_count ?: 12 }})</span>
        </div>
        <div class="price-row">
            <span class="price">{{ money($product->price) }}</span>
            @if($product->compare_at_price)
                <span class="price-compare">{{ money($product->compare_at_price) }}</span>
            @endif
        </div>
        @if($showActions)
            <div style="margin-top:auto;display:grid;gap:0.45rem;padding-top:0.4rem;">
                @if($product->stock > 0)
                    <form method="POST" action="{{ route('cart.add', $product->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-accent" style="width:100%;">{{ mt('cta.add_to_cart') }}</button>
                    </form>
                @else
                    <span class="badge" style="justify-content:center;background:var(--color-danger-soft);color:var(--color-danger);">{{ mt('cta.out_of_stock') }}</span>
                @endif
            </div>
        @endif
    </div>
</article>
