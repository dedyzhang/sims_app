(() => {
    const gate = document.getElementById('survei-mode-fokus');
    const content = document.getElementById('survei-pengisian');
    const form = content.querySelector('form');
    const start = document.getElementById('survei-fokus-mulai');
    const back = document.getElementById('survei-fokus-kembali');
    const heading = document.getElementById('survei-fokus-judul');
    const message = document.getElementById('survei-fokus-pesan');
    const submit = form.querySelector('[type="submit"]');
    const progress = document.getElementById('survei-progres');
    const progressLabel = document.getElementById('survei-progres-label');
    const submitMessage = document.getElementById('survei-kirim-pesan');
    const root = document.documentElement;
    const halaman = content.closest('[data-seb]').dataset;
    const pakaiSeb = halaman.seb === '1';
    const pakaiKios = Boolean(halaman.periodeKios);
    const requestFullscreen = root.requestFullscreen || root.webkitRequestFullscreen;
    const fullscreenActive = () => Boolean(document.fullscreenElement || document.webkitFullscreenElement);
    // iPhone, WebView aplikasi, dan sebagian browser tidak mendukung/menolak layar penuh, atau
    // permintaannya tidak pernah selesai. Batasi waktunya lalu lanjut dalam mode fokus tanpa
    // layar penuh (tetap dijeda saat siswa pindah tab/aplikasi) agar tombol tidak macet.
    const BATAS_LAYAR_PENUH_MS = 2500;
    const layarPenuhDidukung = typeof requestFullscreen === 'function'
        && (document.fullscreenEnabled ?? document.webkitFullscreenEnabled) !== false;
    let tanpaLayarPenuh = false;
    const modeFokusAktif = () => pakaiSeb || pakaiKios || tanpaLayarPenuh || fullscreenActive();

    function mintaLayarPenuh() {
        if (!layarPenuhDidukung) return Promise.resolve(false);
        return new Promise((resolve) => {
            let selesai = false;
            const akhiri = () => {
                if (selesai) return;
                selesai = true;
                clearTimeout(batas);
                document.removeEventListener('fullscreenchange', cek);
                document.removeEventListener('webkitfullscreenchange', cek);
                resolve(fullscreenActive());
            };
            const cek = () => { if (fullscreenActive()) akhiri(); };
            const batas = setTimeout(akhiri, BATAS_LAYAR_PENUH_MS);
            document.addEventListener('fullscreenchange', cek);
            document.addEventListener('webkitfullscreenchange', cek);
            try {
                Promise.resolve(requestFullscreen.call(root)).then(cek, akhiri);
            } catch {
                akhiri();
            }
        });
    }
    const sections = Array.from(form.querySelectorAll('.survei-bagian'));
    const steps = sections.flatMap((section) => Array.from(section.querySelectorAll('[data-langkah]'), (element, index) => ({
        section,
        element,
        number: index + 1,
        feedback: element.hasAttribute('data-masukan'),
        required: Array.from(element.querySelectorAll('input[type="radio"][required]')),
    })));
    const requiredCount = (step) => new Set(step.required.map((input) => input.name)).size;
    const answeredCount = (step) => step.required.filter((input) => input.checked).length;
    const total = steps.reduce((sum, step) => sum + requiredCount(step), 0);
    const sectionButtons = Array.from(content.querySelectorAll('[data-bagian]'));
    const subjectList = content.querySelector('.survei-daftar-mapel');
    const previous = document.getElementById('survei-sebelumnya');
    const next = document.getElementById('survei-berikutnya');
    const currentNumber = document.getElementById('survei-nomor-aktif');
    const numberList = document.getElementById('survei-daftar-nomor');
    const numberPanel = numberList.closest('details');
    const numberSummary = document.getElementById('survei-ringkas-nomor');
    const desktop = window.matchMedia('(min-width: 768px)');
    const confirmation = document.getElementById('survei-konfirmasi');
    let current = 0;
    let confirmed = false;
    const daftar = content.closest('[data-daftar]').dataset.daftar;
    let started = gate.dataset.sudahMulai === '1';
    let active = false;
    let submitting = false;
    let completed = false;
    let scrollTop = 0;
    const scroller = content.closest('main');

    numberPanel.open = desktop.matches;

    const numberButtons = steps.map((step, index) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'survei-nomor';
        button.classList.toggle('survei-nomor-masukan', step.feedback);
        button.textContent = step.feedback ? 'Masukan' : step.number;
        const status = document.createElement('span');
        status.className = 'survei-status-nomor';
        status.setAttribute('aria-hidden', 'true');
        button.append(status);
        button.addEventListener('click', () => {
            if (!desktop.matches) numberPanel.open = false;
            showStep(index);
        });
        numberList.append(button);
        return { button, status };
    });

    function showStep(index, focus = true) {
        if (submitting || !steps[index]) return;
        current = index;
        const step = steps[current];
        sections.forEach((section) => { section.hidden = section !== step.section; });
        steps.forEach(({ element }) => { element.hidden = element !== step.element; });
        const questionCount = steps.filter((item) => item.section === step.section && !item.feedback).length;
        currentNumber.textContent = step.feedback ? 'Masukan untuk bagian ini' : 'Soal ' + step.number + ' dari ' + questionCount;
        previous.disabled = current === 0;
        next.textContent = current === steps.length - 1 ? 'Periksa jawaban' : 'Berikutnya';
        updateProgress();
        if (focus) {
            const activeSubject = sectionButtons.find((button) => button.dataset.bagian === step.section.id);
            const listBounds = subjectList.getBoundingClientRect();
            const buttonBounds = activeSubject.getBoundingClientRect();
            if (!desktop.matches && (buttonBounds.left < listBounds.left || buttonBounds.right > listBounds.right)) {
                subjectList.scrollBy({ left: buttonBounds.left - listBounds.left - 4 });
            }
            currentNumber.focus({ preventScroll: true });
            currentNumber.scrollIntoView({ block: 'start' });
        }
    }

    function updateProgress() {
        const answered = steps.reduce((sum, step) => sum + answeredCount(step), 0);
        progress.max = total;
        progress.value = answered;
        progressLabel.textContent = answered + ' dari ' + total + ' pertanyaan wajib terjawab';
        submit.disabled = answered !== total || !active || submitting || completed;
        sectionButtons.forEach((button) => {
            const bagian = steps.filter((step) => step.section.id === button.dataset.bagian);
            const selesai = bagian.every((step) => answeredCount(step) === requiredCount(step));
            const isCurrent = steps[current].section.id === button.dataset.bagian;
            button.classList.toggle('is-complete', selesai);
            button.setAttribute('aria-pressed', String(isCurrent));
            button.querySelector('[data-status-bagian]').textContent = selesai ? '✓ Lengkap' : '';
        });
        const currentSection = steps[current].section;
        const questions = steps.filter((step) => step.section === currentSection && !step.feedback);
        const answeredQuestions = questions.filter((step) => answeredCount(step) === requiredCount(step)).length;
        numberSummary.textContent = answeredQuestions + '/' + questions.length + ' terjawab';
        numberButtons.forEach(({ button, status }, index) => {
            const step = steps[index];
            button.hidden = step.section !== currentSection;
            const done = requiredCount(step) > 0 && answeredCount(step) === requiredCount(step);
            const answer = done && !step.feedback
                ? step.required.find((input) => input.checked)?.closest('label')?.querySelector('.survei-huruf')?.textContent
                : null;
            button.classList.toggle('is-complete', done);
            button.setAttribute('aria-current', index === current ? 'step' : 'false');
            status.textContent = answer || (done ? '✓' : '');
            button.setAttribute('aria-label', (step.feedback ? 'Masukan' : 'Soal ' + step.number)
                + (answer ? ', jawaban ' + answer : (done ? ', terjawab' : '')));
        });
    }

    function pause(text) {
        if (!active || completed) return;

        active = false;
        if (confirmation.open) confirmation.close();
        scrollTop = scroller.scrollTop;
        content.inert = true;
        content.hidden = true;
        heading.textContent = 'Pengisian dijeda';
        message.textContent = text;
        start.textContent = 'Lanjutkan pengisian';
        gate.hidden = false;
        start.disabled = submitting;
        updateProgress();
        if (!document.hidden && !submitting) start.focus();
    }

    start.addEventListener('click', async () => {
        start.disabled = true;
        const layarPenuh = modeFokusAktif() || await mintaLayarPenuh();
        start.disabled = false;

        if (document.hidden) {
            message.textContent = 'Kembali ke tab survei ini lalu tekan tombol sekali lagi.';
            return;
        }
        if (!layarPenuh) tanpaLayarPenuh = true;

        started = true;
        active = true;
        back.hidden = true;
        gate.hidden = true;
        content.hidden = false;
        content.inert = false;
        scroller.scrollTop = scrollTop;
        updateProgress();
    });

    function syncFullscreen() {
        if (!modeFokusAktif()) {
            pause('Layar penuh berakhir. Lanjutkan pengisian untuk kembali ke survei.');
        }
    }

    document.addEventListener('fullscreenchange', syncFullscreen);
    document.addEventListener('webkitfullscreenchange', syncFullscreen);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) pause('Kembali ke survei untuk melanjutkan pengisian.');
    });

    form.addEventListener('input', updateProgress);
    sectionButtons.forEach((button) => button.addEventListener('click', () => {
        const first = steps.findIndex((step) => step.section.id === button.dataset.bagian);
        showStep(first);
    }));
    previous.addEventListener('click', () => showStep(current - 1));
    next.addEventListener('click', () => {
        if (current < steps.length - 1) {
            showStep(current + 1);
            return;
        }
        const unanswered = steps.findIndex((step) => answeredCount(step) !== requiredCount(step));
        if (unanswered >= 0) showStep(unanswered);
        else form.requestSubmit();
    });
    content.querySelectorAll('[data-ukuran]').forEach((button, _, buttons) => button.addEventListener('click', () => {
        form.dataset.ukuran = button.dataset.ukuran;
        buttons.forEach((option) => option.setAttribute('aria-pressed', String(option === button)));
    }));
    document.getElementById('survei-periksa').addEventListener('click', () => confirmation.close());
    document.getElementById('survei-kirim-final').addEventListener('click', () => {
        confirmation.close();
        confirmed = true;
        form.requestSubmit();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!active || submitting || completed || !modeFokusAktif()) return;
        const invalid = form.querySelector(':invalid');
        if (invalid) {
            const index = steps.findIndex((step) => step.element.contains(invalid));
            if (index >= 0) showStep(index);
            invalid.reportValidity();
            return;
        }
        if (!confirmed) {
            if (typeof confirmation.showModal === 'function') {
                confirmation.showModal();
                return;
            }
            if (!window.confirm('Kirim semua jawaban? Jawaban tidak dapat diubah setelah dikirim.')) return;
        }
        confirmed = false;

        submitting = true;
        submit.setAttribute('aria-busy', 'true');
        submit.textContent = 'Mengirim jawaban…';
        submitMessage.hidden = true;
        updateProgress();

        let urlKeluar;
        try {
            const body = new FormData(form);
            if (pakaiSeb) body.set('bukti_seb', window.SafeExamBrowser?.security?.browserExamKey || '');
            if (pakaiKios) {
                body.set('bukti_kios', await window.webkit.messageHandlers.simsKios.postMessage({
                    action: 'kirim', period: halaman.periodeKios,
                    nonce: halaman.tantanganKios, session: body.get('tanda_sesi'),
                }));
            }
            const response = await fetch(form.action, {
                method: 'POST',
                body,
                headers: { Accept: 'application/json' },
                redirect: 'error',
                cache: 'no-store',
            });
            const result = await response.json();
            if (response.status === 403) {
                throw new Error('Akses sesi sudah berakhir. Hubungi pemandu survei.');
            }
            if (!response.ok || result.tersimpan !== true) {
                throw new Error(response.status === 422
                    ? 'Periksa kembali pilihan kendala dan panjang komentar, lalu kirim ulang.'
                    : 'SIMS belum dapat mengonfirmasi penyimpanan. Silakan coba lagi.');
            }
            if (pakaiSeb && !result.keluar) {
                throw new Error('Jawaban tersimpan. Hubungi pemandu untuk keluar dari Safe Exam Browser.');
            }
            urlKeluar = result.kios_selesai || result.keluar;
        } catch (error) {
            submitting = false;
            submit.removeAttribute('aria-busy');
            start.disabled = false;
            submit.textContent = 'Kirim semua jawaban';
            submitMessage.textContent = error instanceof TypeError || error instanceof SyntaxError
                ? 'Sambungan ke SIMS terputus. Periksa koneksi dan coba kirim lagi.'
                : error.message;
            submitMessage.hidden = false;
            updateProgress();
            return;
        }

        completed = true;
        content.inert = true;
        content.hidden = true;
        form.reset();
        try {
            if (fullscreenActive()) await (document.exitFullscreen || document.webkitExitFullscreen)?.call(document);
        } finally {
            window.location.replace(urlKeluar || daftar);
        }
    });
    window.addEventListener('beforeunload', (event) => {
        if (started && !completed) event.preventDefault();
    });
    window.addEventListener('pagehide', () => {
        content.inert = true;
        content.hidden = true;
    });
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;
        form.reset();
        window.location.reload();
    });

    back.hidden = started || pakaiSeb || pakaiKios;
    if (!layarPenuhDidukung && !pakaiSeb && !pakaiKios && !started) {
        start.textContent = 'Mulai survei';
        message.textContent = 'Siapkan waktu untuk menyelesaikan semua pertanyaan wajib. Jangan berpindah aplikasi selama mengisi.';
    }
    showStep(current, false);
    start.disabled = false;
})();
