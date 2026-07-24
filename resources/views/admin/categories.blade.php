@extends('layouts.app')
@section('title', 'Categories admin')
@section('content')
@include('admin._nav')
<h1 class="font-display">Categories</h1>
<div class="panel" style="margin-top:1rem;padding:0;">
@foreach($categories as $category)
<div style="display:flex;justify-content:space-between;padding:1rem;border-bottom:1px solid var(--color-border);">
<div><strong>{{ $category->name }}</strong><div style="color:var(--color-ink-muted);font-size:.9rem;">{{ $category->description }}</div></div>
<span>{{ $category->products_count }} products</span>
</div>
@endforeach
</div>
@endsection
