@extends('layouts.app')

@section('title', 'Products')

@section('content')
<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;font-size:clamp(1.6rem,3vw,2.2rem);">All products</h1>
        <p>{{ $products->total() }} results @if(request('q')) for “{{ request('q') }}” @endif</p>
    </div>
    @auth
        <a href="{{ route('products.create') }}" class="btn btn-primary">Add product</a>
    @endauth
</div>

@if(!empty($recentSearches) || !empty($popularSearches))
    <div class="panel section" style="padding:1rem;">
        @if(!empty($recentSearches))
            <div style="margin-bottom:.65rem;">
                <strong style="font-size:.85rem;">Recent searches</strong>
                <div class="chip-row" style="margin-top:.4rem;">
                    @foreach($recentSearches as $term)
                        <a class="chip" href="{{ route('products.index', ['q' => $term]) }}">{{ $term }}</a>
                    @endforeach
                </div>
            </div>
        @endif
        <div>
            <strong style="font-size:.85rem;">Popular</strong>
            <div class="chip-row" style="margin-top:.4rem;">
                @foreach($popularSearches as $term)
                    <a class="chip {{ request('q') === $term ? 'is-active' : '' }}" href="{{ route('products.index', ['q' => $term]) }}">{{ $term }}</a>
                @endforeach
            </div>
        </div>
    </div>
@endif

<div style="display:grid;grid-template-columns:260px 1fr;gap:1.25rem;align-items:start;">
    <aside class="panel filter-sidebar" aria-label="Filters">
        <form method="GET" action="{{ route('products.index') }}">
            @if(request('q'))
                <input type="hidden" name="q" value="{{ request('q') }}">
            @endif
            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category" class="form-control">
                    <option value="">All</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name }} ({{ $category->products_count }})</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="brand">Brand</label>
                <select id="brand" name="brand" class="form-control">
                    <option value="">All brands</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand }}" @selected(request('brand') === $brand)>{{ $brand }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Price (TSh)</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;">
                    <input class="form-control" type="number" name="min_price" placeholder="Min" value="{{ request('min_price') }}">
                    <input class="form-control" type="number" name="max_price" placeholder="Max" value="{{ request('max_price') }}">
                </div>
            </div>
            <div class="form-group">
                <label for="rating">Min rating</label>
                <select id="rating" name="rating" class="form-control">
                    <option value="">Any</option>
                    @foreach([4, 3, 2] as $r)
                        <option value="{{ $r }}" @selected(request('rating') == $r)>{{ $r }}+ stars</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:.5rem;font-weight:600;">
                    <input type="checkbox" name="in_stock" value="1" @checked(request()->boolean('in_stock'))>
                    In stock only
                </label>
            </div>
            <div class="form-group">
                <label for="sort">Sort by</label>
                <select id="sort" name="sort" class="form-control">
                    <option value="latest" @selected($sort === 'latest')>Newest</option>
                    <option value="popular" @selected($sort === 'popular')>Most popular</option>
                    <option value="rating" @selected($sort === 'rating')>Top rated</option>
                    <option value="price_asc" @selected($sort === 'price_asc')>Price: low to high</option>
                    <option value="price_desc" @selected($sort === 'price_desc')>Price: high to low</option>
                </select>
            </div>
            <button class="btn btn-primary" type="submit" style="width:100%;">Apply filters</button>
            <a class="btn btn-ghost" href="{{ route('products.index') }}" style="width:100%;margin-top:.5rem;">Reset</a>
        </form>
    </aside>

    <div>
        @if($products->isEmpty())
            <div class="panel" style="text-align:center;padding:2.5rem;">
                <p style="margin:0;color:var(--color-ink-muted);">No products match your filters.</p>
            </div>
        @else
            <div class="products-grid">
                @foreach($products as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
            <div style="margin-top:1.5rem;">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</div>

<style>
@media (max-width: 900px) {
    .site-main > div[style*="grid-template-columns:260px"] { grid-template-columns: 1fr !important; }
}
</style>
@endsection
