// Shapes of the JSON returned by the Laravel API (see backend/app/Http/Resources).

export type RoleSlug = 'admin' | 'project_manager' | 'team_member'

export interface User {
  id: number
  name: string
  email: string
  is_active: boolean
  role?: { slug: RoleSlug; name: string }
  created_at: string | null
}

/** Laravel paginated resource collection. */
export interface Paginated<T> {
  data: T[]
  links: { first: string | null; last: string | null; prev: string | null; next: string | null }
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
  }
}

/** Single-resource wrapper: { data: {...} }. */
export interface Item<T> {
  data: T
}
