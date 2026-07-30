@extends('layouts.app')
@section('title', 'Blog')
@section('content')
<div class="section-head">
    <div>
        <h1 class="font-display" style="margin:0;">SANA Journal</h1>
        <p>Buying guides, seller tips, and marketplace news</p>
    </div>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;">
    @foreach([
        ['title' => 'How to choose your next flagship phone', 'img' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=800&q=80'],
        ['title' => 'Sneaker care tips for East African weather', 'img' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=800&q=80'],
        ['title' => 'Why verified sellers convert better', 'img' => 'https://images.unsplash.com/photo-1556740749-887f6717d7e4?auto=format&fit=crop&w=800&q=80'],
    ] as $post)
        <article class="panel" style="padding:0;overflow:hidden;">
            <img src="{{ $post['img'] }}" alt="" style="width:100%;height:160px;object-fit:cover;" loading="lazy">
            <div style="padding:1rem;">
                <h2 style="font-size:1.05rem;margin:0;">{{ $post['title'] }}</h2>
                <p style="color:var(--color-ink-muted);margin:.5rem 0 0;font-size:.92rem;">Read more in the full marketplace journal (coming soon).</p>
            </div>
        </article>
    @endforeach
</div>
@endsection
