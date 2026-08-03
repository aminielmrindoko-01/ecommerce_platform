@extends('layouts.app')
@section('title', 'Dispute '.$dispute->reference)
@section('content')
@include('admin._nav')
@include('admin.operations._nav')
@if(session('success'))<div class="panel">{{ session('success') }}</div>@endif
<div class="panel">
    <h1 class="font-display" style="margin-top:0;">{{ $dispute->reference }}</h1>
    <p>{{ $dispute->subject }} · {{ str_replace('_',' ', ucfirst($dispute->status)) }}</p>
    @foreach($dispute->messages as $msg)
        <div style="padding:.5rem 0;border-bottom:1px solid var(--color-border);"><strong>{{ ucfirst($msg->author_role) }}</strong><p>{{ $msg->body }}</p></div>
    @endforeach
    <form method="POST" action="{{ route('admin.operations.disputes.respond', $dispute) }}" style="margin-top:1rem;">@csrf<textarea class="form-control" name="body" required></textarea><button class="btn btn-ghost" style="margin-top:.5rem;">Respond</button></form>
    @canPermission('disputes.resolve')
    <form method="POST" action="{{ route('admin.operations.disputes.resolve', $dispute) }}" style="margin-top:1rem;">
        @csrf
        <select class="form-control" name="status">
            <option value="resolved_customer">Resolved — customer</option>
            <option value="resolved_vendor">Resolved — vendor</option>
            <option value="partially_resolved">Partially resolved</option>
            <option value="closed">Closed</option>
        </select>
        <textarea class="form-control" name="notes" placeholder="Notes" style="margin-top:.5rem;"></textarea>
        <button class="btn btn-primary" style="margin-top:.5rem;">Resolve</button>
    </form>
    @endcanPermission
</div>
@endsection
