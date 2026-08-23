import { chromium } from 'playwright';

const browser = await chromium.launch({
  headless: true,
  executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
});
const page = await browser.newPage({ viewport: { width: 1600, height: 1000 }, deviceScaleFactor: 1 });
await page.goto('file:///D:/SIMS_MW_APP/marketing/index.html', { waitUntil: 'load' });
await page.evaluate(() => document.fonts.ready);

const imageAudit = await page.locator('main img').evaluateAll((images) => images.map((image) => ({
  alt: image.alt,
  loaded: image.complete && image.naturalWidth > 0,
  fit: getComputedStyle(image).objectFit,
  cursor: getComputedStyle(image).cursor,
})));
if (imageAudit.some((image) => !image.loaded)) throw new Error('Ada gambar landing page yang gagal dimuat.');
if (imageAudit.some((image) => image.cursor !== 'zoom-in')) throw new Error('Ada gambar tanpa interaksi zoom.');

await page.locator('.feature-card img').first().click();
await page.locator('.image-lightbox.open').waitFor({ state: 'visible' });
await page.locator('[data-zoom-in]').click();
if (await page.locator('[data-zoom-reset]').textContent() !== '120%') throw new Error('Kontrol zoom tidak bekerja.');
await page.keyboard.press('Escape');
if (await page.locator('.image-lightbox').isVisible()) throw new Error('Lightbox tidak tertutup dengan Escape.');

await page.screenshot({ path: 'D:/SIMS_MW_APP/marketing/landing-preview.png', fullPage: true });
await page.setViewportSize({ width: 390, height: 844 });
await page.screenshot({ path: 'D:/SIMS_MW_APP/marketing/landing-preview-mobile.png', fullPage: true });
console.log(`${await page.title()} :: ${page.url()} :: ${imageAudit.length} gambar OK :: lightbox OK`);

for (const fileName of ['fitur.html', 'harga.html', 'kontak.html']) {
  await page.goto(`file:///D:/SIMS_MW_APP/marketing/${fileName}`, { waitUntil: 'load' });
  const audit = await page.locator('main img').evaluateAll((images) => images.map((image) => ({
    loaded: image.complete && image.naturalWidth > 0,
    cursor: getComputedStyle(image).cursor,
  })));
  if (audit.some((image) => !image.loaded || image.cursor !== 'zoom-in')) {
    throw new Error(`Audit gambar gagal pada ${fileName}.`);
  }
  if (audit.length) {
    await page.locator('main img').first().click();
    await page.locator('.image-lightbox.open').waitFor({ state: 'visible' });
    await page.keyboard.press('Escape');
  }
  console.log(`${fileName} :: ${audit.length} gambar OK`);
}
await browser.close();
