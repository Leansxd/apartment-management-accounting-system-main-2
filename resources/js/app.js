const $ = (id) => document.getElementById(id);

// Aç/kapa düğmeleri: kenar menü, konut seçici, ERP mobil menüsü, konut ekleme formu
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

// Konut silme: kodu yazarak onay (sunucu da aynı kodu ister)
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

// Hedefe göre ilgili alanı göster (borç dağıtımı, bildirim, aidat tanımı)
document.addEventListener('change', (event) => {
    const source = event.target.closest('[data-show-target]');
    if (!source) return;
    source.form.querySelectorAll('[data-target-for]').forEach((el) => {
        el.hidden = el.dataset.targetFor !== source.value;
    });
});
document.querySelectorAll('[data-show-target]').forEach((el) => el.dispatchEvent(new Event('change', { bubbles: true })));
