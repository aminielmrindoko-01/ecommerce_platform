@extends('layouts.app')
@section('title', 'Returns')
@section('content')
@include('vendor._nav')
<h1 class="font-display">Returns</h1>
@forelse($returns as $return)
    <div class="panel" style="margin-bottom:.75rem;">
        <a href="{{ route('vendor.returns.show', $return) }}"><strong>{{ $return->reference }}</strong></a>
        · {{ ucfirst($return->status) }} · {{ $return->order?->order_number }}
    </div>
@empty
    <p style="color:var(--color-ink-muted);">No returns for your store.</p>
@endforelse
{{ $returns->links() }}
@endsection
