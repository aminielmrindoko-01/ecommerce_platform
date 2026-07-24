import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    initHeroSlider();
    initFlashCountdowns();
    initRecentlyViewed();
    initToastDismiss();
    initSearchSuggest();
});

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

function initFlashCountdowns() {
    document.querySelectorAll('[data-countdown]').forEach((el) => {
        const endsAt = Number(el.getAttribute('data-countdown'));
        if (!endsAt) return;

        const tick = () => {
            const diff = Math.max(0, endsAt - Date.now());
            const h = Math.floor(diff / 3600000);
            const m = Math.floor((diff % 3600000) / 60000);
            const s = Math.floor((diff % 60000) / 1000);
            el.innerHTML = `
                <span class="flash-unit">${String(h).padStart(2, '0')}</span>
                <span class="flash-unit">${String(m).padStart(2, '0')}</span>
                <span class="flash-unit">${String(s).padStart(2, '0')}</span>
            `;
            if (diff <= 0) clearInterval(timer);
        };

        tick();
        const timer = setInterval(tick, 1000);
    });
}

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
            mount.innerHTML = items
                .map(
                    (p) => `
                <a href="${p.url}" class="product-card">
                    <div class="product-card-media">
                        <img src="${p.image}" alt="${p.name}" loading="lazy" width="400" height="400">
                    </div>
                    <div class="product-card-body">
                        <div class="product-brand">${p.brand || 'SANA'}</div>
                        <h3 class="product-name">${p.name}</h3>
                        <div class="price">TSh ${Number(p.price).toLocaleString()}</div>
                    </div>
                </a>`
                )
                .join('');
        })
        .catch(() => {});
}

function initToastDismiss() {
    document.querySelectorAll('[data-toast]').forEach((el) => {
        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transition = 'opacity 0.35s ease';
            setTimeout(() => el.remove(), 400);
        }, 4500);
    });
}

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
            box.innerHTML = '';
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
                    box.innerHTML = items
                        .map(
                            (item) =>
                                `<a href="${item.url}" role="option">${item.name} <span style="color:var(--color-ink-muted)">${item.brand || ''}</span></a>`
                        )
                        .join('');
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
