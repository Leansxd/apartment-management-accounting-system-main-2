/*
 * Ekranda uzun tabloları 10 satırlık sayfalara böler (raporlar tüm veriyi tek seferde getirir). Gizleme yalnızca
 * ekran içindir (.pager-hidden, @media screen): yazdırınca ve Excel çıktısında tüm satırlar yer alır.
 * Sunucuda sayfalanan listeler zaten 10 satır olduğundan etkilenmez. Bölünmesi istenmeyen tabloya data-no-pager verilir.
 */
const PAGE_SIZE = 10;

function pageList(current, total) {
    const pages = new Set([0, total - 1, current - 1, current, current + 1]);
    return [...pages].filter((page) => page >= 0 && page < total).sort((a, b) => a - b);
}

function setup(table) {
    if (table.dataset.pager || 'noPager' in table.dataset || 'printWhole' in table.dataset
        || table.closest('[data-module-summary], dialog, .no-print')) {
        return;
    }
    const rows = [...(table.tBodies[0]?.rows ?? [])];
    if (rows.length <= PAGE_SIZE) {
        return;
    }
    table.dataset.pager = '1';

    const total = Math.ceil(rows.length / PAGE_SIZE);
    const pager = document.createElement('nav');
    pager.className = 'no-print flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-xs text-slate-500';
    pager.setAttribute('aria-label', 'Tablo sayfaları');
    (table.closest('.overflow-x-auto') ?? table).after(pager);

    let current = 0;
    const show = (page) => {
        current = Math.max(0, Math.min(total - 1, page));
        rows.forEach((row, index) => row.classList.toggle('pager-hidden', Math.floor(index / PAGE_SIZE) !== current));

        const info = document.createElement('span');
        info.textContent = `${current * PAGE_SIZE + 1}–${Math.min((current + 1) * PAGE_SIZE, rows.length)} / ${rows.length} kayıt`;
        const buttons = document.createElement('span');
        buttons.className = 'flex flex-wrap items-center gap-1';
        const button = (label, page, { active = false, disabled = false, title = '' } = {}) => {
            const el = document.createElement('button');
            el.type = 'button';
            el.className = `btn btn-sm ${active ? 'btn-primary' : 'btn-secondary'}`;
            el.textContent = label;
            el.disabled = disabled;
            if (title) el.setAttribute('aria-label', title);
            if (active) el.setAttribute('aria-current', 'page');
            el.addEventListener('click', () => show(page));
            return el;
        };
        buttons.append(button('‹', current - 1, { disabled: current === 0, title: 'Önceki sayfa' }));
        let previous = -1;
        pageList(current, total).forEach((page) => {
            if (page - previous > 1) {
                const gap = document.createElement('span');
                gap.textContent = '…';
                buttons.append(gap);
            }
            buttons.append(button(String(page + 1), page, { active: page === current, title: `Sayfa ${page + 1}` }));
            previous = page;
        });
        buttons.append(button('›', current + 1, { disabled: current === total - 1, title: 'Sonraki sayfa' }));
        pager.replaceChildren(info, buttons);
    };
    show(0);
}

export function paginateTables(root = document) {
    root.querySelectorAll('main table.table').forEach(setup);
}
