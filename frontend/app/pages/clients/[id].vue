<script setup lang="ts">
import type { Client, Item } from '~/types/api'
import ClientFormModal from '~/components/forms/ClientFormModal.vue'
import ProjectFormModal from '~/components/forms/ProjectFormModal.vue'

definePageMeta({ middleware: 'role', roles: ['admin', 'project_manager'] })

const route = useRoute()
const api = useApi()
const overlay = useOverlay()
const clientForm = overlay.create(ClientFormModal)
const projectForm = overlay.create(ProjectFormModal)
const { canEditClient, canCreateProject } = usePermissions()

const client = ref<Client | null>(null)
const loading = ref(true)
const error = ref<{ status: number; message: string } | null>(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    client.value = (await api<Item<Client>>(`/api/clients/${route.params.id}`)).data
  } catch (e) {
    error.value = toApiError(e)
  } finally {
    loading.value = false
  }
}
onMounted(load)
useHead(() => ({ title: client.value?.company_name ?? 'Client' }))

async function edit() {
  if (client.value && (await clientForm.open({ client: client.value }))) load()
}

async function newProject() {
  const saved = await projectForm.open({ clientId: client.value?.id })
  if (saved) await navigateTo(`/projects/${saved.id}`)
}

const details = computed(() => {
  const c = client.value
  if (!c) return []
  return [
    { label: 'Contact person', value: c.contact_person },
    { label: 'Email', value: c.email },
    { label: 'Phone', value: c.phone },
    { label: 'Address', value: c.address },
    { label: 'Account manager', value: c.account_manager?.name },
    { label: 'Client since', value: formatDate(c.created_at) }
  ]
})
</script>

<template>
  <AppPage :title="client?.company_name ?? 'Client'">
    <template #actions>
      <UButton
        v-if="client && canEditClient(client)"
        icon="i-lucide-pencil"
        label="Edit"
        color="neutral"
        variant="outline"
        @click="edit"
      />
    </template>

    <div v-if="loading" class="space-y-4">
      <USkeleton class="h-32" />
      <USkeleton class="h-48" />
    </div>

    <UEmpty
      v-else-if="error"
      :icon="error.status === 403 ? 'i-lucide-shield-x' : 'i-lucide-search-x'"
      :title="error.status === 403 ? 'You cannot view this client' : 'Client not found'"
      :description="error.message"
      :actions="[{ label: 'Back to clients', to: '/clients' }]"
    />

    <div v-else-if="client" class="space-y-6">
      <UAlert
        v-if="client.archived_at"
        color="warning"
        variant="subtle"
        icon="i-lucide-archive"
        title="This client is archived"
        description="It is read-only. An administrator can restore it from the client list."
      />

      <UCard>
        <div class="mb-4 flex items-center gap-3">
          <StatusBadge kind="client" :value="client.status" />
        </div>
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div v-for="item in details" :key="item.label">
            <dt class="text-xs text-muted">{{ item.label }}</dt>
            <dd class="text-sm">{{ item.value || '—' }}</dd>
          </div>
        </dl>
        <p v-if="client.description" class="mt-4 text-sm whitespace-pre-line text-toned">
          {{ client.description }}
        </p>
      </UCard>

      <UCard>
        <template #header>
          <div class="flex items-center justify-between">
            <h3 class="font-medium text-highlighted">Projects</h3>
            <UButton
              v-if="canCreateProject && !client.archived_at"
              icon="i-lucide-plus"
              label="New project"
              size="sm"
              @click="newProject"
            />
          </div>
        </template>
        <UEmpty
          v-if="!client.projects?.length"
          icon="i-lucide-folder"
          title="No projects you can see"
          variant="naked"
        />
        <ul v-else class="divide-y divide-default">
          <li
            v-for="p in client.projects"
            :key="p.id"
            class="flex items-center justify-between gap-4 py-3"
          >
            <div class="min-w-0">
              <NuxtLink :to="`/projects/${p.id}`" class="font-medium hover:underline">{{
                p.name
              }}</NuxtLink>
              <p class="text-xs text-muted">Managed by {{ p.manager?.name ?? '—' }}</p>
            </div>
            <div class="flex w-48 shrink-0 items-center gap-3">
              <UProgress :model-value="p.progress" size="sm" class="flex-1" />
              <StatusBadge kind="project" :value="p.status" />
            </div>
          </li>
        </ul>
      </UCard>
    </div>
  </AppPage>
</template>
