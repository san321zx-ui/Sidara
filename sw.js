self.addEventListener('install', e => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {}); // يلزم لتثبيت التطبيق؛ كل البيانات تُجلب مباشرة من الخادم
