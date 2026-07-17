// This service worker has been retired — stale caches were causing some players
// to run old JS while others got the latest deploy. On activation it wipes every
// cache, unregisters itself, and forces any open tabs to reload so they fall back
// to plain network requests (no more caching layer at all).
self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys();
      await Promise.all(keys.map((k) => caches.delete(k)));
      await self.registration.unregister();
      const clientsList = await self.clients.matchAll({ type: 'window' });
      clientsList.forEach((client) => client.navigate(client.url));
    })()
  );
});
