import { describe, expect, it } from 'vitest'
import type { ActivityLog } from '~/types/api'

// describeActivity relies on auto-imported helpers (findOption), so it runs in
// the Nuxt environment.
const log = (action: string, properties: Record<string, unknown> = {}): ActivityLog => ({
  id: 1,
  action,
  properties,
  project_id: 1,
  subject_type: 'Task',
  subject_id: 1,
  created_at: null
})

describe('describeActivity', () => {
  it('describes status changes with human labels', () => {
    const { text, icon } = describeActivity(
      log('task.status_changed', { title: 'Homepage', from: 'in_progress', to: 'completed' })
    )
    expect(text).toBe('moved "Homepage" from In Progress to Completed')
    expect(icon).toBe('i-lucide-circle-check')
  })

  it('distinguishes work updates from comments', () => {
    expect(describeActivity(log('task.commented', { title: 'X', type: 'work_update' })).text).toBe(
      'posted a work update on "X"'
    )
    expect(describeActivity(log('task.commented', { title: 'X', type: 'comment' })).text).toBe(
      'commented on "X"'
    )
  })

  it('lists added members and handles unassignment', () => {
    expect(
      describeActivity(log('project.members_added', { name: 'Site', members: ['Ann', 'Bob'] })).text
    ).toBe('added Ann, Bob to "Site"')
    expect(describeActivity(log('task.assigned', { title: 'X', from: 'Ann', to: null })).text).toBe(
      'unassigned "X"'
    )
  })

  it('falls back to the raw action for unknown events', () => {
    expect(describeActivity(log('something.new')).text).toBe('something.new')
  })
})

describe('formatDate / findOption', () => {
  it('formats plain API dates without shifting the day across time zones', () => {
    expect(formatDate('2026-12-01')).toContain('2026')
    expect(formatDate('2026-12-01')).toMatch(/1/)
    expect(formatDate(null)).toBe('—')
  })

  it('finds labels and colours for enum values', () => {
    expect(findOption('task', 'in_progress')?.label).toBe('In Progress')
    expect(findOption('priority', 'urgent')?.color).toBe('error')
    expect(findOption('project', 'nope')).toBeUndefined()
  })
})
