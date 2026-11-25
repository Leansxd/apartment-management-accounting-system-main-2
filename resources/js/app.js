import { startTour } from './tour';
import { initSetupWizard } from './setup';
import { initSetupGuide } from './setup-guide';
import './print';
import { initSoftNav } from './soft-nav';
import { paginateTables } from './table-pager';

const $ = (id) => document.getElementById(id);
const root = document.documentElement;
const storage = window.localStorage;
const MODALS = ['apt-modal', 'settings-modal'];

const storedTheme = () => storage.getItem('theme') || 'dark';

const renderPrefs = () => {
    const values = {
        theme: storedTheme(),
        motion: root.dataset.motion,
        rail: storage.getItem('rail-pinned') === '1' ? '1' : '0',
        nav: root.dataset.nav === 'top' ? 'top' : 'side',
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

if ($('app-shell')) syncTheme(storedTheme());

const syncSidebarPin = (pinned) => {
    $('app-sidebar')?.classList.toggle('is-pinned', pinned);
    $('app-shell')?.classList.toggle('lg:pl-[272px]', pinned);
    document.querySelector('[data-pin-icon]')?.classList.toggle('rotate-180', pinned);
};

if (window.localStorage.getItem('rail-pinned') === '1') {
    syncSidebarPin(true);
}

const setPref = {
    theme: (value) => { storage.setItem('theme', value); syncTheme(value); },
    motion: (value) => { storage.setItem('motion', value); root.dataset.motion = value; },
    rail: (value) => { storage.setItem('rail-pinned', value); syncSidebarPin(value === '1'); },
    nav: (value) => { storage.setItem('nav', value); root.dataset.nav = value === 'top' ? 'top' : 'side'; },
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

const syncExpenseRate = (select) => {
    const rate = select.form.querySelector('[data-expense-rate]');
    const option = select.selectedOptions[0];
    const isForeign = option?.dataset.currency !== 'TRY';
    rate.hidden = !isForeign;
    rate.disabled = !isForeign;
    rate.required = isForeign;
    rate.value = isForeign ? option.dataset.rate || '' : '';
};
document.addEventListener('change', (event) => {
    if (event.target.matches('[data-expense-cashbox]')) syncExpenseRate(event.target);
});

document.addEventListener('change', (event) => {
    const source = event.target.closest('[data-show-target]');
    if (!source) return;
    source.form.querySelectorAll('[data-target-for]').forEach((el) => {
        el.hidden = el.dataset.targetFor !== source.value;
    });
});

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches || root.dataset.motion === 'off';
const nf = (min, max) => new Intl.NumberFormat('tr-TR', { minimumFractionDigits: min, maximumFractionDigits: max });
const formats = {
    money: (v) => `₺${nf(0, 0).format(Math.round(v))}`,
    number: (v) => nf(0, 0).format(Math.round(v)),
    percent: (v) => `${nf(0, 0).format(Math.round(v))}%`,
};

const compactAmount = new Intl.NumberFormat('tr-TR', { notation: 'compact', maximumFractionDigits: 1 });

function niceCeiling(value) {
    if (value <= 0) return 1;
    const magnitude = 10 ** Math.floor(Math.log10(value));
    const step = [1, 1.5, 2, 2.5, 3, 4, 5, 7.5, 10].find((s) => value / magnitude <= s) ?? 10;
    return step * magnitude;
}

function drawSeriesChart(chart) {
    const hidden = new Set([...chart.querySelectorAll('[data-series-toggle][aria-pressed="false"]')].map((b) => b.dataset.seriesToggle));
    const bars = [...chart.querySelectorAll('[data-bar]')];
    const visible = bars.filter((bar) => !hidden.has(bar.dataset.bar));
    const peak = niceCeiling(Math.max(0, ...visible.map((bar) => Number(bar.dataset.value))));

    bars.forEach((bar) => {
        const value = Number(bar.dataset.value);
        bar.style.display = hidden.has(bar.dataset.bar) ? 'none' : '';
        bar.style.height = value > 0 ? `max(2px, ${(value / peak) * 100}%)` : '0';
    });

    const axis = chart.querySelector('[data-chart-axis]');
    axis.replaceChildren(...[2, 1, 0].map((step) => {
        const line = document.createElement('div');
        line.className = 'absolute inset-x-0 flex items-center gap-2';
        line.style.top = `${(1 - step / 2) * 100}%`;
        line.style.transform = 'translateY(-50%)';
        const label = document.createElement('span');
        label.className = 'w-14 shrink-0 text-right text-[10px] tabular-nums';
        label.style.color = 'var(--ui-muted)';
        label.textContent = step === 0 ? '0' : `₺${compactAmount.format((peak * step) / 2)}`;
        const rule = document.createElement('span');
        rule.className = 'h-px flex-1';
        rule.style.backgroundColor = 'var(--ui-line)';
        line.append(label, rule);
        return line;
    }));
}

function showChartTip(chart, column) {
    const tip = chart.querySelector('[data-chart-tip]');
    const plot = chart.querySelector('[data-chart-plot]');
    tip.querySelector('[data-tip-title]').textContent = column.dataset.title;
    const rows = JSON.parse(column.dataset.rows);
    tip.querySelector('[data-tip-rows]').replaceChildren(...(rows.length ? rows : [['Hareket yok', '']]).map(([label, value]) => {
        const row = document.createElement('div');
        row.className = 'flex justify-between gap-4 text-[11px]';
        const term = document.createElement('dt');
        term.className = 'text-[#a1a1aa]';
        term.textContent = label;
        const amount = document.createElement('dd');
        amount.className = 'font-medium tabular-nums text-white';
        amount.textContent = value;
        row.append(term, amount);
        return row;
    }));
    tip.hidden = false;
    const center = column.getBoundingClientRect().left - plot.getBoundingClientRect().left + column.offsetWidth / 2;
    tip.style.left = `${Math.max(0, Math.min(plot.offsetWidth - tip.offsetWidth, center - tip.offsetWidth / 2))}px`;
}

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

/*
 * Ana içerikteki öğelerin kurulumu. Sayfa açılışında ve sayfa yenilemeden içerik değiştiğinde
 * (resources/js/soft-nav.js) yeni içerik için tekrar çalışır.
 */
function initContent(root = document, animate = true) {
    paginateTables(root);
    root.querySelectorAll('[data-expense-cashbox]').forEach(syncExpenseRate);
    root.querySelectorAll('[data-show-target]').forEach((el) => el.dispatchEvent(new Event('change', { bubbles: true })));
    if (animate && !reduceMotion) {
        root.querySelectorAll('[data-stagger-root] > *').forEach((el, idx) => {
            if (!el.classList.contains('reveal-up')) {
                el.style.setProperty('--stagger', String(idx));
                el.classList.add('reveal-up');
            }
        });

        root.querySelectorAll('.card, .stat, .tile').forEach((el, idx) => {
            el.classList.add('spotlight-card');
            if (!el.classList.contains('reveal-up')) {
                el.style.setProperty('--stagger', String(Math.min(idx, 8)));
                el.classList.add('reveal-up');
            }
        });

        root.querySelectorAll('[data-count]').forEach((el) => {
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

    root.querySelectorAll('[data-series-chart]').forEach((chart) => {
        drawSeriesChart(chart);
        const hideTip = () => { chart.querySelector('[data-chart-tip]').hidden = true; };

        chart.addEventListener('click', (event) => {
            const toggle = event.target.closest('[data-series-toggle]');
            if (!toggle) return;
            const isLastVisible = toggle.getAttribute('aria-pressed') === 'true' && chart.querySelectorAll('[data-series-toggle][aria-pressed="true"]').length === 1;
            if (!isLastVisible) {
                toggle.setAttribute('aria-pressed', toggle.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
                drawSeriesChart(chart);
            }
        });
        ['mouseover', 'focusin', 'click'].forEach((type) => chart.addEventListener(type, (event) => {
            const column = event.target.closest('[data-chart-col]');
            if (column) showChartTip(chart, column);
        }));
        chart.addEventListener('mouseleave', hideTip);
        chart.addEventListener('focusout', hideTip);
    });

    root.querySelectorAll('[data-tabs]').forEach((box) => {
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
}

if (!reduceMotion) {
    document.addEventListener('pointermove', (event) => {
        const card = event.target.closest('.spotlight-card');
        if (!card) return;
        const rect = card.getBoundingClientRect();
        card.style.setProperty('--mx', `${event.clientX - rect.left}px`);
        card.style.setProperty('--my', `${event.clientY - rect.top}px`);
    }, { passive: true });

}

/*
 * Üst menü (Menü konumu: Üstte): fare olan cihazlarda grup üzerine gelince açılır, ayrılınca kısa bir
 * gecikmeyle kapanır (menüye geçerken aradaki boşlukta kapanmasın diye). Dokunmatik ekranda tıklayarak açılır.
 */
const hoverMenus = () => root.dataset.nav === 'top' && window.matchMedia('(hover: hover)').matches;
let menuCloseTimer;
document.addEventListener('mouseover', (event) => {
    const menu = event.target.closest('#top-nav details.dropdown');
    if (!menu || !hoverMenus()) return;
    clearTimeout(menuCloseTimer);
    document.querySelectorAll('#top-nav details.dropdown[open]').forEach((other) => { if (other !== menu) other.removeAttribute('open'); });
    menu.open = true;
});
document.addEventListener('mouseout', (event) => {
    const menu = event.target.closest('#top-nav details.dropdown');
    if (!menu || !hoverMenus() || menu.contains(event.relatedTarget)) return;
    clearTimeout(menuCloseTimer);
    menuCloseTimer = setTimeout(() => menu.removeAttribute('open'), 180);
});
document.addEventListener('click', (event) => {
    const summary = event.target.closest('#top-nav details.dropdown > summary');
    // Üzerine gelince zaten açık: tıklamak menüyü kapatmasın
    if (summary && hoverMenus()) {
        event.preventDefault();
        summary.parentElement.open = true;
    }
});

initContent();
initSoftNav((root) => initContent(root, false));
