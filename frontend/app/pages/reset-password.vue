<script setup lang="ts">
import type { Form, FormError, FormSubmitEvent } from '@nuxt/ui'

// Opened from the email link: /reset-password?token=...&email=...
definePageMeta({ layout: 'auth', auth: 'guest' })
useHead({ title: 'Choose a new password' })

const { resetPassword } = useAuth()
const route = useRoute()
const toast = useToast()

const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))
const state = reactive({
  email: typeof route.query.email === 'string' ? route.query.email : '',
  password: '',
  password_confirmation: ''
})
const form = useTemplateRef<Form<typeof state>>('form')
const loading = ref(false)
const errorMessage = ref<string | null>(null)

function validate(s: typeof state): FormError[] {
  const errors: FormError[] = []
  if (s.password.length < 8)
    errors.push({ name: 'password', message: 'Use at least 8 characters.' })
  if (s.password !== s.password_confirmation)
    errors.push({ name: 'password_confirmation', message: 'Passwords do not match.' })
  return errors
}

async function onSubmit(event: FormSubmitEvent<typeof state>) {
  loading.value = true
  errorMessage.value = null
  try {
    await resetPassword({ ...event.data, token: token.value })
    toast.add({
      title: 'Password updated',
      description: 'You can now log in.',
      color: 'success',
      icon: 'i-lucide-check'
    })
    await navigateTo('/login')
  } catch (error) {
    const apiError = toApiError(error)
    errorMessage.value = apiError.message
    form.value?.setErrors(toFormErrors(apiError))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="space-y-1 text-center">
      <h1 class="text-lg font-semibold text-highlighted">Choose a new password</h1>
    </div>

    <UAlert
      v-if="!token"
      color="warning"
      variant="subtle"
      icon="i-lucide-link-2-off"
      description="This reset link is incomplete. Request a new one."
    />
    <UAlert
      v-if="errorMessage"
      color="error"
      variant="subtle"
      icon="i-lucide-circle-alert"
      :description="errorMessage"
    />

    <UForm
      v-if="token"
      ref="form"
      :state="state"
      :validate="validate"
      class="space-y-4"
      @submit="onSubmit"
    >
      <UFormField label="Email" name="email">
        <UInput v-model="state.email" type="email" autocomplete="email" class="w-full" />
      </UFormField>
      <UFormField label="New password" name="password">
        <UInput
          v-model="state.password"
          type="password"
          autocomplete="new-password"
          class="w-full"
        />
      </UFormField>
      <UFormField label="Confirm password" name="password_confirmation">
        <UInput
          v-model="state.password_confirmation"
          type="password"
          autocomplete="new-password"
          class="w-full"
        />
      </UFormField>
      <UButton type="submit" label="Update password" block :loading="loading" />
    </UForm>

    <p class="text-center text-sm">
      <ULink :to="token ? '/login' : '/forgot-password'">
        {{ token ? 'Back to login' : 'Request a new link' }}
      </ULink>
    </p>
  </div>
</template>
