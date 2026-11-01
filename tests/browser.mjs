/** Optional Chromium UI check. Start the app first; requires Playwright and Chromium. */
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const origin = process.env.TEST_URL || 'http://127.0.0.1:8080';
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
let checks = 0;
let mapVerified = false;
try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const errors = [];
  const mapRequests = [];
  // Intercept map navigation: test opt-in and URL construction without contacting Google.
  await page.route('https://www.google.com/**', async route => {
    mapRequests.push(route.request().url());
    await route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><title>Map test fixture</title><p>Intercepted map navigation</p>' });
  });
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('requestfailed', r => errors.push(`${r.url()}: ${r.failure()?.errorText}`));
  for (const lang of ['tr', 'en', 'de']) {
    const response = await page.goto(`${origin}/${lang}/`, { waitUntil: 'networkidle' });
    assert.equal(response.status(), 200);
    assert.equal(await page.locator('h1').count(), 1);
    assert.ok((await page.locator('h1').innerText()).trim().length > 5);
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    checks++;
    if (lang === 'tr') await page.screenshot({ path: '/tmp/kas-desktop.png', fullPage: true });
  }
  const langs = ['tr', 'en', 'de', 'fr', 'es', 'it', 'pt', 'nl', 'ru', 'ar', 'zh', 'ja', 'ko', 'fa'];
  const paths = ['', 'teklif-al/', 'randevu/', 'gizlilik/', 'blog/', 'hizmetler/yuk-tasimaciligi/'];
  for (const width of [390, 375, 320, 768, 1024, 1440]) {
    await page.setViewportSize({ width, height: 844 });
    for (const lang of (width <= 390 ? langs : ['tr', 'en', 'de', 'ar', 'fa'])) {
      for (const path of paths) {
        const response = await page.goto(`${origin}/${lang}/${path}`, { waitUntil: 'domcontentloaded' });
        assert.equal(response.status(), 200, `${width} ${lang}/${path}`);
        assert.equal(await page.locator('html').getAttribute('lang'), lang);
        assert.equal(await page.locator('html').getAttribute('dir'), ['ar', 'fa'].includes(lang) ? 'rtl' : 'ltr');
        const dimensions = await page.evaluate(() => ({ viewport: innerWidth, page: document.documentElement.scrollWidth }));
        assert.ok(dimensions.page <= dimensions.viewport, `Horizontal overflow ${width} ${lang}/${path}: ${JSON.stringify(dimensions)}`);
        assert.equal(await page.locator('h1').count(), 1, `one h1: ${lang}/${path}`);
        checks++;
        if ([320, 768].includes(width) && path === '' && lang === 'tr') {
          await page.screenshot({ path: `/tmp/kas-${width === 320 ? 'mobile320' : 'tablet768'}.png`, fullPage: true });
        }
        if (width === 390 && path === '' && ['tr', 'ar', 'fa'].includes(lang)) {
          await page.screenshot({ path: lang === 'tr' ? '/tmp/kas-mobile.png' : `/tmp/kas-mobile-${lang}.png`, fullPage: true });
        }
      }
    }
  }
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`${origin}/tr/`);
  await page.locator('.menu-toggle').click();
  assert.equal(await page.locator('.menu-toggle').getAttribute('aria-expanded'), 'true');
  await page.locator('#navigation a').first().click();
  assert.equal(await page.locator('.menu-toggle').getAttribute('aria-expanded'), 'false');
  await page.locator('.languages summary').click();
  await page.locator('.language-list a[lang="en"]').click();
  assert.equal(new URL(page.url()).pathname, '/en/');
  await page.locator('.hero .button').click();
  assert.equal(new URL(page.url()).pathname, '/en/teklif-al/');
  await page.locator('button[type="submit"]').click();
  assert.equal(await page.locator('input[name="name"]').evaluate(input => input.validity.valueMissing), true);
  assert.equal(new URL(page.url()).pathname, '/en/teklif-al/');
  checks += 5;
  await page.goto(`${origin}/tr/`, { waitUntil: 'networkidle' });
  assert.equal(mapRequests.length, 0, 'No Google Maps request before explicit opt-in');
  const mapButton = page.locator('.map-load-button');
  if (await mapButton.count()) {
    const frame = page.locator('#google-location-map');
    assert.equal(await frame.getAttribute('src'), null, 'Map frame has no initial external URL');
    assert.equal(await frame.isHidden(), true);
    const address = (await page.locator('.location-copy address').innerText()).trim();
    assert.ok(address.length > 0);
    const embed = new URL(await mapButton.getAttribute('data-map-url'));
    assert.equal(embed.origin, 'https://www.google.com');
    assert.equal(embed.pathname, '/maps');
    assert.equal(embed.searchParams.get('q'), address, 'Embedded map uses exact configured address');
    assert.equal(embed.searchParams.get('output'), 'embed');
    const directions = new URL(await page.locator('.location-directions').getAttribute('href'));
    assert.equal(directions.searchParams.get('destination'), address, 'Directions use exact configured address');
    assert.equal(directions.searchParams.get('api'), '1');
    const search = new URL(await page.locator('.map-bottom a').getAttribute('href'));
    assert.equal(search.searchParams.get('query'), address, 'Map search uses exact configured address');
    const mapRequest = page.waitForRequest(request => request.url() === embed.href);
    await mapButton.click();
    await mapRequest;
    await page.waitForFunction(() => document.querySelector('#google-location-map')?.hasAttribute('src'));
    assert.equal(await frame.isVisible(), true);
    assert.equal(await frame.getAttribute('src'), embed.href);
    await page.waitForFunction(() => document.querySelector('#google-location-map')?.contentWindow !== null);

    assert.equal(mapRequests.length, 1, 'Only explicit click starts one map navigation (intercepted)');
    assert.equal(mapRequests[0], embed.href);
    checks += 13;
    mapVerified = true;
  }
  assert.deepEqual(errors, [], `Browser/CSP/network errors: ${JSON.stringify(errors)}`);
  console.log(`PASS: ${checks} desktop, mobile, localization and interaction checks; no browser, CSP or network errors.`);
  console.log(mapVerified ? 'Google Maps: opt-in and exact-address URL construction verified via intercepted iframe; remote map availability not tested.' : 'Google Maps: skipped because no address was configured.');
  console.log('Screenshots: /tmp/kas-desktop.png, /tmp/kas-mobile.png, /tmp/kas-mobile-ar.png, /tmp/kas-mobile-fa.png, /tmp/kas-mobile320.png, /tmp/kas-tablet768.png');
} finally {
  await browser.close();
}
