@extends('layouts.app')
@section('title', 'Reviews admin')
@section('content')
@include('admin._nav')
<h1 class="font-display">Reviews moderation</h1>
<div class="panel" style="margin-top:1rem;padding:0;">
@foreach($reviews as $review)
<article style="padding:1rem;border-bottom:1px solid var(--color-border);">
<strong>{{ $review->product->name ?? 'Product' }}</strong> · ★ {{ $review->rating }} · {{ $review->author_name }}
<p style="margin:.4rem 0 0;color:var(--color-ink-muted);">{{ $review->body }}</p>
</article>
@endforeach
</div>
<div style="margin-top:1rem;">{{ $reviews->links() }}</div>
@endsection
