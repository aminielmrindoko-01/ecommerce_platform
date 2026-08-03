@extends('layouts.app')
@section('title', 'Security events')
@section('content')
@include('admin._nav')
<h1 class="font-display">Security events</h1>
<p style="color:var(--color-ink-muted);">High-signal authorization and authentication events.</p>
<div class="panel" style="margin-top:1rem;overflow:auto;">
<table style="width:100%;border-collapse:collapse;min-width:800px;">
<thead>
<tr style="text-align:left;border-bottom:1px solid var(--color-border);">
<th style="padding:.75rem;">When</th><th>Actor</th><th>Event</th><th>Severity</th><th>IP</th>
</tr>
</thead>
<tbody>
@foreach($events as $event)
<tr style="border-bottom:1px solid var(--color-border);">
<td style="padding:.75rem;white-space:nowrap;">{{ $event->created_at }}</td>
<td>{{ $event->actor?->email ?? '—' }}</td>
<td>{{ $event->event }}</td>
<td>{{ $event->severity }}</td>
<td>{{ $event->ip_address }}</td>
</tr>
@endforeach
</tbody>
</table>
</div>
<div style="margin-top:1rem;">{{ $events->links() }}</div>
@endsection
