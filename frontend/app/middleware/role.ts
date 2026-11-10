import type { RoleSlug } from '~/types/api'

/**
 * Usage in a page:
 *   definePageMeta({ middleware: 'role', roles: ['admin'] })
 *
 * A convenience only: the API rejects the same users with 403 anyway.
 */
export default defineNuxtRouteMiddleware((to) => {
  const roles = to.meta.roles as RoleSlug[] | undefined
  if (!roles?.length) return

  const { hasRole } = useAuth()
  if (!hasRole(...roles)) {
    useToast().add({
      title: 'Access denied',
      description: 'Your role cannot open that page.',
      color: 'error',
      icon: 'i-lucide-shield-x'
    })
    return navigateTo('/')
  }
})
