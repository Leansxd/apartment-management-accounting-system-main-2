/*
 * Sayfa yenilemeden içerik güncelleme. Ana içerikteki GET formları (filtreler, daire / yıl seçimi) ve
 * data-autosubmit seçimli formlar (durum, rol değişikliği) arka planda gönderilir; yalnızca <main> değişir,
 * kaydırma konumu ve menü yerinde kalır. Beklenmeyen bir yanıtta normal sayfa geçişine dönülür.
 * Bir formun her zaman normal gönderilmesi için data-hard verilir.
 */
let controller = null;
let initContent = () => {};

function isSoft(form) {
    return form.closest('main')
        && !form.hasAttribute('data-hard')
        && !form.target
        && !form.querySelector('input[type="file"]')
        && (form.method.toLowerCase() === 'get' || form.dataset.softOnce === '1');
}

function runScripts(root) {
    root.querySelectorAll('script').forEach((old) => {
        const script = document.createElement('script');
        [...old.attributes].forEach((attribute) => script.setAttribute(attribute.name, attribute.value));
        script.textContent = old.textContent;
        old.replaceWith(script);
    });
}

async function visit(url, options = {}, { push = true } = {}) {
    controller?.abort();
    controller = new AbortController();
    const main = document.querySelector('main');
    main.setAttribute('aria-busy', 'true');
    main.style.transition = 'opacity 150ms';
    main.style.opacity = '0.55';

    try {
        const response = await fetch(url, { ...options, credentials: 'same-origin', signal: controller.signal, headers: { Accept: 'text/html' } });
        if (!response.ok || !(response.headers.get('Content-Type') ?? '').includes('text/html')) {
            throw new Error('unexpected response');
        }
        const html = await response.text();
        const page = new DOMParser().parseFromString(html, 'text/html');
        const next = page.querySelector('main');
        if (!next) {
            throw new Error('no main');
        }

        const scrollY = window.scrollY;
        document.title = page.title;
        const adopted = document.importNode(next, true);
        main.replaceWith(adopted);
        runScripts(adopted);
        initContent(adopted);
        if (push && response.url !== window.location.href) {
            window.history.pushState({ soft: true }, '', response.url);
        }
        window.scrollTo(0, scrollY);
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }
        // Yanıt beklenen sayfa değilse (dosya indirme, oturum sonu vb.) normal geçiş yapılır
        if (!options.method || options.method === 'GET') {
            window.location.href = url;
        } else {
            window.location.reload();
        }
    } finally {
        main.removeAttribute('aria-busy');
        main.style.opacity = '';
    }
}

export function initSoftNav(init) {
    initContent = init;
    window.history.replaceState({ soft: true }, '');

    document.addEventListener('change', (event) => {
        const field = event.target.closest('[data-autosubmit]');
        if (!field?.form) {
            return;
        }
        field.form.dataset.softOnce = '1';
        field.form.requestSubmit();
    });

    // Diğer submit dinleyicileri (onay pencereleri) önce çalışsın diye bu dinleyici en son eklenir
    document.addEventListener('submit', (event) => {
        const form = event.target;
        const soft = !event.defaultPrevented && isSoft(form);
        delete form.dataset.softOnce;
        if (!soft) {
            return;
        }
        event.preventDefault();
        const data = new FormData(form, event.submitter);
        if (form.method.toLowerCase() === 'get') {
            const url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
            url.search = new URLSearchParams([...data].filter(([, value]) => value !== '')).toString();
            visit(url.toString());
        } else {
            visit(form.action, { method: 'POST', body: data }, { push: false });
        }
    });

    window.addEventListener('popstate', (event) => {
        if (event.state?.soft) {
            visit(window.location.href, {}, { push: false });
        }
    });
}
