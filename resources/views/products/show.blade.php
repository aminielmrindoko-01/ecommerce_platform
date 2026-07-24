@extends('layouts.app')

@section('title', $product->name)
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($product->description), 155))
@section('og_type', 'product')
@section('og_title', $product->name)
@section('og_description', \Illuminate\Support\Str::limit(strip_tags($product->description), 155))
@section('og_image', $product->image_url)
@section('canonical', route('products.show', $product->id))

@push('head')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product->name,
    'image' => $product->gallery_urls,
    'description' => strip_tags((string) $product->description),
    'sku' => $product->sku,
    'brand' => ['@type' => 'Brand', 'name' => $product->brand ?? 'SANA'],
    'offers' => [
        '@type' => 'Offer',
        'url' => route('products.show', $product->id),
        'priceCurrency' => 'TZS',
        'price' => (float) $product->price,
        'availability' => $product->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'itemCondition' => 'https://schema.org/NewCondition',
    ],
    'aggregateRating' => [
        '@type' => 'AggregateRating',
        'ratingValue' => (float) ($product->rating_avg ?: 4.5),
        'reviewCount' => (int) ($product->rating_count ?: 1),
    ],
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')
{{-- Product gallery + buy box --}}
<div class="panel" style="padding:1.5rem;">
    <div style="display:grid;grid-template-columns:1.05fr 1fr;gap:2rem;align-items:start;">
        <div>
            <div style="position:relative;border-radius:16px;overflow:hidden;background:var(--color-surface-2);aspect-ratio:1;cursor:zoom-in;" id="gallery-main-wrap">
                <img id="gallery-main" src="{{ $product->gallery_urls[0] ?? $product->image_url }}" alt="{{ $product->name }}" style="width:100%;height:100%;object-fit:cover;transition:transform .25s ease;">
            </div>
            <div style="display:flex;gap:.5rem;margin-top:.75rem;overflow:auto;">
                @foreach($product->gallery_urls as $i => $url)
                    <button type="button" class="gallery-thumb" data-src="{{ $url }}" aria-label="View image {{ $i + 1 }}" style="border:2px solid {{ $i === 0 ? 'var(--color-brand)' : 'var(--color-border)' }};border-radius:10px;padding:0;overflow:hidden;width:72px;height:72px;cursor:pointer;background:none;">
                        <img src="{{ $url }}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
                    </button>
                @endforeach
            </div>
        </div>

        <div>
            <div class="product-brand">{{ $product->brand }} · {{ $product->category->name ?? 'Marketplace' }}</div>
            <h1 class="font-display" style="margin:.35rem 0 0.75rem;font-size:clamp(1.5rem,3vw,2.1rem);">{{ $product->name }}</h1>
            <div class="rating" style="margin-bottom:.75rem;">★ {{ number_format($product->rating_avg, 1) }} <span style="color:var(--color-ink-muted);font-weight:500;">({{ $product->rating_count }} reviews) · {{ $product->sold_count }} sold</span></div>

            <div class="price-row" style="margin-bottom:1rem;">
                <span class="price" style="font-size:1.6rem;">{{ money($product->price) }}</span>
                @if($product->compare_at_price)
                    <span class="price-compare">{{ money($product->compare_at_price) }}</span>
                    <span class="badge badge-sale">-{{ $product->discount_percent }}%</span>
                @endif
            </div>

            <p style="color:var(--color-ink-muted);line-height:1.7;margin:0 0 1rem;">{{ $product->description }}</p>

            @if(!empty($product->variants['colors']))
                <div class="form-group">
                    <label>Color</label>
                    <div class="chip-row">
                        @foreach($product->variants['colors'] as $color)
                            <button type="button" class="chip variant-chip" data-group="color">{{ $color }}</button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(!empty($product->variants['sizes']) || !empty($product->variants['storage']))
                <div class="form-group">
                    <label>{{ !empty($product->variants['storage']) ? 'Storage' : 'Size' }}</label>
                    <div class="chip-row">
                        @foreach(($product->variants['storage'] ?? $product->variants['sizes'] ?? []) as $opt)
                            <button type="button" class="chip variant-chip" data-group="size">{{ $opt }}</button>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="panel" style="background:var(--color-surface);margin-bottom:1rem;">
                <p style="margin:0 0 .35rem;"><strong>Seller:</strong> {{ $product->vendor->store_name ?? 'Vendor' }}
                    @if($product->vendor?->is_verified)<span class="badge badge-new">Verified</span>@endif
                </p>
                <p style="margin:0;color:var(--color-ink-muted);font-size:.92rem;">{{ $product->vendor->location ?? 'Tanzania' }} · ★ {{ number_format($product->vendor->rating_avg ?? 4.5, 1) }}</p>
                <p style="margin:.65rem 0 0;font-size:.92rem;">
                    @if($product->stock > 0)
                        <span class="badge badge-stock">{{ $product->stock }} in stock</span>
                    @else
                        <span class="badge" style="background:#fdecea;color:var(--color-danger);">Out of stock</span>
                    @endif
                    · SKU {{ $product->sku }}
                </p>
            </div>

            <div style="display:grid;gap:.6rem;">
                @if($product->stock > 0)
                    <form method="POST" action="{{ route('cart.add', $product->id) }}" style="display:flex;gap:.5rem;flex-wrap:wrap;">
                        @csrf
                        <input class="form-control" type="number" name="quantity" value="1" min="1" max="{{ $product->stock }}" style="width:90px;">
                        <button type="submit" class="btn btn-accent" style="flex:1;">Add to cart</button>
                    </form>
                @endif
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                    @auth
                        <form method="POST" action="{{ route('wishlist.toggle', $product->id) }}">
                            @csrf
                            <button class="btn btn-ghost" type="submit">Wishlist</button>
                        </form>
                    @else
                        <a class="btn btn-ghost" href="{{ route('login') }}">Login to wishlist</a>
                    @endauth
                    <button class="btn btn-ghost" type="button" id="compare-btn" data-id="{{ $product->id }}" data-name="{{ $product->name }}">Compare</button>
                    <button class="btn btn-ghost" type="button" onclick="navigator.share ? navigator.share({title: @js($product->name), url: location.href}) : navigator.clipboard.writeText(location.href).then(()=>alert('Link copied'))">Share</button>
                </div>
            </div>

            <div style="margin-top:1.25rem;display:grid;gap:.45rem;font-size:.92rem;color:var(--color-ink-muted);">
                <div>🚚 Delivery: 1–5 business days · Free over TSh 150,000</div>
                <div>↩️ Returns: 7-day easy returns on unused items</div>
                <div>🛡️ Buyer protection on every eligible order</div>
            </div>
        </div>
    </div>
</div>

@if(!empty($product->specs))
<section class="section" style="margin-top:1.5rem;">
    <h2>Specifications</h2>
    <div class="panel">
        <dl style="display:grid;grid-template-columns:180px 1fr;gap:.65rem 1rem;margin:0;">
            @foreach($product->specs as $label => $value)
                <dt style="font-weight:700;color:var(--color-ink-muted);">{{ $label }}</dt>
                <dd style="margin:0;">{{ $value }}</dd>
            @endforeach
        </dl>
    </div>
</section>
@endif

{{-- Reviews list + submit form --}}
<section class="section">
    <h2>Customer reviews</h2>
    <div style="display:grid;gap:1rem;grid-template-columns:1fr 1fr;">
        <div>
            @forelse($product->reviews as $review)
                <article class="panel" style="margin-bottom:.75rem;">
                    <div class="rating">★ {{ $review->rating }} · {{ $review->author_name }}</div>
                    @if($review->title)<strong>{{ $review->title }}</strong>@endif
                    <p style="margin:.4rem 0 0;color:var(--color-ink-muted);line-height:1.6;">{{ $review->body }}</p>
                </article>
            @empty
                <p class="panel">No reviews yet — be the first.</p>
            @endforelse
        </div>
        <form method="POST" action="{{ route('products.reviews.store', $product->id) }}" class="panel">
            @csrf
            <h3 style="margin-top:0;">Write a review</h3>
            <div class="form-group">
                <label for="rating">Rating</label>
                <select class="form-control" id="rating" name="rating" required>
                    @for($i=5;$i>=1;$i--)
                        <option value="{{ $i }}">{{ $i }} stars</option>
                    @endfor
                </select>
            </div>
            <div class="form-group">
                <label for="title">Title</label>
                <input class="form-control" id="title" name="title">
            </div>
            @guest
                <div class="form-group">
                    <label for="author_name">Your name</label>
                    <input class="form-control" id="author_name" name="author_name" required>
                </div>
            @endguest
            <div class="form-group">
                <label for="body">Review</label>
                <textarea class="form-control" id="body" name="body" rows="4" required></textarea>
            </div>
            <button class="btn btn-primary" type="submit">Submit review</button>
        </form>
    </div>
</section>

{{-- Q&A list + ask form --}}
<section class="section">
    <h2>Questions & answers</h2>
    <div style="display:grid;gap:1rem;grid-template-columns:1fr 1fr;">
        <div>
            @forelse($product->questions as $qa)
                <article class="panel" style="margin-bottom:.75rem;">
                    <strong>Q: {{ $qa->question }}</strong>
                    <p style="margin:.45rem 0 0;color:var(--color-ink-muted);">A: {{ $qa->answer ?? 'Awaiting seller reply' }}</p>
                </article>
            @empty
                <p class="panel">No questions yet.</p>
            @endforelse
        </div>
        <form method="POST" action="{{ route('products.questions.store', $product->id) }}" class="panel">
            @csrf
            <h3 style="margin-top:0;">Ask a question</h3>
            @guest
                <div class="form-group">
                    <label for="q_author">Your name</label>
                    <input class="form-control" id="q_author" name="author_name">
                </div>
            @endguest
            <div class="form-group">
                <label for="question">Question</label>
                <textarea class="form-control" id="question" name="question" rows="3" required></textarea>
            </div>
            <button class="btn btn-primary" type="submit">Submit question</button>
        </form>
    </div>
</section>

@if($fbt->isNotEmpty())
{{-- Frequently bought together (random in-stock picks from controller) --}}
<section class="section">
    <h2>Frequently bought together</h2>
    <div class="products-grid">
        @foreach($fbt as $item)
            <x-product-card :product="$item" />
        @endforeach
    </div>
</section>
@endif

{{-- Same-category related products --}}
<section class="section">
    <h2>Related products</h2>
    <div class="products-grid">
        @foreach($related as $item)
            <x-product-card :product="$item" />
        @endforeach
    </div>
</section>

{{-- Hydrated by app.js from localStorage via /api/products/recent --}}
<section class="section">
    <h2>Recently viewed</h2>
    <div class="products-grid" data-recently-viewed data-exclude-id="{{ $product->id }}"></div>
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.gallery-thumb').forEach((btn) => {
    btn.addEventListener('click', () => {
        const main = document.getElementById('gallery-main');
        main.src = btn.dataset.src;
        document.querySelectorAll('.gallery-thumb').forEach((b) => b.style.borderColor = 'var(--color-border)');
        btn.style.borderColor = 'var(--color-brand)';
    });
});

const wrap = document.getElementById('gallery-main-wrap');
const mainImg = document.getElementById('gallery-main');
if (wrap && mainImg) {
    wrap.addEventListener('mousemove', (e) => {
        const r = wrap.getBoundingClientRect();
        const x = ((e.clientX - r.left) / r.width) * 100;
        const y = ((e.clientY - r.top) / r.height) * 100;
        mainImg.style.transformOrigin = `${x}% ${y}%`;
        mainImg.style.transform = 'scale(1.6)';
    });
    wrap.addEventListener('mouseleave', () => { mainImg.style.transform = 'scale(1)'; });
}

document.querySelectorAll('.variant-chip').forEach((chip) => {
    chip.addEventListener('click', () => {
        document.querySelectorAll(`.variant-chip[data-group="${chip.dataset.group}"]`).forEach((c) => c.classList.remove('is-active'));
        chip.classList.add('is-active');
    });
});

document.getElementById('compare-btn')?.addEventListener('click', (e) => {
    const btn = e.currentTarget;
    const key = 'sana_compare';
    let list = [];
    try { list = JSON.parse(localStorage.getItem(key) || '[]'); } catch {}
    if (!list.find((i) => i.id === btn.dataset.id)) {
        list.push({ id: btn.dataset.id, name: btn.dataset.name });
        list = list.slice(-4);
        localStorage.setItem(key, JSON.stringify(list));
    }
    alert('Compare list: ' + list.map((i) => i.name).join(', '));
});
</script>
@endpush
