<script setup lang="ts">
// Placeholder dashboard for Milestone 5 Part A. The real statistics
// (GET /api/dashboard) arrive in Part B.
useHead({ title: 'Dashboard' })

const { user } = useAuth()
</script>

<template>
  <AppPage title="Dashboard">
    <UEmpty
      icon="i-lucide-layout-dashboard"
      :title="`Welcome, ${user?.name ?? ''}`"
      :description="`You're logged in as ${user?.role?.name ?? 'a user'}. Dashboard statistics arrive in the next step.`"
    />
  </AppPage>
</template>
