<script setup lang="ts">
import type { Form, FormError } from '@nuxt/ui'
import type { Client, ClientStatus, Item } from '~/types/api'

const props = defineProps<{ client?: Client }>()
const emit = defineEmits<{ close: [saved: Client | null] }>()

const api = useApi()
const toast = useToast()
const { isAdmin } = useAuth()
const form = useTemplateRef<Form<typeof state>>('form')
const saving = ref(false)
const isEdit = computed(() => !!props.client)

const state = reactive({
  company_name: props.client?.company_name ?? '',
  contact_person: props.client?.contact_person ?? '',
  email: props.client?.email ?? '',
  phone: props.client?.phone ?? '',
  address: props.client?.address ?? '',
  description: props.client?.description ?? '',
  status: (props.client?.status ?? 'lead') as ClientStatus,
  account_manager_id: props.client?.account_manager_id ?? null
})

function validate(s: typeof state): FormError[] {
  const errors: FormError[] = []
  if (!s.company_name.trim())
    errors.push({ name: 'company_name', message: 'Company name is required.' })
  if (s.email && !/^\S+@\S+\.\S+$/.test(s.email))
    errors.push({ name: 'email', message: 'Enter a valid email.' })
  return errors
}

async function onSubmit() {
  saving.value = true
  // Empty inputs are sent as null; only admins may send the account manager.
  const { account_manager_id, ...fields } = state
  const body: Record<string, unknown> = Object.fromEntries(
    Object.entries(fields).map(([k, v]) => [k, v === '' ? null : v])
  )
  if (isAdmin.value) body.account_manager_id = account_manager_id

  try {
    const res = await api<Item<Client>>(
      isEdit.value ? `/api/clients/${props.client!.id}` : '/api/clients',
      { method: isEdit.value ? 'PUT' : 'POST', body }
    )
    toast.add({
      title: isEdit.value ? 'Client updated' : 'Client created',
      color: 'success',
      icon: 'i-lucide-check'
    })
    emit('close', res.data)
  } catch (error) {
    const apiError = toApiError(error)
    form.value?.setErrors(toFormErrors(apiError))
    toast.add({ title: 'Could not save client', description: apiError.message, color: 'error' })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <UModal
    :title="isEdit ? 'Edit client' : 'New client'"
    :ui="{ footer: 'justify-end' }"
    @update:open="(open: boolean) => !open && emit('close', null)"
  >
    <template #body>
      <UForm
        id="client-form"
        ref="form"
        :state="state"
        :validate="validate"
        class="grid gap-4 sm:grid-cols-2"
        @submit="onSubmit"
      >
        <UFormField label="Company name" name="company_name" required class="sm:col-span-2">
          <UInput v-model="state.company_name" class="w-full" autofocus />
        </UFormField>
        <UFormField label="Contact person" name="contact_person">
          <UInput v-model="state.contact_person" class="w-full" />
        </UFormField>
        <UFormField label="Status" name="status" required>
          <USelect v-model="state.status" :items="clientStatuses" class="w-full" />
        </UFormField>
        <UFormField label="Email" name="email">
          <UInput v-model="state.email" type="email" class="w-full" />
        </UFormField>
        <UFormField label="Phone" name="phone">
          <UInput v-model="state.phone" class="w-full" />
        </UFormField>
        <UFormField label="Address" name="address" class="sm:col-span-2">
          <UInput v-model="state.address" class="w-full" />
        </UFormField>
        <UFormField
          v-if="isAdmin"
          label="Account manager"
          name="account_manager_id"
          class="sm:col-span-2"
        >
          <UserSelect
            v-model="state.account_manager_id"
            :roles="['project_manager']"
            placeholder="Unassigned"
            clearable
          />
        </UFormField>
        <UFormField label="Description" name="description" class="sm:col-span-2">
          <UTextarea v-model="state.description" :rows="3" class="w-full" />
        </UFormField>
      </UForm>
    </template>

    <template #footer>
      <UButton label="Cancel" color="neutral" variant="ghost" @click="emit('close', null)" />
      <UButton
        type="submit"
        form="client-form"
        :label="isEdit ? 'Save' : 'Create'"
        :loading="saving"
      />
    </template>
  </UModal>
</template>
