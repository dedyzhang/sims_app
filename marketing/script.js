document.querySelector('[data-menu]')?.addEventListener('click', () => {
  document.querySelector('[data-nav]')?.classList.toggle('open');
});

document.querySelector('[data-demo-form]')?.addEventListener('submit', (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const message = form.querySelector('[data-form-message]');
  message.textContent = 'Terima kasih. Form demo ini siap dihubungkan ke endpoint lead marketing SIMS.';
  message.classList.add('show');
  form.reset();
});

const pageImages = [...document.querySelectorAll('main img')];

if (pageImages.length) {
  const lightbox = document.createElement('div');
  lightbox.className = 'image-lightbox';
  lightbox.setAttribute('role', 'dialog');
  lightbox.setAttribute('aria-modal', 'true');
  lightbox.setAttribute('aria-label', 'Pratinjau gambar aplikasi');
  lightbox.innerHTML = `
    <div class="lightbox-toolbar">
      <span class="lightbox-title" data-lightbox-title></span>
      <div class="lightbox-controls">
        <button type="button" data-zoom-out aria-label="Perkecil gambar">&minus;</button>
        <button type="button" data-zoom-reset aria-label="Kembalikan ukuran gambar">100%</button>
        <button type="button" data-zoom-in aria-label="Perbesar gambar">+</button>
        <button type="button" data-lightbox-close aria-label="Tutup pratinjau">&times;</button>
      </div>
    </div>
    <div class="lightbox-stage" data-lightbox-stage><img data-lightbox-image alt=""></div>
    <p class="lightbox-help">Gunakan tombol &minus; / + atau roda mouse untuk mengatur ukuran. Tekan Esc untuk menutup.</p>
  `;
  document.body.appendChild(lightbox);

  const stage = lightbox.querySelector('[data-lightbox-stage]');
  const preview = lightbox.querySelector('[data-lightbox-image]');
  const title = lightbox.querySelector('[data-lightbox-title]');
  const resetButton = lightbox.querySelector('[data-zoom-reset]');
  const closeButton = lightbox.querySelector('[data-lightbox-close]');
  let zoom = 1;
  let lastFocusedImage = null;

  const applyZoom = () => {
    const availableWidth = Math.max(280, stage.clientWidth - 52);
    const baseWidth = Math.min(preview.naturalWidth || availableWidth, availableWidth, 1500);
    preview.style.width = `${Math.round(baseWidth * zoom)}px`;
    resetButton.textContent = `${Math.round(zoom * 100)}%`;
  };

  const setZoom = (nextZoom) => {
    zoom = Math.min(3, Math.max(.5, Math.round(nextZoom * 10) / 10));
    applyZoom();
  };

  const openLightbox = (sourceImage) => {
    lastFocusedImage = sourceImage;
    zoom = 1;
    preview.src = sourceImage.currentSrc || sourceImage.src;
    preview.alt = sourceImage.alt;
    title.textContent = sourceImage.alt || 'Tampilan aplikasi Edutive';
    lightbox.classList.add('open');
    document.body.classList.add('lightbox-open');
    requestAnimationFrame(applyZoom);
    closeButton.focus();
  };

  const closeLightbox = () => {
    lightbox.classList.remove('open');
    document.body.classList.remove('lightbox-open');
    preview.removeAttribute('src');
    lastFocusedImage?.focus();
  };

  pageImages.forEach((image) => {
    image.classList.add('zoomable-image');
    image.tabIndex = 0;
    image.title = 'Klik untuk melihat gambar lebih jelas';
    image.setAttribute('role', 'button');
    image.setAttribute('aria-label', `${image.alt}. Klik untuk memperbesar.`);
    image.addEventListener('click', () => openLightbox(image));
    image.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openLightbox(image);
      }
    });
  });

  lightbox.querySelector('[data-zoom-out]').addEventListener('click', () => setZoom(zoom - .2));
  lightbox.querySelector('[data-zoom-in]').addEventListener('click', () => setZoom(zoom + .2));
  resetButton.addEventListener('click', () => setZoom(1));
  closeButton.addEventListener('click', closeLightbox);
  preview.addEventListener('load', applyZoom);
  stage.addEventListener('wheel', (event) => {
    event.preventDefault();
    setZoom(zoom + (event.deltaY < 0 ? .2 : -.2));
  }, { passive: false });
  lightbox.addEventListener('click', (event) => {
    if (event.target === lightbox || event.target === stage) closeLightbox();
  });
  document.addEventListener('keydown', (event) => {
    if (!lightbox.classList.contains('open')) return;
    if (event.key === 'Escape') closeLightbox();
    if (event.key === '+' || event.key === '=') setZoom(zoom + .2);
    if (event.key === '-') setZoom(zoom - .2);
  });
  window.addEventListener('resize', () => {
    if (lightbox.classList.contains('open')) applyZoom();
  });
}
