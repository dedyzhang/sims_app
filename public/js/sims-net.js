/* Animation uses CSS transforms; JS only handles setup, resize and pause events. */
(() => {
    const root = document.querySelector('[data-sims-ticker]');
    if (!root || root.dataset.moving === 'true') return;
    const viewport = root.querySelector('.sims-net-stats');
    const source = root.querySelector('[data-ticker-original]');
    const button = root.querySelector('.sims-net-pause');
    if (!viewport || !source || !button) return;
    const copy = source.cloneNode(true);
    copy.removeAttribute('data-ticker-original');
    copy.classList.add('sims-net-copy');
    copy.setAttribute('aria-hidden', 'true');
    source.parentElement.appendChild(copy);
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const visibility = () => { root.dataset.hidden = String(document.hidden); };
    const size = () => {
        const width = `${viewport.clientWidth}px`;
        if (root.style.getPropertyValue('--sims-net-viewport') !== width) root.style.setProperty('--sims-net-viewport', width);
        const duration = `${Math.max(20, source.scrollWidth / 32)}s`;
        if (root.style.getPropertyValue('--sims-net-duration') !== duration) root.style.setProperty('--sims-net-duration', duration);
    };
    const motionKey = 'sims-net-motion:' + (document.body?.dataset.appearanceOwner || '');
    let forceMotion = false;
    try { forceMotion = sessionStorage.getItem(motionKey) === '1'; } catch (_) {}
    const updateControl = () => {
        const reducedWithoutOverride = reduced.matches && !forceMotion;
        const paused = root.dataset.paused === 'true';
        root.dataset.reduced = String(reducedWithoutOverride);
        root.dataset.forceMotion = String(forceMotion);
        button.setAttribute('aria-pressed', String(paused || reducedWithoutOverride));
        button.setAttribute('aria-label', reducedWithoutOverride ? 'Aktifkan gerakan SIMS-NET' : paused ? 'Lanjutkan gerakan SIMS-NET' : 'Jeda gerakan SIMS-NET');
        button.textContent = reducedWithoutOverride ? 'Gerakkan' : paused ? 'Lanjut' : 'Jeda';
    };
    const preference = (event) => {
        if (event) {
            forceMotion = false;
            try { sessionStorage.removeItem(motionKey); } catch (_) {}
        }
        updateControl(); size();
    };
    button.addEventListener('click', () => {
        if (reduced.matches && !forceMotion) {
            forceMotion = true;
            try { sessionStorage.setItem(motionKey, '1'); } catch (_) {}
            root.dataset.paused = 'false';
        } else root.dataset.paused = String(root.dataset.paused !== 'true');
        updateControl();
    });
    document.addEventListener('visibilitychange', visibility);
    reduced.addEventListener('change', preference);
    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(size);
        observer.observe(viewport);
        observer.observe(source);
    } else window.addEventListener('resize', size);
    if (document.fonts?.ready) document.fonts.ready.then(size);
    button.hidden = false;
    visibility(); preference();
    root.dataset.moving = 'true';
})();
