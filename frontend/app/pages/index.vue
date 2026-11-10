<script setup lang="ts">
import type { DashboardData, Item } from '~/types/api'

useHead({ title: 'Dashboard' })

const api = useApi()
const { user } = useAuth()

const data = ref<DashboardData | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    data.value = (await api<Item<DashboardData>>('/api/dashboard')).data
  } catch (e) {
    error.value = toApiError(e).message
  } finally {
    loading.value = false
  }
}
onMounted(load)

const mine = computed(() => data.value?.scope === 'my_tasks')

const cards = computed(() => {
  const s = data.value?.stats
  if (!s) return []
  return [
    ...(s.total_clients !== null
      ? [{ label: 'Clients', value: s.total_clients, icon: 'i-lucide-building-2', to: '/clients' }]
      : []),
    {
      label: 'Active projects',
      value: s.active_projects,
      icon: 'i-lucide-folder-open',
      to: '/projects'
    },
    {
      label: 'Completed projects',
      value: s.completed_projects,
      icon: 'i-lucide-folder-check',
      to: '/projects'
    },
    {
      label: mine.value ? 'My pending tasks' : 'Pending tasks',
      value: s.pending_tasks,
      icon: 'i-lucide-list-todo',
      to: '/tasks'
    },
    {
      label: 'Overdue',
      value: s.overdue_tasks,
      icon: 'i-lucide-alarm-clock-off',
      to: '/tasks',
      alert: s.overdue_tasks > 0
    },
    { label: 'Due today', value: s.due_today_tasks, icon: 'i-lucide-calendar-clock', to: '/tasks' }
  ]
})
</script>

<template>
  <AppPage title="Dashboard">
    <template #actions>
      <UButton
        icon="i-lucide-refresh-cw"
        color="neutral"
        variant="ghost"
        aria-label="Refresh"
        :loading="loading"
        @click="load"
      />
    </template>

    <div class="space-y-6">
      <div>
        <h2 class="text-xl font-semibold text-highlighted">Hello, {{ user?.name }}</h2>
        <p class="text-sm text-muted">
          {{ mine ? 'Here is what is on your plate.' : "Here's how your projects are doing." }}
        </p>
      </div>

      <UAlert
        v-if="error"
        color="error"
        variant="subtle"
        :description="error"
        :actions="[{ label: 'Retry', color: 'neutral', variant: 'outline', onClick: load }]"
      />

      <!-- Statistic cards -->
      <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
        <template v-if="loading && !data">
          <USkeleton v-for="i in 6" :key="i" class="h-24" />
        </template>
        <NuxtLink v-for="card in cards" v-else :key="card.label" :to="card.to">
          <UCard class="h-full transition hover:ring-accented" :ui="{ body: 'p-4 sm:p-4' }">
            <div class="flex items-center justify-between">
              <p class="text-sm text-muted">{{ card.label }}</p>
              <UIcon
                :name="card.icon"
                class="size-5"
                :class="card.alert ? 'text-error' : 'text-dimmed'"
              />
            </div>
            <p
              class="mt-2 text-2xl font-semibold"
              :class="card.alert ? 'text-error' : 'text-highlighted'"
            >
              {{ card.value }}
            </p>
          </UCard>
        </NuxtLink>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <!-- Project progress -->
        <UCard>
          <template #header>
            <h3 class="font-medium text-highlighted">Project progress</h3>
          </template>
          <div v-if="loading && !data" class="space-y-3">
            <USkeleton v-for="i in 4" :key="i" class="h-10" />
          </div>
          <UEmpty
            v-else-if="!data?.project_progress.length"
            icon="i-lucide-folder-open"
            title="No open projects"
            variant="naked"
          />
          <ul v-else class="space-y-4">
            <li v-for="project in data.project_progress" :key="project.id">
              <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                <NuxtLink :to="`/projects/${project.id}`" class="font-medium hover:underline">
                  {{ project.name }}
                </NuxtLink>
                <span class="text-muted">{{ project.progress }}%</span>
              </div>
              <UProgress :model-value="project.progress" size="sm" />
              <p class="mt-1 text-xs text-muted">
                {{ project.client?.company_name }} · ends {{ formatDate(project.end_date) }}
              </p>
            </li>
          </ul>
        </UCard>

        <!-- Upcoming tasks -->
        <UCard>
          <template #header>
            <h3 class="font-medium text-highlighted">
              {{ mine ? 'My upcoming tasks' : 'Upcoming tasks' }}
            </h3>
          </template>
          <div v-if="loading && !data" class="space-y-3">
            <USkeleton v-for="i in 4" :key="i" class="h-10" />
          </div>
          <UEmpty
            v-else-if="!data?.upcoming_tasks.length"
            icon="i-lucide-party-popper"
            title="Nothing due"
            description="No open tasks with a due date."
            variant="naked"
          />
          <ul v-else class="divide-y divide-default">
            <li
              v-for="task in data.upcoming_tasks"
              :key="task.id"
              class="flex items-center justify-between gap-3 py-2"
            >
              <div class="min-w-0">
                <p class="truncate text-sm font-medium">{{ task.title }}</p>
                <p class="truncate text-xs text-muted">{{ task.project?.name }}</p>
              </div>
              <div class="flex shrink-0 items-center gap-2">
                <StatusBadge kind="priority" :value="task.priority" />
                <span
                  class="text-xs"
                  :class="task.is_overdue ? 'font-medium text-error' : 'text-muted'"
                >
                  {{ formatDate(task.due_date) }}
                </span>
              </div>
            </li>
          </ul>
        </UCard>
      </div>

      <!-- Recent activity: filled once activity logging lands (Milestone 6). -->
      <UCard>
        <template #header>
          <h3 class="font-medium text-highlighted">Recent activity</h3>
        </template>
        <UEmpty
          v-if="!data?.recent_activity.length"
          icon="i-lucide-activity"
          title="No activity yet"
          description="Task updates, comments and project changes will appear here."
          variant="naked"
        />
        <ul v-else class="space-y-2 text-sm">
          <li v-for="item in data.recent_activity" :key="item.id">
            <span class="font-medium">{{ item.user?.name ?? 'Someone' }}</span>
            {{ item.action }} · <span class="text-muted">{{ formatDate(item.created_at) }}</span>
          </li>
        </ul>
      </UCard>
    </div>
  </AppPage>
</template>
