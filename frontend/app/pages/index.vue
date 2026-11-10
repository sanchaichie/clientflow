<script setup lang="ts">
// Temporary Milestone 1 page: proves the SPA can reach the Laravel API
// with credentials and receive Sanctum's XSRF-TOKEN cookie.
// Replaced by the dashboard in Milestone 5.
useHead({ title: 'Setup check' })

const config = useRuntimeConfig()
const status = ref<'idle' | 'loading' | 'ok' | 'error'>('idle')
const message = ref('')

async function checkConnection() {
  status.value = 'loading'
  try {
    await $fetch('/sanctum/csrf-cookie', {
      baseURL: config.public.apiBase,
      credentials: 'include'
    })
    const hasToken = document.cookie.split('; ').some((c) => c.startsWith('XSRF-TOKEN='))
    status.value = hasToken ? 'ok' : 'error'
    message.value = hasToken
      ? 'Laravel responded and the XSRF-TOKEN cookie was set.'
      : 'Laravel responded, but no XSRF-TOKEN cookie is visible. Check SESSION_DOMAIN.'
  } catch (error) {
    status.value = 'error'
    message.value = `Could not reach the API: ${(error as Error).message}`
  }
}
</script>

<template>
  <UContainer class="py-16">
    <UCard class="mx-auto max-w-lg">
      <template #header>
        <AppLogo />
      </template>

      <div class="space-y-4">
        <p class="text-sm text-muted">
          API base: <code>{{ config.public.apiBase }}</code>
        </p>

        <UButton
          :loading="status === 'loading'"
          icon="i-lucide-plug"
          label="Check API connection"
          @click="checkConnection"
        />

        <UAlert
          v-if="status === 'ok' || status === 'error'"
          :color="status === 'ok' ? 'success' : 'error'"
          :icon="status === 'ok' ? 'i-lucide-circle-check' : 'i-lucide-circle-x'"
          :title="status === 'ok' ? 'Connected' : 'Connection failed'"
          :description="message"
        />
      </div>
    </UCard>
  </UContainer>
</template>
