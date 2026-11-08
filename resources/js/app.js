import { startTour } from './tour';
import { initSetupWizard } from './setup';
import { initSetupGuide } from './setup-guide';

const $ = (id) => document.getElementById(id);
const root = document.documentElement;
const storage = window.localStorage;
const MODALS = ['apt-modal', 'settings-modal'];

const themeKey = () => (root.dataset.hud === 'classic' ? 'classic-theme' : 'theme');
const storedTheme = () => storage.getItem(themeKey()) || (root.dataset.hud === 'classic' ? 'light' : 'dark');

const renderPrefs = () => {
    const values = {
        hud: root.dataset.hud,
        theme: storedTheme(),
        accent: root.dataset.accent,
        motion: root.dataset.motion,
        rail: storage.getItem('rail-pinned') === '1' ? '1' : '0',
    };
    document.querySelectorAll('[data-pref]').forEach((button) => {
        const selected = values[button.dataset.pref] === button.dataset.value;
        button.classList.toggle('active', selected);
        button.setAttribute('aria-pressed', String(selected));
    });
};

const syncTheme = (theme) => {
    const isLight = theme === 'light';
    document.documentElement.classList.toggle('light', isLight);
    document.documentElement.classList.toggle('dark', !isLight);
    document.querySelectorAll('[data-theme-sun]').forEach((el) => el.classList.toggle('hidden', isLight));
    document.querySelectorAll('[data-theme-moon]').forEach((el) => el.classList.toggle('hidden', !isLight));
    document.querySelectorAll('[data-theme-label]').forEach((el) => {
        el.textContent = isLight ? 'Koyu Tema' : 'Açık Tema';
    });
};

const syncHud = (mode) => {
    const isClassic = mode === 'classic';
    root.dataset.hud = mode;
    syncTheme(storedTheme());
    document.querySelectorAll('[data-hud-label]').forEach((el) => {
        el.textContent = isClassic ? 'Modern HUD' : 'Klasik HUD';
    });
};

if ($('app-shell')) syncHud(window.localStorage.getItem('hud-mode') || 'modern');

const syncSidebarPin = (pinned) => {
    $('app-sidebar')?.classList.toggle('is-pinned', pinned);
    $('app-shell')?.classList.toggle('lg:pl-[272px]', pinned);
    document.querySelector('[data-pin-icon]')?.classList.toggle('rotate-180', pinned);
};

if (window.localStorage.getItem('rail-pinned') === '1') {
    syncSidebarPin(true);
}

const setPref = {
    hud: (value) => { storage.setItem('hud-mode', value); syncHud(value); },
    theme: (value) => { storage.setItem(themeKey(), value); syncTheme(value); },
    accent: (value) => { storage.setItem('classic-accent', value); root.dataset.accent = value; },
    motion: (value) => { storage.setItem('motion', value); root.dataset.motion = value; },
    rail: (value) => { storage.setItem('rail-pinned', value); syncSidebarPin(value === '1'); },
};

renderPrefs();

initSetupGuide(storage);

const startPanelTour = () => startTour(document.body, {
    finish: {
        title: 'Hazırsınız',
        text: 'İlk iş olarak Yapı menüsünden dairelerinizi ekleyin, ardından sakinleri tanımlayın ve aidatı oluşturun. Bu anlatımı Görünüm Ayarları penceresinden istediğiniz zaman yeniden açabilirsiniz.',
    },
});

if ($('setup-wizard')) initSetupWizard($('setup-wizard'));

if (new URLSearchParams(window.location.search).has('tur')) {
    window.history.replaceState({}, '', window.location.pathname);
    window.setTimeout(startPanelTour, 800);
}

document.addEventListener('click', (event) => {
    const fillBtn = event.target.closest('[data-fill-email]');
    if (fillBtn) {
        const emailInput = $('email');
        const passInput = $('password');
        if (emailInput) emailInput.value = fillBtn.dataset.fillEmail || '';
        if (passInput) passInput.value = fillBtn.dataset.fillPass || 'password';
        if (fillBtn.hasAttribute('data-auto-submit')) {
            emailInput?.closest('form')?.requestSubmit();
        } else {
            passInput?.focus();
        }
        return;
    }

    const passToggle = event.target.closest('[data-toggle-password]');
    if (passToggle) {
        const input = $(passToggle.dataset.togglePassword);
        if (input) {
            const isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            passToggle.textContent = isPass ? 'Gizle' : 'Göster';
        }
        return;
    }

    if (event.target.closest('[data-start-tour]')) {
        MODALS.forEach((id) => {
            $(id)?.classList.add('hidden');
            $(id)?.classList.remove('flex');
        });
        startPanelTour();
        return;
    }

    const prefBtn = event.target.closest('[data-pref]');
    if (prefBtn) {
        setPref[prefBtn.dataset.pref](prefBtn.dataset.value);
        renderPrefs();
        return;
    }

    const expandBtn = event.target.closest('[data-rail-expand]');
    if (expandBtn) {
        event.preventDefault();
        expandBtn.setAttribute('aria-expanded', String(expandBtn.closest('[data-rail-group]').classList.toggle('is-open')));
        return;
    }

    const toggle = event.target.closest('[data-toggle]');
    if (toggle) {
        const id = toggle.dataset.toggle;
        const target = $(id);
        if (id === 'theme') {
            setPref.theme(root.classList.contains('light') ? 'dark' : 'light');
            renderPrefs();
        } else if (id === 'hud') {
            setPref.hud(root.dataset.hud === 'classic' ? 'modern' : 'classic');
            renderPrefs();
        } else if (id === 'rail-pin') {
            setPref.rail($('app-sidebar')?.classList.contains('is-pinned') ? '0' : '1');
            renderPrefs();
        } else if (id === 'sidebar') {
            const open = $('app-sidebar').classList.toggle('-translate-x-full') === false;
            $('app-sidebar').classList.toggle('shadow-xl', open);
            $('sidebar-backdrop')?.classList.toggle('hidden', !open);
            toggle.setAttribute('aria-expanded', String(open));
        } else if (MODALS.includes(id)) {
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
    const backdrop = MODALS.find((modalId) => event.target === $(modalId));
    if (close || backdrop) {
        const id = close?.dataset.close ?? backdrop;
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
        MODALS.forEach((id) => {
            $(id)?.classList.add('hidden');
            $(id)?.classList.remove('flex');
        });
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

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches || root.dataset.motion === 'off';
const nf = (min, max) => new Intl.NumberFormat('tr-TR', { minimumFractionDigits: min, maximumFractionDigits: max });
const formats = {
    money: (v) => `₺${nf(0, 0).format(Math.round(v))}`,
    number: (v) => nf(0, 0).format(Math.round(v)),
    percent: (v) => `${nf(0, 0).format(Math.round(v))}%`,
};

if (!reduceMotion) {
    document.querySelectorAll('[data-stagger-root] > *').forEach((el, idx) => {
        if (!el.classList.contains('reveal-up')) {
            el.style.setProperty('--stagger', String(idx));
            el.classList.add('reveal-up');
        }
    });

    document.querySelectorAll('.card, .stat, .tile').forEach((el, idx) => {
        el.classList.add('spotlight-card');
        if (!el.classList.contains('reveal-up')) {
            el.style.setProperty('--stagger', String(Math.min(idx, 8)));
            el.classList.add('reveal-up');
        }
    });

    document.addEventListener('pointermove', (event) => {
        const card = event.target.closest('.spotlight-card');
        if (!card) return;
        const rect = card.getBoundingClientRect();
        card.style.setProperty('--mx', `${event.clientX - rect.left}px`);
        card.style.setProperty('--my', `${event.clientY - rect.top}px`);
    }, { passive: true });

    document.querySelectorAll('[data-count]').forEach((el) => {
        const target = parseFloat(el.dataset.count) || 0;
        const format = formats[el.dataset.format] ?? formats.number;
        const start = performance.now();
        const step = (now) => {
            const p = Math.min(1, (now - start) / 850);
            el.innerHTML = format(target * (1 - Math.pow(1 - p, 3)));
            if (p < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    });
}

document.addEventListener('mouseover', (event) => {
    const col = event.target.closest('[data-timeline-col]');
    if (!col) return;
    const chart = col.closest('[data-revenue-chart]');
    if (!chart) return;
    const tip = chart.querySelector('[data-timeline-tip]');
    const dateEl = chart.querySelector('[data-tip-date]');
    const paidEl = chart.querySelector('[data-tip-paid]');
    const invEl = chart.querySelector('[data-tip-inv]');

    const left = Math.max(8, Math.min(92, parseFloat(col.dataset.left) || 50));
    if (tip) tip.style.left = `${left}%`;
    if (dateEl) dateEl.textContent = col.dataset.date || '';
    if (paidEl) paidEl.textContent = col.dataset.paid || '₺0';
    if (invEl) invEl.textContent = col.dataset.inv || '₺0';

    chart.querySelectorAll('[data-timeline-dot]').forEach((d) => {
        const active = d.closest('[data-timeline-col]') === col;
        d.classList.toggle('bg-white', active);
        d.classList.toggle('scale-110', active);
        d.classList.toggle('shadow-[0_0_10px_rgba(255,255,255,0.5)]', active);
        d.classList.toggle('bg-[#232329]', !active);
    });
});

document.addEventListener('click', (event) => {
    const modeBtn = event.target.closest('[data-chart-mode]');
    if (!modeBtn) return;
    const mode = modeBtn.dataset.chartMode;
    const section = modeBtn.closest('section');
    if (!section) return;

    section.querySelectorAll('[data-chart-mode]').forEach((b) => {
        const active = b === modeBtn;
        b.classList.toggle('bg-[#1f1f23]', active);
        b.classList.toggle('text-white', active);
        b.classList.toggle('text-[#71717a]', !active);
    });

    section.querySelectorAll('[data-view-panel]').forEach((panel) => {
        const show = panel.dataset.viewPanel === mode;
        panel.classList.toggle('hidden', !show);
        if (show && !reduceMotion) {
            panel.classList.remove('reveal-up');
            void panel.offsetWidth;
            panel.classList.add('reveal-up');
        }
    });
});

document.querySelectorAll('[data-tabs]').forEach((box) => {
    const buttons = box.querySelectorAll('[data-filter]');
    buttons.forEach((btn) => btn.addEventListener('click', () => {
        buttons.forEach((b) => b.classList.toggle('active', b === btn));
        let shown = 0;
        box.querySelectorAll('[data-kind]').forEach((row) => {
            const ok = row.dataset.kind === btn.dataset.filter;
            row.hidden = !ok;
            if (ok) {
                if (!reduceMotion) {
                    row.style.setProperty('--stagger', String(Math.min(shown, 6)));
                    row.classList.remove('reveal-up');
                    void row.offsetWidth;
                    row.classList.add('reveal-up');
                }
                shown += 1;
            }
        });
        box.querySelector('[data-empty]')?.toggleAttribute('hidden', shown > 0);
    }));
});
