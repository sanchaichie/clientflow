<!-- Opened through useConfirm(); resolves true (confirmed) or false. -->
<script setup lang="ts">
withDefaults(
  defineProps<{
    title: string
    description?: string
    confirmLabel?: string
    color?: 'error' | 'warning' | 'primary'
  }>(),
  { description: undefined, confirmLabel: 'Confirm', color: 'error' }
)

const emit = defineEmits<{ close: [confirmed: boolean] }>()
</script>

<template>
  <UModal
    :title="title"
    :description="description"
    :close="false"
    :ui="{ footer: 'justify-end' }"
    @update:open="(open: boolean) => !open && emit('close', false)"
  >
    <template #footer>
      <UButton label="Cancel" color="neutral" variant="ghost" @click="emit('close', false)" />
      <UButton :label="confirmLabel" :color="color" @click="emit('close', true)" />
    </template>
  </UModal>
</template>
