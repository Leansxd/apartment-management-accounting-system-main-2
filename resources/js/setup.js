import { startTour } from './tour';

export function initSetupWizard(form) {
    const panels = [...form.querySelectorAll('[data-setup-panel]')];
    const navItems = [...document.querySelectorAll('[data-setup-nav]')];
    const back = form.querySelector('[data-setup-back]');
    const next = form.querySelector('[data-setup-next]');
    const submit = form.querySelector('[data-setup-submit]');
    const { mode, password, password_confirmation: confirmation } = form.elements;
    let guideOn = true;

    const isClean = () => mode.value === 'clean';
    const route = () => panels.filter((panel) => isClean() || !('cleanOnly' in panel.dataset));
    let current = panels.find((panel) => panel.dataset.setupPanel === form.dataset.initialPanel) ?? panels[0];

    const guide = () => startTour(form, { onSkip: () => { guideOn = false; } });

    const fillSummary = () => {
        form.querySelectorAll('[data-summary]').forEach((el) => {
            const field = form.elements[el.dataset.summary];
            el.textContent = field.tagName === 'SELECT' ? field.selectedOptions[0].textContent : field.value;
        });
        form.querySelectorAll('[data-summary-clean]').forEach((el) => { el.hidden = !isClean(); });
    };

    const show = (panel, withGuide = guideOn) => {
        current = panel;
        panels.forEach((item) => { item.hidden = item !== panel; });

        const steps = route();
        const index = steps.indexOf(panel);
        navItems.forEach((item) => {
            const position = steps.findIndex((step) => step.dataset.setupPanel === item.dataset.setupNav);
            item.hidden = position < 0;
            item.dataset.state = position === index ? 'current' : position < index ? 'done' : 'todo';
            position === index ? item.setAttribute('aria-current', 'step') : item.removeAttribute('aria-current');
        });

        back.hidden = index === 0;
        next.hidden = index === steps.length - 1;
        submit.hidden = !next.hidden;
        if (panel.dataset.setupPanel === 'review') fillSummary();
        if (withGuide) guide();
    };

    const syncMode = () => {
        panels.filter((panel) => 'cleanOnly' in panel.dataset).forEach((panel) => {
            panel.querySelectorAll('input').forEach((input) => { input.disabled = !isClean(); });
        });
    };

    const syncPasswords = () => confirmation.setCustomValidity(confirmation.value === password.value ? '' : 'Şifreler aynı değil.');

    const currentIsValid = () => [...current.querySelectorAll('input, select')]
        .filter((field) => !field.disabled && field.closest('[hidden]') === null)
        .every((field) => field.reportValidity());

    const move = (delta) => {
        const steps = route();
        show(steps[steps.indexOf(current) + delta]);
    };

    next.addEventListener('click', () => { if (currentIsValid()) move(1); });
    back.addEventListener('click', () => move(-1));
    form.querySelector('[data-setup-guide]').addEventListener('click', () => { guideOn = true; guide(); });
    mode.addEventListener('change', () => { syncMode(); show(current, false); });
    password.addEventListener('input', syncPasswords);
    confirmation.addEventListener('input', syncPasswords);

    form.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.tagName === 'INPUT') {
            event.preventDefault();
            if (!next.hidden && !next.disabled) next.click();
        }
    });

    form.addEventListener('submit', () => {
        submit.disabled = true;
        submit.textContent = 'Kuruluyor…';
    });

    syncMode();
    show(current, !('hasErrors' in form.dataset));
}
