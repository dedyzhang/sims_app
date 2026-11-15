(() => {
    const form = document.getElementById('survei-verifikasi-seb');
    const security = window.SafeExamBrowser?.security;
    if (!form || !security) return;

    async function verifikasi() {
        const pesan = document.getElementById('survei-seb-pesan');
        pesan.textContent = 'Memeriksa konfigurasi Safe Exam Browser…';

        try {
            const body = new FormData(form);
            body.set('bukti_seb', security.browserExamKey || '');
            const response = await fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error();

            const { form: url } = await response.json();
            window.location.replace(url);
        } catch {
            pesan.textContent = 'Konfigurasi Safe Exam Browser belum diterima. Hubungi pemandu survei.';
        }
    }

    if (security.browserExamKey) verifikasi();
    else security.updateKeys?.(verifikasi);
})();
