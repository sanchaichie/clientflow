<script setup lang="ts">
import type { AppNotification } from '~/types/api'

useHead({ title: 'Notifications' })

const { unread, list, markRead, markAllRead } = useNotifications()
const toast = useToast()

const items = ref<AppNotification[]>([])
const page = ref(1)
const total = ref(0)
const perPage = 15
const onlyUnread = ref(false)
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    const res = await list({ page: page.value, per_page: perPage, unread: onlyUnread.value })
    items.value = res.data
    total.value = res.meta.total
  } catch (e) {
    toast.add({
      title: 'Could not load notifications',
      description: toApiError(e).message,
      color: 'error'
    })
  } finally {
    loading.value = false
  }
}
onMounted(load)
watch(page, load)
watch(onlyUnread, () => (page.value === 1 ? load() : (page.value = 1)))

async function open(n: AppNotification) {
  await markRead(n).catch(() => {})
  await navigateTo(n.url)
}

async function readAll() {
  await markAllRead()
  await load()
}
</script>

<template>
  <AppPage title="Notifications">
    <template #actions>
      <UButton
        v-if="unread"
        icon="i-lucide-check-check"
        label="Mark all read"
        color="neutral"
        variant="outline"
        @click="readAll"
      />
    </template>

    <div class="space-y-4">
      <USwitch v-model="onlyUnread" label="Unread only" />

      <UCard :ui="{ body: 'p-0 sm:p-0' }">
        <div v-if="loading" class="space-y-2 p-4">
          <USkeleton v-for="i in 5" :key="i" class="h-12" />
        </div>
        <UEmpty
          v-else-if="!items.length"
          icon="i-lucide-bell-off"
          :title="onlyUnread ? 'No unread notifications' : 'No notifications yet'"
          description="You'll be notified about assignments, comments, status changes and deadlines."
          variant="naked"
        />
        <ul v-else class="divide-y divide-default">
          <li v-for="n in items" :key="n.id">
            <button
              type="button"
              class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-elevated"
              @click="open(n)"
            >
              <UIcon :name="notificationIcons[n.kind]" class="mt-0.5 size-5 shrink-0 text-muted" />
              <span class="min-w-0 flex-1">
                <span class="block text-sm" :class="n.read_at ? 'text-muted' : 'font-medium'">
                  {{ n.message }}
                </span>
                <span v-if="n.excerpt" class="block truncate text-sm text-muted"
                  >“{{ n.excerpt }}”</span
                >
                <span class="text-xs text-dimmed">{{ timeAgo(n.created_at) }}</span>
              </span>
              <span v-if="!n.read_at" class="mt-2 size-2 shrink-0 rounded-full bg-primary" />
            </button>
          </li>
        </ul>
      </UCard>

      <div v-if="total > perPage" class="flex justify-end">
        <UPagination v-model:page="page" :total="total" :items-per-page="perPage" />
      </div>
    </div>
  </AppPage>
</template>
