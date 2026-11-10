<script setup lang="ts">
import type { Item, ReportData } from '~/types/api'

definePageMeta({ middleware: 'role', roles: ['admin', 'project_manager'] })
useHead({ title: 'Reports' })

const api = useApi()

// --- Date filter: one row above everything it scopes -------------------------
type Preset = 'all' | '30' | '90' | 'year' | 'custom'
const preset = ref<Preset>('90')
const customFrom = ref('')
const customTo = ref('')

const presetItems = [
  { label: 'Last 30 days', value: '30' },
  { label: 'Last 90 days', value: '90' },
  { label: 'This year', value: 'year' },
  { label: 'All time', value: 'all' },
  { label: 'Custom range', value: 'custom' }
]

const iso = (d: Date) => d.toLocaleDateString('en-CA') // YYYY-MM-DD in local time

const range = computed<{ from?: string; to?: string }>(() => {
  const today = new Date()
  switch (preset.value) {
    case '30':
    case '90': {
      const from = new Date(today)
      from.setDate(from.getDate() - Number(preset.value) + 1)
      return { from: iso(from), to: iso(today) }
    }
    case 'year':
      return { from: `${today.getFullYear()}-01-01`, to: iso(today) }
    case 'custom':
      return { from: customFrom.value || undefined, to: customTo.value || undefined }
    default:
      return {}
  }
})

// --- Data ------------------------------------------------------------------------
const data = ref<ReportData | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    data.value = (await api<Item<ReportData>>('/api/reports', { query: range.value })).data
  } catch (e) {
    error.value = toApiError(e).message
  } finally {
    loading.value = false
  }
}
onMounted(load)
watch(range, load)

const projectBars = computed(() =>
  projectStatuses.map((s) => ({
    key: s.value,
    label: s.label,
    value: data.value?.projects_by_status[s.value] ?? 0
  }))
)
const taskBars = computed(() =>
  taskStatuses.map((s) => ({
    key: s.value,
    label: s.label,
    value: data.value?.tasks_by_status[s.value] ?? 0
  }))
)
const rangeLabel = computed(() => {
  const r = data.value?.range
  if (!r?.from && !r?.to) return 'All time'
  return `${formatDate(r?.from)} – ${formatDate(r?.to)}`
})
</script>

<template>
  <AppPage title="Reports">
    <div class="space-y-6">
      <!-- Filter row -->
      <div class="flex flex-wrap items-end gap-3">
        <UFormField label="Period">
          <USelect v-model="preset" :items="presetItems" class="w-44" />
        </UFormField>
        <template v-if="preset === 'custom'">
          <UFormField label="From">
            <UInput v-model="customFrom" type="date" :max="customTo || undefined" />
          </UFormField>
          <UFormField label="To">
            <UInput v-model="customTo" type="date" :min="customFrom || undefined" />
          </UFormField>
        </template>
        <p class="ms-auto text-sm text-muted">
          Projects and tasks created in:
          <span class="font-medium text-toned">{{ rangeLabel }}</span>
        </p>
      </div>

      <UAlert
        v-if="error"
        color="error"
        variant="subtle"
        :description="error"
        :actions="[{ label: 'Retry', color: 'neutral', variant: 'outline', onClick: load }]"
      />

      <div v-if="!data && loading" class="grid gap-4 md:grid-cols-4">
        <USkeleton v-for="i in 4" :key="i" class="h-24" />
      </div>

      <!-- On refetch keep the previous numbers, dimmed, instead of flashing skeletons. -->
      <div
        v-if="data"
        class="space-y-6 transition-opacity"
        :class="{ 'pointer-events-none opacity-60': loading }"
      >
        <!-- Headline figures -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
          <UCard :ui="{ body: 'p-4 sm:p-4' }">
            <p class="text-sm text-muted">Tasks</p>
            <p class="mt-1 text-2xl font-semibold text-highlighted">{{ data.completion.total }}</p>
          </UCard>
          <UCard :ui="{ body: 'p-4 sm:p-4' }">
            <p class="text-sm text-muted">Completed</p>
            <p class="mt-1 text-2xl font-semibold text-highlighted">
              {{ data.completion.completed }}
            </p>
          </UCard>
          <UCard :ui="{ body: 'p-4 sm:p-4' }">
            <p class="text-sm text-muted">Pending</p>
            <p class="mt-1 text-2xl font-semibold text-highlighted">
              {{ data.completion.pending }}
            </p>
          </UCard>
          <UCard :ui="{ body: 'p-4 sm:p-4' }">
            <p class="flex items-center gap-1 text-sm text-muted">
              <UIcon
                name="i-lucide-alarm-clock-off"
                :class="data.overdue.count ? 'text-error' : ''"
              />
              Overdue
            </p>
            <p class="mt-1 text-2xl font-semibold text-highlighted">{{ data.overdue.count }}</p>
          </UCard>
        </div>

        <!-- Completed vs pending: one ratio → a meter -->
        <UCard>
          <div class="mb-2 flex items-baseline justify-between">
            <h3 class="font-medium text-highlighted">Completed vs pending</h3>
            <p class="text-sm text-muted">
              <span class="text-lg font-semibold text-highlighted"
                >{{ data.completion.rate }}%</span
              >
              complete
            </p>
          </div>
          <ChartsMeterBar
            :value="data.completion.rate"
            :label="`${data.completion.rate}% of tasks completed`"
          />
          <p class="mt-2 text-xs text-muted">
            {{ data.completion.completed }} completed · {{ data.completion.pending }} pending
          </p>
        </UCard>

        <!-- Part-to-whole comparisons → bars, one hue -->
        <div class="grid gap-6 lg:grid-cols-2">
          <UCard>
            <template #header
              ><h3 class="font-medium text-highlighted">Projects by status</h3></template
            >
            <ChartsBarList :items="projectBars" unit="projects" />
          </UCard>
          <UCard>
            <template #header
              ><h3 class="font-medium text-highlighted">Tasks by status</h3></template
            >
            <ChartsBarList :items="taskBars" unit="tasks" />
          </UCard>
        </div>

        <!-- One ratio per person → meter list with the figures beside it -->
        <UCard>
          <template #header>
            <h3 class="font-medium text-highlighted">Team completion</h3>
          </template>
          <UEmpty
            v-if="!data.members.length"
            icon="i-lucide-users"
            title="No assigned tasks in this period"
            variant="naked"
          />
          <ul v-else class="divide-y divide-default">
            <li
              v-for="m in data.members"
              :key="m.user_id"
              class="grid items-center gap-x-4 gap-y-1 py-3 sm:grid-cols-[12rem_1fr_auto]"
            >
              <span class="truncate text-sm font-medium">{{ m.name }}</span>
              <ChartsMeterBar
                :value="m.completion_rate"
                :label="`${m.name}: ${m.completion_rate}% completed`"
              />
              <span class="text-sm whitespace-nowrap text-muted tabular-nums">
                <span class="font-medium text-highlighted">{{ m.completed }}/{{ m.assigned }}</span>
                done · {{ m.completion_rate }}%
                <span v-if="m.overdue" class="ms-2 inline-flex items-center gap-0.5 text-error">
                  <UIcon name="i-lucide-alarm-clock-off" /> {{ m.overdue }} overdue
                </span>
              </span>
            </li>
          </ul>
        </UCard>

        <!-- Overdue list -->
        <UCard :ui="{ body: 'p-0 sm:p-0' }">
          <template #header>
            <div class="flex items-center justify-between">
              <h3 class="font-medium text-highlighted">Overdue tasks</h3>
              <span
                v-if="data.overdue.count > data.overdue.tasks.length"
                class="text-xs text-muted"
              >
                showing {{ data.overdue.tasks.length }} of {{ data.overdue.count }} (oldest first)
              </span>
            </div>
          </template>
          <UEmpty
            v-if="!data.overdue.tasks.length"
            icon="i-lucide-party-popper"
            title="Nothing overdue"
            variant="naked"
          />
          <ul v-else class="divide-y divide-default">
            <li
              v-for="t in data.overdue.tasks"
              :key="t.id"
              class="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
            >
              <div class="min-w-0">
                <NuxtLink :to="`/tasks/${t.id}`" class="font-medium hover:underline">{{
                  t.title
                }}</NuxtLink>
                <p class="text-xs text-muted">
                  {{ t.project?.name }} · {{ t.assignee?.name ?? 'Unassigned' }}
                </p>
              </div>
              <div class="flex items-center gap-2">
                <StatusBadge kind="priority" :value="t.priority" />
                <span class="text-sm font-medium text-error">due {{ formatDate(t.due_date) }}</span>
              </div>
            </li>
          </ul>
        </UCard>
      </div>
    </div>
  </AppPage>
</template>
