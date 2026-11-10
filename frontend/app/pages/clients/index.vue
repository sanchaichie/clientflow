<script setup lang="ts">
import type { DropdownMenuItem, TableColumn } from '@nuxt/ui'
import type { Client, ClientStatus } from '~/types/api'
import ClientFormModal from '~/components/forms/ClientFormModal.vue'

definePageMeta({ middleware: 'role', roles: ['admin', 'project_manager'] })
useHead({ title: 'Clients' })

const api = useApi()
const toast = useToast()
const confirm = useConfirm()
const clientForm = useOverlay().create(ClientFormModal)
const { canCreateClient, canEditClient, canRestore } = usePermissions()

const list = usePaginatedList<Client, { status: ClientStatus | null; archived: boolean }>(
  '/api/clients',
  { status: null, archived: false },
  { sort: 'company_name' }
)
const { rows, total, page, perPage, search, filters, loading, error, refresh } = list

const columns: TableColumn<Client>[] = [
  { accessorKey: 'company_name', header: 'Company' },
  { accessorKey: 'contact_person', header: 'Contact' },
  { accessorKey: 'status', header: 'Status' },
  { id: 'account_manager', header: 'Account manager' },
  { accessorKey: 'projects_count', header: 'Projects' },
  { accessorKey: 'created_at', header: 'Created' },
  { id: 'actions', header: '' }
]

const statusFilter = [{ label: 'All statuses', value: null }, ...clientStatuses]

async function create() {
  const saved = await clientForm.open()
  if (saved) await navigateTo(`/clients/${saved.id}`)
}

async function edit(client: Client) {
  if (await clientForm.open({ client })) refresh()
}

async function archive(client: Client) {
  const ok = await confirm({
    title: `Archive ${client.company_name}?`,
    description:
      'Archived clients are hidden from lists and become read-only. An admin can restore them.',
    confirmLabel: 'Archive',
    color: 'warning'
  })
  if (!ok) return
  try {
    await api(`/api/clients/${client.id}`, { method: 'DELETE' })
    toast.add({ title: 'Client archived', color: 'success', icon: 'i-lucide-archive' })
    refresh()
  } catch (e) {
    toast.add({
      title: 'Could not archive client',
      description: toApiError(e).message,
      color: 'error'
    })
  }
}

async function restore(client: Client) {
  try {
    await api(`/api/clients/${client.id}/restore`, { method: 'POST' })
    toast.add({ title: 'Client restored', color: 'success', icon: 'i-lucide-archive-restore' })
    refresh()
  } catch (e) {
    toast.add({
      title: 'Could not restore client',
      description: toApiError(e).message,
      color: 'error'
    })
  }
}

function actions(client: Client): DropdownMenuItem[] {
  if (client.archived_at) {
    return canRestore.value
      ? [{ label: 'Restore', icon: 'i-lucide-archive-restore', onSelect: () => restore(client) }]
      : []
  }
  if (!canEditClient(client)) return []
  return [
    { label: 'Edit', icon: 'i-lucide-pencil', onSelect: () => edit(client) },
    {
      label: 'Archive',
      icon: 'i-lucide-archive',
      color: 'warning',
      onSelect: () => archive(client)
    }
  ]
}
</script>

<template>
  <AppPage title="Clients">
    <template #actions>
      <UButton v-if="canCreateClient" icon="i-lucide-plus" label="New client" @click="create" />
    </template>

    <DataTable
      v-model:page="page"
      :data="rows"
      :columns="columns"
      :loading="loading"
      :error="error"
      :total="total"
      :per-page="perPage"
      empty-icon="i-lucide-building-2"
      :empty-title="filters.archived ? 'No archived clients' : 'No clients found'"
      empty-description="Try a different search, or add your first client."
      @retry="refresh"
    >
      <template #toolbar>
        <UInput
          v-model="search"
          icon="i-lucide-search"
          placeholder="Search clients…"
          class="w-full sm:w-64"
        />
        <USelect v-model="filters.status" :items="statusFilter" class="w-40" />
        <USwitch v-model="filters.archived" label="Show archived" />
      </template>

      <template #company_name-cell="{ row }">
        <NuxtLink
          :to="`/clients/${row.original.id}`"
          class="font-medium text-highlighted hover:underline"
        >
          {{ row.original.company_name }}
        </NuxtLink>
        <p v-if="row.original.email" class="text-xs text-muted">{{ row.original.email }}</p>
      </template>
      <template #contact_person-cell="{ row }">
        {{ row.original.contact_person ?? '—' }}
      </template>
      <template #status-cell="{ row }">
        <StatusBadge kind="client" :value="row.original.status" />
      </template>
      <template #account_manager-cell="{ row }">
        {{ row.original.account_manager?.name ?? '—' }}
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
