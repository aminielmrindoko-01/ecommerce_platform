@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
@include('account._nav')
<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">Notifications</h1>
        <p>{{ $unreadCount }} unread</p>
    </div>
    @if($unreadCount > 0)
        <form method="POST" action="{{ route('account.notifications.readAll') }}">
            @csrf
            <button type="submit" class="btn btn-ghost">Mark all as read</button>
        </form>
    @endif
</div>

<div class="panel" style="padding:0;margin-top:1rem;">
@forelse($notifications as $n)
    @php $data = $n->data; @endphp
    <article style="padding:1rem;border-bottom:1px solid var(--color-border);{{ $n->read_at ? '' : 'background:var(--color-surface);' }}">
        <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;">
            <div>
                <strong>{{ $data['title'] ?? 'Notification' }}</strong>
                @unless($n->read_at)<span class="chip" style="margin-left:.4rem;">Unread</span>@endunless
                <p style="margin:.35rem 0;color:var(--color-ink-muted);">{{ $data['body'] ?? '' }}</p>
                <span style="font-size:.8rem;color:var(--color-ink-muted);">{{ $n->created_at?->diffForHumans() }}</span>
            </div>
            @unless($n->read_at)
                <form method="POST" action="{{ route('account.notifications.read', $n->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost" style="padding:.4rem .65rem;">Mark read</button>
                </form>
            @endunless
        </div>
    </article>
@empty
    <p style="padding:1.25rem;color:var(--color-ink-muted);">No notifications yet.</p>
@endforelse
</div>
@if(method_exists($notifications, 'links'))
    <div style="margin-top:1rem;">{{ $notifications->links() }}</div>
@endif
@endsection
