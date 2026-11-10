<script setup lang="ts">
import type { Task } from '~/types/api'

defineProps<{ task: Task; showProject?: boolean; locked?: boolean }>()
defineEmits<{ open: [task: Task] }>()
</script>

<template>
  <div
    class="cursor-grab space-y-2 rounded-md border border-default bg-default p-3 shadow-xs hover:border-accented"
    :class="{ 'cursor-default opacity-90': locked }"
    :data-locked="locked || undefined"
    @click="$emit('open', task)"
  >
    <div class="flex items-start justify-between gap-2">
      <p class="text-sm font-medium text-highlighted">{{ task.title }}</p>
      <StatusBadge kind="priority" :value="task.priority" />
    </div>
    <p v-if="showProject && task.project" class="truncate text-xs text-muted">
      {{ task.project.name }}
    </p>
    <div class="flex items-center justify-between gap-2 text-xs text-muted">
      <span :class="{ 'font-medium text-error': task.is_overdue }">
        <UIcon name="i-lucide-calendar" class="align-middle" />
        {{ formatDate(task.due_date) }}
      </span>
      <span class="flex items-center gap-2">
        <span v-if="task.comments_count" class="flex items-center gap-0.5">
          <UIcon name="i-lucide-message-square" /> {{ task.comments_count }}
        </span>
        <UAvatar
          v-if="task.assignee"
          :alt="task.assignee.name"
          size="2xs"
          :title="task.assignee.name"
        />
        <UIcon v-if="locked" name="i-lucide-lock" title="You can't move this task" />
      </span>
    </div>
  </div>
</template>
