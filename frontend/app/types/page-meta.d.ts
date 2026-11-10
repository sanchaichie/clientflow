import type { RoleSlug } from './api'

declare module '#app' {
  interface PageMeta {
    /** 'guest' = only for logged-out users (login, password reset). */
    auth?: 'guest'
    /** Roles allowed to open the page; used with `middleware: 'role'`. */
    roles?: RoleSlug[]
  }
}

export {}
