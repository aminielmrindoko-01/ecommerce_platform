@extends('layouts.app')
@section('title', 'Returns')
@section('content')
@include('admin._nav')
@include('admin.operations._nav')
<h1 class="font-display">Returns</h1>
@forelse($returns as $return)
    <div class="panel" style="margin-bottom:.6rem;">
        <a href="{{ route('admin.operations.returns.show', $return) }}"><strong>{{ $return->reference }}</strong></a>
        · {{ ucfirst($return->status) }} · {{ $return->vendor?->store_name }} · {{ $return->order?->order_number }}
    </div>
@empty
    <p>No returns.</p>
@endforelse
{{ $returns->links() }}
@endsection
