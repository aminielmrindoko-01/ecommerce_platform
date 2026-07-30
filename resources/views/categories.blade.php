@extends('layouts.app')
@section('title', 'Categories')
@section('content')
<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">Shop by category</h1>
        <p>Explore departments across the marketplace</p>
    </div>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
@forelse($categories as $category)
    <a class="category-chip" href="{{ route('products.index', ['category' => $category->slug]) }}" style="background-image:url('{{ $category->image }}');min-height:180px;">
        <strong style="font-size:1.2rem;">{{ $category->name }}</strong>
        <span>{{ $category->products_count }} products · {{ $category->description }}</span>
    </a>
@empty
    <p class="panel">No categories yet. Run the marketplace seeder.</p>
@endforelse
</div>
@endsection
