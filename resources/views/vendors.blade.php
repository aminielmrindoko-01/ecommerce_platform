@extends('layouts.app')

@section('content')

<div class="page-header">
    <h1>🧑‍💼 Our Trusted Vendors</h1>
    <p>Meet our sellers and discover quality products from verified boutiques.</p>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px;">
    <div style="background:white;padding:28px;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
        <h2 style="margin-bottom:10px;color:#111827;">Tech Haven</h2>
        <p style="color:#6b7280;line-height:1.7;">Premium electronics and gadgets with fast delivery and authentic warranties.</p>
        <p style="font-weight:600;margin-top:18px;">Email: techhaven@sana.com</p>
    </div>

    <div style="background:white;padding:28px;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
        <h2 style="margin-bottom:10px;color:#111827;">Fashion Plus</h2>
        <p style="color:#6b7280;line-height:1.7;">Contemporary fashion pieces and seasonal collections designed for modern style.</p>
        <p style="font-weight:600;margin-top:18px;">Email: fashionplus@sana.com</p>
    </div>

    <div style="background:white;padding:28px;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
        <h2 style="margin-bottom:10px;color:#111827;">Home Essentials</h2>
        <p style="color:#6b7280;line-height:1.7;">High-quality home decor and furniture selections crafted for comfortable living.</p>
        <p style="font-weight:600;margin-top:18px;">Email: homeessentials@sana.com</p>
    </div>

    <div style="background:white;padding:28px;border-radius:14px;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
        <h2 style="margin-bottom:10px;color:#111827;">Beauty & Wellness</h2>
        <p style="color:#6b7280;line-height:1.7;">Carefully curated wellness and beauty products for a premium self-care routine.</p>
        <p style="font-weight:600;margin-top:18px;">Email: beauty@sana.com</p>
    </div>
</div>

@endsection