@extends('layouts.app')
@section('title', 'Dispute '.$dispute->reference)
@section('content')
@include('account._nav')
<div class="panel">
    <h1 class="font-display" style="margin-top:0;">{{ $dispute->reference }}</h1>
    <p>{{ $dispute->subject }} · <strong>{{ str_replace('_',' ', ucfirst($dispute->status)) }}</strong></p>
    @foreach($dispute->messages as $msg)
        <div style="padding:.65rem 0;border-bottom:1px solid var(--color-border);">
            <strong>{{ ucfirst($msg->author_role) }}</strong>
            <div style="color:var(--color-ink-muted);font-size:.85rem;">{{ $msg->created_at }}</div>
            <p style="margin:.35rem 0 0;">{{ $msg->body }}</p>
        </div>
    @endforeach
    @if($dispute->isOpen())
        <form method="POST" action="{{ route('account.disputes.respond', $dispute) }}" style="margin-top:1rem;">
            @csrf
            <textarea class="form-control" name="body" required maxlength="5000" placeholder="Your response"></textarea>
            <button class="btn btn-primary" type="submit" style="margin-top:.75rem;">Reply</button>
        </form>
    @endif
</div>
@endsection
