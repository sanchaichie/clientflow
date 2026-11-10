<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui'
import type { RoleSlug } from '~/types/api'

const { hasRole } = useAuth()

interface NavLink extends NavigationMenuItem {
  /** Roles that see this link; omitted = everyone. Mirrors API permissions. */
  roles?: RoleSlug[]
}

const links: NavLink[] = [
  { label: 'Dashboard', icon: 'i-lucide-layout-dashboard', to: '/' },
  {
    label: 'Clients',
    icon: 'i-lucide-building-2',
    to: '/clients',
    roles: ['admin', 'project_manager']
  },
  { label: 'Projects', icon: 'i-lucide-folder-kanban', to: '/projects' },
  { label: 'Tasks', icon: 'i-lucide-list-checks', to: '/tasks' },
  { label: 'Team', icon: 'i-lucide-users', to: '/team', roles: ['admin', 'project_manager'] },
  {
    label: 'Reports',
    icon: 'i-lucide-chart-column',
    to: '/reports',
    roles: ['admin', 'project_manager']
  }
]

const visibleLinks = computed(() =>
  links.filter((link) => !link.roles || hasRole(...link.roles)).map(({ roles: _, ...item }) => item)
)
</script>

<template>
  <UDashboardGroup unit="rem">
    <UDashboardSidebar collapsible resizable :ui="{ footer: 'border-t border-default' }">
      <template #header="{ collapsed }">
        <NuxtLink to="/" class="flex items-center px-1">
          <AppLogo v-if="!collapsed" />
          <UIcon v-else name="i-lucide-workflow" class="size-6 text-primary" />
        </NuxtLink>
      </template>

      <template #default="{ collapsed }">
        <UNavigationMenu
          :collapsed="collapsed"
          :items="visibleLinks"
          orientation="vertical"
          tooltip
        />
      </template>

      <template #footer="{ collapsed }">
        <UserMenu :collapsed="collapsed" />
      </template>
    </UDashboardSidebar>

    <slot />
  </UDashboardGroup>
</template>
