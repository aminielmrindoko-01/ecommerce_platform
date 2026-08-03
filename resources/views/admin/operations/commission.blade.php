@extends('layouts.app')
@section('title', 'Commission')
@section('content')
@include('admin._nav')
@include('admin.operations._nav')
<h1 class="font-display">Commission configuration</h1>
<p style="color:var(--color-ink-muted);">Changes apply to future entitlements only. Historical snapshots are immutable.</p>
@if(session('success'))<div class="panel">{{ session('success') }}</div>@endif
<div class="panel" style="max-width:420px;">
    <p>Current platform: {{ $platform['type'] }} @ {{ $platform['rate'] }} (scope {{ $platform['scope'] }})</p>
    @canPermission('commission.manage')
    <form method="POST" action="{{ route('admin.operations.commission.update') }}">
        @csrf
        <label>Type</label>
        <select class="form-control" name="type"><option value="percentage">percentage</option><option value="fixed">fixed</option></select>
        <label style="margin-top:.5rem;display:block;">Rate (0–1)</label>
        <input class="form-control" name="rate" value="{{ $platform['rate'] }}" required>
        <label style="margin-top:.5rem;display:block;">Fixed amount</label>
        <input class="form-control" name="fixed_amount" value="{{ $platform['fixed_amount'] ?? '0' }}">
        <button class="btn btn-primary" style="margin-top:.75rem;">Update platform commission</button>
    </form>
    @endcanPermission
</div>
@endsection
