@extends('layouts.app')
@section('title', 'Returns')
@section('content')
@include('account._nav')
<h1 class="font-display">Returns</h1>
@forelse($returns as $return)
    <div class="panel" style="margin-bottom:.75rem;">
        <a href="{{ route('account.returns.show', $return) }}"><strong>{{ $return->reference }}</strong></a>
        · {{ ucfirst($return->status) }} · Order {{ $return->order?->order_number }}
        · {{ money($return->refundAmount()) }}
    </div>
@empty
    <p style="color:var(--color-ink-muted);">No return requests yet.</p>
@endforelse
{{ $returns->links() }}
@endsection
