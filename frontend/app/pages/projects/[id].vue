<script setup lang="ts">
import type { TabsItem } from '@nuxt/ui'
import type { Item, Project } from '~/types/api'
import ProjectFormModal from '~/components/forms/ProjectFormModal.vue'

const route = useRoute()
const api = useApi()
const projectForm = useOverlay().create(ProjectFormModal)
const { canEditProject } = usePermissions()

const project = ref<Project | null>(null)
const loading = ref(true)
const error = ref<{ status: number; message: string } | null>(null)

async function load() {
  error.value = null
  try {
    project.value = (await api<Item<Project>>(`/api/projects/${route.params.id}`)).data
  } catch (e) {
    error.value = toApiError(e)
  } finally {
    loading.value = false
  }
}
onMounted(load)
useHead(() => ({ title: project.value?.name ?? 'Project' }))

async function edit() {
  if (project.value && (await projectForm.open({ project: project.value }))) load()
}

const tabs: TabsItem[] = [
  { label: 'Tasks', icon: 'i-lucide-list-checks', slot: 'tasks' as const, value: 'tasks' },
  { label: 'Members', icon: 'i-lucide-users', slot: 'members' as const, value: 'members' },
  { label: 'Overview', icon: 'i-lucide-info', slot: 'overview' as const, value: 'overview' }
]
</script>

<template>
  <AppPage :title="project?.name ?? 'Project'">
    <template #actions>
      <UButton
        v-if="project && canEditProject(project)"
        icon="i-lucide-pencil"
        label="Edit"
        color="neutral"
        variant="outline"
        @click="edit"
      />
    </template>

    <div v-if="loading" class="space-y-4">
      <USkeleton class="h-24" />
      <USkeleton class="h-64" />
    </div>

    <UEmpty
      v-else-if="error"
      :icon="error.status === 403 ? 'i-lucide-shield-x' : 'i-lucide-search-x'"
      :title="error.status === 403 ? 'You are not on this project' : 'Project not found'"
      :description="error.message"
      :actions="[{ label: 'Back to projects', to: '/projects' }]"
    />

    <div v-else-if="project" class="space-y-6">
      <UAlert
        v-if="project.archived_at"
        color="warning"
        variant="subtle"
        icon="i-lucide-archive"
        title="This project is archived"
        description="It and its tasks are read-only."
      />

      <!-- Summary header -->
      <UCard>
        <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
          <div>
            <p class="text-xs text-muted">Client</p>
            <NuxtLink
              v-if="project.client"
              :to="`/clients/${project.client.id}`"
              class="text-sm font-medium hover:underline"
            >
              {{ project.client.company_name }}
            </NuxtLink>
          </div>
          <div>
            <p class="text-xs text-muted">Status</p>
            <StatusBadge kind="project" :value="project.status" />
          </div>
          <div>
            <p class="text-xs text-muted">Manager</p>
            <p class="text-sm">{{ project.manager?.name }}</p>
          </div>
          <div>
            <p class="text-xs text-muted">Dates</p>
            <p class="text-sm">
              {{ formatDate(project.start_date) }} → {{ formatDate(project.end_date) }}
            </p>
          </div>
          <div class="min-w-48 flex-1">
            <p class="mb-1 text-xs text-muted">Progress · {{ project.progress }}%</p>
            <UProgress :model-value="project.progress" size="sm" />
          </div>
        </div>
      </UCard>

      <UTabs :items="tabs" default-value="tasks" variant="link" :unmount-on-hide="false">
        <template #tasks>
          <div class="pt-4">
            <!-- Reload the header (progress) whenever tasks change. -->
            <TasksTaskBoard :project="project" @changed="load" />
          </div>
        </template>

        <template #members>
          <div class="pt-4">
            <ProjectMembers :project="project" @changed="load" />
          </div>
        </template>

        <template #overview>
          <UCard class="mt-4">
            <p v-if="project.description" class="text-sm whitespace-pre-line">
              {{ project.description }}
            </p>
            <p v-else class="text-sm text-muted">No description.</p>
          </UCard>
        </template>
      </UTabs>
    </div>
  </AppPage>
</template>
