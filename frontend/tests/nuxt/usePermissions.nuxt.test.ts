import { beforeEach, describe, expect, it } from 'vitest'
import type { Client, Project, RoleSlug, Task, User } from '~/types/api'

/**
 * The UI permission helper must mirror the backend policies
 * (docs/DECISIONS.md 010). These tests pin the same rules from the frontend side.
 */
function loginAs(id: number, role: RoleSlug) {
  useState<User | null>('auth:user').value = {
    id,
    name: `User ${id}`,
    email: `u${id}@x.test`,
    is_active: true,
    role: { slug: role, name: role },
    created_at: null
  }
}

const project = (overrides: Partial<Project> = {}) =>
  ({ id: 1, manager_id: 10, archived_at: null, ...overrides }) as Project
const task = (overrides: Partial<Task> = {}) =>
  ({ id: 1, assignee_id: 20, project: { id: 1, name: 'P', manager_id: 10 }, ...overrides }) as Task
const client = (overrides: Partial<Client> = {}) =>
  ({ id: 1, account_manager_id: 10, archived_at: null, ...overrides }) as Client

describe('usePermissions', () => {
  beforeEach(() => {
    useState('auth:user').value = null
  })

  it('admins can do everything, including restoring', () => {
    loginAs(1, 'admin')
    const p = usePermissions()
    expect(p.canEditProject(project({ archived_at: '2026-01-01' }))).toBe(true)
    expect(p.canEditTask(task())).toBe(true)
    expect(p.canRestore.value).toBe(true)
    expect(p.canManageUsers.value).toBe(true)
  })

  it('a PM manages only their own, unarchived projects and clients', () => {
    loginAs(10, 'project_manager')
    const p = usePermissions()
    expect(p.canEditProject(project())).toBe(true)
    expect(p.canEditProject(project({ manager_id: 99 }))).toBe(false)
    expect(p.canEditProject(project({ archived_at: '2026-01-01' }))).toBe(false)
    expect(p.canEditClient(client())).toBe(true)
    expect(p.canEditClient(client({ account_manager_id: 99 }))).toBe(false)
    expect(p.canRestore.value).toBe(false)
    expect(p.canManageUsers.value).toBe(false)
  })

  it('a team member moves only tasks assigned to them and edits nothing', () => {
    loginAs(20, 'team_member')
    const p = usePermissions()
    expect(p.canMoveTask(task())).toBe(true)
    expect(p.canMoveTask(task({ assignee_id: 21 }))).toBe(false)
    expect(p.canMoveTask(task({ assignee_id: null }))).toBe(false)
    expect(p.canEditTask(task())).toBe(false)
    expect(p.canViewClients.value).toBe(false)
    expect(p.canCreateProject.value).toBe(false)
  })
})
