import type { AppNotification, Paginated } from '~/types/api'

const POLL_MS = 60_000

/**
 * Unread count shared by the bell and the notifications page.
 *
 * The count is polled once a minute (and when the tab becomes visible again)
 * through a tiny endpoint, instead of keeping a websocket open — enough for
 * in-app notifications and keeps API traffic low (spec §8).
 */
export function useNotifications() {
  const api = useApi()
  const unread = useState<number>('notifications:unread', () => 0)

  async function refreshCount() {
    try {
      const res = await api<{ data: { unread_count: number } }>('/api/notifications/unread-count')
      unread.value = res.data.unread_count
    } catch {
      // A failed poll is not worth bothering the user about; try again next time.
    }
  }

  async function list(query: { unread?: boolean; page?: number; per_page?: number } = {}) {
    const res = await api<Paginated<AppNotification> & { meta: { unread_count: number } }>(
      '/api/notifications',
      { query: { ...query, unread: query.unread ? 1 : undefined } }
    )
    unread.value = res.meta.unread_count
    return res
  }

  async function markRead(notification: AppNotification) {
    if (notification.read_at) return
    await api(`/api/notifications/${notification.id}/read`, { method: 'PATCH' })
    notification.read_at = new Date().toISOString()
    unread.value = Math.max(0, unread.value - 1)
  }

  async function markAllRead() {
    await api('/api/notifications/read-all', { method: 'POST' })
    unread.value = 0
  }

  /** Call once from the app layout. */
  function startPolling() {
    refreshCount()
    const timer = setInterval(() => {
      if (document.visibilityState === 'visible') refreshCount()
    }, POLL_MS)
    const onVisible = () => document.visibilityState === 'visible' && refreshCount()
    document.addEventListener('visibilitychange', onVisible)

    onBeforeUnmount(() => {
      clearInterval(timer)
      document.removeEventListener('visibilitychange', onVisible)
    })
  }

  return { unread: readonly(unread), refreshCount, list, markRead, markAllRead, startPolling }
}
