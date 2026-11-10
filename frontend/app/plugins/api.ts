import type { User } from '~/types/api'

/**
 * One configured HTTP client for the Laravel API, available as `useApi()`.
 *
 * - Sends the session cookie with every request (`credentials: 'include'`).
 * - Before any write (POST/PUT/PATCH/DELETE) makes sure Sanctum's CSRF cookie
 *   exists and echoes it back in the X-XSRF-TOKEN header.
 * - On 401 (session gone) clears the user and sends them to /login.
 */
export default defineNuxtPlugin((nuxtApp) => {
  const config = useRuntimeConfig()
  const baseURL = config.public.apiBase as string
  const user = useState<User | null>('auth:user', () => null)

  const readCookie = (name: string): string | null => {
    const match = document.cookie.split('; ').find((c) => c.startsWith(`${name}=`))
    return match ? decodeURIComponent(match.slice(name.length + 1)) : null
  }

  const ensureCsrfCookie = async () => {
    if (!readCookie('XSRF-TOKEN')) {
      await $fetch('/sanctum/csrf-cookie', { baseURL, credentials: 'include' })
    }
  }

  const api = $fetch.create({
    baseURL,
    credentials: 'include',
    headers: { Accept: 'application/json' },

    async onRequest({ options }) {
      const method = (options.method ?? 'GET').toUpperCase()
      if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
        await ensureCsrfCookie()
        const headers = new Headers(options.headers)
        headers.set('X-XSRF-TOKEN', readCookie('XSRF-TOKEN') ?? '')
        options.headers = headers
      }
    },

    async onResponseError({ request, response }) {
      const url = typeof request === 'string' ? request : request.url
      // /api/user is the "am I logged in?" probe; its 401 is handled by useAuth.
      if (response.status === 401 && !url.endsWith('/api/user') && user.value) {
        user.value = null
        await nuxtApp.runWithContext(() => {
          const route = useRoute()
          return navigateTo({ path: '/login', query: { redirect: route.fullPath } })
        })
      }
    }
  })

  return { provide: { api } }
})
