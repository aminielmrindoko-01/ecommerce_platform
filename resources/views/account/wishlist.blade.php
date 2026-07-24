@extends('layouts.app')
@section('title', 'Wishlist')
@section('content')
@include('account._nav')
<h1 class="font-display">Wishlist</h1>
@if($items->isEmpty())
    <div class="panel" style="margin-top:1rem;text-align:center;padding:2rem;">
        <p style="color:var(--color-ink-muted);">No saved items yet.</p>
        <a class="btn btn-primary" href="{{ route('products.index') }}">Browse products</a>
    </div>
@else
    <div class="products-grid" style="margin-top:1rem;">
        @foreach($items as $item)
            @if($item->product)
                <div>
                    <x-product-card :product="$item->product" />
                    <form method="POST" action="{{ route('wishlist.toggle', $item->product_id) }}" style="margin-top:.4rem;">
                        @csrf
                        <button class="btn btn-ghost" type="submit" style="width:100%;">Remove</button>
                    </form>
                </div>
            @endif
        @endforeach
    </div>
@endif
@endsection
