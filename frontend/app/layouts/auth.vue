<!-- Centered card layout for login and password-reset pages. -->
<template>
  <div class="flex min-h-dvh items-center justify-center bg-muted px-4 py-12">
    <div class="w-full max-w-sm space-y-6">
      <div class="flex justify-center">
        <AppLogo class="text-xl" />
      </div>
      <UCard>
        <slot />
      </UCard>
    </div>
  </div>
</template>
