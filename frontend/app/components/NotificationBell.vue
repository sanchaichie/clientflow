<!-- Bell in the top bar: unread badge + popover with the latest notifications. -->
<script setup lang="ts">
import type { AppNotification } from '~/types/api'

const { unread, list, markRead, markAllRead } = useNotifications()
const toast = useToast()

const open = ref(false)
const items = ref<AppNotification[]>([])
const loading = ref(false)

// Load fresh items every time the popover opens.
watch(open, async (isOpen) => {
  if (!isOpen) return
  loading.value = true
  try {
    items.value = (await list({ per_page: 8 })).data
  } catch (e) {
    toast.add({
      title: 'Could not load notifications',
      description: toApiError(e).message,
      color: 'error'
    })
  } finally {
    loading.value = false
  }
})

async function openItem(n: AppNotification) {
  open.value = false
  await markRead(n).catch(() => {})
  await navigateTo(n.url)
}

async function readAll() {
  await markAllRead()
  items.value.forEach((n) => (n.read_at ??= new Date().toISOString()))
}
</script>

<template>
  <UPopover v-model:open="open" :content="{ align: 'end' }">
    <UChip :show="unread > 0" :text="unread > 9 ? '9+' : unread" size="3xl" color="error" inset>
      <UButton
        icon="i-lucide-bell"
        color="neutral"
        variant="ghost"
        :aria-label="unread ? `${unread} unread notifications` : 'Notifications'"
      />
    </UChip>

    <template #content>
      <div class="w-80 max-w-[90vw]">
        <div class="flex items-center justify-between border-b border-default px-4 py-2">
          <p class="text-sm font-medium">Notifications</p>
          <UButton v-if="unread" label="Mark all read" size="xs" variant="link" @click="readAll" />
        </div>

        <div v-if="loading" class="space-y-2 p-4">
          <USkeleton v-for="i in 3" :key="i" class="h-10" />
        </div>
        <UEmpty
          v-else-if="!items.length"
          icon="i-lucide-bell-off"
          title="You're all caught up"
          variant="naked"
          size="sm"
        />
        <ul v-else class="max-h-96 divide-y divide-default overflow-y-auto">
          <li v-for="n in items" :key="n.id">
            <button
              type="button"
              class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-elevated"
              @click="openItem(n)"
            >
              <UIcon :name="notificationIcons[n.kind]" class="mt-0.5 size-4 shrink-0 text-muted" />
              <span class="min-w-0 flex-1">
                <span class="block text-sm" :class="n.read_at ? 'text-muted' : 'font-medium'">
                  {{ n.message }}
                </span>
                <span class="text-xs text-dimmed">{{ timeAgo(n.created_at) }}</span>
              </span>
              <span v-if="!n.read_at" class="mt-1.5 size-2 shrink-0 rounded-full bg-primary" />
            </button>
          </li>
        </ul>

        <div class="border-t border-default px-4 py-2 text-center">
          <ULink to="/notifications" class="text-sm" @click="open = false">See all</ULink>
        </div>
      </div>
    </template>
  </UPopover>
</template>
