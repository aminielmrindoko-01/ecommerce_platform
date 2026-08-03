@extends('layouts.app')
@section('title', 'Return '.$return->reference)
@section('content')
@include('admin._nav')
@include('admin.operations._nav')
@if($errors->any())<div class="panel" style="border-color:#b91c1c;">{{ $errors->first() }}</div>@endif
@if(session('success'))<div class="panel">{{ session('success') }}</div>@endif
<div class="panel">
    <h1 class="font-display" style="margin-top:0;">{{ $return->reference }}</h1>
    <p>{{ ucfirst($return->status) }} · Vendor {{ $return->vendor?->store_name }} · Order {{ $return->order?->order_number }}</p>
    <p>{{ $return->reason }}</p>
    @foreach($return->items as $item)
        <div>{{ $item->orderItem?->displayName() }} × {{ $item->quantity }} · {{ money($item->line_amount) }}</div>
    @endforeach
    <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap;">
        @if($return->status==='requested')
            <form method="POST" action="{{ route('admin.operations.returns.approve', $return) }}">@csrf<button class="btn btn-primary">Approve</button></form>
            <form method="POST" action="{{ route('admin.operations.returns.reject', $return) }}">@csrf<input name="reason" required placeholder="Reason"><button class="btn btn-ghost">Reject</button></form>
        @endif
        @if($return->status==='approved')
            <form method="POST" action="{{ route('admin.operations.returns.receive', $return) }}">@csrf<label><input type="checkbox" name="restockable" value="1" checked> Restock</label><button class="btn btn-primary">Receive</button></form>
        @endif
        @if($return->status==='received' && auth()->user()->hasPermission('refunds.create'))
            <form method="POST" action="{{ route('admin.operations.returns.refund', $return) }}">@csrf<button class="btn btn-primary">Process refund</button></form>
        @endif
    </div>
</div>
@endsection
