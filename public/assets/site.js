'use strict';
document.querySelectorAll('.language-list a[lang]').forEach((link) => {
  link.addEventListener('click', () => {
    document.cookie = 'tofidesk_locale=' + encodeURIComponent(link.lang) + '; Max-Age=31536000; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  });
});
const toggle = document.querySelector('.menu-toggle');
const navigation = document.querySelector('#navigation');
if (toggle && navigation) {
  toggle.addEventListener('click', () => {
    const expanded = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', String(!expanded));
    navigation.classList.toggle('is-open', !expanded);
  });
  navigation.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
    toggle.setAttribute('aria-expanded', 'false'); navigation.classList.remove('is-open');
  }));
  document.addEventListener('keydown', event => { if(event.key === 'Escape') { toggle.setAttribute('aria-expanded', 'false'); navigation.classList.remove('is-open'); } });
}

document.querySelectorAll('[data-cookie-accept]').forEach((button) => {
  button.addEventListener('click', () => {
    document.cookie = 'tofidesk_cookie_consent=accepted; Max-Age=31536000; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    button.closest('[data-cookie-consent]')?.remove();
  });
});
document.querySelectorAll('[data-cookie-necessary]').forEach((button) => {
  button.addEventListener('click', () => {
    document.cookie = 'tofidesk_cookie_consent=essential; Max-Age=31536000; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    button.closest('[data-cookie-consent]')?.remove();
  });
});
document.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
  if (form.checkValidity()) { const button = form.querySelector('button[type="submit"]'); if (button) { button.disabled = true; button.setAttribute('aria-busy','true'); } }
}));
// Location-aware discovery: visitors search by readable names; the stable
// location id is submitted only after a suggestion is explicitly selected.
document.querySelectorAll('[data-location-picker]').forEach((picker) => {
  const query = picker.querySelector('[data-location-query]');
  const id = picker.querySelector('[data-location-id]');
  const list = picker.querySelector('[data-location-suggestions]');
  if (!query || !id || !list) return;
  let timer = 0;
  let controller = null;
  const close = () => { list.hidden = true; query.setAttribute('aria-expanded', 'false'); };
  const render = (items) => {
    list.replaceChildren();
    for (const item of items) {
      const option = document.createElement('button');
      option.type = 'button'; option.className = 'location-suggestion'; option.setAttribute('role', 'option');
      option.dataset.id = String(item.id);
      option.innerHTML = `<strong>${escapeHtml(item.name)}</strong><small>${escapeHtml([item.level, item.country_code, item.postal_code].filter(Boolean).join(' · '))}</small>`;
      option.addEventListener('click', () => { query.value = item.name; id.value = String(item.id); close(); query.focus(); });
      list.append(option);
    }
    list.hidden = items.length === 0;
    query.setAttribute('aria-expanded', String(items.length > 0));
  };
  const search = async () => {
    const value = query.value.trim();
    if (value.length < 2) { id.value = ''; close(); return; }
    controller?.abort(); controller = new AbortController();
    try {
      const response = await fetch(`/api/platform/v1/locations/search?q=${encodeURIComponent(value)}&limit=12`, {headers:{Accept:'application/json'}, signal:controller.signal});
      if (!response.ok) throw new Error('location');
      render((await response.json()).items || []);
    } catch (error) { if (error.name !== 'AbortError') close(); }
  };
  query.addEventListener('input', () => { id.value = ''; clearTimeout(timer); timer = setTimeout(search, 180); });
  query.addEventListener('focus', () => { if (query.value.trim().length >= 2) search(); });
  document.addEventListener('click', (event) => { if (!picker.contains(event.target)) close(); });
});
function escapeHtml(value) {
  const div = document.createElement('div'); div.textContent = String(value ?? ''); return div.innerHTML;
}
// No map provider is contacted until the visitor explicitly opens the map.
document.querySelectorAll('[data-map-url]').forEach(button => {
  button.addEventListener('click', () => {
    const map = document.getElementById(button.getAttribute('aria-controls'));
    if (!map || map.hasAttribute('src')) return;
    const url = button.getAttribute('data-map-url');
    if (!url || !/^https:\/\/(?:www\.google\.com\/maps\?|www\.openstreetmap\.org\/|www\.bing\.com\/maps\?)/.test(url)) return;
    map.setAttribute('src', url);
    map.hidden = false;
    const placeholder = button.closest('.map-placeholder');
    if (placeholder) placeholder.hidden = true;
    button.disabled = true;
  });
});
const galleryDialog = document.querySelector('[data-gallery-dialog]');
const galleryImage = galleryDialog?.querySelector('[data-gallery-image]');
const galleryCaption = galleryDialog?.querySelector('[data-gallery-caption]');
function galleryOpen(button) {
  if (!galleryDialog || !galleryImage || !galleryCaption) return;
  galleryImage.src = button.dataset.src || '';
  galleryImage.alt = button.dataset.alt || '';
  galleryCaption.textContent = button.dataset.caption || button.dataset.alt || '';
  galleryDialog.showModal();
}
document.addEventListener('click', event => {
  const opener = event.target.closest('[data-gallery-open]');
  if (opener) galleryOpen(opener);
  if (event.target.closest('[data-gallery-close]')) galleryDialog?.close();
  if (event.target === galleryDialog) galleryDialog.close();
});
const galleryGrid = document.querySelector('[data-gallery-grid]');
const galleryMore = document.querySelector('[data-gallery-more]');
if (galleryGrid && galleryMore) {
  galleryMore.addEventListener('click', async () => {
    const offset = Number(galleryGrid.dataset.offset || '0');
    galleryMore.disabled = true;
    try {
      const response = await fetch(`/api/gallery?lang=${encodeURIComponent(galleryGrid.dataset.lang || 'tr')}&offset=${offset}`, {headers:{Accept:'application/json'}});
      if (!response.ok) throw new Error('gallery');
      const data = await response.json();
      for (const item of data.items || []) {
        const button = document.createElement('button'); button.type = 'button'; button.className = 'gallery-card'; button.dataset.galleryOpen = ''; button.dataset.src = item.src; button.dataset.alt = item.alt || ''; button.dataset.caption = item.caption || '';
        const image = document.createElement('img'); image.src = item.src; image.alt = item.alt || ''; image.width = item.width; image.height = item.height; image.loading = 'lazy'; button.append(image);
        if (item.caption) { const caption = document.createElement('span'); caption.textContent = item.caption; button.append(caption); }
        galleryGrid.append(button);
      }
      galleryGrid.dataset.offset = String(data.next_offset || offset + (data.items || []).length);
      if (data.next_offset === null || !data.items?.length) galleryMore.hidden = true;
    } catch (_) { galleryMore.hidden = true; }
    finally { galleryMore.disabled = false; }
  });
}
if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
