@extends('layouts.app')
@section('title', 'Disputes')
@section('content')
@include('vendor._nav')
<h1 class="font-display">Disputes</h1>
@forelse($disputes as $dispute)
    <div class="panel" style="margin-bottom:.75rem;">
        <a href="{{ route('vendor.disputes.show', $dispute) }}"><strong>{{ $dispute->reference }}</strong></a>
        · {{ str_replace('_',' ', ucfirst($dispute->status)) }} · {{ $dispute->subject }}
    </div>
@empty
    <p style="color:var(--color-ink-muted);">No disputes for your store.</p>
@endforelse
{{ $disputes->links() }}
@endsection
