@extends('layouts.app')
@section('title', 'Reviews admin')
@section('content')
@include('admin._nav')
<h1 class="font-display">Reviews moderation</h1>
<p style="color:var(--color-ink-muted);">Moderators change status only — customer review content is not editable here.</p>
<div class="panel" style="margin-top:1rem;padding:0;">
@foreach($reviews as $review)
<article style="padding:1rem;border-bottom:1px solid var(--color-border);">
<div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
<div>
<strong>{{ $review->product->name ?? 'Product' }}</strong> · ★ {{ $review->rating }} · {{ $review->author_name }}
<span class="chip">{{ $review->status ?: 'APPROVED' }}</span>
@if($review->verified_purchase)<span class="chip">Verified purchase</span>@endif
<p style="margin:.4rem 0 0;color:var(--color-ink-muted);">{{ $review->body }}</p>
@if($review->moderation_reason)
<p style="margin:.35rem 0 0;font-size:.85rem;">Moderation: {{ $review->moderation_reason }}</p>
@endif
</div>
@if(auth()->user()?->hasPermission('reviews.moderate'))
<form method="POST" action="{{ route('admin.reviews.moderate', $review) }}" style="display:grid;gap:.4rem;min-width:220px;">
@csrf @method('PATCH')
<select name="status" class="form-control" required>
@foreach(['PENDING','APPROVED','REJECTED','HIDDEN','FLAGGED'] as $status)
<option value="{{ $status }}" @selected(($review->status ?: 'APPROVED') === $status)>{{ $status }}</option>
@endforeach
</select>
<input class="form-control" type="text" name="moderation_reason" maxlength="500" placeholder="Reason (optional)" value="{{ $review->moderation_reason }}">
<button class="btn btn-primary" type="submit">Update status</button>
</form>
@endif
</div>
</article>
@endforeach
</div>
<div style="margin-top:1rem;">{{ $reviews->links() }}</div>
@endsection
