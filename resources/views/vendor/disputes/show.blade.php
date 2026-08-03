@extends('layouts.app')
@section('title', 'Dispute '.$dispute->reference)
@section('content')
@include('vendor._nav')
<div class="panel">
    <h1 class="font-display" style="margin-top:0;">{{ $dispute->reference }}</h1>
    <p>{{ $dispute->subject }} · <strong>{{ str_replace('_',' ', ucfirst($dispute->status)) }}</strong></p>
    @if($dispute->settlementHold)<p style="color:var(--color-ink-muted);">Financial hold: {{ money($dispute->settlementHold->amount) }} ({{ $dispute->settlementHold->status }})</p>@endif
    @foreach($dispute->messages as $msg)
        <div style="padding:.65rem 0;border-bottom:1px solid var(--color-border);">
            <strong>{{ ucfirst($msg->author_role) }}</strong>
            <p style="margin:.35rem 0 0;">{{ $msg->body }}</p>
        </div>
    @endforeach
    @if($dispute->isOpen())
        <form method="POST" action="{{ route('vendor.disputes.respond', $dispute) }}" style="margin-top:1rem;">
            @csrf
            <textarea class="form-control" name="body" required></textarea>
            <button class="btn btn-primary" type="submit" style="margin-top:.75rem;">Respond</button>
        </form>
    @endif
</div>
@endsection
