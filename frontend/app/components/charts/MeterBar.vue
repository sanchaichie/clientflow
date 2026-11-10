<!-- A single ratio against its whole (e.g. completion rate): track + fill. -->
<script setup lang="ts">
defineProps<{ value: number; label?: string }>()
</script>

<template>
  <div
    class="h-2 w-full overflow-hidden rounded-full bg-(--chart-track)"
    role="meter"
    :aria-valuenow="value"
    aria-valuemin="0"
    aria-valuemax="100"
    :aria-label="label ?? `${value}%`"
  >
    <div
      class="h-full rounded-full bg-(--chart-1) transition-[width] duration-300"
      :style="{ width: `${Math.min(100, Math.max(0, value))}%` }"
    />
  </div>
</template>
