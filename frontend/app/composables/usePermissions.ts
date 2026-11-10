import type { Client, Project, Task } from '~/types/api'

/**
 * Mirrors the backend policies (app/Policies, docs/DECISIONS.md 010) so the UI
 * only shows actions the user can actually perform. This is a convenience:
 * the API re-checks every request and is the real security boundary.
 */
export function usePermissions() {
  const { user, isAdmin, isProjectManager } = useAuth()
  const me = computed(() => user.value?.id ?? null)

  const manages = (project: { manager_id: number } | undefined | null) =>
    !!project && project.manager_id === me.value

  return {
    // Clients
    canViewClients: computed(() => isAdmin.value || isProjectManager.value),
    canCreateClient: computed(() => isAdmin.value || isProjectManager.value),
    canEditClient: (c: Client) =>
      isAdmin.value ||
      (isProjectManager.value && c.account_manager_id === me.value && !c.archived_at),
    canRestore: computed(() => isAdmin.value),

    // Projects
    canCreateProject: computed(() => isAdmin.value || isProjectManager.value),
    canEditProject: (p: Project) => isAdmin.value || (manages(p) && !p.archived_at),

    // Tasks (task.project carries manager_id)
    canCreateTaskIn: (p: Project) => isAdmin.value || (manages(p) && !p.archived_at),
    canEditTask: (t: Task) => isAdmin.value || manages(t.project),
    canMoveTask: (t: Task) =>
      isAdmin.value || manages(t.project) || (t.assignee_id !== null && t.assignee_id === me.value),

    // Users
    canManageUsers: computed(() => isAdmin.value)
  }
}
