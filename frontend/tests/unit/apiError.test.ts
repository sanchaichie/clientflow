import { describe, expect, it } from 'vitest'
import { toApiError, toFormErrors } from '../../app/utils/apiError'

describe('toApiError', () => {
  it('uses the first field message for Laravel validation errors (422)', () => {
    const error = toApiError({
      statusCode: 422,
      data: {
        message: 'The email field is required. (and 1 more error)',
        errors: { email: ['The email field is required.'], name: ['Too long.'] }
      }
    })

    expect(error.status).toBe(422)
    expect(error.message).toBe('The email field is required.')
    expect(error.errors.name).toEqual(['Too long.'])
  })

  it("prefers the server's message when there are no field errors", () => {
    expect(
      toApiError({ statusCode: 403, data: { message: 'Your account has been deactivated.' } })
        .message
    ).toBe('Your account has been deactivated.')
  })

  it('falls back to a friendly message per status code', () => {
    expect(toApiError({ statusCode: 429 }).message).toMatch(/Too many attempts/)
    expect(toApiError({ statusCode: 404 }).message).toMatch(/could not be found/)
  })

  it('treats a missing status (network failure) as "cannot reach the server"', () => {
    const error = toApiError(new TypeError('Failed to fetch'))
    expect(error.status).toBe(0)
    expect(error.message).toMatch(/Cannot reach the server/)
  })

  it('never throws on unexpected input', () => {
    expect(() => toApiError(undefined)).not.toThrow()
    expect(toApiError(null).status).toBe(0)
  })
})

describe('toFormErrors', () => {
  it('maps each field to its first message for UForm#setErrors', () => {
    const formErrors = toFormErrors({
      status: 422,
      message: '',
      errors: { email: ['Taken.', 'Also bad.'], due_date: ['Invalid date.'] }
    })

    expect(formErrors).toEqual([
      { name: 'email', message: 'Taken.' },
      { name: 'due_date', message: 'Invalid date.' }
    ])
  })
})
