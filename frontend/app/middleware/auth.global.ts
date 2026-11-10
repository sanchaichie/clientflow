/**
 * Runs before every page.
 * - Pages with `definePageMeta({ auth: 'guest' })` (login, password reset) are
 *   only for logged-out users.
 * - Every other page requires a logged-in user.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  const { user, resolved, fetchUser } = useAuth()

  // Ask the API once per page load; afterwards the answer is kept in state.
  if (!resolved.value) {
    await fetchUser()
  }

  const guestOnly = to.meta.auth === 'guest'

  if (guestOnly && user.value) {
    return navigateTo('/')
  }

  if (!guestOnly && !user.value) {
    return navigateTo({
      path: '/login',
      query: to.fullPath !== '/' ? { redirect: to.fullPath } : {}
    })
  }
})
