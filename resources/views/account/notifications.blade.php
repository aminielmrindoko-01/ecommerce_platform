@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
@include('account._nav')
<h1 class="font-display">Notifications</h1>
<div class="panel" style="padding:0;margin-top:1rem;">
@foreach($notifications as $n)
    <article style="padding:1rem;border-bottom:1px solid var(--color-border);">
        <strong>{{ $n['title'] }}</strong>
        <p style="margin:.35rem 0;color:var(--color-ink-muted);">{{ $n['body'] }}</p>
        <span style="font-size:.8rem;color:var(--color-ink-muted);">{{ $n['time'] }}</span>
    </article>
@endforeach
</div>
@endsection
