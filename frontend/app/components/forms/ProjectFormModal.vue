<script setup lang="ts">
import type { Form, FormError } from '@nuxt/ui'
import type { Client, Item, Paginated, Project, ProjectStatus } from '~/types/api'

const props = defineProps<{ project?: Project; clientId?: number }>()
const emit = defineEmits<{ close: [saved: Project | null] }>()

const api = useApi()
const toast = useToast()
const { isAdmin } = useAuth()
const form = useTemplateRef<Form<typeof state>>('form')
const saving = ref(false)
const isEdit = computed(() => !!props.project)

const state = reactive({
  client_id: props.project?.client_id ?? props.clientId ?? undefined,
  name: props.project?.name ?? '',
  description: props.project?.description ?? '',
  start_date: props.project?.start_date ?? '',
  end_date: props.project?.end_date ?? '',
  status: (props.project?.status ?? 'planning') as ProjectStatus,
  manager_id: props.project?.manager_id ?? null,
  member_ids: [] as number[]
})

// Clients the user may attach projects to (the API filters by visibility).
const clients = ref<{ label: string; value: number }[]>([])
const loadingClients = ref(true)
onMounted(async () => {
  try {
    const res = await api<Paginated<Client>>('/api/clients', {
      query: { per_page: 100, sort: 'company_name' }
    })
    clients.value = res.data.map((c) => ({ label: c.company_name, value: c.id }))
  } catch {
    // Team members cannot list clients; the field is then read-only anyway.
  } finally {
    loadingClients.value = false
  }
})

function validate(s: typeof state): FormError[] {
  const errors: FormError[] = []
  if (!s.client_id) errors.push({ name: 'client_id', message: 'Choose a client.' })
  if (!s.name.trim()) errors.push({ name: 'name', message: 'Project name is required.' })
  if (isAdmin.value && !s.manager_id)
    errors.push({ name: 'manager_id', message: 'Choose a project manager.' })
  if (s.start_date && s.end_date && s.end_date < s.start_date)
    errors.push({ name: 'end_date', message: 'End date must be after the start date.' })
  return errors
}

async function onSubmit() {
  saving.value = true
  const body: Record<string, unknown> = {
    client_id: state.client_id,
    name: state.name,
    description: state.description || null,
    start_date: state.start_date || null,
    end_date: state.end_date || null,
    status: state.status
  }
  if (isAdmin.value) body.manager_id = state.manager_id
  if (!isEdit.value && state.member_ids.length) body.member_ids = state.member_ids

  try {
    const res = await api<Item<Project>>(
      isEdit.value ? `/api/projects/${props.project!.id}` : '/api/projects',
      { method: isEdit.value ? 'PUT' : 'POST', body }
    )
    toast.add({
      title: isEdit.value ? 'Project updated' : 'Project created',
      color: 'success',
      icon: 'i-lucide-check'
    })
    emit('close', res.data)
  } catch (error) {
    const apiError = toApiError(error)
    form.value?.setErrors(toFormErrors(apiError))
    toast.add({ title: 'Could not save project', description: apiError.message, color: 'error' })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <UModal
    :title="isEdit ? 'Edit project' : 'New project'"
    :ui="{ footer: 'justify-end' }"
    @update:open="(open: boolean) => !open && emit('close', null)"
  >
    <template #body>
      <UForm
        id="project-form"
        ref="form"
        :state="state"
        :validate="validate"
        class="grid gap-4 sm:grid-cols-2"
        @submit="onSubmit"
      >
        <UFormField label="Project name" name="name" required class="sm:col-span-2">
          <UInput v-model="state.name" class="w-full" autofocus />
        </UFormField>
        <UFormField label="Client" name="client_id" required>
          <USelectMenu
            v-model="state.client_id"
            :items="clients"
            value-key="value"
            :loading="loadingClients"
            placeholder="Select client"
            class="w-full"
          />
        </UFormField>
        <UFormField label="Status" name="status" required>
          <USelect v-model="state.status" :items="projectStatuses" class="w-full" />
        </UFormField>
        <UFormField label="Start date" name="start_date">
          <UInput v-model="state.start_date" type="date" class="w-full" />
        </UFormField>
        <UFormField label="End date" name="end_date">
          <UInput v-model="state.end_date" type="date" class="w-full" />
        </UFormField>
        <UFormField
          v-if="isAdmin"
          label="Project manager"
          name="manager_id"
          required
          class="sm:col-span-2"
        >
          <UserSelect
            v-model="state.manager_id"
            :roles="['project_manager']"
            placeholder="Select manager"
          />
        </UFormField>
        <UFormField v-if="!isEdit" label="Team members" name="member_ids" class="sm:col-span-2">
          <UserSelect
            v-model="state.member_ids"
            :roles="['team_member', 'project_manager']"
            multiple
            placeholder="Add members (optional)"
          />
        </UFormField>
        <UFormField label="Description" name="description" class="sm:col-span-2">
          <UTextarea v-model="state.description" :rows="3" class="w-full" />
        </UFormField>
      </UForm>
    </template>

    <template #footer>
      <UButton label="Cancel" color="neutral" variant="ghost" @click="emit('close', null)" />
      <UButton
        type="submit"
        form="project-form"
        :label="isEdit ? 'Save' : 'Create'"
        :loading="saving"
      />
    </template>
  </UModal>
</template>
