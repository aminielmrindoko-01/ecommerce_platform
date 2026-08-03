@extends('layouts.app')
@section('title', 'Roles & permissions')
@section('content')
@include('admin._nav')
<h1 class="font-display">Roles & permissions</h1>
<p style="color:var(--color-ink-muted);">System roles seeded from <code>config/authorization.php</code>. Assigning permissions requires <code>permissions.assign</code>.</p>
<div class="panel" style="margin-top:1rem;overflow:auto;">
<table style="width:100%;border-collapse:collapse;min-width:640px;">
<thead>
<tr style="text-align:left;border-bottom:1px solid var(--color-border);">
<th style="padding:.75rem;">Role</th><th>Slug</th><th>Users</th><th>Permissions</th><th>System</th>
</tr>
</thead>
<tbody>
@foreach($roles as $role)
<tr style="border-bottom:1px solid var(--color-border);">
<td style="padding:.75rem;">{{ $role->display_name }}</td>
<td><code>{{ $role->name }}</code></td>
<td>{{ $role->users_count }}</td>
<td>{{ $role->permissions_count }}</td>
<td>{{ $role->is_system ? 'yes' : 'no' }}</td>
</tr>
@endforeach
</tbody>
</table>
</div>
@endsection
