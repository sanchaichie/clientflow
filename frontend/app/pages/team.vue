<script setup lang="ts">
import type { DropdownMenuItem, TableColumn } from '@nuxt/ui'
import type { RoleSlug, User } from '~/types/api'
import UserFormModal from '~/components/forms/UserFormModal.vue'

// PMs may view the directory (to add members/assign tasks); only admins manage users.
definePageMeta({ middleware: 'role', roles: ['admin', 'project_manager'] })
useHead({ title: 'Team' })

const api = useApi()
const toast = useToast()
const confirm = useConfirm()
const userForm = useOverlay().create(UserFormModal)
const { user: me } = useAuth()
const { canManageUsers } = usePermissions()

const { rows, total, page, perPage, search, filters, loading, error, refresh } = usePaginatedList<
  User,
  { role: RoleSlug | null; active: boolean | null }
>('/api/users', { role: null, active: null })

const columns: TableColumn<User>[] = [
  { accessorKey: 'name', header: 'Name' },
  { id: 'role', header: 'Role' },
  { accessorKey: 'is_active', header: 'Status' },
  { accessorKey: 'created_at', header: 'Joined' },
  { id: 'actions', header: '' }
]

const roleFilter = [
  { label: 'All roles', value: null },
  { label: 'Administrators', value: 'admin' },
  { label: 'Project Managers', value: 'project_manager' },
  { label: 'Team Members', value: 'team_member' }
]

async function invite() {
  if (await userForm.open()) refresh()
}

async function edit(user: User) {
  if (await userForm.open({ user })) refresh()
}

async function setActive(user: User, active: boolean) {
  if (!active) {
    const ok = await confirm({
      title: `Deactivate ${user.name}?`,
      description: 'They will be logged out and can no longer sign in. Their history is kept.',
      confirmLabel: 'Deactivate'
    })
    if (!ok) return
  }
  try {
    if (active) await api(`/api/users/${user.id}`, { method: 'PUT', body: { is_active: true } })
    else await api(`/api/users/${user.id}`, { method: 'DELETE' })
    toast.add({ title: active ? 'User reactivated' : 'User deactivated', color: 'success' })
    refresh()
  } catch (e) {
    toast.add({
      title: 'Could not update user',
      description: toApiError(e).message,
      color: 'error'
    })
  }
}

function actions(user: User): DropdownMenuItem[] {
  if (!canManageUsers.value || user.id === me.value?.id) return []
  return [
    { label: 'Edit', icon: 'i-lucide-pencil', onSelect: () => edit(user) },
    user.is_active
      ? {
          label: 'Deactivate',
          icon: 'i-lucide-user-x',
          color: 'error',
          onSelect: () => setActive(user, false)
        }
      : { label: 'Reactivate', icon: 'i-lucide-user-check', onSelect: () => setActive(user, true) }
  ]
}
</script>

<template>
  <AppPage title="Team">
    <template #actions>
      <UButton
        v-if="canManageUsers"
        icon="i-lucide-user-plus"
        label="Invite user"
        @click="invite"
      />
    </template>

    <DataTable
      v-model:page="page"
      :data="rows"
      :columns="columns"
      :loading="loading"
      :error="error"
      :total="total"
      :per-page="perPage"
      empty-icon="i-lucide-users"
      empty-title="No users found"
      @retry="refresh"
    >
      <template #toolbar>
        <UInput
          v-model="search"
          icon="i-lucide-search"
          placeholder="Search name or email…"
          class="w-full sm:w-64"
        />
        <USelect v-model="filters.role" :items="roleFilter" class="w-44" />
      </template>

      <template #name-cell="{ row }">
        <UUser
          :name="row.original.name"
          :description="row.original.email"
          :avatar="{ alt: row.original.name }"
        />
      </template>
      <template #role-cell="{ row }">
        {{ row.original.role?.name ?? '—' }}
      </template>
      <template #is_active-cell="{ row }">
        <UBadge
          :label="row.original.is_active ? 'Active' : 'Deactivated'"
          :color="row.original.is_active ? 'success' : 'neutral'"
          variant="subtle"
        />
      </template>
      <template #created_at-cell="{ row }">
        {{ formatDate(row.original.created_at) }}
      </template>
      <template #actions-cell="{ row }">
        <div class="flex justify-end">
          <UDropdownMenu v-if="actions(row.original).length" :items="actions(row.original)">
            <UButton
              icon="i-lucide-ellipsis-vertical"
              color="neutral"
              variant="ghost"
              aria-label="Actions"
            />
          </UDropdownMenu>
        </div>
      </template>
    </DataTable>
  </AppPage>
</template>
