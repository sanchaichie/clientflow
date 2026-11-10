/**
 * Only allow redirects to paths inside this app. Without this check a link
 * like /login?redirect=https://evil.example could send users to another site
 * after they log in (an "open redirect").
 */
export function safeRedirect(target: unknown, fallback = '/'): string {
  if (typeof target !== 'string') return fallback
  if (!target.startsWith('/') || target.startsWith('//') || target.startsWith('/\\')) {
    return fallback
  }
  return target
}
