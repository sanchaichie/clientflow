<!-- <StatusBadge kind="task" value="in_progress" />  →  coloured "In Progress" badge -->
<script setup lang="ts">
import type { OptionKind } from '~/utils/options'

const props = defineProps<{ kind: OptionKind; value: string | null | undefined }>()
const option = computed(() => findOption(props.kind, props.value))
</script>

<template>
  <UBadge
    v-if="option"
    :label="option.label"
    :color="option.color"
    variant="subtle"
    class="whitespace-nowrap"
  />
  <span v-else class="text-muted">—</span>
</template>
