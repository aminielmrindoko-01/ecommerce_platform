@extends('layouts.app')
@section('title', 'Chargebacks')
@section('content')
@include('admin._nav')
@include('admin.operations._nav')
<h1 class="font-display">Chargebacks</h1>
<p style="color:var(--color-ink-muted);">CHARGEBACK INTEGRATION: INTERNAL ARCHITECTURE / NOT PROVIDER-CONNECTED</p>
@if(session('success'))<div class="panel">{{ session('success') }}</div>@endif
@if($errors->any())<div class="panel" style="border-color:#b91c1c;">{{ $errors->first() }}</div>@endif

@canPermission('chargebacks.create')
<div class="panel" style="max-width:480px;">
    <h2 style="margin-top:0;font-size:1.1rem;">Record chargeback</h2>
    <form method="POST" action="{{ route('admin.operations.chargebacks.store') }}">
        @csrf
        <label>Order ID</label>
        <input class="form-control" name="order_id" required>
        <label style="margin-top:.5rem;display:block;">Amount</label>
        <input class="form-control" name="amount" required>
        <label style="margin-top:.5rem;display:block;">Provider reference</label>
        <input class="form-control" name="provider_reference">
        <label style="margin-top:.5rem;display:block;">Reason</label>
        <textarea class="form-control" name="reason"></textarea>
        <button class="btn btn-primary" style="margin-top:.75rem;">Record</button>
    </form>
</div>
@endcanPermission

@foreach($chargebacks as $cb)
    <div class="panel" style="margin-top:.75rem;">
        <strong>{{ $cb->reference }}</strong> · {{ ucfirst($cb->status) }} · {{ money($cb->amount) }} {{ $cb->currency }}
        · Order #{{ $cb->order_id }}
        @canPermission('chargebacks.resolve')
        <form method="POST" action="{{ route('admin.operations.chargebacks.status', $cb) }}" style="margin-top:.5rem;display:flex;gap:.4rem;flex-wrap:wrap;">
            @csrf
            <select name="status" class="form-control" style="max-width:180px;">
                @foreach(['under_review','responded','accepted','lost','won','closed'] as $st)
                    <option value="{{ $st }}">{{ $st }}</option>
                @endforeach
            </select>
            <button class="btn btn-ghost">Update</button>
        </form>
        @endcanPermission
    </div>
@endforeach
{{ $chargebacks->links() }}
@endsection
