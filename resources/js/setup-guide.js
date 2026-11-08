import { positionAround } from './tour';

const STEP_KEY = 'setup-guide-step';
const EASE = 'cubic-bezier(0.22, 1, 0.36, 1)';
const TYPABLE_FIELDS = 'input:not([type=date]):not([type=password]):not([type=checkbox]):not([type=hidden]), textarea';

const motionEnabled = () => document.documentElement.dataset.motion !== 'off' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const play = (element, frames, options) => (motionEnabled() ? element.animate(frames, { easing: EASE, ...options }).finished.catch(() => {}) : Promise.resolve());
const clearAnimations = (...elements) => elements.forEach((element) => element.getAnimations().forEach((animation) => animation.cancel()));

/** Sağ üstteki Setup butonuyla başlar; gerçek sayfaları gezer, her alanı vurgulayarak anlatır ve kaldığı yeri hatırlar. */
export function initSetupGuide(storage) {
    const data = document.getElementById('setup-guide-data');
    if (!data) return;

    const stops = JSON.parse(data.textContent);
    const guide = document.getElementById('setup-guide');
    const spot = document.getElementById('setup-guide-spot');
    const part = (name) => guide.querySelector(`[data-guide-${name}]`);
    const [progress, content, section, count, title, text, example, hint, back, next, skip] = ['progress', 'content', 'section', 'count', 'title', 'text', 'example', 'hint', 'back', 'next', 'skip'].map(part);
    let index = 0;
    let currentTarget = null;
    let lastLayout = '';
    let isFollowing = false;
    let glideTimer = null;
    let renderToken = 0;
    let typingTimer = null;
    let typedField = null;
    let typedFieldPlaceholder = '';

    const isOpen = () => !guide.classList.contains('hidden');
    const isOnPage = (stop) => !stop.url || new URL(stop.url).pathname === window.location.pathname;
    const needsVisit = (stop) => !isOnPage(stop) && !stop.done;

    const findTarget = (stop) => {
        if (!stop.target || !isOnPage(stop)) return null;
        const target = document.querySelector(stop.target);
        return target && target.getClientRects().length > 0 ? target : null;
    };

    const layoutKey = () => {
        const box = currentTarget?.getBoundingClientRect();
        return [box?.left, box?.top, box?.width, box?.height, guide.offsetWidth, window.innerWidth, window.innerHeight].join();
    };

    /** Sayfa animasyonları, sabitlenen menü veya kaydırma hedefi oynatsa da vurguyu her karede onun üstünde tutar. */
    const followTarget = () => {
        if (!isOpen()) {
            isFollowing = false;
            return;
        }
        const layout = layoutKey();
        if (layout !== lastLayout) {
            lastLayout = layout;
            positionAround(currentTarget, guide, spot);
        }
        requestAnimationFrame(followTarget);
    };

    const blockReason = (stop) => {
        if (stop.done || !isOnPage(stop)) return null;
        if (stop.locked) return 'Devam etmek için bu işlemi tamamlayın.';
        if (stop.fill && !document.getElementById(stop.fill)?.value.trim()) return 'Devam etmek için bu alanı doldurun.';
        return null;
    };

    const updateGate = () => {
        const reason = blockReason(stops[index]);
        next.disabled = reason !== null;
        hint.textContent = reason ?? '';
        hint.classList.toggle('hidden', reason === null);
    };

    const nextLabel = (stop) => {
        if (stop.nextLabel) return stop.nextLabel;
        if (needsVisit(stop)) return 'Sayfaya git';
        return index === stops.length - 1 ? 'Bitir' : 'İleri';
    };

    const fillCard = (stop) => {
        if (stop.open && isOnPage(stop)) document.querySelector(stop.open)?.setAttribute('open', '');
        progress.style.width = `${((index + 1) / stops.length) * 100}%`;
        section.textContent = stop.section;
        count.textContent = `${index + 1} / ${stops.length}`;
        title.textContent = stop.title;
        text.textContent = stop.text;
        example.textContent = stop.example ?? '';
        example.classList.toggle('hidden', !stop.example);
        back.hidden = index === 0;
        skip.textContent = stop.skipLabel ?? 'Rehberi kapat';
        next.textContent = nextLabel(stop);
        updateGate();
    };

    const stopTyping = () => {
        clearTimeout(typingTimer);
        if (!typedField) return;
        typedField.placeholder = typedFieldPlaceholder;
        typedField.classList.remove('guide-typing');
        typedField = null;
    };

    /** Örnek değeri boş alanın içinde yazılıyormuş gibi gösterir; alanın gerçek değerine dokunmaz. */
    const startTyping = (stop) => {
        const field = stop.typing && isOnPage(stop) ? document.getElementById(stop.typing) : null;
        if (!field?.matches(TYPABLE_FIELDS) || field.value !== '') return;

        typedField = field;
        typedFieldPlaceholder = field.placeholder;
        field.classList.add('guide-typing');
        field.focus({ preventScroll: true });
        if (!motionEnabled()) {
            field.placeholder = stop.sample;
            return;
        }

        const typeUpTo = (length) => {
            field.placeholder = `${stop.sample.slice(0, length)}|`;
            if (length < stop.sample.length) {
                typingTimer = setTimeout(() => typeUpTo(length + 1), 55 + Math.random() * 70);
            } else {
                typingTimer = setTimeout(() => typeUpTo(0), 1600);
            }
        };
        typingTimer = setTimeout(() => typeUpTo(1), 420);
    };

    const glide = () => {
        [guide, spot].forEach((element) => element.classList.add('is-gliding'));
        clearTimeout(glideTimer);
        glideTimer = setTimeout(() => [guide, spot].forEach((element) => element.classList.remove('is-gliding')), 520);
    };

    const render = async (direction = 1) => {
        const token = ++renderToken;
        const stop = stops[index];
        const wasOpen = isOpen();
        const previousHeight = content.offsetHeight;
        clearAnimations(guide, spot);
        stopTyping();

        if (wasOpen) {
            await play(content, [{ opacity: 1, transform: 'none' }, { opacity: 0, transform: `translateX(${direction * -14}px)` }], { duration: 120, fill: 'forwards' });
            if (token !== renderToken) return;
        }
        clearAnimations(content);

        fillCard(stop);
        if (wasOpen) glide();
        guide.classList.remove('hidden');
        spot.classList.remove('hidden');

        currentTarget = findTarget(stop);
        if (currentTarget) {
            const box = currentTarget.getBoundingClientRect();
            if (box.top < 90 || box.bottom > window.innerHeight - 20) {
                currentTarget.scrollIntoView({ block: 'center', behavior: motionEnabled() ? 'smooth' : 'auto' });
            }
        }
        positionAround(currentTarget, guide, spot);
        lastLayout = layoutKey();
        startTyping(stop);
        if (!isFollowing) {
            isFollowing = true;
            requestAnimationFrame(followTarget);
        }

        if (wasOpen) {
            play(content, [{ height: `${previousHeight}px` }, { height: `${content.offsetHeight}px` }], { duration: 420 });
        } else {
            play(guide, [{ opacity: 0, transform: 'translateY(14px) scale(0.96)' }, { opacity: 1, transform: 'none' }], { duration: 420 });
            play(spot, [{ opacity: 0 }, { opacity: 1 }], { duration: 360 });
        }
        [...content.children].filter((child) => !child.classList.contains('hidden')).forEach((child, position) => {
            play(child, [{ opacity: 0, transform: `translateX(${direction * 20}px)` }, { opacity: 1, transform: 'none' }], { duration: 420, delay: 60 + position * 55, fill: 'backwards' });
        });
    };

    const fadeOutGuide = () => {
        stopTyping();

        return Promise.all([
            play(guide, [{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateY(10px) scale(0.97)' }], { duration: 200, fill: 'forwards' }),
            play(spot, [{ opacity: 1 }, { opacity: 0 }], { duration: 200, fill: 'forwards' }),
        ]);
    };

    const leaveTo = async (url) => {
        renderToken++;
        await fadeOutGuide();
        window.location.assign(url);
    };

    const goTo = (target) => {
        const direction = target >= index ? 1 : -1;
        index = target;
        storage.setItem(STEP_KEY, stops[target].key);
        if (isOnPage(stops[target])) {
            render(direction);
        } else if (isOpen()) {
            leaveTo(stops[target].url);
        } else {
            window.location.assign(stops[target].url);
        }
    };

    const close = async () => {
        const token = ++renderToken;
        storage.removeItem(STEP_KEY);
        await fadeOutGuide();
        if (token !== renderToken) return;
        guide.classList.add('hidden');
        spot.classList.add('hidden');
        clearAnimations(guide, spot);
    };

    next.addEventListener('click', () => {
        if (needsVisit(stops[index])) {
            leaveTo(stops[index].url);
        } else if (index === stops.length - 1) {
            close();
        } else {
            goTo(index + 1);
        }
    });
    back.addEventListener('click', () => goTo(index - 1));
    skip.addEventListener('click', close);

    /** Rehber açıkken yalnızca vurgulanan alan ve popup tıklanır; görünüm değişmez, adımlar menüden atlanamaz. */
    document.addEventListener('click', (event) => {
        if (!isOpen()) return;
        if (event.target.closest('#setup-guide, [data-setup-guide-start]') || currentTarget?.contains(event.target)) return;
        event.preventDefault();
        event.stopPropagation();
    }, true);

    document.addEventListener('input', () => {
        if (isOpen()) updateGate();
    });
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-setup-guide-start]')) goTo(0);
    });

    const savedKey = storage.getItem(STEP_KEY);
    if (savedKey) {
        index = Math.max(0, stops.findIndex((stop) => stop.key === savedKey));
        if (!isOnPage(stops[index]) && stops[index].done) {
            const stopOnThisPage = stops.findIndex((stop, stopIndex) => stopIndex > index && stop.url && isOnPage(stop));
            if (stopOnThisPage >= 0) {
                index = stopOnThisPage;
                storage.setItem(STEP_KEY, stops[index].key);
            }
        }
        render();
    }
}
