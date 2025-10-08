// Service Worker for Resource Caching and Performance Optimization
const CACHE_NAME = 'portfolio-v1.2';
const CRITICAL_RESOURCES = [
  '/',
  '/index.html',
  '/vendor/bootstrap/bootstrap.min.css',
  '/css/style.min.css',
  '/css/index.css',
  '/css/detail.css',
  '/css/login.css',
  '/css/admin-complete.css',
  '/js/app.min.js',
  '/vendor/bootstrap/bootstrap.min.js',
  '/vendor/bootstrap/popper.min.js'
];

const NON_CRITICAL_RESOURCES = [
  '/vendor/select2/select2.min.css',
  '/vendor/owlcarousel/owl.carousel.min.css',
  '/vendor/lightcase/lightcase.css',
  '/vendor/select2/select2.min.js',
  '/vendor/owlcarousel/owl.carousel.min.js',
  '/vendor/isotope/isotope.min.js',
  '/vendor/lightcase/lightcase.js',
  '/vendor/waypoints/waypoint.min.js',
  '/contactform/contactform.js'
];

// Install event - cache critical resources immediately
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        // Cache critical resources first
        return cache.addAll(CRITICAL_RESOURCES);
      })
      .then(() => {
        // Cache non-critical resources in background
        return caches.open(CACHE_NAME)
          .then(cache => cache.addAll(NON_CRITICAL_RESOURCES));
      })
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch event - serve from cache with network fallback
self.addEventListener('fetch', event => {
  // Skip non-GET requests
  if (event.request.method !== 'GET') return;
  
  // Skip external domains for analytics, etc.
  if (!event.request.url.startsWith(self.location.origin)) {
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Return cached version if available
        if (response) {
          // Update cache in background for non-critical resources
          if (!CRITICAL_RESOURCES.includes(new URL(event.request.url).pathname)) {
            fetch(event.request)
              .then(fetchResponse => {
                if (fetchResponse.ok) {
                  const responseClone = fetchResponse.clone();
                  caches.open(CACHE_NAME)
                    .then(cache => cache.put(event.request, responseClone));
                }
              })
              .catch(() => {}); // Ignore network errors
          }
          return response;
        }

        // Fetch from network and cache
        return fetch(event.request)
          .then(fetchResponse => {
            // Only cache successful responses
            if (!fetchResponse.ok) {
              return fetchResponse;
            }

            const responseClone = fetchResponse.clone();
            caches.open(CACHE_NAME)
              .then(cache => cache.put(event.request, responseClone));

            return fetchResponse;
          });
      })
  );
});

// Background sync for offline functionality
self.addEventListener('sync', event => {
  if (event.tag === 'background-sync') {
    event.waitUntil(
      // Update non-critical caches when network is available
      caches.open(CACHE_NAME)
        .then(cache => {
          return Promise.all(
            NON_CRITICAL_RESOURCES.map(url => {
              return fetch(url)
                .then(response => {
                  if (response.ok) {
                    return cache.put(url, response);
                  }
                })
                .catch(() => {}); // Ignore errors
            })
          );
        })
    );
  }
});