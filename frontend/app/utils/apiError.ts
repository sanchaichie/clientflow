import type { FormError } from '@nuxt/ui'

/** A normalized API error, whatever Laravel returned. */
export interface ApiError {
  status: number
  message: string
  /** Laravel validation errors: { field: ["message", ...] } (422 only). */
  errors: Record<string, string[]>
}

interface FetchErrorLike {
  statusCode?: number
  status?: number
  data?: { message?: string; errors?: Record<string, string[]> }
  message?: string
}

const fallbackMessages: Record<number, string> = {
  0: 'Cannot reach the server. Check your connection and try again.',
  401: 'Your session has expired. Please log in again.',
  403: 'You are not allowed to do that.',
  404: 'That item could not be found.',
  419: 'Your session expired. Please try again.',
  429: 'Too many attempts. Please wait a moment and try again.',
  500: 'Something went wrong on the server. Please try again.'
}

export function toApiError(error: unknown): ApiError {
  const e = (error ?? {}) as FetchErrorLike
  const status = e.statusCode ?? e.status ?? 0
  const errors = e.data?.errors ?? {}
  // For validation errors show the first field message rather than Laravel's
  // "(and 2 more errors)" summary.
  const firstFieldError = Object.values(errors)[0]?.[0]

  return {
    status,
    message:
      firstFieldError ??
      e.data?.message ??
      fallbackMessages[status] ??
      fallbackMessages[500] ??
      'Unexpected error.',
    errors
  }
}

/** Map Laravel 422 errors onto Nuxt UI form fields (UForm#setErrors). */
export function toFormErrors(error: ApiError): FormError[] {
  return Object.entries(error.errors).map(([name, messages]) => ({
    name,
    message: messages[0] ?? 'Invalid value.'
  }))
}
