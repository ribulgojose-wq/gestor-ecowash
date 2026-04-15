const CACHE = 'ecowash-v1';
self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE).then(c => c.addAll([
      '/', '/index.php', '/manifest.json',
      '/img/icono-192-app.png',
      '/img/logo-ecowash-512x512.png',
      '/img/splash-screen.png',
    ])).then(() => self.skipWaiting())
  );
});
self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});
self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);
  if (['guardar.php','cerrar.php','logout.php'].some(f => url.pathname.includes(f))) {
    e.respondWith(fetch(e.request)); return;
  }
  e.respondWith(
    fetch(e.request).catch(() => caches.match(e.request))
  );
});
