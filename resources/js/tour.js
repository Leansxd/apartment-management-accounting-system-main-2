const isVisible = (el) => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';

const element = (tag, className, text) => {
    const el = document.createElement(tag);
    el.className = className;
    if (text) el.textContent = text;
    return el;
};

let closeActive = null;

/** Vurguyu hedefin üstüne, kartı da ekrana sığacak şekilde hedefin yanına koyar. Hedef yoksa kart ortalanır. */
export function positionAround(el, card, spot) {
    const margin = 12;
    const pad = 6;
    const cardBox = card.getBoundingClientRect();
    const viewport = { w: window.innerWidth, h: window.innerHeight };

    if (!el) {
        Object.assign(spot.style, { left: `${viewport.w / 2}px`, top: `${viewport.h / 2}px`, width: '0', height: '0' });
        card.style.left = `${(viewport.w - cardBox.width) / 2}px`;
        card.style.top = `${(viewport.h - cardBox.height) / 2}px`;
        return;
    }

    const box = el.getBoundingClientRect();
    const hole = { left: box.left - pad, top: box.top - pad, width: box.width + pad * 2, height: box.height + pad * 2 };
    Object.assign(spot.style, { left: `${hole.left}px`, top: `${hole.top}px`, width: `${hole.width}px`, height: `${hole.height}px` });

    const candidates = [
        { left: hole.left, top: hole.top + hole.height + margin, fits: hole.top + hole.height + margin + cardBox.height < viewport.h },
        { left: hole.left, top: hole.top - cardBox.height - margin, fits: hole.top - cardBox.height - margin > 0 },
        { left: hole.left + hole.width + margin, top: hole.top, fits: hole.left + hole.width + margin + cardBox.width < viewport.w },
        { left: hole.left - cardBox.width - margin, top: hole.top, fits: hole.left - cardBox.width - margin > 0 },
    ];
    const chosen = candidates.find((candidate) => candidate.fits) ?? { left: margin, top: viewport.h - cardBox.height - margin };
    card.style.left = `${Math.min(Math.max(margin, chosen.left), viewport.w - cardBox.width - margin)}px`;
    card.style.top = `${Math.min(Math.max(margin, chosen.top), viewport.h - cardBox.height - margin)}px`;
}

/**
 * root içindeki görünür [data-tour-step] öğelerini sırayla vurgulayıp açıklar.
 * finish: hedefsiz kapanış mesajı. onSkip: kullanıcı anlatımı yarıda bıraktığında çağrılır.
 */
export function startTour(root, { finish = null, onSkip = null } = {}) {
    closeActive?.(false);

    const steps = [...root.querySelectorAll('[data-tour-step]')]
        .filter((el) => isVisible(el) && !('tourOnce' in el.dataset && el.dataset.tourSeen))
        .sort((a, b) => Number(a.dataset.tourStep) - Number(b.dataset.tourStep))
        .map((el) => ({ el, title: el.dataset.tourTitle, text: el.dataset.tourText }));
    if (finish) steps.push({ el: null, ...finish });
    if (steps.length === 0) return;

    const previousFocus = document.activeElement;
    const shade = element('div', 'tour-shade');
    const spot = element('div', 'tour-spot');
    const card = element('div', 'tour-card');
    card.setAttribute('role', 'dialog');
    card.setAttribute('aria-modal', 'true');
    card.setAttribute('aria-live', 'polite');
    const count = element('p', 'tour-count');
    const title = element('h3', 'tour-title');
    const text = element('p', 'tour-text');
    const actions = element('div', 'tour-actions');
    const skip = element('button', 'tour-skip', 'Anlatımı geç');
    const nav = element('div', 'tour-nav');
    const back = element('button', 'tour-back', 'Geri');
    const next = element('button', 'tour-next', 'İleri');
    [skip, back, next].forEach((button) => { button.type = 'button'; });
    nav.append(back, next);
    actions.append(skip, nav);
    card.append(count, title, text, actions);
    document.body.append(shade, spot, card);

    let index = 0;

    const place = () => positionAround(steps[index].el, card, spot);

    const render = () => {
        const step = steps[index];
        if (step.el) step.el.dataset.tourSeen = '1';
        count.textContent = `${index + 1} / ${steps.length}`;
        title.textContent = step.title;
        text.textContent = step.text;
        back.hidden = index === 0;
        next.textContent = index === steps.length - 1 ? 'Tamam' : 'İleri';
        if (step.el) {
            const box = step.el.getBoundingClientRect();
            if (box.top < 90 || box.bottom > window.innerHeight - 20) step.el.scrollIntoView({ block: 'center' });
        }
        place();
        next.focus({ preventScroll: true });
    };

    const close = (skipped) => {
        window.removeEventListener('resize', place);
        window.removeEventListener('scroll', place, true);
        document.removeEventListener('keydown', onKey, true);
        shade.remove();
        spot.remove();
        card.remove();
        closeActive = null;
        previousFocus?.focus?.({ preventScroll: true });
        if (skipped) onSkip?.();
    };

    const go = (delta) => {
        const target = index + delta;
        if (target >= steps.length) {
            close(false);
            return;
        }
        if (target >= 0) {
            index = target;
            render();
        }
    };

    function onKey(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            close(true);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            go(1);
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            go(-1);
        } else if (event.key === 'Tab') {
            const buttons = [skip, back, next].filter((button) => !button.hidden);
            const position = buttons.indexOf(document.activeElement);
            event.preventDefault();
            buttons[(position + (event.shiftKey ? -1 : 1) + buttons.length) % buttons.length].focus();
        }
    }

    next.addEventListener('click', () => go(1));
    back.addEventListener('click', () => go(-1));
    skip.addEventListener('click', () => close(true));
    window.addEventListener('resize', place);
    window.addEventListener('scroll', place, { capture: true, passive: true });
    document.addEventListener('keydown', onKey, true);
    closeActive = close;
    render();
}
