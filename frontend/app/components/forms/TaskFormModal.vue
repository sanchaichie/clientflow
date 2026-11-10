<script setup lang="ts">
import type { Form, FormError } from '@nuxt/ui'
import type { Item, Paginated, Project, Task, TaskPriority, TaskStatus, User } from '~/types/api'

/**
 * Create (needs a project: pass `projectId`, or choose one in the form) or
 * edit a task. Assignees are limited to the project's members and manager,
 * matching the API rule.
 */
const props = defineProps<{ task?: Task; projectId?: number }>()
const emit = defineEmits<{ close: [saved: Task | null] }>()

const api = useApi()
const toast = useToast()
const form = useTemplateRef<Form<typeof state>>('form')
const saving = ref(false)
const isEdit = computed(() => !!props.task)

const state = reactive({
  project_id: props.task?.project_id ?? props.projectId ?? undefined,
  title: props.task?.title ?? '',
  description: props.task?.description ?? '',
  status: (props.task?.status ?? 'todo') as TaskStatus,
  priority: (props.task?.priority ?? 'medium') as TaskPriority,
  due_date: props.task?.due_date ?? '',
  assignee_id: props.task?.assignee_id ?? null
})

// Projects to choose from when none was given (only ones the user manages
// will be accepted by the API).
const projectOptions = ref<{ label: string; value: number }[]>([])
const needsProjectPicker = computed(() => !isEdit.value && !props.projectId)
onMounted(async () => {
  if (!needsProjectPicker.value) return
  const res = await api<Paginated<Project>>('/api/projects', {
    query: { per_page: 100, sort: 'name' }
  })
  projectOptions.value = res.data.map((p) => ({ label: p.name, value: p.id }))
})

// Assignable people = project members + manager.
const assignable = ref<User[]>([])
watch(
  () => state.project_id,
  async (id) => {
    assignable.value = []
    if (!id) return
    try {
      const { data } = await api<Item<Project>>(`/api/projects/${id}`)
      const people = [...(data.members ?? []), ...(data.manager ? [data.manager] : [])]
      assignable.value = people.filter((u, i) => people.findIndex((p) => p.id === u.id) === i)
      if (state.assignee_id && !assignable.value.some((u) => u.id === state.assignee_id)) {
        state.assignee_id = null
      }
    } catch {
      assignable.value = []
    }
  },
  { immediate: true }
)

function validate(s: typeof state): FormError[] {
  const errors: FormError[] = []
  if (!s.project_id) errors.push({ name: 'project_id', message: 'Choose a project.' })
  if (!s.title.trim()) errors.push({ name: 'title', message: 'Title is required.' })
  return errors
}

async function onSubmit() {
  saving.value = true
  const body: Record<string, unknown> = {
    title: state.title,
    description: state.description || null,
    status: state.status,
    priority: state.priority,
    due_date: state.due_date || null,
    assignee_id: state.assignee_id
  }
  if (!isEdit.value) body.project_id = state.project_id

  try {
    const res = await api<Item<Task>>(
      isEdit.value ? `/api/tasks/${props.task!.id}` : '/api/tasks',
      {
        method: isEdit.value ? 'PUT' : 'POST',
        body
      }
    )
    toast.add({
      title: isEdit.value ? 'Task updated' : 'Task created',
      color: 'success',
      icon: 'i-lucide-check'
    })
    emit('close', res.data)
  } catch (error) {
    const apiError = toApiError(error)
    form.value?.setErrors(toFormErrors(apiError))
    toast.add({ title: 'Could not save task', description: apiError.message, color: 'error' })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <UModal
    :title="isEdit ? 'Edit task' : 'New task'"
    :ui="{ footer: 'justify-end' }"
    @update:open="(open: boolean) => !open && emit('close', null)"
  >
    <template #body>
      <UForm
        id="task-form"
        ref="form"
        :state="state"
        :validate="validate"
        class="grid gap-4 sm:grid-cols-2"
        @submit="onSubmit"
      >
        <UFormField
          v-if="needsProjectPicker"
          label="Project"
          name="project_id"
          required
          class="sm:col-span-2"
        >
          <USelectMenu
            v-model="state.project_id"
            :items="projectOptions"
            value-key="value"
            placeholder="Select project"
            class="w-full"
          />
        </UFormField>
        <UFormField label="Title" name="title" required class="sm:col-span-2">
          <UInput v-model="state.title" class="w-full" autofocus />
        </UFormField>
        <UFormField label="Status" name="status">
          <USelect v-model="state.status" :items="taskStatuses" class="w-full" />
        </UFormField>
        <UFormField label="Priority" name="priority">
          <USelect v-model="state.priority" :items="taskPriorities" class="w-full" />
        </UFormField>
        <UFormField label="Assignee" name="assignee_id">
          <UserSelect
            v-model="state.assignee_id"
            :users="assignable"
            placeholder="Unassigned"
            clearable
          />
        </UFormField>
        <UFormField label="Due date" name="due_date">
          <UInput v-model="state.due_date" type="date" class="w-full" />
        </UFormField>
        <UFormField label="Description" name="description" class="sm:col-span-2">
          <UTextarea v-model="state.description" :rows="4" class="w-full" />
        </UFormField>
      </UForm>
    </template>

    <template #footer>
      <UButton label="Cancel" color="neutral" variant="ghost" @click="emit('close', null)" />
      <UButton
        type="submit"
        form="task-form"
        :label="isEdit ? 'Save' : 'Create'"
        :loading="saving"
      />
    </template>
  </UModal>
</template>
