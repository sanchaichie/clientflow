<script setup lang="ts">
import type { FormError, FormSubmitEvent } from '@nuxt/ui'

definePageMeta({ layout: 'auth', auth: 'guest' })
useHead({ title: 'Forgot password' })

const { forgotPassword } = useAuth()

const state = reactive({ email: '' })
const loading = ref(false)
const sentMessage = ref<string | null>(null)
const errorMessage = ref<string | null>(null)

function validate(s: typeof state): FormError[] {
  return s.email ? [] : [{ name: 'email', message: 'Email is required.' }]
}

async function onSubmit(event: FormSubmitEvent<typeof state>) {
  loading.value = true
  errorMessage.value = null
  try {
    // The API answers the same whether or not the email exists.
    sentMessage.value = (await forgotPassword(event.data.email)).message
  } catch (error) {
    errorMessage.value = toApiError(error).message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="space-y-1 text-center">
      <h1 class="text-lg font-semibold text-highlighted">Reset your password</h1>
      <p class="text-sm text-muted">We'll email you a link to choose a new password.</p>
    </div>

    <UAlert
      v-if="sentMessage"
      color="success"
      variant="subtle"
      icon="i-lucide-mail-check"
      :description="sentMessage"
    />
    <UAlert
      v-if="errorMessage"
      color="error"
      variant="subtle"
      icon="i-lucide-circle-alert"
      :description="errorMessage"
    />

    <UForm
      v-if="!sentMessage"
      :state="state"
      :validate="validate"
      class="space-y-4"
      @submit="onSubmit"
    >
      <UFormField label="Email" name="email">
        <UInput v-model="state.email" type="email" autocomplete="email" class="w-full" autofocus />
      </UFormField>
      <UButton type="submit" label="Send reset link" block :loading="loading" />
    </UForm>

    <p class="text-center text-sm">
      <ULink to="/login">Back to login</ULink>
    </p>
  </div>
</template>
