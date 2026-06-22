document.documentElement.classList.add('js-ready');
for (const a of document.querySelectorAll('a[href]')) {
  const url = a.getAttribute('href') || '';
  if (url.startsWith('http://') || url.startsWith('https://')) {
    a.dataset.external = 'true';
  }
}
