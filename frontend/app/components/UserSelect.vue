<!--
  Pick a user (or several with `multiple`). Either pass `users` directly
  (e.g. a project's members) or let it load active users with the given roles.
-->
<script setup lang="ts">
import type { Paginated, RoleSlug, User } from '~/types/api'

const props = withDefaults(
  defineProps<{
    users?: User[]
    roles?: RoleSlug[]
    multiple?: boolean
    placeholder?: string
    clearable?: boolean
  }>(),
  { users: undefined, roles: undefined, multiple: false, placeholder: 'Select…', clearable: false }
)

const model = defineModel<number | number[] | null>()
const api = useApi()
const loaded = ref<User[]>([])
const loading = ref(false)

async function load() {
  if (props.users) return
  loading.value = true
  try {
    // One request per role keeps the API filter simple; lists are small.
    const roles = props.roles ?? [undefined]
    const pages = await Promise.all(
      roles.map((role) =>
        api<Paginated<User>>('/api/users', { query: { active: 1, per_page: 100, role } })
      )
    )
    loaded.value = pages.flatMap((p) => p.data).sort((a, b) => a.name.localeCompare(b.name))
  } catch {
    loaded.value = []
  } finally {
    loading.value = false
  }
}
onMounted(load)

const items = computed(() =>
  (props.users ?? loaded.value).map((u) => ({
    label: u.name,
    value: u.id,
    description: u.role?.name
  }))
)
</script>

<template>
  <USelectMenu
    v-model="model as any"
    :items="items"
    value-key="value"
    :multiple="multiple"
    :loading="loading"
    :placeholder="placeholder"
    :clear="clearable"
    class="w-full"
  />
</template>
