const $ = (id) => document.getElementById(id);

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-toggle]');
    if (toggle) {
        const id = toggle.dataset.toggle;
        const target = $(id);
        if (id === 'sidebar') {
            const open = $('app-sidebar').classList.toggle('-translate-x-full') === false;
            $('app-sidebar').classList.toggle('shadow-xl', open);
            $('sidebar-backdrop')?.classList.toggle('hidden', !open);
            toggle.setAttribute('aria-expanded', String(open));
        } else if (id === 'apt-modal') {
            target.classList.remove('hidden');
            target.classList.add('flex');
            target.querySelector('a, button, input')?.focus();
        } else if (target) {
            const hidden = target.classList.toggle('hidden');
            toggle.setAttribute('aria-expanded', String(!hidden));
            if (toggle.hasAttribute('data-hide-self')) toggle.classList.toggle('hidden', !hidden);
            if (id === 'apt-add-form' && hidden) document.querySelector('[data-hide-self]')?.classList.remove('hidden');
        }
        return;
    }

    const close = event.target.closest('[data-close]');
    if (close || event.target === $('apt-modal')) {
        const id = close?.dataset.close ?? 'apt-modal';
        if (id === 'sidebar') {
            $('app-sidebar').classList.add('-translate-x-full');
            $('sidebar-backdrop')?.classList.add('hidden');
        } else {
            $(id).classList.add('hidden');
            $(id).classList.remove('flex');
        }
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        $('apt-modal')?.classList.add('hidden');
        $('apt-modal')?.classList.remove('flex');
    }
});

document.addEventListener('click', (event) => {
    const current = event.target.closest('details.dropdown');
    document.querySelectorAll('details.dropdown[open]').forEach((d) => { if (d !== current) d.removeAttribute('open'); });
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') document.querySelectorAll('details.dropdown[open]').forEach((d) => d.removeAttribute('open'));
});

document.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-confirm-code]');
    if (!form) return;
    const typed = window.prompt(form.dataset.confirmText);
    if (typed === null || typed.trim().toUpperCase() !== form.dataset.confirmCode.toUpperCase()) {
        event.preventDefault();
        return;
    }
    form.querySelector('input[name=confirm]').value = form.dataset.confirmCode;
});

document.addEventListener('submit', (event) => {
    const text = event.target.dataset?.promptNote;
    if (text) event.target.querySelector('input[name=note]').value = window.prompt(text) ?? '';
});

document.addEventListener('change', (event) => {
    const source = event.target.closest('[data-show-target]');
    if (!source) return;
    source.form.querySelectorAll('[data-target-for]').forEach((el) => {
        el.hidden = el.dataset.targetFor !== source.value;
    });
});
document.querySelectorAll('[data-show-target]').forEach((el) => el.dispatchEvent(new Event('change', { bubbles: true })));

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const nf = (min, max) => new Intl.NumberFormat('tr-TR', { minimumFractionDigits: min, maximumFractionDigits: max });
const money = (v) => {
    const [int, dec] = nf(2, 2).format(v).split(',');
    return `₺${int}<span class="dec">,${dec}</span>`;
};
const formats = {
    money,
    number: (v) => nf(0, 0).format(Math.round(v)),
    percent: (v) => `${nf(1, 1).format(v)}<span class="dec"> %</span>`,
};

const animateCounter = (el, fromVal, toVal, duration = 900) => {
    const format = formats[el.dataset.format] ?? formats.number;
    if (reduceMotion) {
        el.innerHTML = format(toVal);
        return;
    }
    const start = performance.now();
    const step = (now) => {
        const p = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - p, 3);
        el.innerHTML = format(fromVal + (toVal - fromVal) * eased);
        if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
};

document.querySelectorAll('[data-count]').forEach((el) => {
    const target = parseFloat(el.dataset.count) || 0;
    el.dataset.baseCount = String(target);
    animateCounter(el, 0, target, 1050);
});

document.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-scroll]');
    if (!btn) return;
    const track = document.getElementById(btn.dataset.scroll);
    track?.scrollBy({ left: (btn.dataset.dir === 'prev' ? -1 : 1) * track.clientWidth * 0.8, behavior: reduceMotion ? 'auto' : 'smooth' });
});

const deptIcons = [
    `<span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#f7f7fa]"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 21s-7-4.5-9.2-9C1.1 8.5 3.2 4.5 7 4.5c2.1 0 3.8 1.2 5 2.8 1.2-1.6 2.9-2.8 5-2.8 3.8 0 5.9 4 4.2 7.5-2.2 4.5-9.2 9-9.2 9z" fill="#e14942"/><path d="M9.5 4.8V2.5M14.5 4.8V2.5" stroke="#b9322c" stroke-width="2.2" stroke-linecap="round"/><path d="M8 9.5c1-1.5 2.5-2 4-1.5" stroke="#ff9a95" stroke-width="1.6" stroke-linecap="round"/></svg></span>`,
    `<span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#f7f7fa]"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M6 18L18 6M8.5 6.5l9 9" stroke="#2f8f83" stroke-width="2.2" stroke-linecap="round"/><circle cx="6" cy="18" r="2.5" stroke="#2f8f83" stroke-width="2"/><circle cx="18" cy="18" r="2.5" stroke="#2f8f83" stroke-width="2"/><path d="M14 5l5 5-7 7-5-5 7-7z" fill="#63c5b8" fill-opacity="0.35"/></svg></span>`,
    `<span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#f7f7fa]"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 3l7 3v5c0 5-3.2 8.8-7 10-3.8-1.2-7-5-7-10V6l7-3z" fill="#6e5ce0" fill-opacity="0.2" stroke="#6e5ce0" stroke-width="2"/><path d="M9 12l2 2 4-4" stroke="#6e5ce0" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>`,
];

document.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-dept-nav]');
    if (!btn) return;
    const box = document.getElementById(btn.dataset.deptBox);
    if (!box) return;
    const slides = JSON.parse(box.dataset.deptSlides || '[]');
    if (!slides.length) return;

    const currIdx = parseInt(box.dataset.deptIndex || '0', 10);
    const delta = btn.dataset.deptNav === 'prev' ? -1 : 1;
    const nextIdx = (currIdx + delta + slides.length) % slides.length;
    box.dataset.deptIndex = String(nextIdx);

    const prevSlide = slides[currIdx];
    const nextSlide = slides[nextIdx];

    const titleEl = box.querySelector('[data-dept-title]');
    const linkEl = box.querySelector('[data-dept-link]');
    const iconSlot = box.querySelector('[data-dept-icon-slot]');
    const earnedEl = box.querySelector('[data-dept-earned]');
    const utilEl = box.querySelector('[data-dept-util]');
    const tickBar = box.querySelector('[data-tick-bar]');
    const ticks = Array.from(box.querySelectorAll('[data-tick]'));
    const labelEl = box.querySelector('[data-tick-label]');

    if (titleEl) titleEl.textContent = nextSlide.title;
    if (linkEl && nextSlide.href) linkEl.setAttribute('href', nextSlide.href);
    if (iconSlot) iconSlot.innerHTML = deptIcons[nextIdx % deptIcons.length];

    const fromEarned = parseFloat(prevSlide.earned) || 0;
    const toEarned = parseFloat(nextSlide.earned) || 0;
    const fromUtil = parseFloat(prevSlide.util) || 0;
    const toUtil = parseFloat(nextSlide.util) || 0;
    const fromPct = parseFloat(tickBar?.dataset.pct || prevSlide.pct) || 0;
    const toPct = parseFloat(nextSlide.pct) || 0;
    const totalTicks = ticks.length || 44;

    const start = performance.now();
    const duration = reduceMotion ? 1 : 620;
    const step = (now) => {
        const p = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - p, 3);
        const curEarned = fromEarned + (toEarned - fromEarned) * eased;
        const curUtil = fromUtil + (toUtil - fromUtil) * eased;
        const curPct = fromPct + (toPct - fromPct) * eased;
        const filledCount = Math.round((curPct / 100) * totalTicks);

        if (earnedEl) earnedEl.textContent = `+₺${nf(0, 0).format(Math.round(curEarned))}`;
        if (utilEl) utilEl.textContent = `${Math.round(curUtil)}%`;
        if (tickBar) tickBar.dataset.pct = String(curPct);

        ticks.forEach((t, i) => {
            t.classList.toggle('bg-emerald-500', i < filledCount);
            t.classList.toggle('bg-slate-200/80', i >= filledCount);
        });

        if (labelEl) {
            labelEl.style.left = `${Math.max(8, Math.min(92, curPct))}%`;
            labelEl.textContent = `${Math.round(curPct)}%`;
        }

        if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
});

const activateSplinePoint = (btn) => {
    const wrap = btn.closest('[data-spline]');
    if (!wrap) return;
    const line = wrap.querySelector('[data-spline-line]');
    const dot = wrap.querySelector('[data-spline-dot]');
    const tip = wrap.querySelector('[data-spline-tip]');
    const v1 = wrap.querySelector('[data-spline-v1]');
    const v2 = wrap.querySelector('[data-spline-v2]');
    const day = wrap.querySelector('[data-spline-day]');
    const date = wrap.querySelector('[data-spline-date]');

    const x = btn.dataset.x;
    const y1 = btn.dataset.y1;
    const pctX = parseFloat(btn.dataset.pctX) || 50;
    const pctY = parseFloat(btn.dataset.pctY) || 50;

    if (line) {
        line.setAttribute('x1', x);
        line.setAttribute('x2', x);
        line.setAttribute('y1', y1);
    }
    if (dot) {
        dot.style.left = `${pctX}%`;
        dot.style.top = `${pctY}%`;
    }
    if (tip) {
        const leftOffset = pctX > 72 ? `calc(${pctX}% - 166px)` : `calc(${pctX}% + 14px)`;
        tip.style.left = leftOffset;
        tip.style.top = `${Math.max(26, Math.min(72, pctY))}%`;
    }
    if (v1) v1.textContent = btn.dataset.v1;
    if (v2) v2.textContent = btn.dataset.v2;
    if (day) day.textContent = btn.dataset.day;
    if (date) date.textContent = btn.dataset.date;

    const activeIdx = btn.dataset.splinePoint;
    wrap.querySelectorAll('[data-spline-axis]').forEach((ax) => {
        const isCurrent = ax.dataset.splineAxis === activeIdx;
        ax.classList.toggle('font-semibold', isCurrent);
        ax.classList.toggle('text-ink', isCurrent);
    });
};

document.addEventListener('mouseover', (event) => {
    const pt = event.target.closest('[data-spline-point]');
    if (pt) activateSplinePoint(pt);
});
document.addEventListener('focusin', (event) => {
    const pt = event.target.closest('[data-spline-point]');
    if (pt) activateSplinePoint(pt);
});

const rangeMultipliers = {
    month: { kpi: 1.0, bars: [54, 85, 100, 65, 100, 46] },
    quarter: { kpi: 0.954, bars: [48, 76, 92, 58, 88, 52] },
    year: { kpi: 1.18, bars: [66, 92, 84, 78, 96, 62] },
    all: { kpi: 1.42, bars: [74, 88, 98, 82, 94, 70] },
};

document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-range-switch]');
    if (!link) return;
    event.preventDefault();
    const key = link.dataset.rangeSwitch;
    const cfg = rangeMultipliers[key] || rangeMultipliers.month;

    const label = document.querySelector('[data-range-label]');
    if (label && link.dataset.label) label.textContent = link.dataset.label;

    link.closest('details.dropdown')?.removeAttribute('open');
    document.querySelectorAll('[data-range-switch]').forEach((a) => a.classList.toggle('active', a === link));

    document.querySelectorAll('[data-count]').forEach((el) => {
        const base = parseFloat(el.dataset.baseCount ?? el.dataset.count) || 0;
        const curr = parseFloat(el.dataset.count) || base;
        const next = el.dataset.format === 'percent' ? Math.min(99.4, Math.max(45, base * (key === 'quarter' ? 0.865 : (key === 'year' ? 1.06 : 1.0)))) : base * cfg.kpi;
        el.dataset.count = String(next);
        animateCounter(el, curr, next, 650);
    });

    document.querySelectorAll('[data-bar-col]').forEach((col, i) => {
        const pct = cfg.bars[i % cfg.bars.length];
        const fill = col.querySelector('[data-bar-fill]');
        const dot = col.querySelector('[data-bar-dot]');
        const load = col.querySelector('[data-bar-load]');
        if (fill) fill.style.height = `${pct}%`;
        if (dot) dot.style.bottom = `calc(28px + ${pct} * 1.88px - 7px)`;
        if (load) load.textContent = `${pct}%`;
    });
});

document.addEventListener('input', (event) => {
    const input = event.target.closest('[data-table-search]');
    if (!input) return;
    const q = input.value.trim().toLowerCase();
    const section = input.closest('[data-overview-table]');
    section?.querySelectorAll('[data-table-row]').forEach((row) => {
        row.classList.toggle('hidden', q !== '' && !row.textContent.toLowerCase().includes(q));
    });
});

document.querySelectorAll('[data-tabs]').forEach((box) => {
    const buttons = box.querySelectorAll('[data-filter]');
    buttons.forEach((btn) => btn.addEventListener('click', () => {
        buttons.forEach((b) => b.classList.toggle('active', b === btn));
        let shown = 0;
        box.querySelectorAll('[data-kind]').forEach((row) => {
            const ok = btn.dataset.filter === 'all' || row.dataset.kind === btn.dataset.filter;
            row.hidden = !ok;
            shown += ok ? 1 : 0;
        });
        box.querySelector('[data-empty]')?.toggleAttribute('hidden', shown > 0);
    }));
});
