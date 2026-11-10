<!-- Comment thread of a task: list (author + time), post comment / work update, delete own. -->
<script setup lang="ts">
import type { CommentType, Item, Paginated, Task, TaskComment } from '~/types/api'

const props = defineProps<{ task: Task; readOnly?: boolean }>()
const emit = defineEmits<{ posted: [] }>()

const api = useApi()
const toast = useToast()
const confirm = useConfirm()
const { user, isAdmin } = useAuth()

const comments = ref<TaskComment[]>([])
const loading = ref(true)
const body = ref('')
const type = ref<CommentType>('comment')
const posting = ref(false)

const typeItems = [
  { label: 'Comment', value: 'comment' },
  { label: 'Work update', value: 'work_update' }
]

async function load() {
  loading.value = true
  try {
    comments.value = (
      await api<Paginated<TaskComment>>(`/api/tasks/${props.task.id}/comments`, {
        query: { per_page: 100 }
      })
    ).data
  } catch (e) {
    toast.add({
      title: 'Could not load comments',
      description: toApiError(e).message,
      color: 'error'
    })
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function post() {
  if (!body.value.trim()) return
  posting.value = true
  try {
    const res = await api<Item<TaskComment>>(`/api/tasks/${props.task.id}/comments`, {
      method: 'POST',
      body: { body: body.value, type: type.value }
    })
    comments.value.push(res.data)
    body.value = ''
    type.value = 'comment'
    emit('posted')
  } catch (e) {
    toast.add({ title: 'Could not post', description: toApiError(e).message, color: 'error' })
  } finally {
    posting.value = false
  }
}

async function remove(comment: TaskComment) {
  if (!(await confirm({ title: 'Delete this comment?', confirmLabel: 'Delete' }))) return
  try {
    await api(`/api/comments/${comment.id}`, { method: 'DELETE' })
    comments.value = comments.value.filter((c) => c.id !== comment.id)
  } catch (e) {
    toast.add({
      title: 'Could not delete comment',
      description: toApiError(e).message,
      color: 'error'
    })
  }
}

const canDelete = (c: TaskComment) =>
  !props.readOnly && (isAdmin.value || c.user_id === user.value?.id)
</script>

<template>
  <div class="space-y-4">
    <div v-if="loading" class="space-y-3">
      <USkeleton v-for="i in 2" :key="i" class="h-16" />
    </div>
    <UEmpty
      v-else-if="!comments.length"
      icon="i-lucide-messages-square"
      title="No comments yet"
      description="Ask a question or post a work update."
      variant="naked"
      size="sm"
    />
    <ul v-else class="space-y-4">
      <li v-for="c in comments" :key="c.id" class="flex gap-3">
        <UAvatar :alt="c.author?.name ?? '?'" size="sm" />
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium">{{ c.author?.name ?? 'Former user' }}</span>
            <UBadge
              v-if="c.type === 'work_update'"
              label="Work update"
              color="info"
              variant="subtle"
              size="sm"
            />
            <span class="text-xs text-dimmed" :title="c.created_at ?? ''">{{
              timeAgo(c.created_at)
            }}</span>
            <UButton
              v-if="canDelete(c)"
              icon="i-lucide-trash-2"
              size="xs"
              color="neutral"
              variant="ghost"
              class="ms-auto"
              aria-label="Delete comment"
              @click="remove(c)"
            />
          </div>
          <p class="mt-1 text-sm whitespace-pre-line">{{ c.body }}</p>
        </div>
      </li>
    </ul>

    <form v-if="!readOnly" class="space-y-2 border-t border-default pt-4" @submit.prevent="post">
      <UTextarea
        v-model="body"
        :rows="3"
        placeholder="Write a comment…"
        class="w-full"
        autoresize
      />
      <div class="flex items-center justify-between gap-2">
        <USelect v-model="type" :items="typeItems" size="sm" class="w-36" />
        <UButton
          type="submit"
          label="Post"
          icon="i-lucide-send"
          :loading="posting"
          :disabled="!body.trim()"
        />
      </div>
    </form>
  </div>
</template>
