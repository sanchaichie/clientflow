import { describe, expect, it } from 'vitest'
import { safeRedirect } from '../../app/utils/safeRedirect'

describe('safeRedirect (open-redirect protection)', () => {
  it('allows paths inside the app', () => {
    expect(safeRedirect('/projects/5')).toBe('/projects/5')
    expect(safeRedirect('/tasks?assignee_id=me')).toBe('/tasks?assignee_id=me')
  })

  it.each([
    ['https://evil.example'],
    ['//evil.example/path'],
    ['/\\evil.example'],
    ['javascript:alert(1)'],
    ['projects']
  ])('rejects %s', (target) => {
    expect(safeRedirect(target)).toBe('/')
  })

  it('falls back when the value is missing or not a string', () => {
    expect(safeRedirect(undefined)).toBe('/')
    expect(safeRedirect(['/a', '/b'])).toBe('/')
    expect(safeRedirect(null, '/login')).toBe('/login')
  })
})
