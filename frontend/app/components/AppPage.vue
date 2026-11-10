<!--
  Standard page frame inside the dashboard layout: a top navigation bar with
  the page title (and the mobile sidebar toggle) plus a scrollable body.

  <AppPage title="Clients">
    <template #actions><UButton label="New client" /></template>
    ...page content...
  </AppPage>
-->
<script setup lang="ts">
defineProps<{ title: string }>()
</script>

<template>
  <UDashboardPanel>
    <template #header>
      <UDashboardNavbar :title="title">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <slot name="actions" />
          <NotificationBell />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <slot />
    </template>
  </UDashboardPanel>
</template>
