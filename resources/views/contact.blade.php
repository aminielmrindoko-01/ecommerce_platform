@extends('layouts.app')
@section('title', 'Contact')
@section('content')
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;max-width:960px;margin:0 auto;">
    <div class="panel">
        <h1 class="font-display" style="margin-top:0;">Contact support</h1>
        <p style="color:var(--color-ink-muted);line-height:1.7;">Email: support@sana.market<br>Phone: +255 700 000 000<br>Hours: Mon–Sat, 8:00–20:00 EAT</p>
    </div>
    <form method="POST" action="{{ route('contact.submit') }}" class="panel">
        @csrf
        <h2 style="margin-top:0;font-size:1.15rem;">Send a message</h2>
        <div class="form-group"><label>Name</label><input class="form-control" name="name" required></div>
        <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" required></div>
        <div class="form-group"><label>Message</label><textarea class="form-control" name="message" rows="4" required></textarea></div>
        <button class="btn btn-primary" type="submit">Send</button>
    </form>
</div>
<style>@media(max-width:800px){.site-main>div[style*="1fr 1fr"]{grid-template-columns:1fr!important;}}</style>
@endsection
