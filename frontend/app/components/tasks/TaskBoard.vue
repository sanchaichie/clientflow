<!--
  Task list with table/Kanban toggle, filters and create/edit/delete.
  Used on the Tasks page (all visible tasks) and on a project's page
  (pass `project` to scope everything to it).
-->
<script setup lang="ts">
import type { DropdownMenuItem, TableColumn } from '@nuxt/ui'
import type { Paginated, Project, Task, TaskPriority, TaskStatus } from '~/types/api'
import TaskFormModal from '~/components/forms/TaskFormModal.vue'

const props = defineProps<{ project?: Project }>()
const emit = defineEmits<{ changed: [] }>()

const api = useApi()
const toast = useToast()
const confirm = useConfirm()
const overlay = useOverlay()
const taskForm = overlay.create(TaskFormModal)
const { isTeamMember } = useAuth()
const { canEditTask, canMoveTask, canCreateTaskIn, canCreateProject } = usePermissions()

type TaskFilters = {
  project_id: number | null
  status: TaskStatus | null
  priority: TaskPriority | null
  assignee_id: 'me' | null
  due: 'overdue' | 'today' | null
}

const view = ref<'table' | 'kanban'>('table')
const singleProject = computed(() => !!props.project)

// --- Table data (paginated) --------------------------------------------------
const list = usePaginatedList<Task, TaskFilters>(
  '/api/tasks',
  {
    project_id: props.project?.id ?? null,
    status: null,
    priority: null,
    // Team members start with their own tasks; they can switch "Only mine" off.
    assignee_id: isTeamMember.value ? 'me' : null,
    due: null
  },
  { sort: '-created_at' }
)

// --- Kanban data (whole set, ordered by position) ----------------------------
const kanbanTasks = ref<Task[]>([])
const kanbanLoading = ref(false)
async function loadKanban() {
  kanbanLoading.value = true
  try {
    const { status: _ignored, ...filters } = list.filters // every column is shown
    const res = await api<Paginated<Task>>('/api/tasks', {
      query: {
        ...Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== null)),
        search: list.search.value || undefined,
        sort: 'position',
        per_page: 200
      }
    })
    kanbanTasks.value = res.data
  } catch (error) {
    toast.add({
      title: 'Could not load board',
      description: toApiError(error).message,
      color: 'error'
    })
  } finally {
    kanbanLoading.value = false
  }
}
watch(
  [view, () => ({ ...list.filters }), list.search],
  () => view.value === 'kanban' && loadKanban()
)

function reload() {
  if (view.value === 'kanban') loadKanban()
  else list.refresh()
  emit('changed')
}

// --- Actions ------------------------------------------------------------------
const canCreate = computed(() =>
  props.project ? canCreateTaskIn(props.project) : canCreateProject.value
)

async function openCreate() {
  const saved = await taskForm.open({ projectId: props.project?.id })
  if (saved) reload()
}

/** Clicking a task opens its detail page (comments + history). */
function openTask(task: Task) {
  return navigateTo(`/tasks/${task.id}`)
}

async function openEdit(task: Task) {
  if (!canEditTask(task)) return
  const saved = await taskForm.open({ task })
  if (saved) reload()
}

async function changeStatus(task: Task, status: TaskStatus) {
  try {
    await api(`/api/tasks/${task.id}/status`, { method: 'PATCH', body: { status } })
    toast.add({ title: 'Status updated', color: 'success', icon: 'i-lucide-check' })
    reload()
  } catch (error) {
    toast.add({
      title: 'Could not update status',
      description: toApiError(error).message,
      color: 'error'
    })
  }
}

async function remove(task: Task) {
  const ok = await confirm({
    title: `Delete "${task.title}"?`,
    description: 'The task and its comments will be permanently deleted.',
    confirmLabel: 'Delete'
  })
  if (!ok) return
  try {
    await api(`/api/tasks/${task.id}`, { method: 'DELETE' })
    toast.add({ title: 'Task deleted', color: 'success', icon: 'i-lucide-trash-2' })
    reload()
  } catch (error) {
    toast.add({
      title: 'Could not delete task',
      description: toApiError(error).message,
      color: 'error'
    })
  }
}

function rowActions(task: Task): DropdownMenuItem[][] {
  const groups: DropdownMenuItem[][] = []
  if (canMoveTask(task)) {
    groups.push(
      taskStatuses
        .filter((s) => s.value !== task.status)
        .map((s) => ({ label: `Move to ${s.label}`, onSelect: () => changeStatus(task, s.value) }))
    )
  }
  if (canEditTask(task)) {
    groups.push([
      { label: 'Edit', icon: 'i-lucide-pencil', onSelect: () => openEdit(task) },
      { label: 'Delete', icon: 'i-lucide-trash-2', color: 'error', onSelect: () => remove(task) }
    ])
  }
  return groups
}

// --- Table columns ---------------------------------------------------------------
const columns = computed<TableColumn<Task>[]>(() => [
  { accessorKey: 'title', header: 'Task' },
  ...(singleProject.value ? [] : [{ id: 'project', header: 'Project' }]),
  { accessorKey: 'status', header: 'Status' },
  { accessorKey: 'priority', header: 'Priority' },
  { id: 'assignee', header: 'Assignee' },
  { accessorKey: 'due_date', header: 'Due' },
  { id: 'actions', header: '' }
])

const filterItems = {
  status: [{ label: 'All statuses', value: null }, ...taskStatuses],
  priority: [{ label: 'All priorities', value: null }, ...taskPriorities],
  due: [
    { label: 'Any due date', value: null },
    { label: 'Overdue', value: 'overdue' },
    { label: 'Due today', value: 'today' }
  ]
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center gap-2">
      <UInput
        v-model="list.search.value"
        icon="i-lucide-search"
        placeholder="Search tasks…"
        class="w-full sm:w-64"
      />
      <USelect
        v-if="view === 'table'"
        v-model="list.filters.status"
        :items="filterItems.status"
        class="w-40"
      />
      <USelect v-model="list.filters.priority" :items="filterItems.priority" class="w-40" />
      <USelect v-model="list.filters.due" :items="filterItems.due" class="w-36" />
      <USwitch
        :model-value="list.filters.assignee_id === 'me'"
        label="Only mine"
        @update:model-value="(on: boolean) => (list.filters.assignee_id = on ? 'me' : null)"
      />

      <div class="ms-auto flex items-center gap-2">
        <UFieldGroup>
          <UButton
            icon="i-lucide-table"
            :color="view === 'table' ? 'primary' : 'neutral'"
            :variant="view === 'table' ? 'solid' : 'outline'"
            aria-label="Table view"
            @click="view = 'table'"
          />
          <UButton
            icon="i-lucide-kanban"
            :color="view === 'kanban' ? 'primary' : 'neutral'"
            :variant="view === 'kanban' ? 'solid' : 'outline'"
            aria-label="Kanban view"
            @click="view = 'kanban'"
          />
        </UFieldGroup>
        <UButton v-if="canCreate" icon="i-lucide-plus" label="New task" @click="openCreate" />
      </div>
    </div>

    <DataTable
      v-if="view === 'table'"
      v-model:page="list.page.value"
      :data="list.rows.value"
      :columns="columns"
      :loading="list.loading.value"
      :error="list.error.value"
      :total="list.total.value"
      :per-page="list.perPage.value"
      empty-icon="i-lucide-list-checks"
      empty-title="No tasks found"
      empty-description="Try changing the filters, or create a task."
      @retry="list.refresh"
    >
      <template #title-cell="{ row }">
        <button
          type="button"
          class="text-left font-medium text-highlighted hover:underline"
          @click="openTask(row.original)"
        >
          {{ row.original.title }}
        </button>
      </template>
      <template #project-cell="{ row }">
        <NuxtLink :to="`/projects/${row.original.project_id}`" class="text-sm hover:underline">
          {{ row.original.project?.name }}
        </NuxtLink>
      </template>
      <template #status-cell="{ row }">
        <StatusBadge kind="task" :value="row.original.status" />
      </template>
      <template #priority-cell="{ row }">
        <StatusBadge kind="priority" :value="row.original.priority" />
      </template>
      <template #assignee-cell="{ row }">
        <span v-if="row.original.assignee" class="flex items-center gap-2">
          <UAvatar :alt="row.original.assignee.name" size="2xs" />
          {{ row.original.assignee.name }}
        </span>
        <span v-else class="text-muted">Unassigned</span>
      </template>
      <template #due_date-cell="{ row }">
        <span :class="{ 'font-medium text-error': row.original.is_overdue }">
          {{ formatDate(row.original.due_date) }}
        </span>
      </template>
      <template #actions-cell="{ row }">
        <div class="flex justify-end">
          <UDropdownMenu v-if="rowActions(row.original).length" :items="rowActions(row.original)">
            <UButton
              icon="i-lucide-ellipsis-vertical"
              color="neutral"
              variant="ghost"
              aria-label="Task actions"
            />
          </UDropdownMenu>
        </div>
      </template>
    </DataTable>

    <div v-else>
      <div v-if="kanbanLoading && !kanbanTasks.length" class="grid gap-4 md:grid-cols-4">
        <USkeleton v-for="i in 4" :key="i" class="h-48" />
      </div>
      <TasksTaskKanban
        v-else
        :tasks="kanbanTasks"
        :single-project="singleProject"
        @open="openTask"
        @changed="reload"
      />
      <p v-if="!singleProject" class="mt-3 text-xs text-muted">
        Tip: open a project to reorder cards within a column; here you can change status only.
      </p>
    </div>
  </div>
</template>
