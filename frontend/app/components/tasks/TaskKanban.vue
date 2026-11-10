<!--
  Kanban board: one column per status. Dragging a card calls
  PATCH /api/tasks/{id}/status. Positions are only sent when the board shows a
  single project, because card order is stored per project.
-->
<script setup lang="ts">
import { VueDraggable, type DraggableEvent } from 'vue-draggable-plus'
import type { Item, Task, TaskStatus } from '~/types/api'

const props = defineProps<{ tasks: Task[]; singleProject?: boolean }>()
const emit = defineEmits<{ open: [task: Task]; changed: [] }>()

const api = useApi()
const toast = useToast()
const { canMoveTask } = usePermissions()

// Local, mutable copy of the columns; rebuilt whenever new data arrives.
const columns = ref<Record<TaskStatus, Task[]>>({
  todo: [],
  in_progress: [],
  review: [],
  completed: []
})
watch(
  () => props.tasks,
  (tasks) => {
    const next: Record<TaskStatus, Task[]> = {
      todo: [],
      in_progress: [],
      review: [],
      completed: []
    }
    for (const t of [...tasks].sort((a, b) => a.position - b.position)) next[t.status].push(t)
    columns.value = next
  },
  { immediate: true }
)

async function move(status: TaskStatus, event: DraggableEvent<Task>) {
  const task = event.data
  const body: Record<string, unknown> = { status }
  if (props.singleProject) body.position = event.newIndex
  try {
    const res = await api<Item<Task>>(`/api/tasks/${task.id}/status`, { method: 'PATCH', body })
    Object.assign(task, res.data) // keep the card in sync (status, completed_at, …)
    emit('changed')
  } catch (error) {
    toast.add({
      title: 'Could not move task',
      description: toApiError(error).message,
      color: 'error'
    })
    emit('changed') // reload to undo the optimistic move
  }
}
</script>

<template>
  <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <div
      v-for="column in taskStatuses"
      :key="column.value"
      class="flex flex-col rounded-lg bg-elevated/50 p-3"
    >
      <div class="mb-3 flex items-center justify-between">
        <StatusBadge kind="task" :value="column.value" />
        <span class="text-xs text-muted">{{ columns[column.value].length }}</span>
      </div>

      <VueDraggable
        v-model="columns[column.value]"
        group="tasks"
        :animation="150"
        filter="[data-locked]"
        :prevent-on-filter="false"
        class="flex min-h-24 flex-1 flex-col gap-2"
        @add="(e: DraggableEvent<Task>) => move(column.value, e)"
        @update="(e: DraggableEvent<Task>) => singleProject && move(column.value, e)"
      >
        <TasksTaskCard
          v-for="task in columns[column.value]"
          :key="task.id"
          :task="task"
          :show-project="!singleProject"
          :locked="!canMoveTask(task)"
          @open="emit('open', $event)"
        />
      </VueDraggable>

      <p
        v-if="columns[column.value].length === 0"
        class="pointer-events-none -mt-20 py-8 text-center text-xs text-dimmed"
      >
        Drop tasks here
      </p>
    </div>
  </div>
</template>
