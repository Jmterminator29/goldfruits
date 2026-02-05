const CACHE_NAME = 'goldfruits-v2';
// "App shell" (lo mínimo para arrancar offline)
const urlsToCache = [
  './',
  './index.php',
  './user/nuevo_acopio.php',
  './user/mis_solicitudes.php',
  './manifest.json',
  './offline.html',
  './offline_queue.js',
  './assets/icon-192.png',
  './icon-512.png'
];

// Instalación
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

// Activación y limpieza de cachés viejos
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cache => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    })
  );
});

// Intercepta peticiones
// - Navegación (HTML/PHP): Network First + fallback cache + offline.html
// - Assets (iconos/js): Cache First
self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // Evitar cachear llamadas POST (guardar, etc.)
  if (req.method && req.method.toUpperCase() !== 'GET') return;

  // Navegación
  if (req.mode === 'navigate') {
    event.respondWith(
      (async () => {
        try {
          const fresh = await fetch(req);
          const cache = await caches.open(CACHE_NAME);
          cache.put(req, fresh.clone());
          return fresh;
        } catch (e) {
          const cached = await caches.match(req);
          return cached || caches.match('./offline.html');
        }
      })()
    );
    return;
  }

  // Cache-first para archivos estáticos del mismo origen
  if (url.origin === self.location.origin) {
    event.respondWith(
      caches.match(req).then((cached) => {
        return (
          cached ||
          fetch(req).then((resp) => {
            // Guardar en cache solo si es OK
            if (resp && resp.ok) {
              const copy = resp.clone();
              caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
            }
            return resp;
          })
        );
      })
    );
  }
});