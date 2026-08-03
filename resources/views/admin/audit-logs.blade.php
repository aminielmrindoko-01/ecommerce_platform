@extends('layouts.app')
@section('title', 'Audit logs')
@section('content')
@include('admin._nav')
<h1 class="font-display">Audit logs</h1>
<p style="color:var(--color-ink-muted);">Append-only operational history. Ordinary admins cannot delete these records.</p>
<div class="panel" style="margin-top:1rem;overflow:auto;">
<table style="width:100%;border-collapse:collapse;min-width:800px;">
<thead>
<tr style="text-align:left;border-bottom:1px solid var(--color-border);">
<th style="padding:.75rem;">When</th><th>Actor</th><th>Action</th><th>Resource</th><th>Result</th><th>Category</th>
</tr>
</thead>
<tbody>
@foreach($logs as $log)
<tr style="border-bottom:1px solid var(--color-border);">
<td style="padding:.75rem;white-space:nowrap;">{{ $log->created_at }}</td>
<td>{{ $log->actor?->email ?? 'system' }}</td>
<td>{{ $log->action }}</td>
<td>{{ $log->resource_type }} #{{ $log->resource_id }}</td>
<td>{{ $log->result }}</td>
<td>{{ $log->category }}</td>
</tr>
@endforeach
</tbody>
</table>
</div>
<div style="margin-top:1rem;">{{ $logs->links() }}</div>
@endsection
