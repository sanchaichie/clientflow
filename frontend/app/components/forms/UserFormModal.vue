<script setup lang="ts">
import type { Form, FormError } from '@nuxt/ui'
import type { Item, RoleSlug, User } from '~/types/api'

/** Admin-only. New users receive an email to set their own password. */
const props = defineProps<{ user?: User }>()
const emit = defineEmits<{ close: [saved: User | null] }>()

const api = useApi()
const toast = useToast()
const form = useTemplateRef<Form<typeof state>>('form')
const saving = ref(false)
const isEdit = computed(() => !!props.user)

const roles: { label: string; value: RoleSlug }[] = [
  { label: 'Administrator', value: 'admin' },
  { label: 'Project Manager', value: 'project_manager' },
  { label: 'Team Member', value: 'team_member' }
]

const state = reactive({
  name: props.user?.name ?? '',
  email: props.user?.email ?? '',
  role: (props.user?.role?.slug ?? 'team_member') as RoleSlug
})

function validate(s: typeof state): FormError[] {
  const errors: FormError[] = []
  if (!s.name.trim()) errors.push({ name: 'name', message: 'Name is required.' })
  if (!/^\S+@\S+\.\S+$/.test(s.email))
    errors.push({ name: 'email', message: 'Enter a valid email.' })
  return errors
}

async function onSubmit() {
  saving.value = true
  try {
    const res = await api<Item<User>>(
      isEdit.value ? `/api/users/${props.user!.id}` : '/api/users',
      {
        method: isEdit.value ? 'PUT' : 'POST',
        body: { ...state }
      }
    )
    toast.add({
      title: isEdit.value ? 'User updated' : 'User invited',
      description: isEdit.value
        ? undefined
        : `${state.email} will receive an email to set a password.`,
      color: 'success',
      icon: 'i-lucide-check'
    })
    emit('close', res.data)
  } catch (error) {
    const apiError = toApiError(error)
    form.value?.setErrors(toFormErrors(apiError))
    toast.add({ title: 'Could not save user', description: apiError.message, color: 'error' })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <UModal
    :title="isEdit ? 'Edit user' : 'Invite user'"
    :ui="{ footer: 'justify-end' }"
    @update:open="(open: boolean) => !open && emit('close', null)"
  >
    <template #body>
      <UForm
        id="user-form"
        ref="form"
        :state="state"
        :validate="validate"
        class="space-y-4"
        @submit="onSubmit"
      >
        <UFormField label="Name" name="name" required>
          <UInput v-model="state.name" class="w-full" autofocus />
        </UFormField>
        <UFormField label="Email" name="email" required>
          <UInput v-model="state.email" type="email" class="w-full" />
        </UFormField>
        <UFormField label="Role" name="role" required>
          <USelect v-model="state.role" :items="roles" class="w-full" />
        </UFormField>
      </UForm>
    </template>

    <template #footer>
      <UButton label="Cancel" color="neutral" variant="ghost" @click="emit('close', null)" />
      <UButton
        type="submit"
        form="user-form"
        :label="isEdit ? 'Save' : 'Send invite'"
        :loading="saving"
      />
    </template>
  </UModal>
</template>
