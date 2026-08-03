@extends('layouts.app')
@section('title', 'Disputes')
@section('content')
@include('admin._nav')
@include('admin.operations._nav')
<h1 class="font-display">Disputes</h1>
@forelse($disputes as $dispute)
    <div class="panel" style="margin-bottom:.6rem;">
        <a href="{{ route('admin.operations.disputes.show', $dispute) }}"><strong>{{ $dispute->reference }}</strong></a>
        · {{ str_replace('_',' ', ucfirst($dispute->status)) }} · {{ $dispute->subject }}
    </div>
@empty
    <p>No disputes.</p>
@endforelse
{{ $disputes->links() }}
@endsection
