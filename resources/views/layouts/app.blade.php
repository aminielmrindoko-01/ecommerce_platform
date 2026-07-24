<!DOCTYPE html>
<html lang="{{ $mpLocale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SANA Market') — Online Marketplace</title>
    <meta name="description" content="@yield('meta_description', 'Shop electronics, fashion, home & beauty from verified sellers on SANA Market across East Africa.')">
    <meta name="theme-color" content="#0d7377">
    <link rel="canonical" href="@yield('canonical', url()->current())">

    {{-- Open Graph --}}
    <meta property="og:site_name" content="SANA Market">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('og_title', trim($__env->yieldContent('title')) ?: 'SANA Market')">
    <meta property="og:description" content="@yield('og_description', trim($__env->yieldContent('meta_description')) ?: 'Shop from verified sellers on SANA Market.')">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta property="og:image" content="@yield('og_image', 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=1200&q=80')">
    <meta property="og:locale" content="{{ ($mpLocale ?? 'en') === 'sw' ? 'sw_TZ' : (($mpLocale ?? 'en') === 'fr' ? 'fr_FR' : 'en_US') }}">

    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', 'SANA Market')">
    <meta name="twitter:description" content="@yield('og_description', 'East Africa marketplace for trusted sellers.')">
    <meta name="twitter:image" content="@yield('og_image', 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=1200&q=80')">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'SANA Market',
        'url' => url('/'),
        'logo' => url('/favicon.ico'),
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'telephone' => '+255-700-000-000',
            'contactType' => 'customer service',
            'areaServed' => ['TZ', 'KE', 'UG', 'RW'],
            'availableLanguage' => ['English', 'Swahili', 'French'],
        ],
        'sameAs' => [],
    ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}
    </script>
    @stack('head')
</head>
<body class="site-shell" data-currency="{{ $mpCurrency ?? 'TZS' }}" data-locale="{{ $mpLocale ?? 'en' }}" @isset($product) data-product-id="{{ $product->id }}" @endisset>
    <a class="skip-link" href="#main">Skip to content</a>

    <div class="topbar" role="note">
        <div class="topbar-inner">
            <span>{{ mt('topbar.trust') }}</span>
            <div class="pref-bar" aria-label="Region preferences">
                <form method="POST" action="{{ route('preferences.update') }}" class="pref-form">
                    @csrf
                    <label class="pref-label">
                        <span class="sr-only">Language</span>
                        <select name="locale" class="pref-select" onchange="this.form.submit()" aria-label="Language">
                            @foreach(($mpLanguages ?? []) as $code => $lang)
                                <option value="{{ $code }}" @selected(($mpLocale ?? 'en') === $code)>{{ $lang['native'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="pref-label">
                        <span class="sr-only">Currency</span>
                        <select name="currency" class="pref-select" onchange="this.form.submit()" aria-label="Currency">
                            @foreach(($mpCurrencies ?? []) as $code => $cur)
                                <option value="{{ $code }}" @selected(($mpCurrency ?? 'TZS') === $code)>{{ $code }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="pref-label">
                        <span class="sr-only">Country</span>
                        <select name="country" class="pref-select" onchange="this.form.submit()" aria-label="Ship to country">
                            @foreach(($mpCountries ?? []) as $code => $c)
                                <option value="{{ $code }}" @selected(($mpCountry ?? 'TZ') === $code)>{{ $c['name'] }}</option>
                            @endforeach
                        </select>
                    </label>
                </form>
            </div>
        </div>
    </div>

    <header class="navbar">
        <div class="navbar-inner">
            <a href="{{ route('home') }}" class="brand" aria-label="SANA Market home">
                <span class="brand-mark" aria-hidden="true">S</span>
                SANA Market
            </a>

            <form action="{{ route('products.index') }}" method="GET" class="search-form" role="search" style="position:relative;">
                <label for="nav-search" class="sr-only">Search products</label>
                <input id="nav-search" type="search" name="q" value="{{ request('q') }}" placeholder="{{ mt('search.placeholder') }}" autocomplete="off" data-search-input>
                <button type="submit">{{ mt('search.button') }}</button>
                <div class="search-suggest" data-search-suggest hidden style="position:absolute;left:0;right:0;top:calc(100% + 6px);background:white;border:1px solid var(--color-border);border-radius:12px;box-shadow:var(--shadow-soft);z-index:50;padding:0.4rem;display:grid;gap:0.15rem;"></div>
            </form>

            <nav class="nav-actions" aria-label="Primary">
                <a class="nav-link {{ request()->routeIs('categories') ? 'is-active' : '' }}" href="{{ route('categories') }}">{{ mt('nav.categories') }}</a>
                <a class="nav-link {{ request()->routeIs('deals') ? 'is-active' : '' }}" href="{{ route('deals') }}">{{ mt('nav.deals') }}</a>
                <a class="nav-link {{ request()->routeIs('vendors') ? 'is-active' : '' }}" href="{{ route('vendors') }}">{{ mt('nav.sellers') }}</a>
                @auth
                    <a class="nav-link {{ request()->routeIs('wishlist.*') ? 'is-active' : '' }}" href="{{ route('wishlist.index') }}">{{ mt('nav.wishlist') }}</a>
                    <a class="nav-link {{ request()->routeIs('account.*') || request()->routeIs('profile') ? 'is-active' : '' }}" href="{{ route('account.index') }}">{{ mt('nav.account') }}</a>
                    @if(auth()->user()->isAdmin())
                        <a class="nav-link" href="{{ route('admin.dashboard') }}">{{ mt('nav.admin') }}</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="nav-link" style="background:transparent;border:0;cursor:pointer;font:inherit;">{{ mt('nav.logout') }}</button>
                    </form>
                @else
                    <a class="nav-link" href="{{ route('login') }}">{{ mt('nav.login') }}</a>
                    <a class="btn btn-primary" href="{{ route('register') }}" style="padding:0.5rem 0.9rem;">{{ mt('nav.join') }}</a>
                @endauth
                <a href="{{ route('cart.index') }}" class="cart-pill" aria-label="{{ mt('nav.cart') }} with {{ array_sum(array_column(session('cart', []), 'quantity')) }} items">
                    {{ mt('nav.cart') }}
                    <span class="cart-count">{{ array_sum(array_column(session('cart', []), 'quantity')) ?: 0 }}</span>
                </a>
            </nav>
        </div>
    </header>

    <main class="site-main" id="main">
        @if(session('success'))
            <div class="alert alert-success" role="status" data-toast>{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-error" role="alert" data-toast>{{ session('error') }}</div>
        @endif
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="footer-grid">
            <div>
                <div class="footer-brand">SANA Market</div>
                <p style="margin:0 0 1rem;line-height:1.6;max-width:36ch;">{{ mt('footer.tagline') }}</p>
                <x-payment-methods :compact="true" />
            </div>
            <div>
                <h3>{{ mt('footer.shop') }}</h3>
                <a href="{{ route('products.index') }}">All products</a>
                <a href="{{ route('deals') }}">Flash deals</a>
                <a href="{{ route('categories') }}">Categories</a>
                <a href="{{ route('vendors') }}">Top sellers</a>
            </div>
            <div>
                <h3>{{ mt('footer.help') }}</h3>
                <a href="{{ route('contact') }}">Contact</a>
                <a href="{{ route('about') }}">About us</a>
                <a href="{{ route('blog') }}">Blog</a>
                <a href="{{ route('account.orders') }}">Track order</a>
            </div>
            <div>
                <h3>{{ mt('footer.trust') }}</h3>
                <p style="margin:0;line-height:1.6;">Buyer protection · Easy returns (7 days) · Verified sellers · Encrypted checkout</p>
                <p style="margin:.75rem 0 0;font-size:.85rem;opacity:.85;">Ship region: {{ ($mpShippingRegions ?? [])[($mpCountries[$mpCountry ?? 'TZ']['shipping'] ?? 'east_africa')] ?? 'East Africa' }} · Tax {{ number_format(($mpTaxRate ?? 0.18) * 100, 0) }}%</p>
                <form action="{{ route('newsletter.subscribe') }}" method="POST" style="margin-top:1rem;display:grid;gap:0.5rem;">
                    @csrf
                    <label for="newsletter-email" class="sr-only">Email</label>
                    <input id="newsletter-email" class="form-control" type="email" name="email" required placeholder="Email for deals">
                    <button class="btn btn-accent" type="submit">Subscribe</button>
                </form>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© {{ date('Y') }} SANA Market. All rights reserved.</span>
            <span>Prices shown in {{ $mpCurrency ?? 'TZS' }} · {{ ($mpCountries[$mpCountry ?? 'TZ']['name'] ?? 'Tanzania') }}</span>
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
