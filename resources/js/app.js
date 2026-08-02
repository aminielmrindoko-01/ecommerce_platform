/**
 * Storefront progressive enhancement: hero carousel, flash countdowns,
 * recently-viewed (localStorage + API), toast auto-dismiss, search typeahead.
 * Failures on fetch are swallowed so UI never hard-breaks for optional widgets.
 */

import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    initHeroSlider();
    initFlashCountdowns();
    initRecentlyViewed();
    initToastDismiss();
    initSearchSuggest();
});

/**
 * Allow only same-origin relative paths or http(s) URLs for href/src attributes.
 */
function safeUrl(value, fallback = '#') {
    const url = String(value ?? '').trim();
    if (url.startsWith('/') && !url.startsWith('//')) {
        return url;
    }
    if (/^https?:\/\//i.test(url)) {
        return url;
    }
    return fallback;
}

/**
 * Rotate `[data-hero-slide]` panels every 5.5s; dots jump to a specific slide.
 */
function initHeroSlider() {
    const root = document.querySelector('[data-hero-slider]');
    if (!root) return;

    const slides = [...root.querySelectorAll('[data-hero-slide]')];
    const dots = [...root.querySelectorAll('[data-hero-dot]')];
    if (slides.length < 2) return;

    let index = 0;
    const show = (i) => {
        index = (i + slides.length) % slides.length;
        slides.forEach((s, n) => s.classList.toggle('is-active', n === index));
        dots.forEach((d, n) => d.classList.toggle('is-active', n === index));
    };

    dots.forEach((dot, i) => dot.addEventListener('click', () => show(i)));
    setInterval(() => show(index + 1), 5500);
}

/**
 * Render HH/MM/SS units into `[data-countdown]` from a ms epoch attribute.
 * Stops the interval when the countdown reaches zero.
 */
function initFlashCountdowns() {
    document.querySelectorAll('[data-countdown]').forEach((el) => {
        const endsAt = Number(el.getAttribute('data-countdown'));
        if (!endsAt) return;

        const tick = () => {
            const diff = Math.max(0, endsAt - Date.now());
            const h = Math.floor(diff / 3600000);
            const m = Math.floor((diff % 3600000) / 60000);
            const s = Math.floor((diff % 60000) / 1000);
            el.replaceChildren();
            [h, m, s].forEach((unit) => {
                const span = document.createElement('span');
                span.className = 'flash-unit';
                span.textContent = String(unit).padStart(2, '0');
                el.appendChild(span);
            });
            if (diff <= 0) clearInterval(timer);
        };

        tick();
        const timer = setInterval(tick, 1000);
    });
}

/**
 * Track current product id in localStorage and hydrate `[data-recently-viewed]`
 * via GET /api/products/recent. Prices shown as TSh (display currency not applied in JS).
 */
function initRecentlyViewed() {
    const key = 'sana_recently_viewed';
    const current = document.body.dataset.productId;
    let ids = [];

    try {
        ids = JSON.parse(localStorage.getItem(key) || '[]');
    } catch {
        ids = [];
    }

    if (current) {
        ids = [String(current), ...ids.filter((id) => id !== String(current))].slice(0, 12);
        localStorage.setItem(key, JSON.stringify(ids));
    }

    const mount = document.querySelector('[data-recently-viewed]');
    if (!mount || !ids.length) return;

    const exclude = mount.dataset.excludeId;
    const query = ids.filter((id) => id !== exclude).slice(0, 8).join(',');
    if (!query) return;

    fetch(`/api/products/recent?ids=${encodeURIComponent(query)}`)
        .then((r) => (r.ok ? r.json() : []))
        .then((items) => {
            if (!Array.isArray(items) || !items.length) return;
            mount.replaceChildren();
            items.forEach((p) => {
                const href = safeUrl(p.url, '/products');
                const image = safeUrl(p.image, '/favicon.ico');
                const card = document.createElement('a');
                card.href = href;
                card.className = 'product-card';

                const media = document.createElement('div');
                media.className = 'product-card-media';
                const img = document.createElement('img');
                img.src = image;
                img.alt = String(p.name ?? 'Product');
                img.loading = 'lazy';
                img.width = 400;
                img.height = 400;
                media.appendChild(img);

                const body = document.createElement('div');
                body.className = 'product-card-body';

                const brand = document.createElement('div');
                brand.className = 'product-brand';
                brand.textContent = p.brand || 'SANA';

                const name = document.createElement('h3');
                name.className = 'product-name';
                name.textContent = p.name || 'Product';

                const price = document.createElement('div');
                price.className = 'price';
                price.textContent = `TSh ${Number(p.price).toLocaleString()}`;

                body.append(brand, name, price);
                card.append(media, body);
                mount.appendChild(card);
            });
        })
        .catch(() => {});
}

/**
 * Auto-hide flash toasts after ~4.5s with a short fade-out.
 */
function initToastDismiss() {
    document.querySelectorAll('[data-toast]').forEach((el) => {
        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transition = 'opacity 0.35s ease';
            setTimeout(() => el.remove(), 400);
        }, 4500);
    });
}

/**
 * Debounced typeahead against /api/search/suggest (220ms). Hides panel on outside click.
 * Requires `[data-search-input]` + `[data-search-suggest]` in the layout.
 */
function initSearchSuggest() {
    const input = document.querySelector('[data-search-input]');
    const box = document.querySelector('[data-search-suggest]');
    if (!input || !box) return;

    let timer;
    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) {
            box.hidden = true;
            box.replaceChildren();
            return;
        }
        timer = setTimeout(() => {
            fetch(`/api/search/suggest?q=${encodeURIComponent(q)}`)
                .then((r) => (r.ok ? r.json() : []))
                .then((items) => {
                    if (!items.length) {
                        box.hidden = true;
                        return;
                    }
                    box.hidden = false;
                    box.replaceChildren();
                    items.forEach((item) => {
                        const link = document.createElement('a');
                        link.href = safeUrl(item.url, '/products');
                        link.setAttribute('role', 'option');
                        link.appendChild(document.createTextNode(item.name || 'Product'));
                        if (item.brand) {
                            link.appendChild(document.createTextNode(' '));
                            const span = document.createElement('span');
                            span.style.color = 'var(--color-ink-muted)';
                            span.textContent = item.brand;
                            link.appendChild(span);
                        }
                        box.appendChild(link);
                    });
                })
                .catch(() => {
                    box.hidden = true;
                });
        }, 220);
    });

    document.addEventListener('click', (e) => {
        if (!box.contains(e.target) && e.target !== input) {
            box.hidden = true;
        }
    });
}
