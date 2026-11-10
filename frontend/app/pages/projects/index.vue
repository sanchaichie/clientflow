<script setup lang="ts">
import type { DropdownMenuItem, TableColumn } from '@nuxt/ui'
import type { Project, ProjectStatus } from '~/types/api'
import ProjectFormModal from '~/components/forms/ProjectFormModal.vue'

useHead({ title: 'Projects' })

const api = useApi()
const toast = useToast()
const confirm = useConfirm()
const projectForm = useOverlay().create(ProjectFormModal)
const { canCreateProject, canEditProject, canRestore } = usePermissions()

const { rows, total, page, perPage, search, filters, sort, loading, error, refresh } =
  usePaginatedList<Project, { status: ProjectStatus | null; archived: boolean }>(
    '/api/projects',
    { status: null, archived: false },
    { sort: '-created_at' }
  )

const columns: TableColumn<Project>[] = [
  { accessorKey: 'name', header: 'Project' },
  { id: 'client', header: 'Client' },
  { accessorKey: 'status', header: 'Status' },
  { accessorKey: 'progress', header: 'Progress' },
  { id: 'manager', header: 'Manager' },
  { accessorKey: 'end_date', header: 'Ends' },
  { id: 'team', header: 'Team' },
  { id: 'actions', header: '' }
]

const statusFilter = [{ label: 'All statuses', value: null }, ...projectStatuses]
const sortOptions = [
  { label: 'Newest', value: '-created_at' },
  { label: 'Name', value: 'name' },
  { label: 'End date', value: 'end_date' },
  { label: 'Progress', value: '-progress' }
]

async function create() {
  const saved = await projectForm.open()
  if (saved) await navigateTo(`/projects/${saved.id}`)
}

async function edit(project: Project) {
  if (await projectForm.open({ project })) refresh()
}

async function archive(project: Project) {
  const ok = await confirm({
    title: `Archive ${project.name}?`,
    description: 'The project and its tasks become read-only and are hidden from lists.',
    confirmLabel: 'Archive',
    color: 'warning'
  })
  if (!ok) return
  try {
    await api(`/api/projects/${project.id}`, { method: 'DELETE' })
    toast.add({ title: 'Project archived', color: 'success', icon: 'i-lucide-archive' })
    refresh()
  } catch (e) {
    toast.add({
      title: 'Could not archive project',
      description: toApiError(e).message,
      color: 'error'
    })
  }
}

async function restore(project: Project) {
  try {
    await api(`/api/projects/${project.id}/restore`, { method: 'POST' })
    toast.add({ title: 'Project restored', color: 'success', icon: 'i-lucide-archive-restore' })
    refresh()
  } catch (e) {
    toast.add({
      title: 'Could not restore project',
      description: toApiError(e).message,
      color: 'error'
    })
  }
}

function actions(project: Project): DropdownMenuItem[] {
  if (project.archived_at) {
    return canRestore.value
      ? [{ label: 'Restore', icon: 'i-lucide-archive-restore', onSelect: () => restore(project) }]
      : []
  }
  if (!canEditProject(project)) return []
  return [
    { label: 'Edit', icon: 'i-lucide-pencil', onSelect: () => edit(project) },
    {
      label: 'Archive',
      icon: 'i-lucide-archive',
      color: 'warning',
      onSelect: () => archive(project)
    }
  ]
}
</script>

<template>
  <AppPage title="Projects">
    <template #actions>
      <UButton v-if="canCreateProject" icon="i-lucide-plus" label="New project" @click="create" />
    </template>

    <DataTable
      v-model:page="page"
      :data="rows"
      :columns="columns"
      :loading="loading"
      :error="error"
      :total="total"
      :per-page="perPage"
      empty-icon="i-lucide-folder-kanban"
      :empty-title="filters.archived ? 'No archived projects' : 'No projects found'"
      empty-description="Projects you manage or belong to appear here."
      @retry="refresh"
    >
      <template #toolbar>
        <UInput
          v-model="search"
          icon="i-lucide-search"
          placeholder="Search projects…"
          class="w-full sm:w-64"
        />
        <USelect v-model="filters.status" :items="statusFilter" class="w-40" />
        <USelect v-model="sort" :items="sortOptions" class="w-36" />
        <USwitch v-model="filters.archived" label="Show archived" />
      </template>

      <template #name-cell="{ row }">
        <NuxtLink
          :to="`/projects/${row.original.id}`"
          class="font-medium text-highlighted hover:underline"
        >
          {{ row.original.name }}
        </NuxtLink>
      </template>
      <template #client-cell="{ row }">
        {{ row.original.client?.company_name ?? '—' }}
      </template>
      <template #status-cell="{ row }">
        <StatusBadge kind="project" :value="row.original.status" />
      </template>
      <template #progress-cell="{ row }">
        <div class="flex w-32 items-center gap-2">
          <UProgress :model-value="row.original.progress" size="sm" class="flex-1" />
          <span class="w-9 text-right text-xs text-muted">{{ row.original.progress }}%</span>
        </div>
      </template>
      <template #manager-cell="{ row }">
        {{ row.original.manager?.name ?? '—' }}
      </template>
      <template #end_date-cell="{ row }">
        {{ formatDate(row.original.end_date) }}
      </template>
      <template #team-cell="{ row }">
        <span class="text-sm text-muted">
          {{ row.original.members_count ?? 0 }} members · {{ row.original.tasks_count ?? 0 }} tasks
        </span>
      </template>
      <template #actions-cell="{ row }">
        <div class="flex justify-end">
          <UDropdownMenu v-if="actions(row.original).length" :items="actions(row.original)">
            <UButton
              icon="i-lucide-ellipsis-vertical"
              color="neutral"
              variant="ghost"
              aria-label="Actions"
            />
          </UDropdownMenu>
        </div>
      </template>
    </DataTable>
  </AppPage>
</template>
