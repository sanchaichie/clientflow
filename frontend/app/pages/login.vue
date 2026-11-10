<script setup lang="ts">
import type { FormError, FormSubmitEvent } from '@nuxt/ui'

definePageMeta({ layout: 'auth', auth: 'guest' })
useHead({ title: 'Log in' })

const { login } = useAuth()
const route = useRoute()

const state = reactive({ email: '', password: '', remember: false })
const loading = ref(false)
const errorMessage = ref<string | null>(null)

// Quick client-side checks for instant feedback; the API validates again.
function validate(s: typeof state): FormError[] {
  const errors: FormError[] = []
  if (!s.email) errors.push({ name: 'email', message: 'Email is required.' })
  if (!s.password) errors.push({ name: 'password', message: 'Password is required.' })
  return errors
}

async function onSubmit(event: FormSubmitEvent<typeof state>) {
  loading.value = true
  errorMessage.value = null
  try {
    await login(event.data)
    await navigateTo(safeRedirect(route.query.redirect))
  } catch (error) {
    // 422 wrong credentials, 403 deactivated, 429 throttled — all shown as one message.
    errorMessage.value = toApiError(error).message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="space-y-1 text-center">
      <h1 class="text-lg font-semibold text-highlighted">Welcome back</h1>
      <p class="text-sm text-muted">Log in to your ClientFlow account.</p>
    </div>

    <UAlert
      v-if="errorMessage"
      color="error"
      variant="subtle"
      icon="i-lucide-circle-alert"
      :description="errorMessage"
    />

    <UForm :state="state" :validate="validate" class="space-y-4" @submit="onSubmit">
      <UFormField label="Email" name="email">
        <UInput
          v-model="state.email"
          type="email"
          autocomplete="username"
          placeholder="you@company.com"
          class="w-full"
          autofocus
        />
      </UFormField>

      <UFormField label="Password" name="password">
        <template #hint>
          <ULink to="/forgot-password" class="text-sm">Forgot password?</ULink>
        </template>
        <UInput
          v-model="state.password"
          type="password"
          autocomplete="current-password"
          class="w-full"
        />
      </UFormField>

      <UCheckbox v-model="state.remember" label="Remember me" />

      <UButton type="submit" label="Log in" block :loading="loading" />
    </UForm>
  </div>
</template>
