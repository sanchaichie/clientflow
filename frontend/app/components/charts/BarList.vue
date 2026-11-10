<!--
  Horizontal bar chart for comparing a few amounts (e.g. tasks per status).
  Spec'd per the dataviz guide: one hue for all bars, bars <= 24px thick with a
  rounded data-end and a square baseline, the value printed at the bar tip in
  text colour (never the bar colour), hover/focus tooltip as an extra, and the
  whole row as the hit target. Values are always visible, so the tooltip never
  gates information.
-->
<script setup lang="ts">
const props = defineProps<{
  items: { key: string; label: string; value: number }[]
  /** Shown in tooltips, e.g. "tasks". */
  unit?: string
}>()

const max = computed(() => Math.max(1, ...props.items.map((i) => i.value)))
const total = computed(() => props.items.reduce((sum, i) => sum + i.value, 0))
const share = (v: number) => (total.value ? Math.round((v / total.value) * 100) : 0)
</script>

<template>
  <ul class="space-y-3" role="list">
    <li v-for="item in items" :key="item.key">
      <UTooltip
        :text="`${item.label}: ${item.value} ${unit ?? ''} (${share(item.value)}% of ${total})`"
        :content="{ side: 'top' }"
      >
        <div
          class="grid grid-cols-[7rem_1fr] items-center gap-3 rounded-sm outline-offset-2 focus-visible:outline-2 focus-visible:outline-primary"
          tabindex="0"
          :aria-label="`${item.label}: ${item.value}`"
        >
          <span class="truncate text-sm text-toned">{{ item.label }}</span>
          <span class="flex items-center gap-2">
            <span
              v-if="item.value > 0"
              class="h-4 rounded-e-[4px] bg-(--chart-1) transition-[width] duration-300"
              :style="{ width: `${(item.value / max) * 100}%` }"
            />
            <span class="shrink-0 text-sm font-medium text-highlighted tabular-nums">
              {{ item.value }}
            </span>
          </span>
        </div>
      </UTooltip>
    </li>
  </ul>
</template>
