const CACHE_NAME = 'arp-cache-v1';
const urls = [
  '/', '/annual_report_portal/index.php', '/annual_report_portal/assets/style.css'
];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE_NAME).then(c => c.addAll(urls)));
});
self.addEventListener('fetch', e => {
  e.respondWith(caches.match(e.request).then(r => r || fetch(e.request)));
});
