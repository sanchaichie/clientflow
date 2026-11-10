<!--
  A UTable with the pieces every list needs: toolbar slot (search/filters),
  loading and empty states, an error message and pagination.
  Cell slots (#name-cell, ...) are passed straight through to UTable.
-->
<script setup lang="ts" generic="T extends Record<string, any>">
import type { TableColumn } from '@nuxt/ui'

defineProps<{
  data: T[]
  columns: TableColumn<T>[]
  loading?: boolean
  error?: string | null
  total: number
  perPage: number
  emptyTitle?: string
  emptyDescription?: string
  emptyIcon?: string
}>()

const page = defineModel<number>('page', { required: true })
const emit = defineEmits<{ retry: [] }>()
const slots = useSlots()
const cellSlots = computed(() => Object.keys(slots).filter((name) => name.endsWith('-cell')))
</script>

<template>
  <div class="space-y-4">
    <div v-if="$slots.toolbar" class="flex flex-wrap items-center gap-2">
      <slot name="toolbar" />
    </div>

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      icon="i-lucide-circle-alert"
      :description="error"
      :actions="[
        { label: 'Retry', color: 'neutral', variant: 'outline', onClick: () => emit('retry') }
      ]"
    />

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <UTable :data="data" :columns="columns" :loading="loading" class="w-full">
        <template v-for="name in cellSlots" #[name]="slotProps" :key="name">
          <slot :name="name" v-bind="slotProps" />
        </template>
        <template #empty>
          <UEmpty
            :icon="emptyIcon ?? 'i-lucide-inbox'"
            :title="loading ? 'Loading…' : (emptyTitle ?? 'Nothing here yet')"
            :description="loading ? undefined : emptyDescription"
            variant="naked"
          />
        </template>
      </UTable>
    </UCard>

    <div v-if="total > perPage" class="flex items-center justify-between gap-2">
      <p class="text-sm text-muted">{{ total }} results</p>
      <UPagination v-model:page="page" :total="total" :items-per-page="perPage" />
    </div>
  </div>
</template>
