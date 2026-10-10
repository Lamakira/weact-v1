/* WeAct service worker — web push ONLY.
 *
 * Deliberately no fetch handler and no precache: a caching worker would serve a
 * stale app shell after a deploy. It exists to display push notifications and to
 * route a click to the right screen. The pure helpers below are exercised by
 * frontend/src/__tests__/serviceWorker.spec.ts (the file is evaluated in a sandbox).
 */

var ICON = '/icons/icon-192.png'
var BADGE = '/icons/badge-96.png'

function parsePushPayload(event) {
  if (!event || !event.data) return {}
  try {
    var parsed = event.data.json()
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch (e) {
    return { body: event.data.text() }
  }
}

/** Title/options for showNotification() from the server payload (title, body, tag, icon, badge, data.url). */
function buildNotification(payload) {
  var data = payload && payload.data && typeof payload.data === 'object' ? payload.data : {}

  return {
    title: payload && payload.title ? String(payload.title) : 'WeAct',
    options: {
      body: payload && payload.body ? String(payload.body) : '',
      icon: (payload && payload.icon) || ICON,
      badge: (payload && payload.badge) || BADGE,
      tag: payload && payload.tag ? String(payload.tag) : undefined,
      renotify: Boolean(payload && payload.tag),
      data: { url: typeof data.url === 'string' ? data.url : '/' },
    },
  }
}

/** Absolute URL of the click target, restricted to this origin (fallback: home). */
function resolveTargetUrl(rawUrl, origin) {
  try {
    var url = new URL(rawUrl || '/', origin)
    return url.origin === origin ? url.href : origin + '/'
  } catch (e) {
    return origin + '/'
  }
}

/** First open WeAct window, if any. */
function findWeActClient(windowClients, origin) {
  for (var i = 0; i < windowClients.length; i += 1) {
    if (String(windowClients[i].url).indexOf(origin) === 0) return windowClients[i]
  }
  return null
}

function handleNotificationClick(event, scope) {
  var target = resolveTargetUrl(event.notification && event.notification.data && event.notification.data.url, scope.location.origin)
  event.notification.close()

  return scope.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
    var existing = findWeActClient(windowClients, scope.location.origin)
    if (!existing) return scope.clients.openWindow(target)

    return existing
      .focus()
      .then(function (focused) {
        var client = focused || existing
        return typeof client.navigate === 'function' ? client.navigate(target) : scope.clients.openWindow(target)
      })
      .catch(function () {
        return scope.clients.openWindow(target)
      })
  })
}

self.addEventListener('install', function () {
  self.skipWaiting()
})

self.addEventListener('activate', function (event) {
  event.waitUntil(self.clients.claim())
})

self.addEventListener('push', function (event) {
  var notification = buildNotification(parsePushPayload(event))
  event.waitUntil(self.registration.showNotification(notification.title, notification.options))
})

self.addEventListener('notificationclick', function (event) {
  event.waitUntil(handleNotificationClick(event, self))
})
