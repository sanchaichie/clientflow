import type { Item, RoleSlug, User } from '~/types/api'

/**
 * The logged-in user and auth actions.
 *
 * Role checks here only decide what the UI shows. Every rule is enforced
 * again by the Laravel API, which is the real security boundary.
 */
export function useAuth() {
  const api = useApi()
  const user = useState<User | null>('auth:user', () => null)
  /** True once we've asked the API "who am I?" at least once this page load. */
  const resolved = useState<boolean>('auth:resolved', () => false)

  const role = computed<RoleSlug | null>(() => user.value?.role?.slug ?? null)
  const isAdmin = computed(() => role.value === 'admin')
  const isProjectManager = computed(() => role.value === 'project_manager')
  const isTeamMember = computed(() => role.value === 'team_member')
  const hasRole = (...roles: RoleSlug[]) => role.value !== null && roles.includes(role.value)

  async function fetchUser(): Promise<User | null> {
    try {
      const res = await api<Item<User>>('/api/user')
      user.value = res.data
    } catch {
      user.value = null // 401 = not logged in (or deactivated)
    } finally {
      resolved.value = true
    }
    return user.value
  }

  async function login(credentials: { email: string; password: string; remember?: boolean }) {
    const res = await api<Item<User>>('/api/login', { method: 'POST', body: credentials })
    user.value = res.data
    resolved.value = true
  }

  async function logout() {
    try {
      await api('/api/logout', { method: 'POST' })
    } finally {
      user.value = null
      await navigateTo('/login')
    }
  }

  function forgotPassword(email: string) {
    return api<{ message: string }>('/api/forgot-password', { method: 'POST', body: { email } })
  }

  function resetPassword(payload: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }) {
    return api<{ message: string }>('/api/reset-password', { method: 'POST', body: payload })
  }

  return {
    user: readonly(user),
    resolved: readonly(resolved),
    role,
    isAdmin,
    isProjectManager,
    isTeamMember,
    hasRole,
    fetchUser,
    login,
    logout,
    forgotPassword,
    resetPassword
  }
}
