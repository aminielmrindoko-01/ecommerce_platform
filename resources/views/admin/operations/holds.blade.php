@extends('layouts.app')
@section('title', 'Settlement holds')
@section('content')
@include('admin._nav')
@include('admin.operations._nav')
<h1 class="font-display">Settlement Holds</h1>
@forelse($holds as $hold)
    <div class="panel" style="margin-bottom:.6rem;">
        <strong>{{ $hold->reference }}</strong> · {{ $hold->status }} · {{ $hold->reason_code }}
        · {{ money($hold->amount) }} · {{ $hold->vendor?->store_name }}
        @if($hold->status==='active' && auth()->user()->hasPermission('settlement_holds.manage'))
            <form method="POST" action="{{ route('admin.operations.holds.release', $hold) }}" style="display:inline;">@csrf<button class="btn btn-ghost">Release</button></form>
        @endif
    </div>
@empty
    <p>No holds.</p>
@endforelse
{{ $holds->links() }}
@endsection
