import { chromium } from 'playwright';
import fs from 'node:fs/promises';

const baseUrl = 'http://127.0.0.1:8000';
const outputDir = 'D:/SIMS_MW_APP/marketing/assets/app-original';
await fs.mkdir(outputDir, { recursive: true });

const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const context = await browser.newContext({ viewport: { width: 1600, height: 1000 }, deviceScaleFactor: 1 });
const vendorDir = 'D:/SIMS_MW_APP/marketing/assets/vendor';
const [tailwindCdn, alpineCdn, alpineCollapseCdn] = await Promise.all([
  fs.readFile(`${vendorDir}/tailwindcss-cdn.js`),
  fs.readFile(`${vendorDir}/alpine-3.14.8.min.js`),
  fs.readFile(`${vendorDir}/alpine-collapse-3.14.8.min.js`),
]);
await context.route(/^https:\/\/cdn\.tailwindcss\.com\/?(?:.*)?$/, (route) => route.fulfill({ status: 200, contentType: 'application/javascript', body: tailwindCdn }));
await context.route('https://unpkg.com/alpinejs@3.14.8/dist/cdn.min.js', (route) => route.fulfill({ status: 200, contentType: 'application/javascript', body: alpineCdn }));
await context.route('https://unpkg.com/@alpinejs/collapse@3.14.8/dist/cdn.min.js', (route) => route.fulfill({ status: 200, contentType: 'application/javascript', body: alpineCollapseCdn }));
const page = await context.newPage();
const selectedNames = new Set(process.argv.slice(2));

await page.goto(`${baseUrl}/login`, { waitUntil: 'commit', timeout: 30000 });
await page.locator('form[action$="/login"]').first().waitFor({ state: 'visible', timeout: 30000 });
const loginForm = page.locator('form[action$="/login"]').first();
await loginForm.locator('input[name="credential"]').fill('admin');
await loginForm.locator('input[name="password"]').fill('admin123');
await loginForm.locator('button[type="submit"]').click();
await page.waitForTimeout(1200);

const captures = [
  ['dashboard', '/dashboard'],
  ['absensi', '/absensi'],
  ['ruang-kelas', '/ruang-kelas'],
  ['ai-guru', '/ai/teacher'],
  ['keuangan', '/keuangan'],
  ['sarpras', '/sarpras'],
  ['ujian', '/ujian'],
  ['cbt-ujian', '/ujian/019ffbf0-b07e-7214-bd9b-96efbe149101'],
  ['komunikasi', '/grup'],
  ['pengaturan', '/settings'],
  ['geolocation', '/settings'],
  ['face-recognition', '/absensi/scan'],
  ['agenda-digital', '/agenda/rekap'],
];

for (const [name, path] of captures) {
  if (selectedNames.size && !selectedNames.has(name)) continue;
  await page.goto(`${baseUrl}${path}`, { waitUntil: 'commit', timeout: 30000 });
  await page.waitForFunction(() => document.readyState !== 'loading', null, { timeout: 30000 });
  await page.locator('body *').first().waitFor({ state: 'attached', timeout: 30000 });
  await page.waitForTimeout(1800);
  await page.evaluate(() => document.documentElement.removeAttribute('x-cloak'));
  if (name === 'ai-guru') {
    await page.evaluate(() => {
      const root = document.querySelector('.ai-teacher-page');
      if (root && window.Alpine) window.Alpine.$data(root).needsApiKeySetup = false;
    });
    await page.waitForTimeout(250);
  }
  if (name === 'geolocation') {
    await page.evaluate(() => {
      const lokasiHeading = [...document.querySelectorAll('*')].find((element) =>
        element.textContent?.trim() === 'Lokasi & Absen QR' && element.offsetParent !== null
      );
      lokasiHeading?.scrollIntoView({ block: 'center' });
    });
    await page.waitForTimeout(250);
  }
  if (name !== 'geolocation') await page.evaluate(() => window.scrollTo(0, 0));
  const diagnostics = await page.evaluate(() => ({
    text: document.body.innerText.trim().slice(0, 80),
    htmlLength: document.body.innerHTML.length,
    opacity: getComputedStyle(document.body).opacity,
    visibility: getComputedStyle(document.body).visibility,
    display: getComputedStyle(document.body).display,
    centerStack: document.elementsFromPoint(innerWidth / 2, innerHeight / 2).slice(0, 5).map((element) => ({
      tag: element.tagName,
      id: element.id,
      className: String(element.className).slice(0, 80),
      background: getComputedStyle(element).backgroundColor,
      zIndex: getComputedStyle(element).zIndex,
    })),
  }));
  console.log(`${name} diagnostics: ${JSON.stringify(diagnostics)}`);
  const layoutAudit = await page.evaluate(() => ({
    sidebarWidth: document.querySelector('aside')?.getBoundingClientRect().width ?? 0,
    widestSvg: Math.max(0, ...[...document.querySelectorAll('svg')].map((svg) => svg.getBoundingClientRect().width)),
    sidebarIcon: (() => {
      const icon = document.querySelector('aside svg');
      if (!icon) return { present: false, visible: false, width: 0, height: 0 };
      const rect = icon.getBoundingClientRect();
      const style = getComputedStyle(icon);
      return {
        present: true,
        visible: rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none',
        width: rect.width,
        height: rect.height,
      };
    })(),
  }));
  if (layoutAudit.sidebarWidth > 420 || layoutAudit.widestSvg > 600 || !layoutAudit.sidebarIcon.visible) {
    throw new Error(`Layout ${name} tidak valid: ${JSON.stringify(layoutAudit)}; aset lama tidak ditimpa.`);
  }
  const screenshot = await page.screenshot({ fullPage: false });
  if (screenshot.byteLength < 20000) {
    throw new Error(`Capture ${name} tampak kosong (${screenshot.byteLength} byte); aset lama tidak ditimpa.`);
  }
  await fs.writeFile(`${outputDir}/${name}.png`, screenshot);
  console.log(`${name}: ${page.url()} :: ${await page.title()}`);
}

if (!selectedNames.size || selectedNames.has('arena-belajar')) {
  await page.goto(`${baseUrl}/ruang-kelas/ZNCNY9/arena-belajar`, { waitUntil: 'commit', timeout: 30000 });
  await page.locator('body *').first().waitFor({ state: 'attached', timeout: 30000 });
  await page.waitForTimeout(900);
  await page.evaluate(() => document.documentElement.removeAttribute('x-cloak'));
  const arenaSidebarIcon = await page.evaluate(() => {
    const icon = document.querySelector('aside svg');
    if (!icon) return { present: false, visible: false, width: 0, height: 0 };
    const rect = icon.getBoundingClientRect();
    const style = getComputedStyle(icon);
    return {
      present: true,
      visible: rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none',
      width: rect.width,
      height: rect.height,
    };
  });
  if (!arenaSidebarIcon.visible) {
    throw new Error(`Layout arena-belajar tidak valid: ikon sidebar tidak tampil (${JSON.stringify(arenaSidebarIcon)}).`);
  }
  const screenshot = await page.screenshot({ fullPage: false });
  if (screenshot.byteLength < 20000) {
    throw new Error(`Capture arena-belajar tampak kosong (${screenshot.byteLength} byte); aset lama tidak ditimpa.`);
  }
  await fs.writeFile(`${outputDir}/arena-belajar.png`, screenshot);
  console.log(`arena-belajar: ${page.url()} :: ${await page.title()}`);
}

await browser.close();
