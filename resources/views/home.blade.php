@extends('layouts.app')

@section('title', 'SANA Market')
@section('meta_description', 'Shop phones, fashion, home & beauty from verified sellers across East Africa.')

@section('content')
{{-- Hero carousel (driven by data-hero-slider in app.js) --}}
<section class="hero-slider section" data-hero-slider aria-roledescription="carousel" aria-label="Featured promotions">
    @php
        $slides = [
            [
                'image' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=1600&q=80',
                'title' => 'SANA Market',
                'copy' => 'The marketplace for quality goods — verified sellers, secure payments, nationwide delivery.',
                'cta' => route('products.index'),
                'ctaLabel' => 'Shop bestsellers',
            ],
            [
                'image' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=1600&q=80',
                'title' => 'SANA Market',
                'copy' => 'Flagship phones & audio on flash sale — limited stock, countdown live.',
                'cta' => route('deals'),
                'ctaLabel' => 'View flash deals',
            ],
            [
                'image' => 'https://images.unsplash.com/photo-1445205170230-053b83016050?auto=format&fit=crop&w=1600&q=80',
                'title' => 'SANA Market',
                'copy' => 'Fashion drops from Nike, Adidas & independent makers — free shipping over TSh 150,000.',
                'cta' => route('products.index', ['category' => 'fashion']),
                'ctaLabel' => 'Explore fashion',
            ],
        ];
    @endphp

    @foreach($slides as $i => $slide)
        <div class="hero-slide {{ $i === 0 ? 'is-active' : '' }}" data-hero-slide style="background-image:url('{{ $slide['image'] }}')">
            <div class="hero-overlay">
                <p class="hero-brand">{{ $slide['title'] }}</p>
                <p class="hero-copy">{{ $slide['copy'] }}</p>
                <div class="hero-actions">
                    <a class="btn btn-accent" href="{{ $slide['cta'] }}">{{ $slide['ctaLabel'] }}</a>
                    <a class="btn btn-ghost" href="{{ route('vendors') }}" style="border-color:rgba(255,255,255,.35);color:#fff;">Meet sellers</a>
                </div>
            </div>
        </div>
    @endforeach
    <div class="hero-dots" role="tablist">
        @foreach($slides as $i => $slide)
            <button type="button" class="hero-dot {{ $i === 0 ? 'is-active' : '' }}" data-hero-dot aria-label="Show slide {{ $i + 1 }}"></button>
        @endforeach
    </div>
</section>

{{-- Trust signals strip --}}
<section class="section trust-strip" aria-label="Trust signals">
    <div class="trust-item"><strong>Secure pay</strong><span style="color:var(--color-ink-muted);font-size:.9rem;">Cards, M-Pesa & COD</span></div>
    <div class="trust-item"><strong>Fast delivery</strong><span style="color:var(--color-ink-muted);font-size:.9rem;">1–5 days nationwide</span></div>
    <div class="trust-item"><strong>Buyer protection</strong><span style="color:var(--color-ink-muted);font-size:.9rem;">Easy 7-day returns</span></div>
    <div class="trust-item"><strong>Verified sellers</strong><span style="color:var(--color-ink-muted);font-size:.9rem;">Quality you can trust</span></div>
</section>

{{-- Category rail --}}
<section class="section">
    <div class="section-head">
        <div>
            <h2>Shop by category</h2>
            <p>Browse curated departments from phones to home.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('categories') }}">All categories</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;">
        @forelse($categories as $category)
            <a class="category-chip" href="{{ route('products.index', ['category' => $category->slug]) }}" style="background-image:url('{{ $category->image }}')">
                <strong style="font-size:1.1rem;">{{ $category->name }}</strong>
                <span style="opacity:.9;font-size:.9rem;">{{ $category->description }}</span>
            </a>
        @empty
            <p>Categories coming soon.</p>
        @endforelse
    </div>
</section>

@if($flashSales->isNotEmpty())
{{-- Flash sales with countdown --}}
<section class="section">
    <div class="section-head">
        <div>
            <h2>Flash sales</h2>
            <p>Limited-time prices — ends soon.</p>
        </div>
        <div class="flash-timer" data-countdown="{{ $flashEndsAt }}" aria-live="polite"></div>
    </div>
    <div class="products-grid">
        @foreach($flashSales as $product)
            <x-product-card :product="$product" />
        @endforeach
    </div>
</section>
@endif

{{-- Featured merchandising rail --}}
<section class="section">
    <div class="section-head">
        <div>
            <h2>Featured picks</h2>
            <p>Editor-selected bestsellers this week.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('products.index', ['sort' => 'popular']) }}">See more</a>
    </div>
    <div class="products-grid">
        @forelse($featured as $product)
            <x-product-card :product="$product" />
        @empty
            <p class="panel">No featured products yet. Seed the database to populate the marketplace.</p>
        @endforelse
    </div>
</section>

<section class="section">
    <div class="section-head">
        <div>
            <h2>Best sellers</h2>
            <p>What shoppers across East Africa are buying.</p>
        </div>
    </div>
    <div class="products-grid">
        @foreach($bestSellers as $product)
            <x-product-card :product="$product" />
        @endforeach
    </div>
</section>

<section class="section">
    <div class="section-head">
        <div>
            <h2>New arrivals</h2>
            <p>Fresh drops from verified sellers.</p>
        </div>
    </div>
    <div class="products-grid">
        @foreach($newArrivals as $product)
            <x-product-card :product="$product" />
        @endforeach
    </div>
</section>

<section class="section">
    <div class="section-head">
        <div>
            <h2>Trending now</h2>
            <p>High-rated products shoppers love.</p>
        </div>
    </div>
    <div class="products-grid">
        @foreach($trending as $product)
            <x-product-card :product="$product" />
        @endforeach
    </div>
</section>

<section class="section">
    <div class="section-head">
        <div>
            <h2>Recommended for you</h2>
            <p>Based on popular categories and ratings.</p>
        </div>
    </div>
    <div class="products-grid">
        @foreach($featured->take(4)->merge($trending->take(4))->unique('id')->take(8) as $product)
            <x-product-card :product="$product" />
        @endforeach
    </div>
</section>

<section class="section">
    <div class="section-head">
        <div>
            <h2>Recently viewed</h2>
            <p>Pick up where you left off.</p>
        </div>
    </div>
    <div class="products-grid" data-recently-viewed>
        <div class="skeleton" style="height:280px;"></div>
        <div class="skeleton" style="height:280px;"></div>
        <div class="skeleton" style="height:280px;"></div>
        <div class="skeleton" style="height:280px;"></div>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <div>
            <h2>Top brands</h2>
            <p>Shop names you know and trust.</p>
        </div>
    </div>
    <div class="chip-row">
        @foreach($brands as $brand)
            <a class="chip" href="{{ route('products.index', ['brand' => $brand]) }}">{{ $brand }}</a>
        @endforeach
    </div>
</section>

<section class="section">
    <div class="section-head">
        <div>
            <h2>Trusted sellers</h2>
            <p>Verified stores shipping across Tanzania.</p>
        </div>
        <a class="btn btn-ghost" href="{{ route('vendors') }}">All sellers</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
        @foreach($vendors as $vendor)
            <div class="panel">
                <strong style="font-size:1.05rem;">{{ $vendor->store_name }}</strong>
                @if($vendor->is_verified)
                    <span class="badge badge-new" style="margin-left:.4rem;">Verified</span>
                @endif
                <p style="color:var(--color-ink-muted);margin:.5rem 0;">{{ $vendor->description }}</p>
                <div style="font-size:.9rem;color:var(--color-ink-muted);">★ {{ number_format($vendor->rating_avg, 1) }} · {{ $vendor->products_count }} products · {{ $vendor->location }}</div>
            </div>
        @endforeach
    </div>
</section>

<section class="section">
    <div class="section-head">
        <div>
            <h2>What shoppers say</h2>
            <p>Real feedback from the SANA community.</p>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
        <blockquote class="panel" style="margin:0;">
            <p style="margin:0 0 .75rem;line-height:1.6;">“Ordered a Galaxy phone — arrived sealed with invoice. Checkout with M-Pesa was seamless.”</p>
            <footer style="color:var(--color-ink-muted);">— Neema, Dar es Salaam</footer>
        </blockquote>
        <blockquote class="panel" style="margin:0;">
            <p style="margin:0 0 .75rem;line-height:1.6;">“Love the flash deals. Nike sneakers came in two days and packaging was solid.”</p>
            <footer style="color:var(--color-ink-muted);">— Brian, Arusha</footer>
        </blockquote>
        <blockquote class="panel" style="margin:0;">
            <p style="margin:0 0 .75rem;line-height:1.6;">“As a small seller, the store tools and verified badge helped me get more orders.”</p>
            <footer style="color:var(--color-ink-muted);">— Fatma, vendor</footer>
        </blockquote>
    </div>
</section>

<section class="panel section" style="display:grid;gap:1rem;grid-template-columns:1.2fr .8fr;align-items:center;background:linear-gradient(120deg,#0d7377,#095456);color:#fff;border:0;">
    <div>
        <h2 style="margin:0 0 .5rem;color:#fff;">Get SANA deals in your inbox</h2>
        <p style="margin:0;opacity:.9;">Weekly drops, flash alerts, and seller exclusives — no spam.</p>
    </div>
    <form action="{{ route('newsletter.subscribe') }}" method="POST" style="display:flex;gap:.5rem;flex-wrap:wrap;">
        @csrf
        <input class="form-control" type="email" name="email" required placeholder="you@email.com" style="flex:1;min-width:180px;">
        <button class="btn btn-accent" type="submit">Subscribe</button>
    </form>
</section>
@endsection
