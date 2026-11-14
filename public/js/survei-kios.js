(() => {
    const form = document.getElementById('survei-verifikasi-kios');
    const kios = window.webkit?.messageHandlers?.simsKios;
    if (!form || !kios) return;

    async function verifikasi() {
        const status = document.getElementById('survei-kios-pesan');
        status.textContent = 'Mengaktifkan penguncian perangkat…';
        try {
            const body = new FormData(form);
            const signature = await kios.postMessage({
                action: 'buka', period: form.dataset.periode,
                nonce: form.dataset.tantangan, session: body.get('tanda_sesi'),
            });
            body.set('bukti_kios', signature);
            const response = await fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error();
            window.location.replace((await response.json()).form);
        } catch {
            status.textContent = 'Penguncian perangkat belum berhasil. Hubungi pemandu survei.';
        }
    }

    verifikasi();
})();
