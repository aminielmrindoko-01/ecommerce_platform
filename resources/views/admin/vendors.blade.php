@extends('layouts.app')
@section('title', 'Admin vendors')
@section('content')
@include('admin._nav')
<h1 class="font-display">Vendors</h1>
<div class="panel" style="margin-top:1rem;padding:0;">
@foreach($vendors as $vendor)
<div style="display:flex;justify-content:space-between;gap:1rem;padding:1rem;border-bottom:1px solid var(--color-border);flex-wrap:wrap;align-items:center;">
<div>
<strong>{{ $vendor->store_name }}</strong>
@if($vendor->is_verified)<span class="badge badge-new">Verified</span>@endif
<div style="color:var(--color-ink-muted);font-size:.9rem;">{{ $vendor->email }} · {{ $vendor->products_count }} products · {{ $vendor->location }}</div>
</div>
<form method="POST" action="{{ route('admin.vendors.toggle', $vendor->id) }}">
@csrf
<button class="btn {{ $vendor->is_verified ? 'btn-ghost' : 'btn-primary' }}" type="submit">{{ $vendor->is_verified ? 'Unverify' : 'Verify' }}</button>
</form>
</div>
@endforeach
</div>
@endsection
