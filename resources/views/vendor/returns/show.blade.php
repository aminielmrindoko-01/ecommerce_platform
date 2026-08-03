@extends('layouts.app')
@section('title', 'Return '.$return->reference)
@section('content')
@include('vendor._nav')
@if($errors->any())<div class="panel" style="border-color:#b91c1c;">{{ $errors->first() }}</div>@endif
@if(session('success'))<div class="panel">{{ session('success') }}</div>@endif
<div class="panel">
    <h1 class="font-display" style="margin-top:0;">{{ $return->reference }}</h1>
    <p>Status: <strong>{{ ucfirst($return->status) }}</strong></p>
    <p>{{ $return->reason }}</p>
    @foreach($return->items as $item)
        <div>{{ $item->orderItem?->displayName() }} × {{ $item->quantity }} · {{ money($item->line_amount) }}</div>
    @endforeach
    @if($return->status === 'requested')
        <form method="POST" action="{{ route('vendor.returns.approve', $return) }}" style="display:inline-block;margin-top:1rem;">@csrf<button class="btn btn-primary">Approve</button></form>
        <form method="POST" action="{{ route('vendor.returns.reject', $return) }}" style="display:inline-block;margin-top:1rem;">
            @csrf
            <input name="reason" required placeholder="Rejection reason" class="form-control" style="display:inline-block;width:220px;">
            <button class="btn btn-ghost">Reject</button>
        </form>
    @endif
    @if($return->status === 'approved')
        <form method="POST" action="{{ route('vendor.returns.receive', $return) }}" style="margin-top:1rem;">
            @csrf
            <label><input type="checkbox" name="restockable" value="1" checked> Restock inventory</label>
            <button class="btn btn-primary" type="submit" style="display:block;margin-top:.75rem;">Mark received</button>
        </form>
    @endif
</div>
@endsection
