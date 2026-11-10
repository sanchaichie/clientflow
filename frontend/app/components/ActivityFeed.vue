<!--
  Activity history from GET /api/activity. Pass `projectId` or `taskId` to
  narrow it; without either it shows everything the user may see.
-->
<script setup lang="ts">
import type { ActivityLog, Paginated } from '~/types/api'

const props = withDefaults(
  defineProps<{ projectId?: number; taskId?: number; perPage?: number }>(),
  {
    projectId: undefined,
    taskId: undefined,
    perPage: 15
  }
)

const api = useApi()
const logs = ref<ActivityLog[]>([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(false)
const error = ref<string | null>(null)

async function load(reset = false) {
  if (reset) page.value = 1
  loading.value = true
  error.value = null
  try {
    const res = await api<Paginated<ActivityLog>>('/api/activity', {
      query: {
        project_id: props.projectId,
        task_id: props.taskId,
        per_page: props.perPage,
        page: page.value
      }
    })
    logs.value = reset ? res.data : [...logs.value, ...res.data]
    lastPage.value = res.meta.last_page
  } catch (e) {
    error.value = toApiError(e).message
  } finally {
    loading.value = false
  }
}

function more() {
  page.value++
  load()
}

onMounted(() => load(true))
defineExpose({ reload: () => load(true) })
</script>

<template>
  <div>
    <UAlert v-if="error" color="error" variant="subtle" :description="error" class="mb-3" />

    <div v-if="loading && !logs.length" class="space-y-3">
      <USkeleton v-for="i in 4" :key="i" class="h-8" />
    </div>

    <UEmpty
      v-else-if="!logs.length"
      icon="i-lucide-activity"
      title="No activity yet"
      description="Task updates, comments and project changes will appear here."
      variant="naked"
    />

    <ol v-else class="relative space-y-4 border-s border-default ps-5">
      <li v-for="log in logs" :key="log.id" class="relative">
        <span
          class="absolute -start-[1.85rem] flex size-6 items-center justify-center rounded-full bg-default ring-1 ring-default"
        >
          <UIcon :name="describeActivity(log).icon" class="size-3.5 text-muted" />
        </span>
        <p class="text-sm">
          <span class="font-medium">{{ log.user?.name ?? 'Someone' }}</span>
          {{ describeActivity(log).text }}
        </p>
        <p class="text-xs text-dimmed" :title="log.created_at ?? ''">
          {{ timeAgo(log.created_at) }}
        </p>
      </li>
    </ol>

    <div v-if="page < lastPage" class="mt-4 text-center">
      <UButton
        label="Load more"
        color="neutral"
        variant="outline"
        size="sm"
        :loading="loading"
        @click="more"
      />
    </div>
  </div>
</template>
