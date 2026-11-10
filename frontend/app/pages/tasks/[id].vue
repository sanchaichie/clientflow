<script setup lang="ts">
import type { Item, Task, TaskStatus } from '~/types/api'
import TaskFormModal from '~/components/forms/TaskFormModal.vue'

const route = useRoute()
const api = useApi()
const toast = useToast()
const confirm = useConfirm()
const taskForm = useOverlay().create(TaskFormModal)
const { canEditTask, canMoveTask } = usePermissions()

const task = ref<Task | null>(null)
const loading = ref(true)
const error = ref<{ status: number; message: string } | null>(null)
const history = useTemplateRef<{ reload: () => void }>('history')

async function load() {
  error.value = null
  try {
    task.value = (await api<Item<Task>>(`/api/tasks/${route.params.id}`)).data
  } catch (e) {
    error.value = toApiError(e)
  } finally {
    loading.value = false
  }
}
onMounted(load)
useHead(() => ({ title: task.value?.title ?? 'Task' }))

const statusItems = taskStatuses.map((s) => ({ label: s.label, value: s.value }))

async function changeStatus(status: TaskStatus) {
  if (!task.value || status === task.value.status) return
  try {
    task.value = (
      await api<Item<Task>>(`/api/tasks/${task.value.id}/status`, {
        method: 'PATCH',
        body: { status }
      })
    ).data
    toast.add({ title: 'Status updated', color: 'success', icon: 'i-lucide-check' })
    history.value?.reload()
  } catch (e) {
    toast.add({
      title: 'Could not update status',
      description: toApiError(e).message,
      color: 'error'
    })
  }
}

async function edit() {
  if (!task.value) return
  if (await taskForm.open({ task: task.value })) {
    await load()
    history.value?.reload()
  }
}

async function remove() {
  if (!task.value) return
  const ok = await confirm({
    title: `Delete "${task.value.title}"?`,
    description: 'The task and its comments will be permanently deleted.',
    confirmLabel: 'Delete'
  })
  if (!ok) return
  try {
    const projectId = task.value.project_id
    await api(`/api/tasks/${task.value.id}`, { method: 'DELETE' })
    toast.add({ title: 'Task deleted', color: 'success', icon: 'i-lucide-trash-2' })
    await navigateTo(`/projects/${projectId}`)
  } catch (e) {
    toast.add({
      title: 'Could not delete task',
      description: toApiError(e).message,
      color: 'error'
    })
  }
}
</script>

<template>
  <AppPage :title="task?.title ?? 'Task'">
    <template #actions>
      <template v-if="task && canEditTask(task)">
        <UButton
          icon="i-lucide-pencil"
          label="Edit"
          color="neutral"
          variant="outline"
          @click="edit"
        />
        <UButton
          icon="i-lucide-trash-2"
          color="error"
          variant="ghost"
          aria-label="Delete task"
          @click="remove"
        />
      </template>
    </template>

    <div v-if="loading" class="grid gap-6 lg:grid-cols-3">
      <USkeleton class="h-64 lg:col-span-2" />
      <USkeleton class="h-64" />
    </div>

    <UEmpty
      v-else-if="error"
      :icon="error.status === 403 ? 'i-lucide-shield-x' : 'i-lucide-search-x'"
      :title="error.status === 403 ? 'You cannot view this task' : 'Task not found'"
      :description="error.message"
      :actions="[{ label: 'Back to tasks', to: '/tasks' }]"
    />

    <div v-else-if="task" class="grid gap-6 lg:grid-cols-3">
      <!-- Left: description + conversation -->
      <div class="space-y-6 lg:col-span-2">
        <UCard>
          <template #header>
            <h3 class="font-medium text-highlighted">Description</h3>
          </template>
          <p v-if="task.description" class="text-sm whitespace-pre-line">{{ task.description }}</p>
          <p v-else class="text-sm text-muted">No description.</p>
        </UCard>

        <UCard>
          <template #header>
            <h3 class="font-medium text-highlighted">Comments & work updates</h3>
          </template>
          <TasksTaskComments :task="task" @posted="history?.reload()" />
        </UCard>
      </div>

      <!-- Right: details + history -->
      <div class="space-y-6">
        <UCard>
          <dl class="space-y-4 text-sm">
            <div>
              <dt class="mb-1 text-xs text-muted">Status</dt>
              <dd>
                <USelect
                  v-if="canMoveTask(task)"
                  :model-value="task.status"
                  :items="statusItems"
                  class="w-full"
                  @update:model-value="changeStatus"
                />
                <StatusBadge v-else kind="task" :value="task.status" />
              </dd>
            </div>
            <div>
              <dt class="text-xs text-muted">Priority</dt>
              <dd><StatusBadge kind="priority" :value="task.priority" /></dd>
            </div>
            <div>
              <dt class="text-xs text-muted">Project</dt>
              <dd>
                <NuxtLink :to="`/projects/${task.project_id}`" class="hover:underline">
                  {{ task.project?.name }}
                </NuxtLink>
              </dd>
            </div>
            <div>
              <dt class="text-xs text-muted">Assignee</dt>
              <dd>{{ task.assignee?.name ?? 'Unassigned' }}</dd>
            </div>
            <div>
              <dt class="text-xs text-muted">Due date</dt>
              <dd :class="{ 'font-medium text-error': task.is_overdue }">
                {{ formatDate(task.due_date) }}<span v-if="task.is_overdue"> · overdue</span>
              </dd>
            </div>
            <div>
              <dt class="text-xs text-muted">Created by</dt>
              <dd>{{ task.creator?.name ?? '—' }} · {{ formatDate(task.created_at) }}</dd>
            </div>
          </dl>
        </UCard>

        <UCard>
          <template #header>
            <h3 class="font-medium text-highlighted">History</h3>
          </template>
          <ActivityFeed ref="history" :task-id="task.id" :per-page="10" />
        </UCard>
      </div>
    </div>
  </AppPage>
</template>
