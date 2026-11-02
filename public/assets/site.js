'use strict';
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
// No map provider is contacted until the visitor explicitly opens the map.
document.querySelectorAll('[data-map-url]').forEach(button => {
  button.addEventListener('click', () => {
    const map = document.getElementById(button.getAttribute('aria-controls'));
    if (!map || map.hasAttribute('src')) return;
    const url = button.getAttribute('data-map-url');
    if (!url || !url.startsWith('https://www.google.com/maps?')) return;
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
