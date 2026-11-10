<!-- Members tab of a project: list, add (manager/admin) and remove. -->
<script setup lang="ts">
import type { Project, User } from '~/types/api'

const props = defineProps<{ project: Project }>()
const emit = defineEmits<{ changed: [] }>()

const api = useApi()
const toast = useToast()
const confirm = useConfirm()
const { canEditProject } = usePermissions()
const canManage = computed(() => canEditProject(props.project))

const toAdd = ref<number[]>([])
const adding = ref(false)

async function add() {
  if (!toAdd.value.length) return
  adding.value = true
  try {
    await api(`/api/projects/${props.project.id}/members`, {
      method: 'POST',
      body: { user_ids: toAdd.value }
    })
    toast.add({ title: 'Members added', color: 'success', icon: 'i-lucide-user-plus' })
    toAdd.value = []
    emit('changed')
  } catch (e) {
    toast.add({
      title: 'Could not add members',
      description: toApiError(e).message,
      color: 'error'
    })
  } finally {
    adding.value = false
  }
}

async function remove(member: User) {
  const ok = await confirm({
    title: `Remove ${member.name}?`,
    description: 'Their open tasks in this project will become unassigned.',
    confirmLabel: 'Remove'
  })
  if (!ok) return
  try {
    await api(`/api/projects/${props.project.id}/members/${member.id}`, { method: 'DELETE' })
    toast.add({ title: 'Member removed', color: 'success', icon: 'i-lucide-user-minus' })
    emit('changed')
  } catch (e) {
    toast.add({
      title: 'Could not remove member',
      description: toApiError(e).message,
      color: 'error'
    })
  }
}
</script>

<template>
  <UCard>
    <div v-if="canManage" class="mb-4 flex flex-col gap-2 sm:flex-row">
      <UserSelect
        v-model="toAdd"
        :roles="['team_member', 'project_manager']"
        multiple
        placeholder="Choose people to add…"
      />
      <UButton
        icon="i-lucide-user-plus"
        label="Add"
        :loading="adding"
        :disabled="!toAdd.length"
        @click="add"
      />
    </div>

    <ul class="divide-y divide-default">
      <li v-if="project.manager" class="flex items-center justify-between py-3">
        <UUser
          :name="project.manager.name"
          :description="project.manager.email"
          :avatar="{ alt: project.manager.name }"
        />
        <UBadge label="Manager" variant="subtle" />
      </li>
      <li
        v-for="member in project.members"
        :key="member.id"
        class="flex items-center justify-between py-3"
      >
        <UUser :name="member.name" :description="member.email" :avatar="{ alt: member.name }" />
        <UButton
          v-if="canManage"
          icon="i-lucide-x"
          color="neutral"
          variant="ghost"
          :aria-label="`Remove ${member.name}`"
          @click="remove(member)"
        />
      </li>
    </ul>
    <UEmpty
      v-if="!project.members?.length"
      icon="i-lucide-users"
      title="No members yet"
      variant="naked"
    />
  </UCard>
</template>
