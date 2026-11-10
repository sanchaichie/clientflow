<!-- Shown for unknown routes (404) and unexpected errors. -->
<script setup lang="ts">
import type { NuxtError } from '#app'

const props = defineProps<{ error: NuxtError }>()
const notFound = computed(() => props.error.statusCode === 404)

useHead({ title: notFound.value ? 'Page not found' : 'Error' })
</script>

<template>
  <UApp>
    <div class="flex min-h-dvh items-center justify-center px-4">
      <UEmpty
        :icon="notFound ? 'i-lucide-map-pin-off' : 'i-lucide-triangle-alert'"
        :title="notFound ? 'Page not found' : 'Something went wrong'"
        :description="notFound ? 'The page you are looking for does not exist yet.' : error.message"
        :actions="[
          {
            label: 'Back to dashboard',
            icon: 'i-lucide-house',
            onClick: () => clearError({ redirect: '/' })
          }
        ]"
      />
    </div>
  </UApp>
</template>
