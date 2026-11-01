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
